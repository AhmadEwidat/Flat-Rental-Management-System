<?php
require_once 'dbconfig.inc.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birzeit Homes</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <header>
        <div>
            <img src="../assets/images/logo.png" alt="Birzeit Homes Logo" class="logo">
            <h1>Birzeit Homes</h1>
        </div>
        <div>
            <a href="about_us.php">About Us</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-card">
                    <img src="../assets/images/user_photo.png" alt="User Photo">
                    <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="profile.php">Profile</a>
                </div>
                <?php if ($_SESSION['role'] === 'customer'): ?>
                    <a href="shopping_basket.php">Shopping Basket</a>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="customer_register.php">Sign Up</a>
            <?php endif; ?>
        </div>
    </header>