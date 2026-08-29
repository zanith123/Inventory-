<?php
require_once __DIR__ . '/../config/base_url.php';
require_once __DIR__ . '/../config/session_handler.php';
session_destroy();
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
