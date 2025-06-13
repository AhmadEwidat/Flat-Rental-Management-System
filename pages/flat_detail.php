<?php

require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

// Check if flat ID is provided
if (!isset($_GET['id'])) {
    header("Location: view_flats.php");
    exit;
}

$flat_id = $_GET['id'];

// Get flat details with owner information
$stmt = $pdo->prepare("
    SELECT f.*, o.name AS owner_name, o.owner_id, o.mobile, o.email,
           (SELECT COUNT(*) FROM preview_requests WHERE flat_id = f.flat_id AND status = 'pending') as pending_requests
    FROM flats f 
    JOIN owners o ON f.owner_id = o.owner_id 
    WHERE f.flat_id = :flat_id 
    AND f.status = 'approved'
");
$stmt->execute(['flat_id' => $flat_id]);
$flat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$flat) {
    die("Flat not found or not approved.");
}

// Get flat photos
$stmt = $pdo->prepare("SELECT * FROM flat_photos WHERE flat_id = :flat_id ORDER BY photo_id DESC");
$stmt->execute(['flat_id' => $flat_id]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get marketing info
$stmt = $pdo->prepare("SELECT * FROM marketing_info WHERE flat_id = :flat_id");
$stmt->execute(['flat_id' => $flat_id]);
$marketing_info = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the title for marketing info
$stmt = $pdo->prepare("SELECT title FROM marketing_info WHERE flat_id = :flat_id LIMIT 1");
$stmt->execute(['flat_id' => $flat_id]);
$marketing_title = $stmt->fetch(PDO::FETCH_ASSOC)['title'] ?? 'Nearby Places';

// Get viewing times
$stmt = $pdo->prepare("SELECT * FROM viewing_times WHERE flat_id = :flat_id ORDER BY day_of_week, time_from");
$stmt->execute(['flat_id' => $flat_id]);
$viewing_times = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if user is logged in and is a customer
$is_customer = false;
$customer_id = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT c.customer_id 
        FROM customers c 
        JOIN users u ON c.user_id = u.user_id 
        WHERE u.user_id = :user_id
    ");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($customer) {
        $is_customer = true;
        $customer_id = $customer['customer_id'];
    }
}

