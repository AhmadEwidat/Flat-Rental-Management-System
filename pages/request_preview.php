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
    SELECT vt.*, 
           DATE_ADD(CURDATE(), INTERVAL (DAYOFWEEK(vt.day_of_week) - DAYOFWEEK(CURDATE()) + 7) % 7 DAY) as next_date
    FROM viewing_times vt 
    WHERE vt.flat_id = :flat_id 
    AND (
        (vt.day_of_week = DAYNAME(NOW()) AND vt.time_from > TIME(NOW()))
        OR vt.day_of_week != DAYNAME(NOW())
    )
    ORDER BY next_date, vt.time_from
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

    $viewing_time_id = $_POST['viewing_time_id'];
    $preview_date = $_POST['preview_date'];
    
    // Validate viewing time
    $valid_time = false;
    $selected_time = null;
    foreach ($viewing_times as $time) {
        if ($time['id'] == $viewing_time_id) {
            $valid_time = true;
            $selected_time = $time;
            break;
        }
    }
    
    if (!$valid_time) {
        $error = "Invalid viewing time selected.";
    } else {
        // Check if customer already has a pending request for this flat
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as pending_requests 
            FROM preview_requests 
            WHERE flat_id = :flat_id 
            AND customer_id = :customer_id 
            AND status = 'pending'
        ");
        $stmt->execute([
            'flat_id' => $flat_id,
            'customer_id' => $customer_id
        ]);
        $pending_requests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_requests'];
        
        if ($pending_requests > 0) {
            $error = "You already have a pending preview request for this flat.";
        } else {
            // Check if the time slot is already booked
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as booked_slots
                FROM preview_requests
                WHERE flat_id = :flat_id
                AND requested_date = :requested_date
                AND requested_time = :requested_time
                AND status != 'rejected'
            ");
            $stmt->execute([
                'flat_id' => $flat_id,
                'requested_date' => $preview_date,
                'requested_time' => $selected_time['time_from']
            ]);
            $booked_slots = $stmt->fetch(PDO::FETCH_ASSOC)['booked_slots'];
            
            if ($booked_slots > 0) {
                $error = "This time slot is already booked. Please select another time.";
            } else {
                // Insert preview request
                $stmt = $pdo->prepare("
                    INSERT INTO preview_requests (
                        flat_id, 
                        customer_id, 
                        requested_date,
                        requested_time,
                        status
                    ) VALUES (
                        :flat_id, 
                        :customer_id, 
                        :requested_date,
                        :requested_time,
                        'pending'
                    )
                ");
                $stmt->execute([
                    'flat_id' => $flat_id,
                    'customer_id' => $customer_id,
                    'requested_date' => $preview_date,
                    'requested_time' => $selected_time['time_from']
                ]);
                
                // Notify customer
                $stmt = $pdo->prepare("
                    INSERT INTO messages (
                        receiver_user_id, 
                        sender_user_id, 
                        title, 
                        body, 
                        is_read
                    ) VALUES (
                        :receiver_user_id, 
                        NULL, 
                        :title, 
                        :body, 
                        0
                    )
                ");
                $stmt->execute([
                    'receiver_user_id' => $_SESSION['user_id'],
                    'title' => "Preview Request Submitted",
                    'body' => "Your preview request for flat {$flat['ref_number']} has been submitted for {$preview_date} at {$selected_time['time_from']}. The owner will contact you to confirm the appointment."
                ]);
                
                // Notify owner
                $stmt = $pdo->prepare("
                    INSERT INTO messages (
                        receiver_user_id, 
                        sender_user_id, 
                        title, 
                        body, 
                        is_read
                    ) SELECT 
                        u.user_id, 
                        NULL, 
                        :title, 
                        :body, 
                        0 
                    FROM users u 
                    JOIN owners o ON u.user_id = o.user_id 
                    WHERE o.owner_id = :owner_id
                ");
                $stmt->execute([
                    'owner_id' => $flat['owner_id'],
                    'title' => "New Preview Request",
                    'body' => "You have a new preview request for flat {$flat['ref_number']} from {$_SESSION['user_name']} for {$preview_date} at {$selected_time['time_from']}."
                ]);
                
                header("Location: flat_detail.php?id=$flat_id&success=preview_requested");
                exit;
            }
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
            foreach ($viewing_times as $time): 
                if ($current_date !== $time['next_date']):
                    if ($current_date !== null) echo '</div>'; // Close previous day's div
                    $current_date = $time['next_date'];
            ?>
                <div class="day-slot">
                    <h4><?php echo date('l, F j, Y', strtotime($time['next_date'])); ?></h4>
                    <div class="time-slots">
            <?php endif; ?>
                        <div class="time-slot">
                            <form method="POST" onsubmit="return confirm('Are you sure you want to book this viewing time?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="viewing_time_id" value="<?php echo $time['id']; ?>">
                                <input type="hidden" name="preview_date" value="<?php echo $time['next_date']; ?>">
                                <div class="time-info">
                                    <span class="time"><?php echo date('g:i A', strtotime($time['time_from'])); ?> - 
                                    <?php echo date('g:i A', strtotime($time['time_to'])); ?></span>
                                    <span class="contact">Contact: <?php echo htmlspecialchars($time['phone_number']); ?></span>
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