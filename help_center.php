<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
openregistry_ensure_extensions($conn);

/**
 * Minimal markdown → HTML for the user guide (headings, lists, paragraphs).
 */
function openregistry_simple_markdown_to_html(string $md): string
{
    $lines = preg_split('/\r\n|\r|\n/', $md);
    $out = '';
    $inUl = false;
    foreach ($lines as $line) {
        $trim = rtrim($line);
        if (preg_match('/^### (.+)/', $trim, $m)) {
            if ($inUl) { $out .= '</ul>'; $inUl = false; }
            $out .= '<h4 class="guide-h">' . htmlspecialchars($m[1]) . '</h4>';
            continue;
        }
        if (preg_match('/^## (.+)/', $trim, $m)) {
            if ($inUl) { $out .= '</ul>'; $inUl = false; }
            $out .= '<h3 class="guide-h">' . htmlspecialchars($m[1]) . '</h3>';
            continue;
        }
        if (preg_match('/^# (.+)/', $trim, $m)) {
            if ($inUl) { $out .= '</ul>'; $inUl = false; }
            $out .= '<h2 class="guide-h">' . htmlspecialchars($m[1]) . '</h2>';
            continue;
        }
        if (preg_match('/^[-*] (.+)/', $trim, $m)) {
            if (!$inUl) { $out .= '<ul class="guide-ul">'; $inUl = true; }
            $out .= '<li>' . htmlspecialchars($m[1]) . '</li>';
            continue;
        }
        if ($trim === '---') {
            if ($inUl) { $out .= '</ul>'; $inUl = false; }
            $out .= '<hr class="guide-hr">';
            continue;
        }
        if ($trim === '') {
            if ($inUl) { $out .= '</ul>'; $inUl = false; }
            $out .= '<br>';
            continue;
        }
        if ($inUl) { $out .= '</ul>'; $inUl = false; }
        $out .= '<p class="guide-p">' . htmlspecialchars($trim) . '</p>';
    }
    if ($inUl) $out .= '</ul>';
    return $out;
}

$guidePath = __DIR__ . '/docs/USER_GUIDE.md';
$guideHtml = '';
if (is_readable($guidePath)) {
    $raw = file_get_contents($guidePath);
    $guideHtml = openregistry_simple_markdown_to_html($raw);
}