// Get current and future rents for this flat
$stmt = $pdo->prepare("
    SELECT r.start_date, r.end_date, r.status, r.approval_status,
           c.name as customer_name, c.mobile as customer_mobile
    FROM rents r
    JOIN customers c ON r.customer_id = c.customer_id
    WHERE r.flat_id = :flat_id 
    AND r.approval_status = 'approved'
    AND r.end_date >= CURDATE()
    ORDER BY r.start_date ASC
");
$stmt->execute(['flat_id' => $flat_id]);
$current_rents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate available periods
$available_periods = [];
$current_date = new DateTime($flat['available_from']);
$end_date = new DateTime($flat['available_to']);

if (empty($current_rents)) {
    $available_periods[] = [
        'start' => $flat['available_from'],
        'end' => $flat['available_to']
    ];
} else {
    foreach ($current_rents as $rent) {
        $rent_start = new DateTime($rent['start_date']);
        $rent_end = new DateTime($rent['end_date']);
        
        if ($current_date < $rent_start) {
            $available_periods[] = [
                'start' => $current_date->format('Y-m-d'),
                'end' => $rent_start->modify('-1 day')->format('Y-m-d')
            ];
        }
        $current_date = $rent_end->modify('+1 day');
    }
    
    if ($current_date <= $end_date) {
        $available_periods[] = [
            'start' => $current_date->format('Y-m-d'),
            'end' => $end_date->format('Y-m-d')
        ];
    }
}
?>

<main>
    <div class="flatcard">
        <!-- Flat Photos -->
        <div class="flat-photos">
            <?php if (empty($photos)): ?>
                <div class="no-photos">
                    <p>No photos available for this flat.</p>
                </div>
            <?php else: ?>
                <div class="photo-grid">
                    <?php foreach ($photos as $photo): ?>
                        <div class="photo-item">
                            <img src="<?php echo htmlspecialchars($photo['photo_url']); ?>" 
                                 alt="Flat Photo <?php echo htmlspecialchars($photo['photo_id']); ?>"
                                 loading="lazy">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Flat Description -->
        <div class="flat-description">
            <h2><?php echo htmlspecialchars($flat['ref_number']); ?> - <?php echo htmlspecialchars($flat['location']); ?></h2>
            
            <div class="flat-status">
                <?php if (!empty($available_periods)): ?>
                    <span class="status available">Available</span>
                <?php else: ?>
                    <span class="status unavailable">Not Available</span>
                <?php endif; ?>
            </div>

            <!-- Address -->
            <div class="address-info">
                <h3>Address</h3>
                <p><?php echo htmlspecialchars($flat['flat_number'] . ', ' . $flat['street_name'] . ', ' . $flat['city'] . ', ' . $flat['postal_code']); ?></p>
            </div>

            <!-- Price and Basic Info -->
            <div class="flat-info-grid">
                <div class="info-item">
                    <strong>Price:</strong>
                    <span>$<?php echo number_format($flat['monthly_rent'], 2); ?> per month</span>
                </div>
                <div class="info-item">
                    <strong>Bedrooms:</strong>
                    <span><?php echo htmlspecialchars($flat['bedrooms']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Bathrooms:</strong>
                    <span><?php echo htmlspecialchars($flat['bathrooms']); ?></span>
                </div>
                <div class="info-item">
                    <strong>Size:</strong>
                    <span><?php echo htmlspecialchars($flat['size_sqm']); ?> m²</span>
                </div>
            </div>

            <!-- Features -->
            <div class="features">
                <h3>Features</h3>
                <ul>
                    <?php if ($flat['heating']): ?><li>Heating</li><?php endif; ?>
                    <?php if ($flat['air_conditioning']): ?><li>Air Conditioning</li><?php endif; ?>
                    <?php if ($flat['access_control']): ?><li>Access Control</li><?php endif; ?>
                    <?php if ($flat['parking']): ?><li>Parking: <?php echo htmlspecialchars($flat['parking']); ?></li><?php endif; ?>
                    <?php if ($flat['backyard']): ?><li>Backyard: <?php echo htmlspecialchars($flat['backyard']); ?></li><?php endif; ?>
                    <?php if ($flat['playground']): ?><li>Playground</li><?php endif; ?>
                    <?php if ($flat['storage']): ?><li>Storage</li><?php endif; ?>
                    <?php if ($flat['is_furnished']): ?><li>Furnished</li><?php endif; ?>
                </ul>
            </div>

            <!-- Availability -->
            <div class="availability-info">
                <h3>Availability</h3>
                <?php if (empty($available_periods)): ?>
                    <p class="not-available">This flat is currently not available for new bookings.</p>
                <?php else: ?>
                    <div class="available-periods">
                        <h4>Available Periods:</h4>
                        <ul>
                            <?php foreach ($available_periods as $period): ?>
                                <li>
                                    <?php 
                                    echo date('F j, Y', strtotime($period['start'])) . ' to ' . 
                                         date('F j, Y', strtotime($period['end']));
                                    ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($current_rents)): ?>
                    <div class="current-rents">
                        <h4>Current Bookings:</h4>
                        <ul>
                            <?php foreach ($current_rents as $rent): ?>
                                <li>
                                    <strong>Period:</strong> 
                                    <?php 
                                    echo date('F j, Y', strtotime($rent['start_date'])) . ' to ' . 
                                         date('F j, Y', strtotime($rent['end_date']));
                                    ?>
                                    <br>
                                    <strong>Status:</strong> <?php echo ucfirst($rent['status']); ?>
                                    <?php if ($_SESSION['role'] === 'owner' || $_SESSION['role'] === 'manager'): ?>
                                        <br>
                                        <strong>Rented by:</strong> <?php echo htmlspecialchars($rent['customer_name']); ?>
                                        <br>
                                        <strong>Contact:</strong> <?php echo htmlspecialchars($rent['customer_mobile']); ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Side Navigation -->
            <div class="side-nav">
                <ul>
                    <li>
                        <a href="request_preview.php?id=<?php echo $flat_id; ?>">Request Flat Viewing Appointment</a>
                    </li>
                    <?php if (!empty($available_periods)): ?>
                        <li>
                            <a href="rent_flat.php?id=<?php echo $flat_id; ?>">Rent the Flat</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Marketing Information -->
        <?php if (!empty($marketing_info)): ?>
            <div class="marketing-info">
                <h3><?php echo htmlspecialchars($marketing_title); ?></h3>
                <ul>
                    <?php foreach ($marketing_info as $info): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($info['name'] ?? ''); ?></strong>
                            <p><?php echo htmlspecialchars($info['description'] ?? ''); ?></p>
                            <?php if (!empty($info['url'])): ?>
                                <a href="<?php echo htmlspecialchars($info['url']); ?>" target="_blank" rel="noopener noreferrer">More Info</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>