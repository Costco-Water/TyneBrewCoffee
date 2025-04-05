<?php
# Security Measures. #




if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 1800);
    
    session_set_cookie_params([
        'lifetime' => 1800,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}


define('SESSION_TIMEOUT', 1800);        #30 minutes#
define('SESSION_REGENERATE_TIME', 300);  #5 minutes#
define('MAX_LOGIN_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW', 300);       #5 minutes#
define('ALLOWED_METHODS', ['GET', 'POST']);
define('MIN_PASSWORD_LENGTH', 8);
define('TOKEN_EXPIRY', 3600);           #1 hour#
define('MAX_UPLOAD_SIZE', 5242880);     #5MB#


define('SQL_PATTERNS', [
    '\bOR\b\s*\'?\d*\'?\s*=\s*\'?\d*\'?',
    '\bUNION\b.*\bSELECT\b',
    '\bAND\b\s*\'?\d*\'?\s*=\s*\'?\d*\'?',
    '--',
    '\/\*|\*\/',
    ';',
    '\'',
    '=',
    'DROP',
    'TRUNCATE',
    'DELETE',
    'INSERT',
    'UPDATE',
    'EXEC',
    'EXECUTE'
]);

function setSecurityHeaders() {
    if (headers_sent($filename, $linenum)) {
        error_log("Headers already sent in $filename on line $linenum");
        return;
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Content-Security-Policy: default-src \'self\'; img-src \'self\' data: https:; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; font-src \'self\' https://fonts.gstatic.com');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}


function validateSession($required = true) {
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
        $_SESSION['LAST_ACTIVITY'] = time();
        $_SESSION['IP'] = $_SERVER['REMOTE_ADDR'];
        return true;
    }

    if ($_SESSION['IP'] !== $_SERVER['REMOTE_ADDR']) {
        return false;
    }

    if (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT) {
        return false;
    }

    $_SESSION['LAST_ACTIVITY'] = time();
    return true;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    if (!validateSession() || !isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

function checkPasswordStrength($password) {
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        throw new Exception("Password must be at least " . MIN_PASSWORD_LENGTH . " characters");
    }
    if (!preg_match('/[A-Z]/', $password)) {
        throw new Exception("Must contain uppercase letter");
    }
    if (!preg_match('/[a-z]/', $password)) {
        throw new Exception("Must contain lowercase letter");
    }
    if (!preg_match('/[0-9]/', $password)) {
        throw new Exception("Must contain a number");
    }
    if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
        throw new Exception("Must contain special character");
    }
    return true;
}

function checkRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    if (!isset($_SESSION['login_attempts'][$ip])) {
        $_SESSION['login_attempts'][$ip] = [];
    }
    
    $time = time();
    $_SESSION['login_attempts'][$ip] = array_filter(
        $_SESSION['login_attempts'][$ip],
        function($attempt) use ($time) {
            return ($time - $attempt) < RATE_LIMIT_WINDOW;
        }
    );
    
    if (count($_SESSION['login_attempts'][$ip]) >= MAX_LOGIN_ATTEMPTS) {
        return false;
    }
    
    $_SESSION['login_attempts'][$ip][] = $time;
    return true;
}

function sanitizeInput($input, $type = 'string') {
    if (is_array($input)) {
        return array_map(function($item) use ($type) {
            return sanitizeInput($item, $type);
        }, $input);
    }

    $input = trim($input);
    
    foreach (SQL_PATTERNS as $pattern) {
        if (preg_match('/'. $pattern .'/i', $input)) {
            error_log("SQL Injection attempt detected: " . $input);
            return '';
        }
    }
    
    switch($type) {
        case 'search':
            $clean = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $input);
            return htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');
        
        case 'order_id':
            return filter_var($input, FILTER_VALIDATE_INT);
        
        case 'customer_name':
            $clean = preg_replace('/[^a-zA-Z0-9\s\-\']/', '', $input);
            return htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');
            
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
            
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
            
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT);
            
        case 'url':
            return filter_var($input, FILTER_SANITIZE_URL);
            
        case 'filename':
            return preg_replace('/[^a-zA-Z0-9._-]/', '', $input);
            
        case 'product':
            $clean = strip_tags(trim($input));
            return htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');
            
        default:
            $clean = strip_tags($input);
            return htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');
    }
}

function validateRequestMethod($allowed = ['GET']) {
    return in_array($_SERVER['REQUEST_METHOD'], $allowed);
}

function validateReferer() {
    if (!isset($_SERVER['HTTP_REFERER'])) {
        return false;
    }
    $referer = parse_url($_SERVER['HTTP_REFERER']);
    $host = $_SERVER['HTTP_HOST'];
    return isset($referer['host']) && $referer['host'] === $host;
}

function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return false;
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    return in_array($ext, $allowedTypes) && strpos($mimeType, 'image/') === 0;
}
?>