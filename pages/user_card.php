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
    <div class="user-card <?php echo $user['role']; ?>" style="border: 2px solid <?php echo $user['role'] === 'customer' ? '#007BFF' : '#28A745'; ?>; padding: 20px; background-color: #f8f9fa;">
        <h2><?php echo htmlspecialchars($user['name']); ?></h2>
        <p><strong>City:</strong> <?php echo htmlspecialchars($user['city']); ?></p>
        <p><strong>Phone:</strong> <span style="margin-right: 5px;">📞</span><?php echo htmlspecialchars($user['mobile']); ?></p>
        <p><strong>Email:</strong> <span style="margin-right: 5px;">📧</span><a href="mailto:<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></a></p>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>