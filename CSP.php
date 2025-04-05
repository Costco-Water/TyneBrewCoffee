<?php
# CSP security #
# Security Measures. # 
# Nonce based validations #
# Headers #

function setSecurityHeaders() {
    $nonce = base64_encode(random_bytes(16));
    
    
    header("Content-Security-Policy: ".
        "default-src 'self'; " .
        "script-src 'self' 'nonce-{$nonce}'; " .
        "style-src 'self' 'unsafe-inline'; " .
        "img-src 'self' data: https:; " .
        "frame-ancestors 'none'; " .
        "form-action 'self'; " .
        "base-uri 'self'; " .
        "upgrade-insecure-requests; " .
        "block-all-mixed-content"
    );

    
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

    return $nonce;
}


$nonce = setSecurityHeaders();
define('CSP_NONCE', $nonce);