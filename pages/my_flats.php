<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

// Check if user is logged in and is an owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header('Location: login.php');
    exit;
}

// Get owner_id
$stmt = $pdo->prepare("SELECT owner_id FROM owners WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$owner = $stmt->fetch();

if (!$owner) {
    die("Owner not found.");
}

// Get all flats for this owner
$query = "SELECT f.*, 
          (SELECT photo_url FROM flat_photos WHERE flat_id = f.flat_id LIMIT 1) as first_photo,
          (SELECT COUNT(*) FROM rents r WHERE r.flat_id = f.flat_id AND r.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as rent_requests
          FROM flats f 
          WHERE f.owner_id = :owner_id 
          ORDER BY f.flat_id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute(['owner_id' => $owner['owner_id']]);
$flats = $stmt->fetchAll();
?>

<main>
    <div class="page-header">
        <h1>My Flats</h1>
        <a href="offer_flat.php" class="add-flat-button">Add New Flat</a>
    </div>

    <div class="flats-container">
        <?php if (empty($flats)): ?>
            <p class="no-flats">You haven't added any flats yet.</p>
        <?php else: ?>
            <?php foreach ($flats as $flat): ?>
                <div class="flat-card">
                    <figure>
                        <img src="<?php echo htmlspecialchars($flat['photo_url'] ?? '../assets/images/default_flat.jpg'); ?>" 
                             alt="Flat Image">
                        <div class="status-badge <?php echo $flat['status']; ?>">
                            <?php echo ucfirst($flat['status']); ?>
                        </div>
                    </figure>
                    <div class="flat-info">
                        <h3><?php echo htmlspecialchars($flat['location']); ?></h3>
                        <p class="price">$<?php echo number_format($flat['monthly_rent'], 2); ?> / month</p>
                        <div class="details">
                            <span><?php echo $flat['bedrooms']; ?> beds</span>
                            <span>•</span>
                            <span><?php echo $flat['bathrooms']; ?> baths</span>
                            <span>•</span>
                            <span><?php echo number_format($flat['size_sqm']); ?> sq ft</span>
                        </div>
                        <div class="location">
                            <p><?php echo htmlspecialchars($flat['street_name'] . ', ' . $flat['city']); ?></p>
                        </div>
                        <div class="flat-actions">
                            <a href="flat_detail.php?id=<?php echo $flat['flat_id']; ?>" class="view-button">View Details</a>
                            <?php if ($flat['status'] === 'approved'): ?>
                                <a href="edit_flat.php?id=<?php echo $flat['flat_id']; ?>" class="edit-button">Edit</a>
                            <?php endif; ?>
                            <?php if ($flat['rent_requests'] > 0): ?>
                                <span class="rent-requests"><?php echo $flat['rent_requests']; ?> new rent request(s)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?> 