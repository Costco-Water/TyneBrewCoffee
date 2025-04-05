<?php
# The account.php is a secure user dashboard for the Tyne Brew Coffee website. #
# Security Measures. #
# Retreieves user details, order history from DB and displays account info. #
# Has a password change option and order details. #
# Errors logged and shown user-friendly messages.#
# #


session_start();
require 'DB.php';
require 'security.php';

if (!validateSession()) {
    session_destroy();
    header('Location: login.php');
    exit();
}

if(!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

setSecurityHeaders();
$csrf_token = generateCSRFToken();

try {
    $db = new DB();
    
    $stmt = $db->connect()->prepare("SELECT * FROM Users WHERE username = :username AND id = :user_id");
    $stmt->execute([
        ':username' => $_SESSION['username'],
        ':user_id' => $_SESSION['user_id']
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }

    
    $stmt = $db->connect()->prepare("
        SELECT o.*, 
               COUNT(oi.item_id) as item_count
        FROM tbl_orders o
        LEFT JOIN tbl_order_items oi ON o.order_id = oi.order_id
        WHERE o.user_id = :user_id 
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([':user_id' => $user['id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Account Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Tyne Brew Coffee</title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="account-container">
        <h1>My Account</h1>
        
        <?php if(isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
            <div class="account-info">
                <h2>Account Details</h2>
                <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                <div class="account-actions">
                    <a href="change_password.php" class="btn">Change Password</a>
                </div>
            </div>
            
            <div class="order-history">
                <h2>Order History</h2>
                <?php if(empty($orders)): ?>
                    <p>No orders found.</p>
                <?php else: ?>
                    <div class="orders-grid">
                        <?php foreach($orders as $order): ?>
                            <div class="order-card">
                                <h3>Order #<?= htmlspecialchars($order['order_id']) ?></h3>
                                <p><strong>Date:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($order['order_date']))) ?></p>
                                <p><strong>Items:</strong> <?= htmlspecialchars($order['item_count']) ?></p>
                                <p><strong>Total:</strong> £<?= number_format($order['total_price'], 2) ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="status-<?= htmlspecialchars($order['status']) ?>">
                                        <?= ucfirst(htmlspecialchars($order['status'])) ?>
                                    </span>
                                </p>
                                <a href="order_details.php?id=<?= htmlspecialchars($order['order_id']) ?>" class="btn">View Details</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>