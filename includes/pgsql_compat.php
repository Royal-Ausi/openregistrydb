<?php
/**
 * PostgreSQL database layer with a mysqli-compatible API.
 * Uses PHP PDO (pgsql driver) internally for prepared statements and connection pooling.
 */

declare(strict_types=1);

/**
 * Translate common MySQL-specific SQL to PostgreSQL.
 */
function openregistry_translate_sql(string $sql): string
{
    $original = $sql;

    if (preg_match('/^\s*SHOW\s+COLUMNS\s+FROM\s+[`"]?(\w+)[`"]?\s+LIKE\s+\?\s*$/i', $sql, $m)) {
        return "SELECT column_name AS \"Field\" FROM information_schema.columns "
            . "WHERE table_schema = CURRENT_SCHEMA() AND table_name = '{$m[1]}' AND column_name = ?";
    }

    if (preg_match('/^\s*SHOW\s+COLUMNS\s+FROM\s+[`"]?(\w+)[`"]?\s+LIKE\s+[\'"](\w+)[\'"]\s*$/i', $sql, $m)) {
        return "SELECT column_name AS \"Field\" FROM information_schema.columns "
            . "WHERE table_schema = CURRENT_SCHEMA() AND table_name = '{$m[1]}' AND column_name = '{$m[2]}'";
    }

    if (preg_match('/^\s*SHOW\s+TABLES\s+LIKE\s+[\'"](\w+)[\'"]\s*$/i', $sql, $m)) {
        return "SELECT tablename AS \"Tables_in_db\" FROM pg_catalog.pg_tables "
            . "WHERE schemaname = CURRENT_SCHEMA() AND tablename = '{$m[1]}'";
    }

    $sql = preg_replace(
        '/DATE_SUB\s*\(\s*NOW\s*\(\s*\)\s*,\s*INTERVAL\s+(\d+)\s+DAY\s*\)/i',
        "(CURRENT_TIMESTAMP - INTERVAL '$1 days')",
        $sql
    );

    if (preg_match('/^\s*INSERT\s+IGNORE\s+INTO\s+(`?\w+`?)\s*\(([^)]+)\)\s*VALUES\s*\(([^)]+)\)\s*$/i', $sql, $m)) {
        $table = str_replace('`', '', $m[1]);
        $cols = str_replace('`', '', $m[2]);
        $vals = $m[3];
        if (stripos($cols, 'keyword') !== false) {
            return "INSERT INTO {$table} ({$cols}) VALUES ({$vals}) ON CONFLICT (keyword) DO NOTHING";
        }
        return "INSERT INTO {$table} ({$cols}) VALUES ({$vals}) ON CONFLICT DO NOTHING";
    }

    if (preg_match(
        '/UPDATE\s+(`?\w+`?)\s+(\w+)\s+JOIN\s+(\w+)\s+(\w+)\s+ON\s+(.+?)\s+SET\s+(.+?)\s+WHERE\s+(.+)$/is',
        $sql,
        $m
    )) {
        $table = str_replace('`', '', $m[1]);
        $alias = $m[2];
        $joinTable = $m[3];
        $joinAlias = $m[4];
        $on = str_replace('`', '', $m[5]);
        $set = str_replace('`', '', $m[6]);
        $where = str_replace('`', '', $m[7]);
        return "UPDATE {$table} {$alias} SET {$set} FROM {$joinTable} {$joinAlias} "
            . "WHERE {$on} AND {$where}";
    }

    $sql = str_replace('`', '', $sql);
    $sql = preg_replace('/\s+FIRST\b/i', '', $sql);
    $sql = preg_replace('/\bUNSIGNED\b/i', '', $sql);
    $sql = preg_replace('/TINYINT\s*\(\s*1\s*\)/i', 'SMALLINT', $sql);
    $sql = preg_replace('/\bIFNULL\s*\(/i', 'COALESCE(', $sql);

    return $sql;
}

/** @return array<int, mixed> */
function openregistry_pdo_params_from_types(string $types, array $params): array
{
    $bound = [];
    $len = strlen($types);
    for ($i = 0; $i < $len; $i++) {
        $value = $params[$i] ?? null;
        $type = $types[$i] ?? 's';
        if ($type === 'i') {
            $bound[] = ($value === '' || $value === null) ? null : (int)$value;
        } elseif ($type === 'd') {
            $bound[] = ($value === '' || $value === null) ? null : (float)$value;
        } else {
            $bound[] = $value;
        }
    }
    return $bound;
}

final class PgResultField
{
    public string $name;
    public string $orgname;
    public string $table;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->orgname = $name;
        $this->table = '';
    }
}

final class PgResult
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;
    /** @var array<int, PgResultField>|null */
    private ?array $fields = null;
    public int $num_rows;
    private int $position = 0;

    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->num_rows = count($rows);
        $this->position = 0;
    }

    public function fetch_assoc(): ?array
    {
        if ($this->position >= $this->num_rows) {
            return null;
        }
        return $this->rows[$this->position++];
    }

    /** @return array<int, mixed>|null */
    public function fetch_array(int $mode = MYSQLI_ASSOC): ?array
    {
        return $this->fetch_assoc();
    }

    /** @return array<int, PgResultField> */
    public function fetch_fields(): array
    {
        if ($this->fields !== null) {
            return $this->fields;
        }
        $this->fields = [];
        if (!empty($this->rows)) {
            foreach (array_keys($this->rows[0]) as $name) {
                $this->fields[] = new PgResultField((string)$name);
            }
        }
        return $this->fields;
    }

    public function free(): void
    {
        $this->rows = [];
        $this->num_rows = 0;
    }

    public function data_seek(int $offset): bool
    {
        return reset($this->rows) !== false;
    }
}

