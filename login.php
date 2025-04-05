<?php
# Login system. #
# Security Measures. #


session_start();
require 'DB.php';
require 'security.php';


setSecurityHeaders();
validateSession(false);
$csrf_token = generateCSRFToken();
$error = '';
$db = new DB();

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Security validation failed');
        }

        if (!checkRateLimit()) {
            throw new Exception('Too many attempts. Please try again later.');
        }

        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];

        if(empty($email) || empty($password)) {
            throw new Exception('Please fill in all fields');
        }

        $stmt = $db->connect()->prepare("SELECT * FROM Users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        
        if($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if(password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['CREATED'] = time();
                $_SESSION['LAST_ACTIVITY'] = time();
                $_SESSION['IP'] = $_SERVER['REMOTE_ADDR'];
                
                header("Location: index.php");
                exit();
            }
        }
        throw new Exception('Invalid email or password');
        
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Tyne Brew Coffee</title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="login-container">
        <h1>Login</h1>
        
        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="login-form" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" name="login" class="btn">Login</button>
            
            <p class="register-link">
                Don't have an account? <a href="register.php">Register here</a>
            </p>
        </form>
    </div>
</body>
</html>