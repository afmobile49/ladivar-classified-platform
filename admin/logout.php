<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';

unset($_SESSION['admin_id'], $_SESSION['is_admin']);
session_destroy();

header('Location: ' . BASE_URL . '/admin/login.php');
exit;