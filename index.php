<?php
require_once __DIR__ . '/config/base_url.php';
require_once __DIR__ . '/config/session_handler.php';
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
}
exit;
