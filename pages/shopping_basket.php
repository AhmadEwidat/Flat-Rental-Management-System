<?php

require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

// Handle remove from basket
if (isset($_POST['remove']) && isset($_POST['flat_id'])) {
    unset($_SESSION['shopping_basket'][$_POST['flat_id']]);
    header("Location: shopping_basket.php");
    exit;
}

// Get items from shopping basket
$basket_items = $_SESSION['shopping_basket'] ?? [];

// Calculate total
$total = 0;
foreach ($basket_items as $item) {
    $total += $item['total_amount'];
}
?>

<main>
    <h1>Shopping Basket</h1>

    <?php if (empty($basket_items)): ?>
        <p>Your basket is empty.</p>
    <?php else: ?>
        <div class="basket-items">
            <?php foreach ($basket_items as $flat_id => $item): ?>
                <div class="basket-item">
                    <div class="flat-info">
                        <h3>Flat <?php echo htmlspecialchars($item['ref_number']); ?></h3>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($item['location']); ?></p>
                        <p><strong>Monthly Rent:</strong> $<?php echo number_format($item['monthly_rent'], 2); ?></p>
                        <p><strong>Period:</strong> <?php echo date('Y-m-d', strtotime($item['start_date'])); ?> to <?php echo date('Y-m-d', strtotime($item['end_date'])); ?></p>
                        <p><strong>Duration:</strong> <?php echo $item['months']; ?> months</p>
                        <p><strong>Security Deposit:</strong> $<?php echo number_format($item['security_deposit'], 2); ?></p>
                        <p><strong>Total Amount:</strong> $<?php echo number_format($item['total_amount'], 2); ?></p>
                        <p><strong>Owner:</strong> <?php echo htmlspecialchars($item['owner_name']); ?></p>
                    </div>
                    <form method="POST" class="remove-form">
                        <input type="hidden" name="flat_id" value="<?php echo $flat_id; ?>">
                        <button type="submit" name="remove" class="remove-button">Remove</button>
                    </form>
                </div>
            <?php endforeach; ?>

            <div class="basket-summary">
                <h3>Total: $<?php echo number_format($total, 2); ?></h3>
                <p class="note">Note: Items in your basket will expire after 24 hours.</p>
                <a href="checkout.php" class="checkout-button">Proceed to Checkout</a>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>