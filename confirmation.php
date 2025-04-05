<?php
# A detailed confirmation page for orders with user checks on whos order. #
# Security Measures. #


session_start();
require 'DB.php';
require 'security.php';


if(!validateSession()) {
    session_destroy();
    header('Location: login.php');
    exit();
}

if(!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

if(!isset($_GET['order_id'])) {
    header('Location: index.php');
    exit();
}


setSecurityHeaders();

$db = new DB();
$order_id = filter_var($_GET['order_id'], FILTER_VALIDATE_INT);
if(!$order_id) {
    header('Location: index.php');
    exit();
}


$stmt = $db->connect()->prepare("
    SELECT o.*, u.username, u.email 
    FROM tbl_orders o 
    JOIN Users u ON o.user_id = u.id 
    WHERE o.order_id = :order_id 
    AND u.username = :username
    AND o.user_id = :user_id
");

$stmt->execute([
    ':order_id' => $order_id,
    ':username' => $_SESSION['username'],
    ':user_id' => $_SESSION['user_id']
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$order) {
    header('Location: index.php');
    exit();
}


$stmt = $db->connect()->prepare("
    SELECT oi.*, p.name 
    FROM tbl_order_items oi
    JOIN tbl_products p ON oi.product_id = p.id
    WHERE oi.order_id = :order_id
");
$stmt->execute([':order_id' => $order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Tyne Brew Coffee</title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Order Confirmation</h1>
            <div class="admin-navigation">
                <a href="index.php" class="btn">Continue Shopping</a>
                <a href="account.php" class="btn">View Order History</a>
            </div>
        </div>

        <div class="admin-section">
            <div class="confirmation-message">
                <h2>Thank you for your order!</h2>
                <p>Your order has been successfully placed.</p>
            </div>

            <div class="order-details">
                <h3>Order #<?= htmlspecialchars($order['order_id']) ?></h3>
                <div class="details-grid">
                    <div class="order-info">
                        <p><strong>Date:</strong> <?= htmlspecialchars($order['order_date']) ?></p>
                        <p><strong>Status:</strong> <span class="status-<?= htmlspecialchars($order['status']) ?>">
                            <?= ucfirst(htmlspecialchars($order['status'])) ?>
                        </span></p>
                    </div>
                </div>

                <?php if(!empty($items)): ?>
                    <h3>Order Items</h3>
                    <table class="admin-table">
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                        <?php foreach($items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['quantity']) ?></td>
                                <td>£<?= number_format($item['price'], 2) ?></td>
                                <td>£<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <div style="text-align: right; margin-top: 20px; background: #fff; padding: 15px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <p class="total-price" style="font-size: 1.2em; font-weight: bold; margin: 0;">
                            Total: £<?= number_format($order['total_price'], 2) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>