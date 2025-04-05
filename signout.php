<?php
#Secure Signout Page.# 
#Security Measures.#




session_start();
session_regenerate_id(true);


ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');


$_SESSION = array();


if (isset($_COOKIE[session_name()])) {
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]
    );
}


session_destroy();


header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Clear-Site-Data: "cache", "cookies", "storage"');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');


header('Location: login.php');
exit();