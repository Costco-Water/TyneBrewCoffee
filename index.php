<!-- Triggering CI pipeline for assignment evidence -->


<?php
# Product listing page. #
# Security Measures. #
# Category filtering, images, prices and add to basket functionality. #



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
$conn = $db->connect();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Security validation failed');
        }
        
        if (isset($_POST['add']) && isset($_POST['product_id'])) {
            $id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
            if (add($db, $id)) {
                $success = 'Product added to basket';
            }
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}


$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';


try {
    $query = "SELECT * FROM tbl_products WHERE active = 1 AND (is_deleted = 0 OR is_deleted IS NULL)";
    if (!empty($category)) {
        $query .= " AND category = :category";
    }
    $stmt = $conn->prepare($query);
    if (!empty($category)) {
        $stmt->bindParam(':category', $category);
    }
    $stmt->execute();
    $products = $stmt->fetchAll();

    
    $catStmt = $conn->query("SELECT DISTINCT category FROM tbl_products WHERE active = 1 AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY category");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch(Exception $e) {
    error_log("Product query error: " . $e->getMessage());
    $error = "Failed to load products";
    $products = [];
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Tyne Brew Coffee</title>
    <link rel="stylesheet" href="./style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="main-container">
        <h1>Our Products</h1>

        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="filter-section">
            <form method="GET" action="index.php">
                <select name="category" class="filter-select">
                    <option value="">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn">Filter</button>
            </form>
        </div>

        <div class="products-grid">
            <?php foreach($products as $product): ?>
                <div class="product-card">
                    <img src="<?= htmlspecialchars($product['image']) ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>">
                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                    <p class="price">£<?= number_format($product['price'], 2) ?></p>
                    <form method="post" action="basket.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <button type="submit" name="add" class="btn">Add to Basket</button>
                    </form>
                    <a href="information.php?id=<?= $product['id'] ?>" class="btn">More Info</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>