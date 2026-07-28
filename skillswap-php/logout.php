<?php
require_once __DIR__ . '/includes/auth.php';
sign_out();
header('Location: login.php');
exit;
