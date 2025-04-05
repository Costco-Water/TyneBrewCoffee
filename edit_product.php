<?php
# Edit product system #
# Security Measures #


session_start();
require_once 'DB.php';
require_once 'security.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit();
}

setSecurityHeaders();
$csrf_token = generateCSRFToken();
$error = '';
$success = '';
$db = new DB();


if(isset($_GET['id'])) {
    try {
        $stmt = $db->connect()->prepare("SELECT * FROM tbl_products WHERE id = :id");
        $stmt->execute([':id' => $_GET['id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$product) {
            throw new Exception('Product not found');
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Security validation failed');
        }

        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
        $category = sanitizeInput($_POST['category']);
        $stock = filter_var($_POST['stock'], FILTER_VALIDATE_INT);

        if (!$price || $price <= 0) {
            throw new Exception('Invalid price');
        }

        if (!$stock || $stock < 0) {
            throw new Exception('Invalid stock quantity');
        }

        $stmt = $db->connect()->prepare("
            UPDATE tbl_products 
            SET name = :name, description = :description, price = :price, 
                category = :category, stock = :stock
            WHERE id = :id
        ");

        if ($stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':price' => $price,
            ':category' => $category,
            ':stock' => $stock,
            ':id' => $_GET['id']
        ])) {
            $success = 'Product updated successfully';
            header('Refresh: 2; URL=admin.php');
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product - Admin Dashboard</title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Edit Product</h1>
            <div class="admin-navigation">
                <a href="admin.php" class="btn secondary">Back to Dashboard</a>
            </div>
        </div>

        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if(isset($product)): ?>
            <form method="post" class="edit-form">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required><?= htmlspecialchars($product['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="price">Price (£)</label>
                    <input type="number" id="price" name="price" step="0.01" value="<?= $product['price'] ?>" required>
                </div>

                <div class="form-group">
                    <label for="category">Category:</label>
                    <select name="category" id="category" class="form-control" required>
                        <option value="Coffee" <?php echo ($product['category'] === 'Coffee') ? 'selected' : ''; ?>>Coffee</option>
                        <option value="Accessories" <?php echo ($product['category'] === 'Accessories') ? 'selected' : ''; ?>>Accessories</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="stock">Stock</label>
                    <input type="number" id="stock" name="stock" value="<?= $product['stock'] ?>" required>
                </div>

                <button type="submit" class="btn">Update Product</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>