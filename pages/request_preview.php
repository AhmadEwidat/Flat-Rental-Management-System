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

// Get flat and owner details with availability check
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

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
    die("Flat not found, not approved, or no longer available for viewing.");
}

// Get available viewing times for the next 7 days
$stmt = $pdo->prepare("
    SELECT vts.*, vt.phone_number
    FROM viewing_time_slots vts
    JOIN viewing_times vt ON vts.viewing_time_id = vt.id
    WHERE vts.flat_id = :flat_id 
    AND vts.slot_date >= CURDATE()
    AND vts.status = 'available'
    ORDER BY vts.slot_date, vts.slot_time
    LIMIT 7
");
$stmt->execute(['flat_id' => $flat_id]);
$viewing_times = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($viewing_times)) {
    die("No available viewing times for this flat in the next 7 days.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid request.");
    }

    $slot_id = $_POST['slot_id'];
    
    // Validate slot
    $valid_slot = false;
    $selected_slot = null;
    foreach ($viewing_times as $slot) {
        if ($slot['slot_id'] == $slot_id) {
            $valid_slot = true;
            $selected_slot = $slot;
            break;
        }
    }
    
    if (!$valid_slot) {
        $error = "Invalid viewing time selected.";
    } else {
        try {
            $pdo->beginTransaction();

            // Check if customer already has a pending request for this flat
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as pending_requests 
                FROM preview_requests pr
                WHERE pr.flat_id = :flat_id 
                AND pr.customer_id = :customer_id 
                AND pr.status = 'pending'
            ");
            $stmt->execute([
                'flat_id' => $flat_id,
                'customer_id' => $customer_id
            ]);
            $pending_requests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_requests'];
            
            if ($pending_requests > 0) {
                throw new Exception("You already have a pending preview request for this flat.");
            }

            // Insert preview request
            $stmt = $pdo->prepare("
                INSERT INTO preview_requests (
                    flat_id, 
                    customer_id, 
                    requested_date,
                    requested_time,
                    slot_id,
                    status
                ) VALUES (
                    :flat_id, 
                    :customer_id, 
                    :requested_date,
                    :requested_time,
                    :slot_id,
                    'pending'
                )
            ");
            $stmt->execute([
                'flat_id' => $flat_id,
                'customer_id' => $customer_id,
                'requested_date' => $selected_slot['slot_date'],
                'requested_time' => $selected_slot['slot_time'],
                'slot_id' => $selected_slot['slot_id']
            ]);
            
            // Get the preview request ID
            $preview_request_id = $pdo->lastInsertId();

            // Update slot status
            $stmt = $pdo->prepare("
                UPDATE viewing_time_slots 
                SET status = 'booked',
                    preview_request_id = :preview_request_id
                WHERE slot_id = :slot_id
            ");
            $stmt->execute([
                'slot_id' => $selected_slot['slot_id'],
                'preview_request_id' => $preview_request_id
            ]);

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
                    preview_request_id
                ) VALUES (
                    :receiver_user_id, 
                    NULL, 
                    :title, 
                    :body, 
                    0,
                    'preview_request',
                    :flat_id,
                    :preview_request_id
                )
            ");
            $stmt->execute([
                'receiver_user_id' => $_SESSION['user_id'],
                'title' => "Preview Request Submitted",
                'body' => "Your preview request for flat {$flat['ref_number']} has been submitted for {$selected_slot['slot_date']} at {$selected_slot['slot_time']}. The owner will contact you to confirm the appointment.",
                'flat_id' => $flat_id,
                'preview_request_id' => $preview_request_id
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
                    preview_request_id
                ) SELECT 
                    u.user_id, 
                    NULL, 
                    :title, 
                    :body, 
                    0,
                    'preview_request',
                    :flat_id,
                    :preview_request_id
                FROM users u 
                JOIN owners o ON u.user_id = o.user_id 
                WHERE o.owner_id = :owner_id
            ");
            $stmt->execute([
                'owner_id' => $flat['owner_id'],
                'title' => "New Preview Request",
                'body' => "You have a new preview request for flat {$flat['ref_number']} from {$_SESSION['user_name']} for {$selected_slot['slot_date']} at {$selected_slot['slot_time']}. Please review and respond to this request.",
                'flat_id' => $flat_id,
                'preview_request_id' => $preview_request_id
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
                    preview_request_id
                ) SELECT 
                    u.user_id, 
                    NULL, 
                    :title, 
                    :body, 
                    0,
                    'preview_request',
                    :flat_id,
                    :preview_request_id
                FROM users u 
                WHERE u.role = 'manager'
            ");
            $stmt->execute([
                'title' => "New Preview Request",
                'body' => "A new preview request has been submitted for flat {$flat['ref_number']}. " .
                         "Customer: {$_SESSION['user_name']} ({$_SESSION['user_email']}). " .
                         "Requested Date: {$selected_slot['slot_date']} at {$selected_slot['slot_time']}. " .
                         "Owner: {$flat['owner_name']} ({$flat['mobile']}).",
                'flat_id' => $flat_id,
                'preview_request_id' => $preview_request_id
            ]);

            $pdo->commit();
            header("Location: flat_detail.php?id=$flat_id&success=preview_requested");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
            error_log("Preview Request Error: " . $e->getMessage());
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<main>
    <h2>Request Preview for Flat <?php echo htmlspecialchars($flat['ref_number']); ?></h2>
    
    <?php if (isset($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <div class="flat-info">
        <h3>Flat Details</h3>
        <p><strong>Location:</strong> <?php echo htmlspecialchars($flat['location']); ?></p>
        <p><strong>Monthly Rent:</strong> $<?php echo htmlspecialchars($flat['monthly_rent']); ?></p>
        <p><strong>Owner:</strong> <?php echo htmlspecialchars($flat['owner_name']); ?></p>
        <p><strong>Contact:</strong> <?php echo htmlspecialchars($flat['mobile']); ?></p>
    </div>

    <?php if (empty($viewing_times)): ?>
        <p>No available viewing times for this flat in the next 7 days.</p>
    <?php else: ?>
        <h3>Available Viewing Times</h3>
        <div class="viewing-times">
            <?php 
            $current_date = null;
            foreach ($viewing_times as $slot): 
                if ($current_date !== $slot['slot_date']):
                    if ($current_date !== null) echo '</div>'; // Close previous day's div
                    $current_date = $slot['slot_date'];
            ?>
                <div class="day-slot">
                    <h4><?php echo date('l, F j, Y', strtotime($slot['slot_date'])); ?></h4>
                    <div class="time-slots">
            <?php endif; ?>
                        <div class="time-slot">
                            <form method="POST" onsubmit="return confirm('Are you sure you want to book this viewing time?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="slot_id" value="<?php echo $slot['slot_id']; ?>">
                                <div class="time-info">
                                    <span class="time"><?php echo date('g:i A', strtotime($slot['slot_time'])); ?></span>
                                    <span class="contact">Contact: <?php echo htmlspecialchars($slot['phone_number']); ?></span>
                                </div>
                                <button type="submit" class="btn btn-primary">Book This Time</button>
                            </form>
                        </div>
            <?php endforeach; ?>
                    </div>
                </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>