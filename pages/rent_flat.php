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

// Get flat and owner details
$stmt = $pdo->prepare("SELECT f.*, o.name AS owner_name, o.owner_id, o.mobile FROM flats f JOIN owners o ON f.owner_id = o.owner_id WHERE f.flat_id = :flat_id AND f.status = 'approved'");
$stmt->execute(['flat_id' => $flat_id]);
$flat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$flat) {
    die("Flat not found or not approved.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        $_SESSION['rent_data'] = $_POST;
        $start_date = new DateTime($_POST['start_date']);
        $end_date = new DateTime($_POST['end_date']);
        $months = $start_date->diff($end_date)->m + ($start_date->diff($end_date)->y * 12);
        $_SESSION['rent_data']['total_amount'] = $flat['monthly_rent'] * $months;
        header('Location: rent_flat.php?id=' . $flat_id . '&step=2');
        exit;
    } elseif ($step == 2) {
        $data = $_SESSION['rent_data'];
        if (!preg_match('/^[0-9]{9}$/', $_POST['payment_card'])) {
            $error = "Invalid credit card number. Must be 9 digits.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO rents (flat_id, customer_id, start_date, end_date, total_amount, payment_card, payment_name, payment_expiry) VALUES (:flat_id, :customer_id, :start_date, :end_date, :total_amount, :payment_card, :payment_name, :payment_expiry)");
            $stmt->execute([
                'flat_id' => $flat_id,
                'customer_id' => $customer_id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_amount' => $data['total_amount'],
                'payment_card' => $_POST['payment_card'],
                'payment_name' => $_POST['payment_name'],
                'payment_expiry' => $_POST['payment_expiry']
            ]);

            $stmt = $pdo->prepare("UPDATE flats SET status = 'rented' WHERE flat_id = :flat_id");
            $stmt->execute(['flat_id' => $flat_id]);

            // Notify customer
            $stmt = $pdo->prepare("INSERT INTO messages (receiver_user_id, sender_user_id, title, body, is_read) VALUES (:receiver_user_id, NULL, :title, :body, 0)");
            $stmt->execute([
                'receiver_user_id' => $_SESSION['user_id'],
                'title' => "Flat Rental Confirmation",
                'body' => "Your rental for flat {$flat['ref_number']} is confirmed. Contact {$flat['owner_name']} at {$flat['mobile']} to collect the keys."
            ]);

            // Notify owner
            $stmt = $pdo->prepare("INSERT INTO messages (receiver_user_id, sender_user_id, title, body, is_read) SELECT u.user_id, NULL, :title, :body, 0 FROM users u JOIN owners o ON u.user_id = o.user_id WHERE o.owner_id = :owner_id");
            $stmt->execute([
                'owner_id' => $flat['owner_id'],
                'title' => "Flat Rental Confirmation",
                'body' => "Your flat {$flat['ref_number']} has been rented by {$_SESSION['user_name']}."
            ]);

            unset($_SESSION['rent_data']);
            echo "<p>Rental successful! Contact {$flat['owner_name']} at {$flat['mobile']} to collect the keys.</p>";
            exit;
        }
    }
}
?>

<main>
    <?php if (isset($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if ($step == 1): ?>
        <form method="POST" action="rent_flat.php?id=<?php echo $flat_id; ?>&step=1">
            <p><strong>Flat Reference:</strong> <?php echo htmlspecialchars($flat['ref_number']); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($flat['location']); ?></p>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($flat['flat_number'] . ', ' . $flat['street_name'] . ', ' . $flat['city'] . ', ' . $flat['postal_code']); ?></p>
            <p><strong>Owner:</strong> <?php echo htmlspecialchars($flat['owner_name']); ?> (ID: <?php echo htmlspecialchars($flat['owner_id']); ?>)</p>
            <label>Start Date<input type="date" name="start_date" required></label>
            <label>End Date<input type="date" name="end_date" required></label>
            <button type="submit">Next</button>
        </form>
    <?php elseif ($step == 2): ?>
        <form method="POST" action="rent_flat.php?id=<?php echo $flat_id; ?>&step=2">
            <p><strong>Total Amount:</strong> $<?php echo $_SESSION['rent_data']['total_amount']; ?></p>
            <p><strong>Rental Period:</strong> <?php echo htmlspecialchars($_SESSION['rent_data']['start_date'] . ' to ' . $_SESSION['rent_data']['end_date']); ?></p>
            <label>Credit Card Number (9 digits)<input type="text" name="payment_card" required pattern="[0-9]{9}"></label>
            <label>Name on Card<input type="text" name="payment_name" required></label>
            <label>Expiry Date<input type="date" name="payment_expiry" required></label>
            <button type="submit">Confirm Rent</button>
        </form>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>