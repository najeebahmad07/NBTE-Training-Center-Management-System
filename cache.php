
<?php
// Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Destroy PHP session
session_start();
session_unset();
session_destroy();

// Delete all cookies
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach ($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time() - 1000, '/');
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Clear Browser Data</title>

<script>
function clearBrowserData(){

    // Clear Local Storage
    localStorage.clear();

    // Clear Session Storage
    sessionStorage.clear();

    // Clear Cookies
    document.cookie.split(";").forEach(function(c) {
        document.cookie = c.replace(/^ +/, "")
        .replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
    });

    // Force reload without cache
    location.reload(true);
}
</script>

<style>
body{
    font-family: Arial;
    text-align:center;
    margin-top:120px;
}

button{
    padding:15px 30px;
    font-size:18px;
    background:#28a745;
    color:white;
    border:none;
    border-radius:6px;
    cursor:pointer;
}
</style>

</head>

<body>

<h2>Clear Browser Cache & Data</h2>

<button onclick="clearBrowserData()">Clear Cache</button>

</body>
</html>

