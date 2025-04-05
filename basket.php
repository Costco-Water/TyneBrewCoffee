<?php
# Secure basket page with adding, updating and removing items, emptying and calculating totals. #
# Security Measures. #
# Interface has product displays, quantity controls and checkout button conditioned if user is logged in or not. #



session_start();
require_once 'DB.php';
require_once 'security.php';
require_once 'basketFunctions.php';

setSecurityHeaders();
validateSession(false);
$csrf_token = generateCSRFToken();
$error = '';
$success = '';
$db = new DB();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Security validation failed');
        }

        
        if (isset($_POST['add']) && isset($_POST['product_id'])) {
            $id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
            if (add($db, $id)) {
                $success = 'Product added to basket';
            } else {
                throw new Exception('Failed to add product, No stock available.');
            }
        }

        
        if (isset($_POST['update']) && isset($_POST['product_id']) && isset($_POST['quantity'])) {
            $id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
            $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
            if (updateQuantity($id, $quantity)) {
                $success = 'Basket updated';
            }
        }

        
        if (isset($_POST['remove']) && isset($_POST['product_id'])) {
            $id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
            if (removeItem($id)) {
                $success = 'Item removed';
            }
        }

        
        if (isset($_POST['empty'])) {
            emptyBasket();
            $success = 'Basket emptied';
        }
    }

    $basketItems = getBasketItems();
    $total = getBasketTotal();

} catch(Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shopping Basket - Tyne Brew Coffee</title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="container">
        <div class="back-navigation">
            <a href="index.php" class="btn secondary">Continue Shopping</a>
        </div>

        <h1>Shopping Basket</h1>
        
        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if(empty($basketItems)): ?>
            <p>Your basket is empty</p>
        <?php else: ?>
            <div class="basket-items">
                <?php foreach($basketItems as $item): ?>
                    <div class="basket-item">
                        <img src="<?= htmlspecialchars($item['image']) ?>" 
                             alt="<?= htmlspecialchars($item['name']) ?>">
                        <div class="item-details">
                            <h3><?= htmlspecialchars($item['name']) ?></h3>
                            <p class="price">£<?= number_format($item['price'], 2) ?></p>
                            
                            <form method="post" class="quantity-form">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                <input type="number" name="quantity" 
                                       value="<?= $item['quantity'] ?>" 
                                       min="0" max="99"
                                       class="quantity-input">
                                <button type="submit" name="update" class="btn">Update</button>
                                <button type="submit" name="remove" class="btn danger">Remove</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="basket-summary">
                    <p class="total">Total: £<?= number_format($total, 2) ?></p>
                    
                    <form method="post" class="basket-actions">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" name="empty" class="btn secondary">Empty Basket</button>
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <a href="checkout.php" class="btn primary">Proceed to Checkout</a>
                        <?php else: ?>
                            <a href="login.php" class="btn primary">Login to Checkout</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>