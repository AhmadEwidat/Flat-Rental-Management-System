<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flat_id = $_POST['flat_id'];
    $action = $_POST['action'];
    $status = $action === 'approve' ? 'approved' : 'rejected';
    
    // Update flat status
    $stmt = $pdo->prepare("UPDATE flats SET status = :status WHERE flat_id = :flat_id");
    $stmt->execute(['status' => $status, 'flat_id' => $flat_id]);
    
    // Notify owner
    $stmt = $pdo->prepare("INSERT INTO messages (receiver_user_id, sender_user_id, title, body, is_read) SELECT u.user_id, NULL, :title, :body, 0 FROM users u JOIN owners o ON u.user_id = o.user_id JOIN flats f ON o.owner_id = f.owner_id WHERE f.flat_id = :flat_id");
    $stmt->execute([
        'flat_id' => $flat_id,
        'title' => "Flat Approval Status",
        'body' => "Your flat (Ref: {$flat['ref_number']}) has been $status."
    ]);
    
    echo "<p>Flat $status successfully.</p>";
}

// Fetch pending flats
$stmt = $pdo->prepare("SELECT f.*, o.name AS owner_name FROM flats f JOIN owners o ON f.owner_id = o.owner_id WHERE f.status = 'pending'");
$stmt->execute();
$flats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main>
    <h2>Approve Flats</h2>
    <?php if (empty($flats)): ?>
        <p>No flats pending approval.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Flat Reference</th>
                    <th>Location</th>
                    <th>Owner</th>
                    <th>Monthly Rent</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($flats as $flat): ?>
                    <tr>
                        <td><a href="flat_detail.php?id=<?php echo $flat['flat_id']; ?>" class="flat-ref-button" target="_blank"><?php echo htmlspecialchars($flat['ref_number']); ?></a></td>
                        <td><?php echo htmlspecialchars($flat['location']); ?></td>
                        <td><a href="user_card.php?id=<?php echo $flat['owner_id']; ?>" target="_blank"><?php echo htmlspecialchars($flat['owner_name']); ?></a></td>
                        <td>$<?php echo number_format($flat['monthly_rent'], 2); ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="flat_id" value="<?php echo $flat['flat_id']; ?>">
                                <button type="submit" name="action" value="approve">Approve</button>
                                <button type="submit" name="action" value="reject">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>