<?php
require_once 'includes/dbconfig.inc.php';
require_once 'includes/header.php';
require_once 'includes/nav.php';

// Fetch approved flats
$stmt = $pdo->prepare("
    SELECT f.flat_id, f.ref_number, f.location, f.monthly_rent, f.bedrooms, f.bathrooms, 
           f.size_sqm, m.title, p.photo_url
    FROM flats f
    LEFT JOIN marketing_info m ON f.flat_id = m.flat_id
    LEFT JOIN flat_photos p ON f.flat_id = p.flat_id
    WHERE f.status = 'approved'
    GROUP BY f.flat_id
    ORDER BY f.available_from DESC
");
$stmt->execute();
$flats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main>
    <section class="hero">
        <h1>Welcome to Birzeit Homes</h1>
        <p>Find your perfect home in Birzeit</p>
        <div class="hero-buttons">
            <a href="pages/view_flats.php" class="cta-button">Browse Flats</a>
            <div class="registration-buttons">
                <a href="pages/customer_register.php" class="register-button customer">Register as Customer</a>
                <a href="pages/owner_register.php" class="register-button owner">Register as Owner</a>
            </div>
        </div>
    </section>

    <section class="featured-flats">
        <h2>Featured Flats</h2>
        <div class="flats-grid">
            <?php
            // Get featured flats (approved and available)
            $stmt = $pdo->query("
                SELECT f.*, o.name as owner_name 
                FROM flats f 
                JOIN owners o ON f.owner_id = o.owner_id 
                WHERE f.status = 'approved' 
                AND f.available_from <= CURDATE() 
                AND f.available_to >= CURDATE() 
                LIMIT 3
            ");
            
            while ($flat = $stmt->fetch()) {
                echo '<div class="flat-card">';
                echo '<figure>';
                // Get first photo for the flat
                $photoStmt = $pdo->prepare("SELECT photo_url FROM flat_photos WHERE flat_id = ? LIMIT 1");
                $photoStmt->execute([$flat['flat_id']]);
                $photo = $photoStmt->fetch();
                
                if ($photo) {
                    echo '<img src="' . htmlspecialchars($photo['photo_url']) . '" alt="Flat ' . htmlspecialchars($flat['ref_number']) . '">';
                }
                echo '</figure>';
                
                echo '<div class="flat-info">';
                echo '<h3>Flat ' . htmlspecialchars($flat['ref_number']) . '</h3>';
                echo '<p class="price">$' . number_format($flat['monthly_rent'], 2) . ' / month</p>';
                echo '<p class="location">' . htmlspecialchars($flat['location']) . '</p>';
                echo '<p class="details">' . $flat['bedrooms'] . ' beds • ' . $flat['bathrooms'] . ' baths</p>';
                echo '<a href="pages/flat_detail.php?ref=' . htmlspecialchars($flat['ref_number']) . '" class="view-details">View Details</a>';
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>
    </section>

    <section class="about-preview">
        <h2>About Birzeit Homes</h2>
        <p>Your trusted partner in finding the perfect home in Birzeit. We offer a wide range of flats for rent, from cozy studios to spacious family apartments.</p>
        <a href="pages/about_us.php" class="learn-more">Learn More</a>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>