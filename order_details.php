<?php
session_start();
# Order Details to display order info. #
# Security Measures. #
# Role based access. #
#


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require 'DB.php';
require 'security.php';

$validSession = validateSession();
if (!$validSession) {
    $_SESSION['CREATED'] = time();
    $_SESSION['LAST_ACTIVITY'] = time();
    $_SESSION['IP'] = $_SERVER['REMOTE_ADDR'];
}

setSecurityHeaders();
$csrf_token = generateCSRFToken();
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$db = new DB();
$order = null;
$items = array();
$error = '';


if(isset($_POST['update_status']) && $isAdmin) {
    if(!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed';
    } else {
        try {
            $stmt = $db->connect()->prepare("UPDATE tbl_orders SET status = ? WHERE order_id = ?");
            $stmt->execute([
                $_POST['status'],
                $_POST['order_id']
            ]);
            header('Location: order_details.php?id=' . $_POST['order_id']);
            exit();
        } catch(Exception $e) {
            $error = 'Failed to update status';
        }
    }
}

if(isset($_GET['id'])) {
    try {
        
        $query = "SELECT 
            o.*,
            u.username,
            u.email,
            CASE 
                WHEN o.order_id <= 32 THEN u.username
                ELSE COALESCE(o.customer_name, u.username, 'Unknown User')
            END as display_name,
            CASE 
                WHEN o.order_id <= 32 THEN 'Legacy Contact'
                ELSE COALESCE(o.phone, 'Not Available')
            END as display_phone,
            CASE 
                WHEN o.order_id <= 32 THEN 'Legacy Order'
                ELSE COALESCE(o.address_line1, 'Not Available')
            END as display_address1,
            CASE 
                WHEN o.order_id <= 32 THEN ''
                ELSE COALESCE(o.address_line2, '')
            END as display_address2,
            CASE 
                WHEN o.order_id <= 32 THEN ''
                ELSE COALESCE(o.city, '')
            END as display_city,
            CASE 
                WHEN o.order_id <= 32 THEN ''
                ELSE COALESCE(o.postcode, '')
            END as display_postcode
            FROM tbl_orders o 
            LEFT JOIN Users u ON o.user_id = u.id 
            WHERE o.order_id = :id";
        
        if (!$isAdmin) {
            $query .= " AND o.user_id = :user_id";
        }

        $stmt = $db->connect()->prepare($query);
        $params = [':id' => $_GET['id']];
        if (!$isAdmin) {
            $params[':user_id'] = $_SESSION['user_id'];
        }
        
        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $isLegacyOrder = $order['order_id'] <= 32;
            
            if (!$isLegacyOrder) {
                $stmt = $db->connect()->prepare("
                    SELECT oi.*, p.name as product_name, p.category,
                           (oi.price * oi.quantity) as subtotal
                    FROM tbl_order_items oi
                    LEFT JOIN tbl_products p ON oi.product_id = p.id
                    WHERE oi.order_id = :order_id
                ");
                $stmt->execute([':order_id' => $_GET['id']]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $items = [[
                    'product_name' => 'Legacy Order',
                    'category' => 'Historical Purchase',
                    'quantity' => 1,
                    'price' => $order['total_price'],
                    'subtotal' => $order['total_price']
                ]];
            }
        }
    } catch(Exception $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $isAdmin ? 'Order Details - Admin Dashboard' : 'Order Details' ?></title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Order Details #<?= htmlspecialchars($_GET['id'] ?? '') ?></h1>
            <div class="admin-navigation">
                <a href="<?= $isAdmin ? 'admin.php' : 'account.php' ?>" class="btn">
                    <?= $isAdmin ? 'Back to Dashboard' : 'Back to Account' ?>
                </a>
            </div>
        </div>

        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if($order): ?>
            <div class="admin-section">
                <div class="order-details">
                    <div class="details-grid">
                        <div class="customer-info">
                            <h2>Customer Information</h2>
                            <p><strong>Username:</strong> <?= htmlspecialchars($order['username'] ?? 'Unknown User') ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($order['email'] ?? 'Not Available') ?></p>
                            <?php if (!$isLegacyOrder): ?>
                                <p><strong>Name:</strong> <?= htmlspecialchars($order['display_name']) ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($order['display_phone']) ?></p>
                            <?php endif; ?>
                            <?php if ($isLegacyOrder): ?>
                                <p><em>Legacy order - limited information available</em></p>
                            <?php endif; ?>
                        </div>

                        <div class="delivery-info">
                            <h2>Delivery Address</h2>
                            <?php if (!$isLegacyOrder): ?>
                                <p><?= htmlspecialchars($order['display_address1']) ?></p>
                                <?php if($order['display_address2']): ?>
                                    <p><?= htmlspecialchars($order['display_address2']) ?></p>
                                <?php endif; ?>
                                <p><?= htmlspecialchars($order['display_city']) ?></p>
                                <p><?= htmlspecialchars($order['display_postcode']) ?></p>
                            <?php else: ?>
                                <p><em>Legacy order - no delivery details recorded</em></p>
                            <?php endif; ?>
                        </div>

                        <div class="order-info">
                            <h2>Order Information</h2>
                            <p><strong>Order Date:</strong> <?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></p>
                            <?php if($isAdmin): ?>
                            <p><strong>Status:</strong> 
                                <form method="post" class="status-form">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="status">
                                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                    <button type="submit" class="btn btn-small">Update</button>
                                </form>
                            </p>
                            <?php else: ?>
                            <p><strong>Status:</strong> <?= ucfirst(htmlspecialchars($order['status'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h2>Order Items</h2>
                    <?php if(!empty($items)): ?>
                        <div style="background: #f8f8f8; padding: 20px; border-radius: 8px; margin-top: 20px;">
                            <table class="admin-table" style="background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                </tr>
                                <?php foreach($items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><?= htmlspecialchars($item['category']) ?></td>
                                        <td>£<?= number_format($item['price'], 2) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>£<?= number_format($item['subtotal'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                            <div style="text-align: right; margin-top: 20px; background: #fff; padding: 15px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <p class="total-price" style="font-size: 1.2em; font-weight: bold; margin: 0; color: #333;">
                                    Total: £<?= number_format($order['total_price'], 2) ?>
                                </p>
                            </div>
                        </div>
                    <?php else: ?>
                        <p>No items found for this order.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <p>Order not found.</p>
        <?php endif; ?>
    </div>
</body>
</html>