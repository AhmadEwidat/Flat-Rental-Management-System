<?php
session_start();
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

$flat_id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM flats WHERE flat_id = ? AND status = 'approved'");
$stmt->execute([$flat_id]);
$flat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$flat) {
    die("Flat not found or not approved.");
}

$stmt = $pdo->prepare("SELECT * FROM flat_photos WHERE flat_id = ? LIMIT 3");
$stmt->execute([$flat_id]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM marketing_info WHERE flat_id = ?");
$stmt->execute([$flat_id]);
$marketing = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main>
    <div class="flatcard">
        <div class="photos">
            <?php foreach ($photos as $photo): ?>
                <img src="<?php echo htmlspecialchars($photo['photo_url']); ?>" alt="<?php echo htmlspecialchars($photo['caption'] ?? 'Flat Photo'); ?>">
            <?php endforeach; ?>
        </div>
        <div class="description">
            <h2>Flat Details</h2>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($flat['flat_number'] . ', ' . $flat['street_name'] . ', ' . $flat['city'] . ', ' . $flat['postal_code']); ?></p>
            <p><strong>Price:</strong> $<?php echo htmlspecialchars($flat['monthly_rent']); ?>/month</p>
            <p><strong>Rental Conditions:</strong> <?php echo htmlspecialchars($flat['rent_conditions']); ?></p>
            <p><strong>Bedrooms:</strong> <?php echo htmlspecialchars($flat['bedrooms']); ?></p>
            <p><strong>Bathrooms:</strong> <?php echo htmlspecialchars($flat['bathrooms']); ?></p>
            <p><strong>Size:</strong> <?php echo htmlspecialchars($flat['size_sqm']); ?> sqm</p>
            <p><strong>Heating:</strong> <?php echo $flat['heating'] ? 'Yes' : 'No'; ?></p>
            <p><strong>Air Conditioning:</strong> <?php echo $flat['air_conditioning'] ? 'Yes' : 'No'; ?></p>
            <p><strong>Access Control:</strong> <?php echo $flat['access_control'] ? 'Yes' : 'No'; ?></p>
            <p><strong>Parking:</strong> <?php echo $flat['parking'] ? 'Yes' : 'No'; ?></p>
            <p><strong>Backyard:</strong> <?php echo htmlspecialchars($flat['backyard']); ?></p>
            <p><strong>Playground:</strong> <?php echo $flat['playground'] ? 'Yes' : 'No'; ?></p>
            <p><strong>Storage:</strong> <?php echo $flat['storage'] ? 'Yes' : 'No'; ?></p>
        </div>
        <aside>
            <h3>Nearby Landmarks</h3>
            <?php foreach ($marketing as $info): ?>
                <div>
                    <h4><?php echo htmlspecialchars($info['title']); ?></h4>
                    <p><?php echo htmlspecialchars($info['description']); ?></p>
                    <?php if ($info['url']): ?>
                        <a href="<?php echo htmlspecialchars($info['url']); ?>" target="_blank">More Info</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </aside>
    </div>
    <nav class="side-nav">
        <a href="request_preview.php?id=<?php echo $flat['flat_id']; ?>">Request Flat Viewing Appointment</a>
        <a href="rent_flat.php?id=<?php echo $flat['flat_id']; ?>">Rent the Flat</a>
    </nav>
</main>

<?php require_once '../includes/footer.php'; ?>