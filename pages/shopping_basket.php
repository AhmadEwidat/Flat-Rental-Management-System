<?php
session_start();
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

// Handle remove from basket
if (isset($_POST['remove']) && isset($_POST['rent_id'])) {
    $stmt = $pdo->prepare("DELETE FROM rents WHERE rent_id = ? AND customer_id = ?");
    $stmt->execute([$_POST['rent_id'], $customer_id]);
}

// Get rented flats
$stmt = $pdo->prepare("
    SELECT r.*, f.*, o.name as owner_name
    FROM rents r
    JOIN flats f ON r.flat_id = f.flat_id
    JOIN owners o ON f.owner_id = o.owner_id
    WHERE r.customer_id = ? AND r.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY r.created_at DESC
");
$stmt->execute([$customer_id]);
$rented_flats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total
$total = 0;
foreach ($rented_flats as $flat) {
    $total += $flat['price'];
}
?>

<main>
    <h1>Shopping Basket</h1>

    <?php if (empty($rented_flats)): ?>
        <p>Your basket is empty.</p>
    <?php else: ?>
        <div class="basket-items">
            <?php foreach ($rented_flats as $flat): ?>
                <div class="basket-item">
                    <div class="flat-info">
                        <h3><?php echo htmlspecialchars($flat['title']); ?></h3>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($flat['location']); ?></p>
                        <p><strong>Price:</strong> $<?php echo number_format($flat['price'], 2); ?></p>
                        <p><strong>Owner:</strong> <?php echo htmlspecialchars($flat['owner_name']); ?></p>
                        <p><strong>Added to basket:</strong> <?php echo date('Y-m-d H:i', strtotime($flat['created_at'])); ?></p>
                    </div>
                    <form method="POST" class="remove-form">
                        <input type="hidden" name="rent_id" value="<?php echo $flat['rent_id']; ?>">
                        <button type="submit" name="remove" class="remove-button">Remove</button>
                    </form>
                </div>
            <?php endforeach; ?>

            <div class="basket-summary">
                <h3>Total: $<?php echo number_format($total, 2); ?></h3>
                <p class="note">Note: Items in your basket will expire after 24 hours.</p>
                <button class="checkout-button">Proceed to Checkout</button>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>