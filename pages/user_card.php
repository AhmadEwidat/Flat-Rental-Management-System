<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

$user_id = $_GET['id'] ?? '';
$user = null;

// Try customers table
$stmt = $pdo->prepare("SELECT c.name, c.city, c.mobile, c.email, u.role FROM customers c JOIN users u ON c.user_id = u.user_id WHERE c.customer_id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Try owners table if not found in customers
if (!$user) {
    $stmt = $pdo->prepare("SELECT o.name, o.city, o.mobile, o.email, u.role FROM owners o JOIN users u ON o.user_id = u.user_id WHERE o.owner_id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$user) {
    die("User not found.");
}
?>

<main>
    <div class="user-card <?php echo $user['role']; ?>">
        <h2><?php echo HtmlSpecialChars($user['name']); ?></h2>
        <p><strong>City:</strong> <?php echo HtmlSpecialChars($user['city']); ?></p>
        <p><strong>Phone:</strong> <span class="phone-icon">📞</span><?php echo HtmlSpecialChars($user['mobile']); ?></p>
        <p><strong>Email:</strong> <span class="email-icon">📧</span><a href="mailto:<?php echo HtmlSpecialChars($user['email']); ?>" class="external-link"><?php echo HtmlSpecialChars($user['email']); ?></a></p>
    </div>
</main>
<?php require_once '../includes/footer.php'; ?>