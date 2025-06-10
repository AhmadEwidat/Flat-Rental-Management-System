<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: login.php');
    exit;
}

$sort_column = $_GET['sort'] ?? (isset($_COOKIE['sort_column']) ? $_COOKIE['sort_column'] : 'start_date');
$sort_order = $_GET['order'] ?? (isset($_COOKIE['sort_order']) ? $_COOKIE['sort_order'] : 'DESC');
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';
$next_order = $sort_order === 'ASC' ? 'DESC' : 'ASC';

setcookie('sort_column', $sort_column, time() + (7 * 24 * 60 * 60), '/');
setcookie('sort_order', $sort_order, time() + (7 * 24 * 60 * 60), '/');

$valid_columns = ['ref_number', 'monthly_rent', 'start_date', 'end_date', 'location', 'owner_name', 'customer_name'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'start_date';

$filters = [
    'available_from' => $_GET['available_from'] ?? '',
    'available_to' => $_GET['available_to'] ?? '',
    'location' => $_GET['location'] ?? '',
    'available_on' => $_GET['available_on'] ?? '',
    'owner_id' => $_GET['owner_id'] ?? '',
    'customer_id' => $_GET['customer_id'] ?? ''
];

$query = "SELECT r.*, f.ref_number, f.monthly_rent, f.location, o.name AS owner_name, o.owner_id, c.name AS customer_name, c.customer_id 
          FROM rents r 
          JOIN flats f ON r.flat_id = f.flat_id 
          JOIN owners o ON f.owner_id = o.owner_id 
          JOIN customers c ON r.customer_id = c.customer_id 
          WHERE 1=1";
$params = [];

if ($filters['available_from']) {
    $query .= " AND f.available_from >= :available_from";
    $params['available_from'] = $filters['available_from'];
}
if ($filters['available_to']) {
    $query .= " AND (f.available_to <= :available_to OR f.available_to IS NULL)";
    $params['available_to'] = $filters['available_to'];
}
if ($filters['location']) {
    $query .= " AND f.location LIKE :location";
    $params['location'] = "%{$filters['location']}%";
}
if ($filters['available_on']) {
    $query .= " AND f.available_from <= :available_on AND (f.available_to >= :available_on OR f.available_to IS NULL)";
    $params['available_on'] = $filters['available_on'];
}
if ($filters['owner_id']) {
    $query .= " AND f.owner_id = :owner_id";
    $params['owner_id'] = $filters['owner_id'];
}
if ($filters['customer_id']) {
    $query .= " AND r.customer_id = :customer_id";
    $params['customer_id'] = $filters['customer_id'];
}

$query .= " ORDER BY r.$sort_column $sort_order";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main>
    <h2>Inquire Flats</h2>
    <form method="GET">
        <label>Available From<input type="date" name="available_from" value="<?php echo htmlspecialchars($filters['available_from']); ?>"></label>
        <label>Available To<input type="date" name="available_to" value="<?php echo htmlspecialchars($filters['available_to']); ?>"></label>
        <label>Location<input type="text" name="location" value="<?php echo htmlspecialchars($filters['location']); ?>"></label>
        <label>Available On<input type="date" name="available_on" value="<?php echo htmlspecialchars($filters['available_on']); ?>"></label>
        <label>Owner ID<input type="text" name="owner_id" value="<?php echo htmlspecialchars($filters['owner_id']); ?>"></label>
        <label>Customer ID<input type="text" name="customer_id" value="<?php echo htmlspecialchars($filters['customer_id']); ?>"></label>
        <button type="submit">Search</button>
    </form>
    <table>
        <thead>
            <tr>
                <th><a href="?sort=ref_number&order=<?php echo $sort_column === 'ref_number' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'ref_number' ? strtolower($sort_order) : ''; ?>">Flat Reference</a></th>
                <th><a href="?sort=monthly_rent&order=<?php echo $sort_column === 'monthly_rent' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'monthly_rent' ? strtolower($sort_order) : ''; ?>">Monthly Rent</a></th>
                <th><a href="?sort=start_date&order=<?php echo $sort_column === 'start_date' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'start_date' ? strtolower($sort_order) : ''; ?>">Start Date</a></th>
                <th><a href="?sort=end_date&order=<?php echo $sort_column === 'end_date' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'end_date' ? strtolower($sort_order) : ''; ?>">End Date</a></th>
                <th><a href="?sort=location&order=<?php echo $sort_column === 'location' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'location' ? strtolower($sort_order) : ''; ?>">Location</a></th>
                <th><a href="?sort=owner_name&order=<?php echo $sort_column === 'owner_name' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'owner_name' ? strtolower($sort_order) : ''; ?>">Owner</a></th>
                <th><a href="?sort=customer_name&order=<?php echo $sort_column === 'customer_name' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'customer_name' ? strtolower($sort_order) : ''; ?>">Customer</a></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rentals as $rental): ?>
                <tr>
                    <td><a href="flat_detail.php?id=<?php echo $rental['flat_id']; ?>" class="flat-ref-button" target="_blank"><?php echo htmlspecialchars($rental['ref_number']); ?></a></td>
                    <td><?php echo htmlspecialchars($rental['monthly_rent']); ?></td>
                    <td><?php echo htmlspecialchars($rental['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($rental['end_date']); ?></td>
                    <td><?php echo htmlspecialchars($rental['location']); ?></td>
                    <td><a href="user_card.php?id=<?php echo $rental['owner_id']; ?>" target="_blank"><?php echo htmlspecialchars($rental['owner_name']); ?></a></td>
                    <td><a href="user_card.php?id=<?php echo $rental['customer_id']; ?>" target="_blank"><?php echo htmlspecialchars($rental['customer_name']); ?></a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>

<?php require_once '../includes/footer.php'; ?>