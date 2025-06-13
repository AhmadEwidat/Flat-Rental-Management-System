<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

// Check if shopping basket is empty
if (empty($_SESSION['shopping_basket'])) {
    header("Location: shopping_basket.php");
    exit;
}

// Get customer_id
$stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = :user_id");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    die("Customer not found.");
}
$customer_id = $customer['customer_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate payment details
    if (!preg_match('/^[0-9]{9}$/', $_POST['payment_card'])) {
        $error = "Invalid credit card number. Must be 9 digits.";
    } else {
        // Start transaction
        $pdo->beginTransaction();
        try {
            foreach ($_SESSION['shopping_basket'] as $flat_id => $rent_data) {
                // Insert rent record
                $stmt = $pdo->prepare("
                    INSERT INTO rents (
                        flat_id, 
                        customer_id, 
                        start_date, 
                        end_date, 
                        total_amount,
                        payment_card, 
                        payment_name, 
                        payment_expiry,
                        status,
                        approval_status
                    ) VALUES (
                        :flat_id, 
                        :customer_id, 
                        :start_date, 
                        :end_date, 
                        :total_amount,
                        :payment_card, 
                        :payment_name, 
                        :payment_expiry,
                        'pending',
                        'pending'
                    )
                ");
                $stmt->execute([
                    'flat_id' => $flat_id,
                    'customer_id' => $customer_id,
                    'start_date' => $rent_data['start_date'],
                    'end_date' => $rent_data['end_date'],
                    'total_amount' => $rent_data['total_amount'],
                    'payment_card' => password_hash($_POST['payment_card'], PASSWORD_DEFAULT),
                    'payment_name' => $_POST['payment_name'],
                    'payment_expiry' => $_POST['payment_expiry']
                ]);

                // Get the rent ID
                $rent_id = $pdo->lastInsertId();

                // Notify customer
                $stmt = $pdo->prepare("
                    INSERT INTO messages (
                        receiver_user_id, 
                        sender_user_id, 
                        title, 
                        body, 
                        is_read,
                        message_type,
                        flat_id,
                        rent_id,
                        status
                    ) VALUES (
                        :receiver_user_id, 
                        NULL, 
                        :title, 
                        :body, 
                        0,
                        'rent_request',
                        :flat_id,
                        :rent_id,
                        'pending'
                    )
                ");
                $stmt->execute([
                    'receiver_user_id' => $_SESSION['user_id'],
                    'title' => "Rental Request Submitted",
                    'body' => "Your rental request for flat {$rent_data['ref_number']} has been submitted for the period {$rent_data['start_date']} to {$rent_data['end_date']}. The owner will review your request and contact you soon.",
                    'flat_id' => $flat_id,
                    'rent_id' => $rent_id
                ]);

                // Notify owner
                $stmt = $pdo->prepare("
                    INSERT INTO messages (
                        receiver_user_id, 
                        sender_user_id, 
                        title, 
                        body, 
                        is_read,
                        message_type,
                        flat_id,
                        rent_id,
                        status
                    ) SELECT 
                        u.user_id, 
                        NULL, 
                        :title, 
                        :body, 
                        0,
                        'rent_request',
                        :flat_id,
                        :rent_id,
                        'pending'
                    FROM users u 
                    JOIN owners o ON u.user_id = o.user_id 
                    WHERE o.owner_id = :owner_id
                ");
                $stmt->execute([
                    'owner_id' => $rent_data['owner_id'],
                    'title' => "New Rental Request",
                    'body' => "You have a new rental request for flat {$rent_data['ref_number']} from {$_SESSION['user_name']} for the period {$rent_data['start_date']} to {$rent_data['end_date']}. Please review and respond to this request.",
                    'flat_id' => $flat_id,
                    'rent_id' => $rent_id
                ]);

                // Notify manager
                $stmt = $pdo->prepare("
                    INSERT INTO messages (
                        receiver_user_id, 
                        sender_user_id, 
                        title, 
                        body, 
                        is_read,
                        message_type,
                        flat_id,
                        rent_id,
                        status
                    ) SELECT 
                        u.user_id, 
                        NULL, 
                        :title, 
                        :body, 
                        0,
                        'rent_request',
                        :flat_id,
                        :rent_id,
                        'pending'
                    FROM users u 
                    WHERE u.role = 'manager'
                ");
                $stmt->execute([
                    'title' => "New Rental Request",
                    'body' => "A new rental request has been submitted for flat {$rent_data['ref_number']}. " .
                              "Customer: {$_SESSION['user_name']} ({$_SESSION['user_email']}). " .
                              "Period: {$rent_data['start_date']} to {$rent_data['end_date']}. " .
                              "Owner: {$rent_data['owner_name']} ({$rent_data['owner_mobile']}).",
                    'flat_id' => $flat_id,
                    'rent_id' => $rent_id
                ]);
            }

            // Commit transaction
            $pdo->commit();

            // Clear shopping basket
            unset($_SESSION['shopping_basket']);

            header("Location: view_messages.php?success=checkout");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error = "An error occurred while processing your request: " . $e->getMessage();
            error_log("Checkout Error: " . $e->getMessage());
        }
    }
}

// Calculate total
$total = 0;
foreach ($_SESSION['shopping_basket'] as $item) {
    $total += $item['total_amount'];
}
?>

<main>
    <div class="checkout-container">
        <h2>Checkout</h2>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <div class="order-summary">
            <h3>Order Summary</h3>
            <?php foreach ($_SESSION['shopping_basket'] as $flat_id => $item): ?>
                <div class="order-item">
                    <h4>Flat <?php echo htmlspecialchars($item['ref_number']); ?></h4>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($item['location']); ?></p>
                    <p><strong>Period:</strong> <?php echo date('Y-m-d', strtotime($item['start_date'])); ?> to <?php echo date('Y-m-d', strtotime($item['end_date'])); ?></p>
                    <p><strong>Duration:</strong> <?php echo $item['months']; ?> months</p>
                    <p><strong>Monthly Rent:</strong> $<?php echo number_format($item['monthly_rent'], 2); ?></p>
                    <p><strong>Security Deposit:</strong> $<?php echo number_format($item['security_deposit'], 2); ?></p>
                    <p><strong>Total:</strong> $<?php echo number_format($item['total_amount'], 2); ?></p>
                </div>
            <?php endforeach; ?>

            <div class="order-total">
                <h4>Total Amount: $<?php echo number_format($total, 2); ?></h4>
            </div>
        </div>

        <form method="POST" class="payment-form">
            <h3>Payment Details</h3>
            <div class="form-group">
                <label for="payment_card">Credit Card Number (9 digits)</label>
                <input type="text" id="payment_card" name="payment_card" required pattern="[0-9]{9}" maxlength="9">
            </div>
            <div class="form-group">
                <label for="payment_name">Name on Card</label>
                <input type="text" id="payment_name" name="payment_name" required>
            </div>
            <div class="form-group">
                <label for="payment_expiry">Card Expiry Date</label>
                <input type="date" id="payment_expiry" name="payment_expiry" required 
                       min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-note">
                <p>* Your card details are encrypted and securely stored</p>
            </div>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to proceed with the rental?')">Confirm Payment</button>
        </form>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?> 