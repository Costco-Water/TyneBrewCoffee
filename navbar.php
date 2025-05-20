<?php
# Nav bar, session based options #
# Security Measures #
# Role based access to Admin #

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'basketFunctions.php';
require_once 'security.php'; 
$currentPage = basename($_SERVER['PHP_SELF']);
$basketCount = getBasketItemCount();
$csrf_token = generateCSRFToken(); 
?>

<nav class="navbar">
    <div class="nav-container">
        <div class="nav-brand">
            <a href="index.php">Tyne Brew Coffee</a>
        </div>
        
        <div class="nav-links">
            <a href="index.php" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">Products</a>
            
            <a href="basket.php" class="nav-link <?= $currentPage === 'basket.php' ? 'active' : '' ?>">
                Basket <?= $basketCount > 0 ? "($basketCount)" : '' ?>
            </a>
            
            <?php if(isset($_SESSION['username'])): ?>
                <a href="account.php" class="nav-link <?= $currentPage === 'account.php' ? 'active' : '' ?>">My Account</a>
                
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="admin.php" class="nav-link <?= $currentPage === 'admin.php' ? 'active' : '' ?>">Admin</a>
                <?php endif; ?>
                
                <form method="post" action="signout.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <button type="submit" class="btn" style="background:none; border:none; cursor:pointer;">Sign Out</button>
                </form>
            <?php else: ?>
                <a href="login.php" class="nav-link <?= $currentPage === 'login.php' ? 'active' : '' ?>">Login</a>
                <a href="register.php" class="nav-link" <?= $currentPage === 'register.php' ? 'active' : '' ?>">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>