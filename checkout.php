<?php
# Secure order processing. #
# Security Measures. #
# Order Summaries. #


session_start();
require 'DB.php';
require 'basketFunctions.php';
require 'security.php';

if (!validateSession()) {
    header('Location: login.php');
    exit();
}

setSecurityHeaders();
$csrf_token = generateCSRFToken();
$error = '';
$db = new DB();

if(empty($_SESSION['basket'])) {
    header('Location: basket.php');
    exit();
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateRequestMethod(['POST'])) {
            throw new Exception('Invalid request method');
        }

        if (!validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Security validation failed');
        }

        if (!checkRateLimit()) {
            throw new Exception('Too many attempts. Please try again later.');
        }

        if (!validateBasket($db)) {
            throw new Exception('Some items in your basket are no longer available');
        }

        $pdo = $db->connect();
        $pdo->beginTransaction();

        try {
            $basketItems = getBasketItems();
            $totalPrice = getBasketTotal();
            
            $stmt = $pdo->prepare("
                INSERT INTO tbl_orders (
                    user_id, order_date, total_price, status,
                    customer_name, address_line1, address_line2,
                    city, postcode, phone,
                    card_name, card_number, card_expiry, card_cvv
                ) VALUES (
                    :user_id, NOW(), :total_price, 'pending',
                    :customer_name, :address_line1, :address_line2,
                    :city, :postcode, :phone,
                    :card_name, :card_number, :card_expiry, :card_cvv
                )
            ");
            
            $params = [
                ':user_id' => $_SESSION['user_id'],
                ':total_price' => $totalPrice,
                ':customer_name' => $_POST['customer_name'],
                ':address_line1' => $_POST['address_line1'],
                ':address_line2' => $_POST['address_line2'] ?? '',
                ':city' => $_POST['city'],
                ':postcode' => strtoupper($_POST['postcode']),
                ':phone' => $_POST['phone'],
                ':card_name' => $_POST['card_name'],
                ':card_number' => password_hash($_POST['card_number'], PASSWORD_DEFAULT),
                ':card_expiry' => $_POST['card_expiry'],
                ':card_cvv' => password_hash($_POST['card_cvv'], PASSWORD_DEFAULT)
            ];
            
            $stmt->execute($params);
            $orderId = $pdo->lastInsertId();
            
            foreach($basketItems as $item) {
                $itemStmt = $pdo->prepare("
                    INSERT INTO tbl_order_items (order_id, product_id, quantity, price) 
                    VALUES (:order_id, :product_id, :quantity, :price)
                ");
                
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $item['id'],
                    ':quantity' => $item['quantity'],
                    ':price' => $item['price']
                ]);

                $stockStmt = $pdo->prepare("
                    UPDATE tbl_products 
                    SET stock = stock - :quantity 
                    WHERE id = :id
                ");
                
                $stockStmt->execute([
                    ':quantity' => $item['quantity'],
                    ':id' => $item['id']
                ]);
            }
            
            $pdo->commit();
            emptyBasket();
            
            header('Location: confirmation.php?order_id=' . $orderId);
            exit();
            
        } catch(Exception $e) {
            $pdo->rollBack();
            throw new Exception('Order processing failed: ' . $e->getMessage());
        }
        
    } catch(Exception $e) {
        $error = $e->getMessage();
        error_log("Checkout Error: " . $e->getMessage());
    }
}

$basketItems = getBasketItems();
$total = getBasketTotal();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - Tyne Brew Coffee</title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Checkout</h1>
            <div class="admin-navigation">
                <a href="basket.php" class="btn">Back to Basket</a>
            </div>
        </div>

        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="admin-section">
            <div class="checkout-grid">
                <div class="checkout-details">
                    <form method="post" class="checkout-form">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        
                        <div class="form-section">
                            <h2>Delivery Details</h2>
                            <div class="form-group">
                                <label for="customer_name">Full Name</label>
                                <input type="text" id="customer_name" name="customer_name" required>
                            </div>

                            <div class="form-group">
                                <label for="address_line1">Address Line 1</label>
                                <input type="text" id="address_line1" name="address_line1" required>
                            </div>

                            <div class="form-group">
                                <label for="address_line2">Address Line 2</label>
                                <input type="text" id="address_line2" name="address_line2">
                            </div>

                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" required>
                            </div>

                            <div class="form-group">
                                <label for="postcode">Postcode</label>
                                <input type="text" id="postcode" name="postcode" 
                                       pattern="[A-Za-z0-9]{2,4}\s[A-Za-z0-9]{3}" 
                                       maxlength="8" 
                                       placeholder="NE1 1AA" required>
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" 
                                       pattern="[0-9]{11}" 
                                       maxlength="11" 
                                       placeholder="07123456789" required>
                            </div>
                        </div>

                        <div class="form-section">
                            <h2>Payment Details</h2>
                            <div class="form-group">
                                <label for="card_name">Name on Card</label>
                                <input type="text" id="card_name" name="card_name" required>
                            </div>

                            <div class="form-group">
                                <label for="card_number">Card Number</label>
                                <input type="text" id="card_number" name="card_number" 
                                       pattern="\d{16}" maxlength="16" 
                                       placeholder="1234567890123456" required>
                            </div>

                            <div class="form-group card-details">
                                <div>
                                    <label for="card_expiry">Expiry Date</label>
                                    <input type="text" id="card_expiry" name="card_expiry" 
                                           pattern="\d{2}/\d{2}" maxlength="5" 
                                           placeholder="MM/YY" required>
                                </div>
                                <div>
                                    <label for="card_cvv">CVV</label>
                                    <input type="text" id="card_cvv" name="card_cvv" 
                                           pattern="\d{3}" maxlength="3" 
                                           placeholder="123" required>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn">Place Order</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="order-summary-section">
                    <h2>Order Summary</h2>
                    <table class="admin-table">
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                        <?php foreach($basketItems as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>£<?= number_format($item['price'], 2) ?></td>
                                <td>£<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <div class="order-total">
                        <p class="total-price">
                            Total: £<?= number_format($total, 2) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>