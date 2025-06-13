<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

// Initialize sorting and cookies
$sort_column = $_GET['sort'] ?? 'monthly_rent';
$sort_order = $_GET['order'] ?? 'ASC';
$valid_columns = ['monthly_rent', 'bedrooms', 'bathrooms', 'size_sqm', 'location'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'monthly_rent';
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

// Save sorting preference in cookie
setcookie('sort_column', $sort_column, time() + (86400 * 30), "/");
setcookie('sort_order', $sort_order, time() + (86400 * 30), "/");

// Build query conditions
$conditions = ['f.status = :status'];
$params = [':status' => 'approved'];

if (!empty($_GET['location'])) {
    $conditions[] = 'f.location LIKE :location';
    $params[':location'] = '%' . $_GET['location'] . '%';
}

if (!empty($_GET['min_rent'])) {
    $conditions[] = 'f.monthly_rent >= :min_rent';
    $params[':min_rent'] = (float)$_GET['min_rent'];
}

if (!empty($_GET['max_rent'])) {
    $conditions[] = 'f.monthly_rent <= :max_rent';
    $params[':max_rent'] = (float)$_GET['max_rent'];
}

if (!empty($_GET['bedrooms'])) {
    $conditions[] = 'f.bedrooms = :bedrooms';
    $params[':bedrooms'] = (int)$_GET['bedrooms'];
}

if (isset($_GET['is_furnished']) && $_GET['is_furnished'] !== '') {
    $conditions[] = 'f.is_furnished = :is_furnished';
    $params[':is_furnished'] = (int)$_GET['is_furnished'];
}

// Build query
$sql = "SELECT f.*, o.name AS owner_name 
        FROM flats f 
        JOIN owners o ON f.owner_id = o.owner_id 
        WHERE " . implode(' AND ', $conditions) . 
        " ORDER BY $sort_column $sort_order";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$flats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="search-page">
    <h2>Search Flats</h2>
    <section class="search-form">
        <form method="GET">
            <label>Location<input type="text" name="location" value="<?php echo htmlspecialchars($_GET['location'] ?? ''); ?>"></label>
            <label>Min Rent<input type="number" name="min_rent" value="<?php echo htmlspecialchars($_GET['min_rent'] ?? ''); ?>" step="100"></label>
            <label>Max Rent<input type="number" name="max_rent" value="<?php echo htmlspecialchars($_GET['max_rent'] ?? ''); ?>" step="100"></label>
            <label>Bedrooms<select name="bedrooms">
                <option value="">Any</option>
                <option value="1" <?php echo ($_GET['bedrooms'] ?? '') == '1' ? 'selected' : ''; ?>>1</option>
                <option value="2" <?php echo ($_GET['bedrooms'] ?? '') == '2' ? 'selected' : ''; ?>>2</option>
                <option value="3" <?php echo ($_GET['bedrooms'] ?? '') == '3' ? 'selected' : ''; ?>>3+</option>
            </select></label>
            <label>Furnished<select name="is_furnished">
                <option value="">Any</option>
                <option value="1" <?php echo ($_GET['is_furnished'] ?? '') == '1' ? 'selected' : ''; ?>>Yes</option>
                <option value="0" <?php echo ($_GET['is_furnished'] ?? '') == '0' ? 'selected' : ''; ?>>No</option>
            </select></label>
            <button type="submit">Search</button>
        </form>
    </section>
    <section class="search-results">
        <?php if (empty($flats)): ?>
            <p>No flats found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th class="sortable <?php echo $sort_column === 'ref_number' ? strtolower($sort_order) : ''; ?>">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'ref_number', 'order' => $sort_column === 'ref_number' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                Reference Number
                            </a>
                        </th>
                        <th class="sortable <?php echo $sort_column === 'monthly_rent' ? strtolower($sort_order) : ''; ?>">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'monthly_rent', 'order' => $sort_column === 'monthly_rent' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                Monthly Rent
                            </a>
                        </th>
                        <th class="sortable <?php echo $sort_column === 'available_from' ? strtolower($sort_order) : ''; ?>">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'available_from', 'order' => $sort_column === 'available_from' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                Available From
                            </a>
                        </th>
                        <th class="sortable <?php echo $sort_column === 'location' ? strtolower($sort_order) : ''; ?>">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'location', 'order' => $sort_column === 'location' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                Location
                            </a>
                        </th>
                        <th class="sortable <?php echo $sort_column === 'bedrooms' ? strtolower($sort_order) : ''; ?>">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'bedrooms', 'order' => $sort_column === 'bedrooms' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                Bedrooms
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($flats as $flat): ?>
                        <tr>
                            <td class="text-center">
                                <a href="flat_detail.php?id=<?php echo $flat['flat_id']; ?>" target="_blank">
                                    <img src="<?php echo !empty($flat['photo_url']) ? htmlspecialchars($flat['photo_url']) : '../assets/images/no-image.jpg'; ?>" 
                                         alt="Flat <?php echo htmlspecialchars($flat['ref_number']); ?>" 
                                         class="flat-thumbnail">
                                </a>
                            </td>
                            <td class="text-center">
                                <a href="flat_detail.php?id=<?php echo $flat['flat_id']; ?>" target="_blank" class="flat-ref-button">
                                    <?php echo htmlspecialchars($flat['ref_number']); ?>
                                </a>
                            </td>
                            <td class="text-right">$<?php echo number_format($flat['monthly_rent'], 2); ?></td>
                            <td class="text-center"><?php echo date('Y-m-d', strtotime($flat['available_from'])); ?></td>
                            <td><?php echo htmlspecialchars($flat['location']); ?></td>
                            <td class="text-center"><?php echo $flat['bedrooms']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>

<?php require_once '../includes/footer.php'; ?>