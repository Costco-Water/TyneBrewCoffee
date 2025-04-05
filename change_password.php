<?php
# Handles password changes. #
# Security measures and strength checks. #
# Friendly UI with validation and error messages. #
#

session_start();
require 'DB.php';
require 'security.php';


if (!validateSession()) {
    session_destroy();
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

setSecurityHeaders();
$csrf_token = generateCSRFToken();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Security validation failed');
        }

        if (!checkRateLimit()) {
            throw new Exception('Too many attempts. Please try again later.');
        }

        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        
        if (!checkPasswordStrength($newPassword)) {
            throw new Exception('New password must be at least 8 characters long and contain uppercase, lowercase, and numbers');
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception('New passwords do not match');
        }

        $db = new DB();
        $stmt = $db->connect()->prepare("SELECT password FROM Users WHERE username = :username");
        $stmt->execute([':username' => $_SESSION['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($currentPassword, $user['password'])) {
            throw new Exception('Current password is incorrect');
        }

        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->connect()->prepare("UPDATE Users SET password = :password WHERE username = :username");
        $stmt->execute([
            ':password' => $hashedPassword,
            ':username' => $_SESSION['username']
        ]);

        $success = 'Password updated successfully';

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Tyne Brew Coffee</title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="container">
        <h1>Change Password</h1>
        
        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" class="form">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required
                       pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                       title="Must contain at least one number, one uppercase and lowercase letter, and at least 8 characters">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn">Change Password</button>
            <a href="account.php" class="btn">Back to Account</a>
        </form>
    </div>
</body>
</html>