<?php
/**
 * RISE - Index / Landing Page
 * =============================
 */

session_start();

// If logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Otherwise redirect to login
header('Location: login.php');
exit;