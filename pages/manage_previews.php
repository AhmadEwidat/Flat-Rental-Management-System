<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/session.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header('Location: /pages/login.php');
    exit;
}

// Get owner_id
$stmt = $pdo->prepare("SELECT owner_id FROM owners WHERE user_id = :user_id");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$owner = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$owner) {
    die("Owner not found.");
}
$owner_id = $owner['owner_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $preview_id = $_POST['preview_id'];
        $action = $_POST['action'];
        $status = $action === 'accept' ? 'accepted' : 'rejected';
        
        // Update preview request status
        $stmt = $pdo->prepare("
            UPDATE preview_requests 
            SET status = :status 
            WHERE id = :preview_id AND EXISTS (
                SELECT 1 FROM flats f 
                WHERE f.flat_id = preview_requests.flat_id 
                AND f.owner_id = :owner_id
            )
        ");
        $stmt->execute([
            'status' => $status,
            'preview_id' => $preview_id,
            'owner_id' => $owner_id
        ]);

        if ($stmt->rowCount() > 0) {
            // Notify customer
            $stmt = $pdo->prepare("
                INSERT INTO messages (
                    receiver_user_id, 
                    sender_user_id, 
                    title, 
                    body, 
                    is_read
                ) 
                SELECT 
                    c.user_id, 
                    :sender_id, 
                    :title, 
                    :body, 
                    0 
                FROM customers c 
                JOIN preview_requests pr ON c.customer_id = pr.customer_id 
                WHERE pr.id = :preview_id
            ");
            
            $stmt->execute([
                'sender_id' => $_SESSION['user_id'],
                'preview_id' => $preview_id,
                'title' => "Preview Request $status",
                'body' => "Your preview request for flat on {$_POST['requested_date']} at {$_POST['requested_time']} has been $status."
            ]);

            setFlashMessage('success', "Preview request has been $status successfully.");
        } else {
            setFlashMessage('error', "Failed to update preview request.");
        }
    } catch (PDOException $e) {
        setFlashMessage('error', "An error occurred: " . $e->getMessage());
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch preview requests
try {
    $stmt = $pdo->prepare("
        SELECT 
            pr.*,
            f.ref_number,
            f.location,
            f.monthly_rent,
            c.name AS customer_name,
            c.mobile AS customer_mobile,
            c.email AS customer_email
        FROM preview_requests pr 
        JOIN flats f ON pr.flat_id = f.flat_id 
        JOIN customers c ON pr.customer_id = c.customer_id 
        WHERE f.owner_id = :owner_id 
        AND pr.status = 'pending'
        AND pr.requested_date >= CURDATE()
        ORDER BY pr.requested_date ASC, pr.requested_time ASC
    ");
    $stmt->execute(['owner_id' => $owner_id]);
    $previews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setFlashMessage('error', "Error fetching preview requests: " . $e->getMessage());
    $previews = [];
}
?>

<main>
    <h2>Manage Preview Requests</h2>
    
    <?php
    $flash = getFlashMessage();
    if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($previews)): ?>
        <p>No pending preview requests.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Flat Reference</th>
                    <th>Location</th>
                    <th>Monthly Rent</th>
                    <th>Customer</th>
                    <th>Contact Info</th>
                    <th>Date & Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($previews as $preview): ?>
                    <tr>
                        <td>
                            <a href="flat_detail.php?ref=<?php echo htmlspecialchars($preview['ref_number']); ?>" 
                               class="flat-ref-button" 
                               target="_blank">
                                <?php echo htmlspecialchars($preview['ref_number']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($preview['location']); ?></td>
                        <td>$<?php echo number_format($preview['monthly_rent'], 2); ?></td>
                        <td>
                            <a href="user_card.php?email=<?php echo urlencode($preview['customer_email']); ?>" 
                               target="_blank">
                                <?php echo htmlspecialchars($preview['customer_name']); ?>
                            </a>
                        </td>
                        <td>
                            <a href="tel:<?php echo htmlspecialchars($preview['customer_mobile']); ?>">
                                📞 <?php echo htmlspecialchars($preview['customer_mobile']); ?>
                            </a>
                            <br>
                            <a href="mailto:<?php echo htmlspecialchars($preview['customer_email']); ?>">
                                ✉️ <?php echo htmlspecialchars($preview['customer_email']); ?>
                            </a>
                        </td>
                        <td>
                            <?php 
                            $date = new DateTime($preview['requested_date']);
                            $time = new DateTime($preview['requested_time']);
                            echo $date->format('Y-m-d') . '<br>' . $time->format('H:i');
                            ?>
                        </td>
                        <td>
                            <form method="POST" class="action-form">
                                <input type="hidden" name="preview_id" value="<?php echo $preview['id']; ?>">
                                <input type="hidden" name="requested_date" value="<?php echo $preview['requested_date']; ?>">
                                <input type="hidden" name="requested_time" value="<?php echo $preview['requested_time']; ?>">
                                <button type="submit" name="action" value="accept" class="btn-accept">Accept</button>
                                <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>