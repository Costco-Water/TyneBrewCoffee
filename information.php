<?php
# Displays product info. #
# Security Measures. #
# Error handling. #

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
    if(!isset($_GET['id'])) {
        throw new Exception('Product ID not provided');
    }

    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if($id === false) {
        throw new Exception('Invalid product ID');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Security validation failed');
        }
        
        if(add($db, $id)) {
            header('Location: basket.php');
            exit();
        } else {
            throw new Exception('Failed to add product to basket');
        }
    }

    $stmt = $db->connect()->prepare("
        SELECT * FROM tbl_products 
        WHERE id = :id AND active = 1
    ");
    
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$product) {
        throw new Exception('Product not found');
    }

} catch(Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= isset($product) ? htmlspecialchars($product['name']) : 'Product Details' ?> - Tyne Brew Coffee</title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
    </head>
<body>
    <div class="container">
        <div class="back-navigation">
            <a href="index.php" class="btn secondary">Back to Products</a>
        </div>

        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if(isset($product)): ?>
            <div class="product-details">
                <div class="product-image">
                    <img src="<?= htmlspecialchars($product['image']) ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>">
                </div>
                <div class="product-info">
                    <h1><?= htmlspecialchars($product['name']) ?></h1>
                    <p class="price">£<?= number_format($product['price'], 2) ?></p>
                    <p class="category">Category: <?= htmlspecialchars($product['category']) ?></p>
                    <p class="description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" name="add" class="btn primary">Add to Basket</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>