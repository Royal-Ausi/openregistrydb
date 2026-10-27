<?php
/**
 * semiCurrent_edit.php — legacy module stub.
 * Editing is handled centrally by edit_register.php.
 * This file redirects to the central editor for backward compatibility.
 */
require_once __DIR__ . '/../includes/auth.php';
openregistry_session_start();

$base = openregistry_dashboard_href();
header('Location: ' . rtrim(openregistry_uri(''), '/') . '/edit_register.php?table=semicurrent_records&rows=10&mode=new');
exit();
