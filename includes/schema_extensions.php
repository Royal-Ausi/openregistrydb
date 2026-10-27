<?php
/**
 * Ensures optional tables/columns exist (idempotent) — PostgreSQL.
 */

declare(strict_types=1);

function openregistry_column_exists(PgConnection $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.columns '
        . 'WHERE table_schema = CURRENT_SCHEMA() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

function openregistry_table_exists(PgConnection $conn, string $table): bool
{
    $stmt = $conn->prepare(
        'SELECT 1 FROM information_schema.tables '
        . 'WHERE table_schema = CURRENT_SCHEMA() AND table_name = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * Resync a PostgreSQL SERIAL/identity sequence after MySQL data import (prevents duplicate id on INSERT).
 */
function openregistry_sync_serial_sequence(PgConnection $conn, string $table, string $column): void
{
    if (!openregistry_table_exists($conn, $table) || !openregistry_column_exists($conn, $table, $column)) {
        return;
    }
    $seqRow = $conn->query("SELECT pg_get_serial_sequence('{$table}', '{$column}') AS seq");
    if (!$seqRow instanceof PgResult) {
        return;
    }
    $seq = $seqRow->fetch_assoc()['seq'] ?? null;
    if (!$seq) {
        return;
    }
    @$conn->query(
        "SELECT setval('{$seq}', COALESCE((SELECT MAX({$column}) FROM {$table}), 1), true)"
    );
}

function openregistry_resync_register_sequences(PgConnection $conn): void
{
    $tables = [
        ['master_register', 'id'],
        ['pensioners_register', 'id'],
        ['contractstaff_register', 'serial_number'],
        ['semicurrent_records', 'serial_number'],
        ['file_diary', 'serial_number'],
        ['subject_index', 'id'],
        ['status_keywords', 'id'],
    ];

    foreach ($tables as [$table, $column]) {
        openregistry_sync_serial_sequence($conn, $table, $column);
    }
}

function openregistry_ensure_file_diary_extensions(PgConnection $conn): void
{
    if (!openregistry_table_exists($conn, 'file_diary')) {
        return;
    }
    if (!openregistry_column_exists($conn, 'file_diary', 'file_name')) {
        @$conn->query('ALTER TABLE file_diary ADD COLUMN file_name VARCHAR(255) NULL');
    }
    openregistry_sync_serial_sequence($conn, 'file_diary', 'serial_number');
}

function openregistry_ensure_extensions(PgConnection $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS help_submissions (
            id SERIAL PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            question TEXT NOT NULL,
            submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    if (!openregistry_column_exists($conn, 'users', 'can_import')) {
        @$conn->query('ALTER TABLE users ADD COLUMN can_import BOOLEAN NOT NULL DEFAULT FALSE');
    }
    if (!openregistry_column_exists($conn, 'users', 'can_export')) {
        @$conn->query('ALTER TABLE users ADD COLUMN can_export BOOLEAN NOT NULL DEFAULT FALSE');
    }
    if (!openregistry_column_exists($conn, 'users', 'id')) {
        @$conn->query('ALTER TABLE users ADD COLUMN id SERIAL UNIQUE');
    }
    openregistry_sync_serial_sequence($conn, 'users', 'id');

    @$conn->query("UPDATE users SET user_type = 'admin' WHERE role = 'admin' AND user_type IS DISTINCT FROM 'admin' AND role != 'system_operator'");
    @$conn->query("UPDATE users SET role = 'admin' WHERE user_type = 'admin' AND role IS DISTINCT FROM 'admin' AND user_type != 'system_operator'");

    openregistry_ensure_status_keywords($conn);
    openregistry_ensure_register_columns($conn);
    openregistry_ensure_file_diary_extensions($conn);
    openregistry_resync_register_sequences($conn);
}

function openregistry_ensure_register_columns(PgConnection $conn): void
{
    $tableColumnMap = [
        'master_register' => [
            'address_contact' => 'VARCHAR(255) NULL',
        ],
        'pensioners_register' => [
            'address_contact' => 'VARCHAR(255) NULL',
            'status' => 'VARCHAR(50) NULL',
        ],
        'contractstaff_register' => [
            'address_contact' => 'VARCHAR(255) NULL',
            'date_opened' => 'DATE NULL',
        ],
        'subject_index' => [
            'date_opened' => 'DATE NULL',
        ],
    ];

    foreach ($tableColumnMap as $table => $columns) {
        if (!openregistry_table_exists($conn, $table)) {
            continue;
        }

        foreach ($columns as $column => $definition) {
            if (!openregistry_column_exists($conn, $table, $column)) {
                @$conn->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
    }
}

function openregistry_ensure_status_keywords(PgConnection $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS status_keywords (
            id SERIAL PRIMARY KEY,
            keyword VARCHAR(50) NOT NULL UNIQUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $defaults = [
        'Active', 'Semi-active', 'Inactive', 'Transferred',
        'Closed', 'Archived', 'Deceased', 'Resigned', 'Didnt Report',
    ];
    foreach ($defaults as $kw) {
        $stmt = $conn->prepare('INSERT INTO status_keywords (keyword) VALUES (?) ON CONFLICT (keyword) DO NOTHING');
        if ($stmt) {
            $stmt->bind_param('s', $kw);
            $stmt->execute();
            $stmt->close();
        }
    }

    $tablesNeedingStatusId = ['master_register', 'pensioners_register', 'contractstaff_register', 'subject_index'];
    foreach ($tablesNeedingStatusId as $tbl) {
        if (!openregistry_table_exists($conn, $tbl)) {
            continue;
        }

        if (!openregistry_column_exists($conn, $tbl, 'status_id')) {
            @$conn->query("ALTER TABLE {$tbl} ADD COLUMN status_id INTEGER NULL");
        }

        if (openregistry_column_exists($conn, $tbl, 'status')) {
            @$conn->query(
                "INSERT INTO status_keywords (keyword)
                 SELECT DISTINCT status FROM {$tbl}
                 WHERE status IS NOT NULL AND status <> ''
                 ON CONFLICT (keyword) DO NOTHING"
            );
            @$conn->query(
                "UPDATE {$tbl} AS t
                 SET status_id = sk.id
                 FROM status_keywords sk
                 WHERE sk.keyword = t.status
                   AND t.status_id IS NULL
                   AND t.status IS NOT NULL
                   AND t.status <> ''"
            );
            @$conn->query("ALTER TABLE {$tbl} DROP COLUMN IF EXISTS status");
        }
    }

    openregistry_sync_serial_sequence($conn, 'status_keywords', 'id');
}

/** @return array<int, string> */
function openregistry_get_status_keywords(PgConnection $conn): array
{
    $result = $conn->query('SELECT id, keyword FROM status_keywords ORDER BY keyword ASC');
    $list = [];
    if ($result instanceof PgResult) {
        while ($row = $result->fetch_assoc()) {
            $list[(int)$row['id']] = $row['keyword'];
        }
    }
    return $list;
}

function openregistry_resolve_status(PgConnection $conn, ?int $statusId, string $fallback = ''): string
{
    if ($statusId === null || $statusId <= 0) {
        return $fallback;
    }
    $stmt = $conn->prepare('SELECT keyword FROM status_keywords WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return $fallback;
    }
    $stmt->bind_param('i', $statusId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['keyword'] ?? $fallback;
}

function openregistry_admin_where_sql(): string
{
    return "(role = 'admin' OR user_type = 'admin') AND role != 'system_operator' AND user_type != 'system_operator'";
}

function openregistry_portal_user_where_sql(): string
{
    return "role NOT IN ('admin', 'system_operator') AND user_type NOT IN ('admin', 'system_operator')";
}
