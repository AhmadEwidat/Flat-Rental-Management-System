<?php
require_once 'dbconfig.inc.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birzeit Homes</title>
    <link rel="stylesheet" href="/AhmadEwidat1212596/assets/css/styles.css">
</head>
<body>
    <header>
        <div>
            <a href="/AhmadEwidat1212596/index.php" class="logo">
                <img src="/AhmadEwidat1212596/assets/images/logo.png" alt="Birzeit Homes Logo">
            </a>
        </div>
        <div>
         
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-card">
                    <?php
                    // جلب صورة المستخدم من قاعدة البيانات
                    $stmt = $pdo->prepare("SELECT photo FROM users WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user_photo = $stmt->fetchColumn();
                    
                    // إذا لم يكن هناك صورة، استخدم الصورة الافتراضية
                    if (!$user_photo) {
                        $user_photo = "/AhmadEwidat1212596/assets/images/user_photo.png";
                    } elseif (!str_starts_with($user_photo, 'http://') && !str_starts_with($user_photo, 'https://')) {
                        // إضافة الرابط الكامل إذا كان المسار نسبي
                        $user_photo = "http://" . $_SERVER['HTTP_HOST'] . $user_photo;
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($user_photo); ?>" alt="User Photo">
                    <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="/AhmadEwidat1212596/pages/profile.php">Profile</a>
                </div>
                <?php if ($_SESSION['role'] === 'customer'): ?>
                    <a href="/AhmadEwidat1212596/pages/shopping_basket.php" class="basket-icon">
                        Shopping Basket
                        <?php
                        // Get number of items in basket
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rents WHERE customer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                        $stmt->execute([$_SESSION['user_id']]);
                        $basketCount = $stmt->fetchColumn();
                        if ($basketCount > 0):
                        ?>
                        <span class="basket-count"><?php echo $basketCount; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <a href="/AhmadEwidat1212596/pages/logout.php">Logout</a>
            <?php else: ?>
                <a class="head" href="/AhmadEwidat1212596/pages/login.php">Login</a>
                <a class="head" href="/AhmadEwidat1212596/pages/signup.php" class="signup-link">Sign Up</a>
            <?php endif; ?>
            <a href="/AhmadEwidat1212596/pages/about_us.php">About Us</a>
        </div>
    </header>