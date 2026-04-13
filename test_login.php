<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

$result = loginUser('admin@rise.com', 'password'); // your email & password

echo '<pre>';
print_r($result);
echo '</pre>';

echo '<br>Session: <pre>';
print_r($_SESSION);
echo '</pre>';