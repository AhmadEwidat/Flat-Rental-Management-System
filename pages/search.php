<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

// Handle sorting
$sort_column = $_GET['sort'] ?? (isset($_COOKIE['sort_column']) ? $_COOKIE['sort_column'] : 'monthly_rent');
$sort_order = $_GET['order'] ?? (isset($_COOKIE['sort_order']) ? $_COOKIE['sort_order'] : 'ASC');
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';
$next_order = $sort_order === 'ASC' ? 'DESC' : 'ASC';

// Store sorting preferences in cookies
setcookie('sort_column', $sort_column, time() + (7 * 24 * 60 * 60), '/');
setcookie('sort_order', $sort_order, time() + (7 * 24 * 60 * 60), '/');

$valid_columns = ['ref_number', 'monthly_rent', 'available_from', 'location', 'bedrooms'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'monthly_rent';

$filters = [
    'price_min' => $_GET['price_min'] ?? '',
    'price_max' => $_GET['price_max'] ?? '',
    'location' => $_GET['location'] ?? '',
    'bedrooms' => $_GET['bedrooms'] ?? '',
    'bathrooms' => $_GET['bathrooms'] ?? '',
    'is_furnished' => $_GET['is_furnished'] ?? ''
];

$query = "SELECT f.flat_id, f.ref_number, f.monthly_rent, f.available_from, f.location, f.bedrooms, fp.photo_url 
          FROM flats f 
          LEFT JOIN flat_photos fp ON f.flat_id = fp.flat_id 
          WHERE f.status = 'approved' AND (f.available_to IS NULL OR f.available_to >= CURDATE())";
$params = [];

if ($filters['price_min']) {
    $query .= " AND f.monthly_rent >= ?";
    $params[] = $filters['price_min'];
}
if ($filters['price_max']) {
    $query .= " AND f.monthly_rent <= ?";
    $params[] = $filters['price_max'];
}
if ($filters['location']) {
    $query .= " AND f.location LIKE ?";
    $params[] = "%{$filters['location']}%";
}
if ($filters['bedrooms']) {
    $query .= " AND f.bedrooms = ?";
    $params[] = $filters['bedrooms'];
}
if ($filters['bathrooms']) {
    $query .= " AND f.bathrooms = ?";
    $params[] = $filters['bathrooms'];
}
if ($filters['is_furnished'] !== '') {
    $query .= " AND f.is_furnished = ?";
    $params[] = $filters['is_furnished'];
}

$query .= " ORDER BY f.$sort_column $sort_order";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$flats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main>
    <section class="search-page" style="display: grid; grid-template-rows: auto 1fr;">
        <div class="search-form">
            <form method="GET">
                <input type="number" name="price_min" placeholder="Min Price" value="<?php echo htmlspecialchars($filters['price_min']); ?>">
                <input type="number" name="price_max" placeholder="Max Price" value="<?php echo htmlspecialchars($filters['price_max']); ?>">
                <input type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($filters['location']); ?>">
                <input type="number" name="bedrooms" placeholder="Bedrooms" value="<?php echo htmlspecialchars($filters['bedrooms']); ?>">
                <input type="number" name="bathrooms" placeholder="Bathrooms" value="<?php echo htmlspecialchars($filters['bathrooms']); ?>">
                <select name="is_furnished">
                    <option value="">Furnished?</option>
                    <option value="1" <?php echo $filters['is_furnished'] === '1' ? 'selected' : ''; ?>>Yes</option>
                    <option value="0" <?php echo $filters['is_furnished'] === '0' ? 'selected' : ''; ?>>No</option>
                </select>
                <button type="submit">Search</button>
            </form>
        </div>
        <div class="search-results">
            <table>
                <thead>
                    <tr>
                        <th><a href="?sort=ref_number&order=<?php echo $sort_column === 'ref_number' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'ref_number' ? strtolower($sort_order) : ''; ?>">Flat Reference</a></th>
                        <th><a href="?sort=monthly_rent&order=<?php echo $sort_column === 'monthly_rent' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'monthly_rent' ? strtolower($sort_order) : ''; ?>">Monthly Rent</a></th>
                        <th><a href="?sort=available_from&order=<?php echo $sort_column === 'available_from' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'available_from' ? strtolower($sort_order) : ''; ?>">Available From</a></th>
                        <th><a href="?sort=location&order=<?php echo $sort_column === 'location' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'location' ? strtolower($sort_order) : ''; ?>">Location</a></th>
                        <th><a href="?sort=bedrooms&order=<?php echo $sort_column === 'bedrooms' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'bedrooms' ? strtolower($sort_order) : ''; ?>">Bedrooms</a></th>
                        <th>Photo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($flats as $flat): ?>
                        <tr>
                            <td><a href="flat_detail.php?id=<?php echo $flat['flat_id']; ?>" class="flat-ref-button" target="_blank"><?php echo htmlspecialchars($flat['ref_number']); ?></a></td>
                            <td><?php echo htmlspecialchars($flat['monthly_rent']); ?></td>
                            <td><?php echo htmlspecialchars($flat['available_from']); ?></td>
                            <td><?php echo htmlspecialchars($flat['location']); ?></td>
                            <td><?php echo htmlspecialchars($flat['bedrooms']); ?></td>
                            <td><a href="flat_detail.php?id=<?php echo $flat['flat_id']; ?>" target="_blank"><img src="<?php echo htmlspecialchars($flat['photo_url'] ?? '../assets/images/placeholder.jpg'); ?>" alt="Flat Photo" width="100"></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once '../includes/footer.php'; ?>