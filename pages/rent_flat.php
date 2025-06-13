<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['redirect_url'] = 'rent_flat.php?id=' . ($_GET['id'] ?? '');
    header('Location: login.php');
    exit;
}

$flat_id = $_GET['id'] ?? 0;
$step = $_GET['step'] ?? 1;

// Get customer_id
$stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = :user_id");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    die("Customer not found.");
}
$customer_id = $customer['customer_id'];

// Get flat and owner details with availability check
$current_date = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT f.*, o.name AS owner_name, o.owner_id, o.mobile 
    FROM flats f 
    JOIN owners o ON f.owner_id = o.owner_id 
    WHERE f.flat_id = :flat_id 
    AND f.status = 'approved'
    AND f.available_to >= :current_date
");
$stmt->execute([
    'flat_id' => $flat_id,
    'current_date' => $current_date
]);
$flat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$flat) {
    die("Flat not found, not approved, or no longer available for rent.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $months = $start->diff($end)->m + ($start->diff($end)->y * 12);

        // Validate dates
        if (strtotime($end_date) <= strtotime($start_date)) {
            $error = "End date must be after start date.";
        } elseif (strtotime($start_date) < strtotime($flat['available_from'])) {
            $error = "Start date cannot be before flat availability date.";
        } elseif (strtotime($end_date) > strtotime($flat['available_to'])) {
            $error = "End date cannot be after flat availability end date.";
        } else {
            // Check if flat is available for the selected period
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as overlapping_rents 
                FROM rents r 
                WHERE r.flat_id = :flat_id 
                AND (
                    (r.approval_status = 'approved' AND r.status = 'current')
                    OR (r.approval_status = 'pending')
                )
                AND (
                    (r.start_date <= :end_date AND r.end_date >= :start_date)
                    OR (r.start_date BETWEEN :start_date AND :end_date)
                    OR (r.end_date BETWEEN :start_date AND :end_date)
                )
            ");
            $stmt->execute([
                'flat_id' => $flat_id,
                'start_date' => $start_date,
                'end_date' => $end_date
            ]);
            $overlapping_rents = $stmt->fetch(PDO::FETCH_ASSOC)['overlapping_rents'];

            if ($overlapping_rents > 0) {
                $error = "Flat is not available for the selected period.";
            } else {
                // Check for overlapping preview requests
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as overlapping_previews
                    FROM preview_requests pr
                    WHERE pr.flat_id = :flat_id
                    AND pr.status = 'pending'
                    AND pr.requested_date BETWEEN :start_date AND :end_date
                ");
                $stmt->execute([
                    'flat_id' => $flat_id,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]);
                $overlapping_previews = $stmt->fetch(PDO::FETCH_ASSOC)['overlapping_previews'];

                if ($overlapping_previews > 0) {
                    $error = "There are pending preview requests during this period.";
                } else {
                    // Calculate total amount with security deposit
                    $security_deposit = $flat['monthly_rent'];
                    $total_amount = ($flat['monthly_rent'] * $months) + $security_deposit;

                    // Store in session for shopping basket
                    $_SESSION['rent_data'] = [
                        'flat_id' => $flat_id,
                        'ref_number' => $flat['ref_number'],
                        'location' => $flat['location'],
                        'address' => $flat['flat_number'] . ', ' . $flat['street_name'] . ', ' . $flat['city'] . ', ' . $flat['postal_code'],
                        'owner_name' => $flat['owner_name'],
                        'owner_id' => $flat['owner_id'],
                        'monthly_rent' => $flat['monthly_rent'],
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                        'months' => $months,
                        'security_deposit' => $security_deposit,
                        'total_amount' => $total_amount
                    ];
                    // Add to shopping basket
                    if (!isset($_SESSION['shopping_basket'])) {
                        $_SESSION['shopping_basket'] = [];
                    }
                    $_SESSION['shopping_basket'][$flat_id] = $_SESSION['rent_data'];
                    header('Location: rent_flat.php?id=' . $flat_id . '&step=2');
                    exit;
                }
            }
        }
    } else if ($step == 2) {
        $data = $_SESSION['rent_data'];
        
        // Validate payment details
        if (!preg_match('/^[0-9]{9}$/', $_POST['payment_card'])) {
            $error = "Invalid credit card number. Must be 9 digits.";
        } elseif (strtotime($_POST['payment_expiry']) < strtotime($data['start_date'])) {
            $error = "Card expiry date must be after rental start date.";
        } else {
            // Start transaction
            $pdo->beginTransaction();
            try {
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
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'total_amount' => $data['total_amount'],
                    'payment_card' => password_hash($_POST['payment_card'], PASSWORD_DEFAULT),
                    'payment_name' => $_POST['payment_name'],
                    'payment_expiry' => $_POST['payment_expiry']
                ]);

                // Get the rent ID once
                $rent_id = $pdo->lastInsertId();

                // Notify customer
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
'body' => "Your rental request for flat {$flat['ref_number']} has been submitted for the period {$data['start_date']} to {$data['end_date']}. The owner will review your request and contact you soon.",
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
'owner_id' => $flat['owner_id'],
'title' => "New Rental Request",
'body' => "You have a new rental request for flat {$flat['ref_number']} from {$_SESSION['user_name']} for the period {$data['start_date']} to {$data['end_date']}. Please review and respond to this request.",
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
'body' => "A new rental request has been submitted for flat {$flat['ref_number']}. " .
          "Customer: {$_SESSION['user_name']} ({$_SESSION['user_email']}). " .
          "Period: {$data['start_date']} to {$data['end_date']}. " .
          "Owner: {$flat['owner_name']} ({$flat['mobile']}).",
'flat_id' => $flat_id,
'rent_id' => $rent_id
]);
                // Commit transaction
                $pdo->commit();

                // Remove from shopping basket
                unset($_SESSION['shopping_basket'][$flat_id]);
                unset($_SESSION['rent_data']);

                header("Location: flat_detail.php?id=$flat_id&success=rent_requested");
                exit;
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $error = "An error occurred while processing your request: " . $e->getMessage();
                error_log("Rent Error: " . $e->getMessage());
            }
        }
    }
}
?>

