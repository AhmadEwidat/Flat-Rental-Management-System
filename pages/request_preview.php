<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['redirect_url'] = 'request_preview.php?id=' . ($_GET['id'] ?? '');
    header('Location: login.php');
    exit;
}

$flat_id = $_GET['id'] ?? 0;

// Get customer_id
$stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = :user_id");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    die("Customer not found.");
}
$customer_id = $customer['customer_id'];

$stmt = $pdo->prepare("SELECT * FROM flats WHERE flat_id = :flat_id AND status = 'approved'");
$stmt->execute(['flat_id' => $flat_id]);
$flat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$flat) {
    die("Flat not found or not approved.");
}

// Get available time slots (not booked and not expired)
$stmt = $pdo->prepare("
    SELECT vt.* 
    FROM viewing_times vt 
    LEFT JOIN preview_requests pr 
    ON vt.flat_id = pr.flat_id 
    AND vt.day_of_week = DAYNAME(pr.requested_date) 
    AND vt.time_from = pr.requested_time 
    WHERE vt.flat_id = :flat_id 
    AND pr.id IS NULL 
    AND CONCAT(DATE_FORMAT(NOW(), '%Y-%m-%d'), ' ', vt.time_from) >= NOW()
");
$stmt->execute(['flat_id' => $flat_id]);
$times = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $viewing_id = $_POST['viewing_id'];
    $requested_date = $_POST['requested_date'];
    $requested_time = $_POST['requested_time'];

    // Insert preview request
    $stmt = $pdo->prepare("INSERT INTO preview_requests (flat_id, customer_id, requested_date, requested_time, status) VALUES (:flat_id, :customer_id, :requested_date, :requested_time, 'pending')");
    $stmt->execute([
        'flat_id' => $flat_id,
        'customer_id' => $customer_id,
        'requested_date' => $requested_date,
        'requested_time' => $requested_time
    ]);

    // Notify owner
    $stmt = $pdo->prepare("INSERT INTO messages (receiver_user_id, sender_user_id, title, body, is_read) SELECT u.user_id, :sender_user_id, :title, :body, 0 FROM users u JOIN owners o ON u.user_id = o.user_id WHERE o.owner_id = :owner_id");
    $stmt->execute([
        'sender_user_id' => $_SESSION['user_id'],
        'owner_id' => $flat['owner_id'],
        'title' => "Preview Request for Flat {$flat['ref_number']}",
        'body' => "Customer {$_SESSION['user_name']} has requested a preview on $requested_date at $requested_time."
    ]);

    echo "<p>Preview request sent successfully. Awaiting owner approval.</p>";
    exit;
}
?>

<main>
    <h2>Request Preview for Flat <?php echo htmlspecialchars($flat['ref_number']); ?></h2>
    <table>
        <thead>
            <tr>
                <th>Day</th>
                <th>Time</th>
                <th>Contact Number</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($times as $time): ?>
                <tr>
                    <td><?php echo htmlspecialchars($time['day_of_week']); ?></td>
                    <td><?php echo htmlspecialchars($time['time_from'] . ' - ' . $time['time_to']); ?></td>
                    <td><?php echo htmlspecialchars($time['phone_number']); ?></td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="viewing_id" value="<?php echo $time['id']; ?>">
                            <input type="hidden" name="requested_date" value="<?php echo date('Y-m-d', strtotime("next {$time['day_of_week']}")); ?>">
                            <input type="hidden" name="requested_time" value="<?php echo $time['time_from']; ?>">
                            <button type="submit">Book</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>

<?php require_once '../includes/footer.php'; ?>