$formOk  = false;
$formErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['help_submit'])) {
    $email    = trim((string)($_POST['email']    ?? ''));
    $question = trim((string)($_POST['question'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formErr = 'Please enter a valid email address.';
    } elseif ($question === '' || strlen($question) < 10) {
        $formErr = 'Please enter a question (at least 10 characters).';
    } else {
        $stmt = $conn->prepare('INSERT INTO help_submissions (email, question) VALUES (?, ?)');
        if ($stmt) {
            $stmt->bind_param('ss', $email, $question);
            if ($stmt->execute()) { $formOk = true; }
            else { $formErr = 'Could not save your question. Please try again later.'; }
            $stmt->close();
        } else {
            $formErr = 'Database error.';
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Help | Open Registry</title>
    <link rel="icon" type="image/jpeg" href="includes/images/mlhud jpeg 5.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary:       #E08D3C;
            --primary-dark:  #c97a2d;
            --primary-light: #FCF1E3;
            --primary-deep:  #b57526;
            --accent-light:  #F7E5D9;
            --border:        #e8e8e8;
            --text:          #1a1a1a;
            --muted:         #555;
            --surface:       #f7f7f7;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: var(--primary-light);
            color: var(--text);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── TOP BAR ── */
        .hc-topbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 56px;
            background: var(--primary);
            border-bottom: 1px solid var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 100;
            box-shadow: 0 2px 4px rgba(0,0,0,.12);
        }
        .hc-brand { display: flex; align-items: center; gap: 10px; }
        .hc-logo-mark {
            width: 32px; height: 32px;
            background: rgba(255,255,255,.25);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 14px;
        }
        .hc-brand-name  { font-size: 15px; font-weight: 600; color: #fff; line-height: 1.2; }
        .hc-brand-sub   { font-size: 11px; color: var(--primary-light); opacity: .85; }
        .hc-back-btn {
            font-size: 13px;
            color: #fff;
            border: 1px solid rgba(255,255,255,.5);
            border-radius: 6px;
            padding: 5px 13px;
            background: transparent;
            cursor: pointer;
            text-decoration: none;
            display: flex; align-items: center; gap: 6px;
            transition: background .15s;
        }
        .hc-back-btn:hover { background: rgba(255,255,255,.2); color: #fff; }

        /* ── LAYOUT ── */
        .hc-layout {
            display: flex;
            margin-top: 56px;
            min-height: calc(100vh - 56px);
        }

        /* ── SIDEBAR ── */
        .hc-sidebar {
            width: 260px;
            flex-shrink: 0;
            background: #fff;
            border-right: 1px solid var(--border);
            padding: 24px 0;
            position: sticky;
            top: 56px;
            height: calc(100vh - 56px);
            overflow-y: auto;
        }
        .hc-sidebar-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #aaa;
            padding: 0 20px 8px;
        }
        .hc-sidebar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 20px;
            font-size: 14px;
            color: var(--muted);
            cursor: pointer;
            border-left: 3px solid transparent;
            transition: background .12s, color .12s;
            user-select: none;
        }
        .hc-sidebar-item:hover  { background: var(--accent-light); color: var(--text); }
        .hc-sidebar-item.active {
            background: var(--accent-light);
            color: var(--primary-dark);
            border-left-color: var(--primary);
            font-weight: 600;
        }
        .hc-sidebar-item i      { width: 18px; text-align: center; font-size: 13px; opacity: .75; }
        .hc-sidebar-divider     { height: 1px; background: var(--border); margin: 14px 20px; }

        /* ── MAIN CONTENT ── */
        .hc-main {
            flex: 1;
            padding: 36px 44px;
            max-width: 920px;
            min-width: 0;
        }
        .hc-page { display: none; }
        .hc-page.active { display: block; }

        .hc-page-title { font-size: 22px; font-weight: 700; color: var(--text); margin-bottom: 6px; }
        .hc-page-sub   { font-size: 14px; color: var(--muted); margin-bottom: 26px; line-height: 1.6; }

        /* ── CARDS ── */
        .hc-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .hc-card-body { padding: 22px 26px; }
        .hc-card-section { padding: 20px 26px; border-bottom: 1px solid var(--border); }
        .hc-card-section:last-child { border-bottom: none; }
        .hc-section-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .hc-badge {
            font-size: 11px;
            background: var(--accent-light);
            color: var(--primary-deep);
            border-radius: 4px;
            padding: 2px 8px;
            font-weight: 500;
        }

        /* ── TOC GRID ── */
        .hc-toc-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 24px;
        }
        .hc-toc-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 13px 15px;
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s;
        }
        .hc-toc-card:hover { border-color: var(--primary); box-shadow: 0 2px 8px rgba(224,141,60,.15); }
        .hc-toc-num   { font-size: 12px; font-weight: 600; color: var(--primary); margin-bottom: 4px; }
        .hc-toc-title { font-size: 14px; font-weight: 600; color: var(--text); }
        .hc-toc-desc  { font-size: 12px; color: var(--muted); margin-top: 3px; }

        /* ── BULLETS & STEPS ── */
        ul.hc-bullets { list-style: none; padding: 0; margin: 0 0 10px; }
        ul.hc-bullets li {
            font-size: 14px; color: var(--muted); line-height: 1.8;
            padding: 3px 0 3px 20px; position: relative;
        }
        ul.hc-bullets li::before {
            content: ''; width: 6px; height: 6px;
            background: var(--primary); border-radius: 50%;
            position: absolute; left: 2px; top: 11px;
        }

        ol.hc-steps { list-style: none; padding: 0; margin: 0 0 10px; counter-reset: hcs; }
        ol.hc-steps li {
            counter-increment: hcs;
            display: flex; gap: 12px;
            font-size: 14px; color: var(--muted); line-height: 1.7;
            margin-bottom: 11px; align-items: flex-start;
        }
        ol.hc-steps li::before {
            content: counter(hcs);
            width: 24px; height: 24px; border-radius: 50%;
            background: var(--accent-light); color: var(--primary-deep);
            font-size: 12px; font-weight: 700; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            margin-top: 1px;
        }

        /* ── NOTE BOX ── */
        .hc-note {
            background: var(--accent-light);
            border-left: 4px solid var(--primary);
            border-radius: 0 8px 8px 0;
            padding: 12px 16px;
            margin-top: 14px;
        }
        .hc-note p { font-size: 13px; color: var(--primary-deep); line-height: 1.65; margin: 0; }

        /* ── ROLE CARDS ── */
        .hc-role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .hc-role-card { border: 1px solid var(--border); border-radius: 10px; padding: 18px; }
        .hc-role-label {
            font-size: 14px; font-weight: 700; color: var(--text);
            margin-bottom: 11px; display: flex; align-items: center; gap: 8px;
        }
        .hc-dot-orange { width: 9px; height: 9px; background: var(--primary);  border-radius: 50%; }
        .hc-dot-blue   { width: 9px; height: 9px; background: #378ADD; border-radius: 50%; }

        /* ── SUB TITLE ── */
        .hc-sub-title { font-size: 13px; font-weight: 700; color: var(--text); margin: 16px 0 7px; }
        .hc-sub-title:first-child { margin-top: 0; }
        .hc-divider { height: 1px; background: var(--border); margin: 16px 0; }
        .hc-prose { font-size: 14px; color: var(--muted); line-height: 1.8; margin-bottom: 10px; }
        .hc-prose:last-child { margin-bottom: 0; }

        /* ── FAQ ── */
        .hc-faq-row {
            padding: 15px 26px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
        }
        .hc-faq-row:last-child { border-bottom: none; }
        .hc-faq-row:hover { background: var(--accent-light); }
        .hc-faq-q {
            font-size: 14px; font-weight: 600; color: var(--text);
            display: flex; justify-content: space-between; align-items: center; gap: 12px;
        }
        .hc-faq-chevron { font-size: 12px; color: #aaa; transition: transform .2s; flex-shrink: 0; }
        .hc-faq-chevron.open { transform: rotate(180deg); }
        .hc-faq-a { font-size: 14px; color: var(--muted); line-height: 1.75; margin-top: 10px; display: none; }
        .hc-faq-a.open { display: block; }

        /* ── ASK FORM ── */
        .hc-form-card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 26px 28px; max-width: 600px; }
        .hc-form-hint { font-size: 14px; color: var(--muted); margin-bottom: 20px; line-height: 1.7; }
        .hc-field { margin-bottom: 18px; }
        .hc-field label { display: block; font-size: 13px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        .hc-field input,
        .hc-field select,
        .hc-field textarea {
            width: 100%; font-size: 14px;
            border: 1px solid var(--border); border-radius: 8px;
            padding: 10px 14px; background: #fff; color: var(--text);
            outline: none; font-family: inherit;
            transition: border-color .15s, box-shadow .15s;
        }
        .hc-field input:focus,
        .hc-field select:focus,
        .hc-field textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(224,141,60,.18);
        }
        .hc-field textarea { resize: vertical; min-height: 110px; }
        .hc-submit-btn {
            background: var(--primary); color: #fff;
            border: none; border-radius: 7px;
            padding: 10px 22px; font-size: 13px; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
            font-family: inherit;
            transition: background .15s;
        }
        .hc-submit-btn:hover { background: var(--primary-dark); }

        /* ── GUIDE RENDERED CONTENT ── */
        .guide-box { margin-top: 4px; }
        .guide-h  { color: var(--text); margin: 14px 0 6px; font-size: 14px; font-weight: 600; }
        .guide-p  { font-size: 13px; color: var(--muted); line-height: 1.7; margin-bottom: 6px; }
        .guide-ul { padding-left: 20px; margin-bottom: 8px; }
        .guide-ul li { font-size: 13px; color: var(--muted); line-height: 1.7; }
        .guide-hr { border: none; border-top: 1px solid var(--border); margin: 14px 0; }
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="hc-topbar">
    <div class="hc-brand">
        <div class="hc-logo-mark"><i class="fas fa-building-columns"></i></div>
        <div>
            <div class="hc-brand-name">Open Registry</div>
            <div class="hc-brand-sub">Help Center</div>
        </div>
    </div>
    <button class="hc-back-btn" onclick="history.back()">
        <i class="fas fa-arrow-left"></i> Back
    </button>
</div>

<div class="hc-layout">

    <!-- SIDEBAR -->
    <aside class="hc-sidebar">
        <div class="hc-sidebar-label">Documentation</div>
        <div class="hc-sidebar-item active" onclick="showPage('overview', this)">
            <i class="#"></i>Overview
        </div>
        <div class="hc-sidebar-item" onclick="showPage('roles', this)">
            <i class="#"></i>User roles
        </div>
        <div class="hc-sidebar-item" onclick="showPage('signin', this)">
            <i class="#"></i>Signing in
        </div>
        <div class="hc-sidebar-item" onclick="showPage('tasks', this)">
            <i class="#"></i>Daily tasks
        </div>
        <div class="hc-sidebar-item" onclick="showPage('editing', this)">
            <i class="#"></i>Editing records
        </div>
        <div class="hc-sidebar-divider"></div>
        <div class="hc-sidebar-label">Support</div>
        <div class="hc-sidebar-item" onclick="showPage('faq', this)">
            <i class="fas fa-circle-question"></i> FAQ
        </div>
        <div class="hc-sidebar-item" onclick="showPage('ask', this)">
            <i class="fas fa-envelope"></i> Ask a question
        </div>
    </aside>

    <!-- MAIN -->
    <main class="hc-main">

        <!-- ══ OVERVIEW ══ -->
        <div id="page-overview" class="hc-page active">
            <div class="hc-page-title">User guide manual</div>
            <p class="hc-page-sub">For Senior Records Officers, Assistant Records Officers, Records Officers, Data Entry Clerks &amp; Office Attendants.</p>

            <div class="hc-toc-grid">
                <div class="hc-toc-card" onclick="showPage('roles', null)">
                    <div class="hc-toc-num">Section 2</div>
                    <div class="hc-toc-title">User  roles</div>
                    <div class="hc-toc-desc">Users vs Admin access</div>
                </div>
                <div class="hc-toc-card" onclick="showPage('signin', null)">
                    <div class="hc-toc-num">Section 3</div>
                    <div class="hc-toc-title">Signing in</div>
                    <div class="hc-toc-desc">Step-by-step login guide</div>
                </div>
                <div class="hc-toc-card" onclick="showPage('tasks', null)">
                    <div class="hc-toc-num">Section 4</div>
                    <div class="hc-toc-title">Daily tasks</div>
                    <div class="hc-toc-desc">Dashboard, viewing, editing</div>
                </div>
                <div class="hc-toc-card" onclick="showPage('editing', null)">
                    <div class="hc-toc-num">Section 5</div>
                    <div class="hc-toc-title">Editing records</div>
                    <div class="hc-toc-desc">Spreadsheet editor tips</div>
                </div>
                <div class="hc-toc-card" onclick="showPage('faq', null)">
                    <div class="hc-toc-num">Section 6</div>
                    <div class="hc-toc-title">Getting help</div>
                    <div class="hc-toc-desc">Troubleshooting &amp; FAQ</div>
                </div>
                <div class="hc-toc-card" onclick="showPage('ask', null)">
                    <div class="hc-toc-num">Support</div>
                    <div class="hc-toc-title">Ask a question</div>
                    <div class="hc-toc-desc">Submit via email</div>
                </div>
            </div>

            <div class="hc-card">
                <div class="hc-card-section">
                    <div class="hc-section-title">What this system does <span class="hc-badge">Overview</span></div>
                    <ul class="hc-bullets">
                        <li>Keeps electronic registers and table lists of files/records in one place</li>
                        <li>Lets you search and view particular entries and registers</li>
                        <li>Lets you add or change records only for authorized registers assigned to you</li>
                        <li>Enables administrator tools: user accounts, data import/upload, exports, and status reporting</li>
                    </ul>
                </div>
                <div class="hc-card-section">
                    <div class="hc-section-title">Important access rules</div>
                    <p class="hc-prose">Any logged-in user can view any register in read-only mode. You can only edit the registers your admin has assigned to your account — typically up to three per user. If a link sends you to the wrong dashboard, sign out and sign back in through the correct portal.</p>
                </div>
                <?php if ($guideHtml !== ''): ?>
                <div class="hc-card-section">
                    <div class="hc-section-title">Full guide content</div>
                    <div class="guide-box"><?= $guideHtml ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══ USER ROLES ══ -->
        <div id="page-roles" class="hc-page">
            <div class="hc-page-title">User roles</div>
            <p class="hc-page-sub">The system has two main roles — regular Users and Administrators.</p>

            <div class="hc-role-grid">
                <div class="hc-role-card">
                    <div class="hc-role-label"><span class="hc-dot-orange"></span> Regular user</div>
                    <ul class="hc-bullets">
                        <li>Access via <strong>Access User Portal</strong> on the home page</li>
                        <li>View any register (read-only) from the sidebar via View Database</li>
                        <li>Edit only assigned/authorized registers — usually up to 3 per user</li>
                        <li>See dashboard quick summaries and daily totals</li>
                        <li>Cannot access bulk import, admin dashboard, or user creation</li>
                    </ul>
                </div>
                <div class="hc-role-card">
                    <div class="hc-role-label"><span class="hc-dot-blue"></span> Administrator</div>
                    <ul class="hc-bullets">
                        <li>Access via <strong>Access Admin Portal</strong> — separate account table from regular users</li>
                        <li>Full admin dashboard access</li>
                        <li>Create and manage user accounts &amp; module assignments</li>
                        <li>Import spreadsheet data in CSV format</li>
                        <li>Use status manager &amp; analytics to export data for stored media</li>
                    </ul>
                </div>
            </div>

            <div class="hc-note">
                <p>To reach Get Help: from the home page footer or the User login screen, click <strong>Get Help</strong>. You can read this guide and use Ask a Question to submit a message for feedback.</p>
            </div>
        </div>

        <!-- ══ SIGNING IN ══ -->
        <div id="page-signin" class="hc-page">
            <div class="hc-page-title">Signing in</div>
            <p class="hc-page-sub">Step-by-step login guide for both user types.</p>

            <div class="hc-card">
                <div class="hc-card-section">
                    <div class="hc-section-title">Regular user login</div>
                    <ol class="hc-steps">
                        <li>Open the Open Registry home page.</li>
                        <li>Click <strong>Access User Portal</strong>, or go directly to User Login if bookmarked.</li>
                        <li>Enter the username and password given by your administrator.</li>
                        <li>You will land on the User dashboard.</li>
                    </ol>
                </div>
                <div class="hc-card-section">
                    <div class="hc-section-title">Administrator login</div>
                    <ol class="hc-steps">
                        <li>Open the home page.</li>
                        <li>Click <strong>Access Admin Portal</strong>.</li>
                        <li>Enter your admin username and password.</li>
                        <li>You will land on the Admin dashboard.</li>
                    </ol>
                </div>
                <div class="hc-card-section">
                    <div class="hc-section-title">Signing out</div>
                    <p class="hc-prose">Use <strong>Sign out / Logout</strong> from the dashboard or sidebar. Always sign out on shared or public computers.</p>
                </div>
            </div>
        </div>

        <!-- ══ DAILY TASKS ══ -->
        <div id="page-tasks" class="hc-page">
            <div class="hc-page-title">User dashboard &amp; daily tasks</div>
            <p class="hc-page-sub">Common things you will do on the dashboard every day.</p>

            <div class="hc-card">
                <div class="hc-card-section">
                    <div class="hc-section-title">See your overview</div>
                    <p class="hc-prose">The dashboard shows totals such as entries made today and your personal contributions to the registers.</p>
                </div>
                <div class="hc-card-section">
                    <div class="hc-section-title">View any register (read-only)</div>
                    <ol class="hc-steps">
                        <li>Click <strong>View Database</strong> in the sidebar.</li>
                        <li>In the modal, choose the register you want to view — e.g. Master Register, Pensioners Register.</li>
                        <li>Click <strong>View Table</strong>.</li>
                        <li>The database viewer opens — you can search, sort, and refresh.</li>
                    </ol>
                    <div class="hc-note">
                        <p>CSV export in the viewer is for administrators only. Regular users can browse and search only.</p>
                    </div>
                </div>
                <div class="hc-card-section">
                    <div class="hc-section-title">Edit an authorized register</div>
                    <ol class="hc-steps">
                        <li>Click <strong>Edit Register</strong>.</li>
                        <li>In the modal, choose a register — only your authorized registers appear.</li>
                        <li>Enter how many rows you want to work on.</li>
                        <li>Click <strong>Start editing</strong> the spreadsheet-style entry screen opens.</li>
                    </ol>
                    <p class="hc-prose" style="margin-top:10px">If you need access to another register, ask your senior administrator to update your assigned modules.</p>
                </div>
            </div>
        </div>

        <!-- ══ EDITING RECORDS ══ -->
        <div id="page-editing" class="hc-page">
            <div class="hc-page-title">Spreadsheet-style editing</div>
            <p class="hc-page-sub">Tips for using the data entry screen effectively.</p>

            <div class="hc-card">
                <div class="hc-card-section">
                    <div class="hc-section-title">Navigation &amp; saving</div>
                    <ul class="hc-bullets">
                        <li>Move between cells using <strong>Tab</strong> or <strong>Enter</strong> where supported.</li>
                        <li>Many modules save a row automatically when all required fields are completed.</li>
                        <li>A yellow highlight or confirmation message confirms a successful save.</li>
                    </ul>
                </div>
                <div class="hc-card-section">
                    <div class="hc-section-title">If you see a warning</div>
                    <p class="hc-prose">A warning about missing required fields means the row has not been saved. Complete all highlighted cells and the row will save automatically.</p>
                </div>
                <div class="hc-card-section">
                    <div class="hc-section-title">Data standards</div>
                    <p class="hc-prose">Refer to the official data standards document for how file numbers, dates, and statuses should be formatted and entered into the system.</p>
                </div>
                <div class="hc-card-section">
                    <div class="hc-section-title">Rules to remember</div>
                    <ul class="hc-bullets">
                        <li>Any logged-in user can view any register in read-only mode.</li>
                        <li>You can only edit registers that your senior admin has assigned and authorized to your account.</li>
                        <li>If a link sends you to the wrong dashboard, sign out and sign in again using the correct portal (User vs Admin).</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ══ FAQ ══ -->
        <div id="page-faq" class="hc-page">
            <div class="hc-page-title">Frequently asked questions</div>
            <p class="hc-page-sub">Quick answers to the most common issues.</p>

            <div class="hc-card">
                <div class="hc-faq-row" onclick="toggleFaq(this)">
                    <div class="hc-faq-q">I can't log in — what should I do? <i class="fas fa-chevron-down hc-faq-chevron"></i></div>
                    <div class="hc-faq-a">Confirm you are using the correct login page — User Portal for regular users, Admin Portal for admins. These use separate account tables, so credentials from one will not work on the other. If your credentials are correct and login still fails, contact your administrator to verify your account is active.</div>
                </div>
                <div class="hc-faq-row" onclick="toggleFaq(this)">
                    <div class="hc-faq-q">A register I need is not showing in Edit Register <i class="fas fa-chevron-down hc-faq-chevron"></i></div>
                    <div class="hc-faq-a">Only registers assigned to your account by an administrator will appear in the Edit Register list. Contact your senior administrator and ask them to add the required register to your assigned modules.</div>
                </div>
                <div class="hc-faq-row" onclick="toggleFaq(this)">
                    <div class="hc-faq-q">My row is not saving in the editor <i class="fas fa-chevron-down hc-faq-chevron"></i></div>
                    <div class="hc-faq-a">Look for highlighted or empty required fields in the row. All required cells must be filled before the row is saved. Complete any missing fields and the save will trigger automatically.</div>
                </div>
                <div class="hc-faq-row" onclick="toggleFaq(this)">
                    <div class="hc-faq-q">I see a blank page or database error <i class="fas fa-chevron-down hc-faq-chevron"></i></div>
                    <div class="hc-faq-a">Contact your IT administrator immediately. Note the page you were on and the last action you took before the error appeared — this information helps diagnose the issue quickly.</div>
                </div>
                <div class="hc-faq-row" onclick="toggleFaq(this)">
                    <div class="hc-faq-q">I landed on the wrong dashboard after logging in <i class="fas fa-chevron-down hc-faq-chevron"></i></div>
                    <div class="hc-faq-a">Sign out completely using the Logout button, then sign in again through the correct portal — User Portal or Admin Portal. Make sure you are entering the right credentials for each portal type.</div>
                </div>
            </div>

            <div class="hc-note" style="margin-top:16px">
                <p>Still stuck? Use the <strong>Ask a question</strong> section in the sidebar to send your question directly to the support team.</p>
            </div>
        </div>

        <!-- ══ ASK A QUESTION ══ -->
        <div id="page-ask" class="hc-page">
            <div class="hc-page-title">Ask a question</div>
            <p class="hc-page-sub">Submit your question and we will respond to your official email address.</p>

            <?php if ($formOk): ?>
                <div class="hc-note" style="margin-bottom:20px">
                    <p><strong>Thank you.</strong> Your question was submitted. Expect a response by email when possible.</p>
                </div>
            <?php endif; ?>

            <?php if ($formErr !== ''): ?>
                <div class="alert alert-danger mb-3" style="font-size:13px"><?= htmlspecialchars($formErr) ?></div>
            <?php endif; ?>

            <div class="hc-form-card">
                <p class="hc-form-hint">Clearly describe your issue and use your official email address so we can respond directly to you.</p>
                <form method="post" action="">
                    <input type="hidden" name="help_submit" value="1">
                    <div class="hc-field">
                        <label for="email">Official email address</label>
                        <input type="email" id="email" name="email" required
                               placeholder="yourname@office.go.ug"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="hc-field">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">Select a category…</option>
                            <option>Login / access issue</option>
                            <option>Register editing problem</option>
                            <option>Data entry / Saving issue</option>
                            <option>Admin / User management</option>
                            <option>Technical error (blank page, crash)</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="hc-field">
                        <label for="question">Your question or description</label>
                        <textarea id="question" name="question" required
                                  placeholder="Describe what you need help with, including any steps you already tried…"><?= htmlspecialchars($_POST['question'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="hc-submit-btn">
                        <i class="#"></i>Submit 
                    </button>
                </form>
            </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showPage(id, clickedItem) {
    document.querySelectorAll('.hc-page').forEach(function(p) {
        p.classList.remove('active');
    });
    document.getElementById('page-' + id).classList.add('active');

    document.querySelectorAll('.hc-sidebar-item').forEach(function(i) {
        i.classList.remove('active');
    });
    if (clickedItem) {
        clickedItem.classList.add('active');
    } else {
        var map = {
            overview: 0, roles: 1, signin: 2, tasks: 3, editing: 4, faq: 5, ask: 6
        };
        var items = document.querySelectorAll('.hc-sidebar-item');
        if (items[map[id]]) items[map[id]].classList.add('active');
    }

    document.querySelector('.hc-main').scrollTop = 0;
    window.scrollTo(0, 0);
}

function toggleFaq(row) {
    var answer  = row.querySelector('.hc-faq-a');
    var chevron = row.querySelector('.hc-faq-chevron');
    answer.classList.toggle('open');
    chevron.classList.toggle('open');
}
</script>
</body>
</html>