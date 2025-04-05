<?php
# Product creation system which validates if correct user is accessing. #
# Security Measures. #
# Error logging. #


session_start();
require_once 'DB.php';
require_once 'security.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit();
}

$categories = [
    'Coffee',
    'Accessories'
];

setSecurityHeaders();
$csrf_token = generateCSRFToken();
$error = '';
$success = '';
$db = new DB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Security validation failed');
        }

        error_log("POST data received: " . print_r($_POST, true));
        error_log("FILES data received: " . print_r($_FILES, true));

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

        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Image upload failed: ' . $_FILES['image']['error']);
        }

        $targetDir = "uploads/";
        if (!file_exists($targetDir)) {
            if (!mkdir($targetDir, 0777, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }

        $imageFileType = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $imageName = uniqid() . '.' . $imageFileType;
        $targetFile = $targetDir . $imageName;

        
        if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            throw new Exception('Only JPG, JPEG, PNG & GIF files are allowed');
        }

        if ($_FILES["image"]["size"] > 5000000) {
            throw new Exception('File is too large. Maximum size is 5MB');
        }

        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            throw new Exception('Failed to upload image: ' . error_get_last()['message']);
        }

        $imageUrl = '/L5SW/Callum/' . $targetFile;

        $conn = $db->connect();
        $stmt = $conn->prepare("
            INSERT INTO tbl_products (name, description, price, category, stock, image) 
            VALUES (:name, :description, :price, :category, :stock, :image)
        ");

        $params = [
            ':name' => $name,
            ':description' => $description,
            ':price' => $price,
            ':category' => $category,
            ':stock' => $stock,
            ':image' => $imageUrl
        ];

        error_log("SQL params: " . print_r($params, true));

        if (!$stmt->execute($params)) {
            throw new Exception('Database error: ' . implode(', ', $stmt->errorInfo()));
        }

        $success = 'Product created successfully';
        header('Refresh: 2; URL=admin.php');
    } catch(Exception $e) {
        $error = $e->getMessage();
        error_log("Create product error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Product - Admin Dashboard</title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Create New Product</h1>
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

        <form method="post" class="create-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="form-group">
                <label for="name">Product Name</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" required></textarea>
            </div>

            <div class="form-group">
                <label for="price">Price (£)</label>
                <input type="number" id="price" name="price" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <option value="">Select a category</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="stock">Stock</label>
                <input type="number" id="stock" name="stock" required>
            </div>

            <div class="form-group">
                <label for="image">Product Image</label>
                <input type="file" id="image" name="image" accept="image/*" required>
                <small>Maximum file size: 5MB. Allowed formats: JPG, JPEG, PNG, GIF</small>
            </div>

            <button type="submit" class="btn">Create Product</button>
        </form>
    </div>
</body>
</html>