final class PgStatement
{
    private PgConnection $conn;
    /** @var PDOStatement|null */
    private $stmt;
    private string $sql;
    private string $types = '';
    public int $affected_rows = 0;
    public string $error = '';
    public int $param_count = 0;
    /** @var array<int, mixed> */
    private array $paramRefs = [];
    /** @var PgResult|null */
    private ?PgResult $result = null;
    private bool $isSelect = false;

    public function __construct(PgConnection $conn, PDOStatement $stmt, string $sql)
    {
        $this->conn = $conn;
        $this->stmt = $stmt;
        $this->sql = $sql;
        $this->param_count = substr_count($sql, '?');
        $this->isSelect = (bool)preg_match('/^\s*(SELECT|WITH)\b/i', $sql);
    }

    public function bind_param(string $types, &...$vars): bool
    {
        $args = func_get_args();
        $this->types = (string)array_shift($args);
        $this->paramRefs = [];
        foreach ($args as $i => &$arg) {
            $this->paramRefs[$i] = &$arg;
        }
        return true;
    }

    public function execute(): bool
    {
        $this->error = '';
        $this->result = null;
        try {
            $values = [];
            foreach ($this->paramRefs as &$ref) {
                $values[] = $ref;
            }
            unset($ref);
            $bound = openregistry_pdo_params_from_types($this->types, $values);
            $ok = $this->stmt->execute($bound);
            $this->affected_rows = max(0, $this->stmt->rowCount());
            if ($this->isSelect) {
                $rows = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->result = new PgResult($rows ?: []);
                $this->affected_rows = $this->result->num_rows;
            } elseif (preg_match('/^\s*INSERT\b/i', $this->sql)) {
                $this->conn->captureInsertId($this->sql);
            }
            return $ok;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->conn->setLastError($e->getMessage());
            return false;
        }
    }

    public function get_result(): PgResult
    {
        return $this->result ?? new PgResult([]);
    }

    public function close(): void
    {
        $this->stmt = null;
    }
}

final class PgConnection
{
    /** @var PDO|null */
    private $pdo;
    public string $connect_error = '';
    public string $error = '';
    public int $insert_id = 0;
    public int $affected_rows = 0;
    private string $lastInsertSql = '';

    public function __construct(string $host, string $user, string $password, string $dbname, int $port = 5432)
    {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        try {
            $this->pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            $this->connect_error = $e->getMessage();
        }
    }

    public function setLastError(string $message): void
    {
        $this->error = $message;
    }

    public function captureInsertId(string $sql): void
    {
        $this->lastInsertSql = $sql;
        try {
            $id = $this->pdo->lastInsertId();
            if ($id !== false && $id !== '0' && $id !== '') {
                $this->insert_id = (int)$id;
                return;
            }
            if (preg_match('/INSERT\s+INTO\s+(\w+)/i', $sql, $m)) {
                $table = $m[1];
                $seq = $this->pdo->query("SELECT pg_get_serial_sequence('{$table}', 'id') AS seq")->fetchColumn();
                if (!$seq) {
                    $seq = $this->pdo->query("SELECT pg_get_serial_sequence('{$table}', 'serial_number') AS seq")->fetchColumn();
                }
                if ($seq) {
                    $this->insert_id = (int)$this->pdo->query("SELECT CURRVAL('{$seq}')")->fetchColumn();
                }
            }
        } catch (Throwable $e) {
            $this->insert_id = 0;
        }
    }

    public function set_charset(string $charset): bool
    {
        return true;
    }

    public function begin_transaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /** @return PgResult|bool */
    public function query(string $sql)
    {
        $this->error = '';
        $this->affected_rows = 0;
        $sql = openregistry_translate_sql($sql);
        try {
            $stmt = $this->pdo->query($sql);
            if ($stmt instanceof PDOStatement) {
                if (preg_match('/^\s*(SELECT|WITH|SHOW)\b/i', $sql)) {
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $result = new PgResult($rows ?: []);
                    $this->affected_rows = $result->num_rows;
                    return $result;
                }
                $this->affected_rows = max(0, $stmt->rowCount());
                if (preg_match('/^\s*INSERT\b/i', $sql)) {
                    $this->captureInsertId($sql);
                }
                return true;
            }
            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * @return PgStatement|false
     */
    public function prepare(string $sql)
    {
        $this->error = '';
        $sql = openregistry_translate_sql($sql);
        try {
            $stmt = $this->pdo->prepare($sql);
            return new PgStatement($this, $stmt, $sql);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function real_escape_string(string $value): string
    {
        return substr($this->pdo->quote($value), 1, -1);
    }

    public function ping(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function getServerInfo(): string
    {
        try {
            return (string)$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (Throwable $e) {
            return 'PostgreSQL';
        }
    }

    public function close(): void
    {
        $this->pdo = null;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}

// Allow legacy type hints: function foo(mysqli $conn) still documents intent.
if (!class_exists('mysqli', false)) {
    class_alias(PgConnection::class, 'mysqli');
}
if (!class_exists('mysqli_stmt', false)) {
    class_alias(PgStatement::class, 'mysqli_stmt');
}
if (!class_exists('mysqli_result', false)) {
    class_alias(PgResult::class, 'mysqli_result');
}

if (!defined('MYSQLI_ASSOC')) {
    define('MYSQLI_ASSOC', 1);
}
