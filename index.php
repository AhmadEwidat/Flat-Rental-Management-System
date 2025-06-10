<?php
require_once 'includes/dbconfig.inc.php';
require_once 'includes/header.php';
require_once 'includes/nav.php';

$stmt = $pdo->prepare("SELECT f.flat_id, f.ref_number, f.monthly_rent, f.location, fp.photo_url 
                       FROM flats f 
                       LEFT JOIN flat_photos fp ON f.flat_id = fp.flat_id 
                       WHERE f.status = 'approved' 
                       ORDER BY f.created_at DESC LIMIT 3");
$stmt->execute();
$new_flats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main>
    <h2>Welcome to Birzeit Homes</h2>
    <h3>Newly Added Flats</h3>
    <div class="promotions">
        <?php foreach ($new_flats as $flat): ?>
            <div>
                <a href="pages/flat_detail.php?id=<?php echo $flat['flat_id']; ?>" target="_blank">
                    <img src="<?php echo htmlspecialchars($flat['photo_url'] ?? 'assets/images/placeholder.jpg'); ?>" alt="Flat Photo" width="200">
                </a>
                <p>Ref: <?php echo htmlspecialchars($flat['ref_number']); ?></p>
                <p>Location: <?php echo htmlspecialchars($flat['location']); ?></p>
                <p>Price: $<?php echo htmlspecialchars($flat['monthly_rent']); ?>/month</p>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>