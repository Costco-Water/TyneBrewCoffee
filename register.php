<?php
# Register a new user. #
# Security Measures.# 
# Error logging. #



session_start();
require_once 'DB.php';
require_once 'security.php';

setSecurityHeaders();
validateSession(false);
$csrf_token = generateCSRFToken();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Security validation failed');
        }

        $username = sanitizeInput($_POST['username']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
            throw new Exception('All fields are required');
        }

        if ($password !== $confirmPassword) {
            throw new Exception('Passwords do not match');
        }

        
        checkPasswordStrength($password);

        $db = new DB();
        $conn = $db->connect();

        $stmt = $conn->prepare("SELECT id FROM Users WHERE email = :email OR username = :username");
        $stmt->execute([':email' => $email, ':username' => $username]);
        
        if ($stmt->fetch()) {
            throw new Exception('Username or email already exists');
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO Users (username, email, password, role) VALUES (:username, :email, :password, 'user')");
        
        if (!$stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $hashedPassword
        ])) {
            throw new Exception('Registration failed - Database error');
        }

        $success = 'Registration successful! Please log in.';
        header('Refresh: 2; URL=login.php');

    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Tyne Brew Coffee</title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="register-container">
        <h1>Register</h1>
        
        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" class="register-form">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <div class="password-requirements">
                    Password requirements:
                    <ul>
                        <li>Minimum 8 characters</li>
                        <li>One uppercase letter</li>
                        <li>One lowercase letter</li>
                        <li>One number</li>
                        <li>One special character (!@#$%^&*)</li>
                    </ul>
                </div>
            </div>

            <button type="submit" class="btn">Register</button>
            
            <p class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </p>
        </form>
    </div>
</body>
</html>