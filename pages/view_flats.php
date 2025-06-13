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

// Get flats with their first image and available dates
$query = "
    SELECT f.flat_id, f.location, f.flat_number, f.street_name, f.city, 
           f.monthly_rent, f.bedrooms, f.bathrooms, f.size_sqm,
           f.available_from, f.available_to,
           o.name as owner_name,
           (SELECT photo_url FROM flat_photos WHERE flat_id = f.flat_id LIMIT 1) as photo_url,
           GROUP_CONCAT(
               CONCAT(r.start_date, ' to ', r.end_date)
               ORDER BY r.start_date
               SEPARATOR '|'
           ) as booked_periods
    FROM flats f
    JOIN owners o ON f.owner_id = o.owner_id
    LEFT JOIN rents r ON f.flat_id = r.flat_id 
        AND r.approval_status = 'approved' 
        AND r.status = 'current'
    WHERE f.status = 'approved'
    GROUP BY f.flat_id
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

// Function to get available periods
function getAvailablePeriods($available_from, $available_to, $booked_periods) {
    $available_periods = [];
    $current_date = new DateTime($available_from);
    $end_date = new DateTime($available_to);
    
    if (empty($booked_periods)) {
        return [[
            'start' => $available_from,
            'end' => $available_to
        ]];
    }
    
    $booked_ranges = [];
    foreach (explode('|', $booked_periods) as $period) {
        list($start, $end) = explode(' to ', $period);
        $booked_ranges[] = [
            'start' => new DateTime($start),
            'end' => new DateTime($end)
        ];
    }
    
    // Sort booked ranges by start date
    usort($booked_ranges, function($a, $b) {
        return $a['start'] <=> $b['start'];
    });
    
    // Find available periods
    foreach ($booked_ranges as $booked) {
        if ($current_date < $booked['start']) {
            $available_periods[] = [
                'start' => $current_date->format('Y-m-d'),
                'end' => $booked['start']->modify('-1 day')->format('Y-m-d')
            ];
        }
        $current_date = $booked['end']->modify('+1 day');
    }
    
    // Add final period if there's time left
    if ($current_date <= $end_date) {
        $available_periods[] = [
            'start' => $current_date->format('Y-m-d'),
            'end' => $end_date->format('Y-m-d')
        ];
    }
    
    return $available_periods;
}
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
                        
                        <div class="availability">
                            <h4>Available Periods:</h4>
                            <?php 
                            $available_periods = getAvailablePeriods(
                                $flat['available_from'], 
                                $flat['available_to'], 
                                $flat['booked_periods']
                            );
                            if (empty($available_periods)): 
                            ?>
                                <p class="not-available">Currently not available for new bookings</p>
                            <?php else: ?>
                                <ul class="available-periods">
                                    <?php foreach ($available_periods as $period): ?>
                                        <li>
                                            <?php 
                                            echo date('M d, Y', strtotime($period['start'])) . ' to ' . 
                                                 date('M d, Y', strtotime($period['end']));
                                            ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        
                        <a href="flat_detail.php?id=<?php echo $flat['flat_id']; ?>" class="view-button">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?> 