<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_session_start();
unset($_SESSION['system_operator']);
header('Location: ' . openregistry_uri('index.php'));
exit();
