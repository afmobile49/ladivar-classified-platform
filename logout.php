<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';
user_logout();
header('Location: ' . BASE_URL . '/index.php');
exit;