<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

// Get filter parameters
$location = $_GET['location'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$bedrooms = $_GET['bedrooms'] ?? '';
$sort = $_GET['sort'] ?? 'price_asc';

// Get flats with their first image
$query = "
    SELECT f.flat_id, f.location, f.flat_number, f.street_name, f.city, 
           f.monthly_rent, f.bedrooms, f.bathrooms, f.size_sqm,
           o.name as owner_name,
           (SELECT photo_url FROM flat_photos WHERE flat_id = f.flat_id LIMIT 1) as photo_url
    FROM flats f
    JOIN owners o ON f.owner_id = o.owner_id
    WHERE f.status = 'approved'
    ORDER BY f.monthly_rent ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute();
$flats = $stmt->fetchAll();

// Get unique locations for filter
$stmt = $pdo->query("SELECT DISTINCT location FROM flats WHERE status = 'approved' ORDER BY location");
$locations = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique bedroom counts for filter
$stmt = $pdo->query("SELECT DISTINCT bedrooms FROM flats WHERE status = 'approved' ORDER BY bedrooms");
$bedroom_counts = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<main>
    <h1>Available Flats</h1>

    <div class="filters">
        <form method="GET" class="filter-form">
            <div class="form-group">
                <label for="location">Location</label>
                <select name="location" id="location">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>" 
                                <?php echo $location === $loc ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="min_price">Min Price</label>
                <input type="number" name="min_price" id="min_price" 
                       value="<?php echo htmlspecialchars($min_price); ?>" min="0">
            </div>

            <div class="form-group">
                <label for="max_price">Max Price</label>
                <input type="number" name="max_price" id="max_price" 
                       value="<?php echo htmlspecialchars($max_price); ?>" min="0">
            </div>

            <div class="form-group">
                <label for="bedrooms">Bedrooms</label>
                <select name="bedrooms" id="bedrooms">
                    <option value="">Any</option>
                    <?php foreach ($bedroom_counts as $count): ?>
                        <option value="<?php echo $count; ?>" 
                                <?php echo $bedrooms == $count ? 'selected' : ''; ?>>
                            <?php echo $count; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="sort">Sort By</label>
                <select name="sort" id="sort">
                    <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>
                        Price: Low to High
                    </option>
                    <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>
                        Price: High to Low
                    </option>
                    <option value="bedrooms_desc" <?php echo $sort === 'bedrooms_desc' ? 'selected' : ''; ?>>
                        Bedrooms: High to Low
                    </option>
                    <option value="bedrooms_asc" <?php echo $sort === 'bedrooms_asc' ? 'selected' : ''; ?>>
                        Bedrooms: Low to High
                    </option>
                    <option value="area_desc" <?php echo $sort === 'area_desc' ? 'selected' : ''; ?>>
                        Area: High to Low
                    </option>
                    <option value="area_asc" <?php echo $sort === 'area_asc' ? 'selected' : ''; ?>>
                        Area: Low to High
                    </option>
                </select>
            </div>

            <button type="submit" class="filter-button">Apply Filters</button>
        </form>
    </div>

    <div class="flats-container">
        <?php if (empty($flats)): ?>
            <p>No flats found matching your criteria.</p>
        <?php else: ?>
            <?php foreach ($flats as $flat): ?>
                <div class="flat-card">
                    <figure>
                        <img src="<?php echo htmlspecialchars($flat['photo_url'] ?? '../assets/images/default_flat.jpg'); ?>" 
                             alt="Flat Image">
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
                        <a href="flat_detail.php?id=<?php echo $flat['flat_id']; ?>" class="view-button">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?> 