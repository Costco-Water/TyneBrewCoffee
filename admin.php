<?php
# Admin dashboard, with security and functionality. #
# Security Measures. #
# Order management with filtering, status updates and customer / order details. #
# Product management with CRUD. #
# Analytics tracking orders, revenue and stats. #


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

$validStatuses = ['pending', 'processing', 'completed'];
$statusFilter = isset($_GET['status_filter']) && in_array($_GET['status_filter'], $validStatuses, true) 
    ? sanitizeInput($_GET['status_filter'], 'string') 
    : '';

$searchTerm = '';
if (isset($_GET['search'])) {
    $searchInput = sanitizeInput($_GET['search'], 'search');
    if ($searchInput !== '') {
        $searchTerm = $searchInput;
    }
}

try {
    $conn = $db->connect();
    
    if(isset($_POST['update_status'])) {
        if(!validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Security validation failed');
        }

        $newStatus = sanitizeInput($_POST['status'], 'string');
        if (!in_array($newStatus, $validStatuses, true)) {
            throw new Exception('Invalid status value');
        }

        $orderId = filter_var($_POST['order_id'], FILTER_VALIDATE_INT);
        if (!$orderId) {
            throw new Exception('Invalid order ID');
        }

        $updateStmt = $conn->prepare("UPDATE tbl_orders SET status = :status WHERE order_id = :order_id");
        if(!$updateStmt->execute([
            ':status' => $newStatus,
            ':order_id' => $orderId
        ])) {
            throw new Exception('Failed to update status');
        }

        header('Location: admin.php' . ($statusFilter ? "?status_filter=" . urlencode($statusFilter) : ''));
        exit();
    }
    
    $stmtOrders = $conn->prepare("SELECT COUNT(*) as total FROM tbl_orders");
    $stmtRevenue = $conn->prepare("SELECT SUM(total_price) as revenue FROM tbl_orders");
    
    $stmtOrders->execute();
    $stmtRevenue->execute();

    $totalOrders = $stmtOrders->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $totalRevenue = $stmtRevenue->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

    $baseSQL = "SELECT o.*, u.username,
                CASE 
                    WHEN o.order_id <= 32 THEN u.username
                    ELSE COALESCE(o.customer_name, u.username, 'Unknown User')
                END as display_name
                FROM tbl_orders o 
                LEFT JOIN Users u ON o.user_id = u.id";
    
    $whereConditions = [];
    $params = [];

    if (!empty($statusFilter)) {
        $whereConditions[] = "o.status = :status";
        $params[':status'] = $statusFilter;
    }

    if (!empty($searchTerm)) {
        if (ctype_digit($searchTerm)) {
            $whereConditions[] = "o.order_id = :order_id";
            $params[':order_id'] = (int)$searchTerm;
        } else {
            $whereConditions[] = "LOWER(o.customer_name) LIKE LOWER(:customer_name)";
            $params[':customer_name'] = '%' . $searchTerm . '%';
        }
    }

    $sql = $baseSQL;
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(' AND ', $whereConditions);
    }
    $sql .= " ORDER BY o.order_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    
    if(isset($_POST['delete_id'])) {
        if (!validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Security validation failed');
        }
        
        $sanitized_id = filter_var($_POST['delete_id'], FILTER_VALIDATE_INT);
        if($sanitized_id === false) {
            throw new Exception('Invalid product ID');
        }
        
        $stmt = $conn->prepare("UPDATE tbl_products SET is_deleted = 1 WHERE id = :id");
        $stmt->execute([':id' => $sanitized_id]);
        
        header('Location: admin.php');
        exit();
    }

    
    $stmt = $conn->prepare("SELECT * FROM tbl_products WHERE is_deleted = 0 ORDER BY category, name");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e) {
    $error = $e->getMessage();
    error_log("Admin Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Tyne Brew Coffee</title>
    <link rel="stylesheet" href="./style.css">
    <?php include 'navbar.php'; ?>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Admin Dashboard</h1>
            <div class="admin-navigation">
                <a href="create.php" class="btn">Add New Product</a>
                <a href="index.php" class="btn">Back to Store</a>
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="admin-section analytics-section">
            <h2>Analytics Overview</h2>
            <div class="stats-grid">
                <div class="stat-box">
                    <h3>Total Orders</h3>
                    <p><?= $totalOrders ?></p>
                </div>
                <div class="stat-box">
                    <h3>Total Revenue</h3>
                    <p>£<?= number_format($totalRevenue, 2) ?></p>
                </div>
            </div>
        </div>

        <div class="admin-grid">
            <div class="admin-section">
                <h2>Order Management</h2>
                <div class="order-filters">
                    <form method="get" class="filter-form">
                        <select name="status_filter">
                            <option value="">All Status</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                        <input type="text" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search orders...">
                        <button type="submit" class="btn">Search</button>
                    </form>
                </div>
                
                <?php if(!empty($orders)): ?>
                    <table class="admin-table">
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        <?php foreach($orders as $order): ?>
                            <tr>
                                <td><?= $order['order_id'] ?></td>
                                <td><?= htmlspecialchars($order['display_name']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></td>
                                <td>£<?= number_format($order['total_price'], 2) ?></td>
                                <td>
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
                                </td>
                                <td>
                                    <a href="order_details.php?id=<?= $order['order_id'] ?>" class="btn">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>No orders found.</p>
                <?php endif; ?>
            </div>

            <div class="admin-section">
                <h2>Product Management</h2>
                <a href="create.php" class="btn add-new">Add New Product</a>
                
                <?php if(!empty($products)): ?>
                    <table class="admin-table">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Category</th>
                            <th>Actions</th>
                        </tr>
                        <?php foreach($products as $product): ?>
                            <tr>
                                <td><?= $product['id'] ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td>£<?= number_format($product['price'], 2) ?></td>
                                <td><?= htmlspecialchars($product['category']) ?></td>
                                <td>
                                    <div class="admin-actions">
                                        <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn">Edit</a>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="delete_id" value="<?= $product['id'] ?>">
                                            <button type="submit" class="btn" onclick="return confirm('Are you sure?')">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>No products found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>