<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_session_start();

if (openregistry_is_system_operator()) {
    header('Location: ' . openregistry_uri('system_admin_dashboard.php'));
    exit();
}

header('Location: ' . openregistry_uri('index.php'));
exit();