<main>
    <div class="rent-container">
        <?php if (isset($error)): ?>
            <div class="error-message">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <div class="rent-form">
                <h2>Select Rental Period</h2>
                <div class="flat-summary">
                    <h3>Flat Details</h3>
                    <p><strong>Reference:</strong> <?php echo htmlspecialchars($flat['ref_number']); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($flat['location']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($flat['flat_number'] . ', ' . $flat['street_name'] . ', ' . $flat['city'] . ', ' . $flat['postal_code']); ?></p>
                    <p><strong>Owner:</strong> <?php echo htmlspecialchars($flat['owner_name']); ?></p>
                    <p><strong>Monthly Rent:</strong> $<?php echo number_format($flat['monthly_rent'], 2); ?></p>
                    <p><strong>Available From:</strong> <?php echo date('F j, Y', strtotime($flat['available_from'])); ?></p>
                    <p><strong>Available To:</strong> <?php echo date('F j, Y', strtotime($flat['available_to'])); ?></p>
                </div>

                <form method="POST" action="rent_flat.php?id=<?php echo $flat_id; ?>&step=1" class="rental-period-form">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" required 
                               min="<?php echo $flat['available_from']; ?>" 
                               max="<?php echo $flat['available_to']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" required 
                               min="<?php echo $flat['available_from']; ?>" 
                               max="<?php echo $flat['available_to']; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Calculate Total</button>
                </form>
            </div>
        <?php elseif ($step == 2): ?>
            <div class="rent-form">
                <h2>Payment Details</h2>
                <div class="rental-summary">
                    <h3>Rental Summary</h3>
                    <p><strong>Flat:</strong> <?php echo htmlspecialchars($_SESSION['rent_data']['ref_number']); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($_SESSION['rent_data']['location']); ?></p>
                    <p><strong>Period:</strong> <?php echo htmlspecialchars($_SESSION['rent_data']['start_date'] . ' to ' . $_SESSION['rent_data']['end_date']); ?></p>
                    <p><strong>Duration:</strong> <?php echo $_SESSION['rent_data']['months']; ?> months</p>
                    <p><strong>Monthly Rent:</strong> $<?php echo number_format($_SESSION['rent_data']['monthly_rent'], 2); ?></p>
                    <p><strong>Security Deposit:</strong> $<?php echo number_format($_SESSION['rent_data']['security_deposit'], 2); ?></p>
                    <p class="total-amount"><strong>Total Amount:</strong> $<?php echo number_format($_SESSION['rent_data']['total_amount'], 2); ?></p>
                </div>

                <form method="POST" action="rent_flat.php?id=<?php echo $flat_id; ?>&step=2" class="payment-form">
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
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to proceed with the rental?')">Confirm Rental</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>