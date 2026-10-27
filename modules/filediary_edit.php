<?php

require_once __DIR__ . '/../includes/auth.php';
openregistry_session_start();

header('Location: ' . rtrim(openregistry_uri(''), '/') . '/edit_register.php?table=file_diary&rows=10&mode=new');
exit();
