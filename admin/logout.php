<?php
require __DIR__ . '/inc/admin-auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    unset($_SESSION['aid']);
}
redirect('login.php');
