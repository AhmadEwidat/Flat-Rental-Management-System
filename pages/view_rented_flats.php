<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['customer', 'owner'])) {
    header('Location: login.php');
    exit;
}

// Get customer_id or owner_id
$customer_id = null;
$owner_id = null;
if ($_SESSION['role'] === 'customer') {
    $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($customer) {
        $customer_id = $customer['customer_id'];
    }
} else {
    $stmt = $pdo->prepare("SELECT owner_id FROM owners WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($owner) {
        $owner_id = $owner['owner_id'];
    }
}

$sort_column = $_GET['sort'] ?? (isset($_COOKIE['sort_column']) ? $_COOKIE['sort_column'] : 'start_date');
$sort_order = $_GET['order'] ?? (isset($_COOKIE['sort_order']) ? $_COOKIE['sort_order'] : 'DESC');
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';
$next_order = $sort_order === 'ASC' ? 'DESC' : 'ASC';

setcookie('sort_column', $sort_column, time() + (7 * 24 * 60 * 60), '/');
setcookie('sort_order', $sort_order, time() + (7 * 24 * 60 * 60), '/');

$valid_columns = ['ref_number', 'monthly_rent', 'start_date', 'end_date', 'location', 'owner_name'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'start_date';

$query = "SELECT r.*, f.ref_number, f.monthly_rent, f.location, o.name AS owner_name, o.owner_id 
          FROM rents r 
          JOIN flats f ON r.flat_id = f.flat_id 
          JOIN owners o ON f.owner_id = o.owner_id 
          WHERE r.customer_id = :customer_id OR f.owner_id = :owner_id 
          ORDER BY r.$sort_column $sort_order";
$stmt = $pdo->prepare($query);
$stmt->execute([
    'customer_id' => $customer_id ?? '',
    'owner_id' => $owner_id ?? ''
]);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main>
    <h2>Rented Flats</h2>
    <table>
        <thead>
            <tr>
                <th><a href="?sort=ref_number&order=<?php echo $sort_column === 'ref_number' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'ref_number' ? strtolower($sort_order) : ''; ?>">Flat Reference</a></th>
                <th><a href="?sort=monthly_rent&order=<?php echo $sort_column === 'monthly_rent' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'monthly_rent' ? strtolower($sort_order) : ''; ?>">Monthly Rent</a></th>
                <th><a href="?sort=start_date&order=<?php echo $sort_column === 'start_date' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'start_date' ? strtolower($sort_order) : ''; ?>">Start Date</a></th>
                <th><a href="?sort=end_date&order=<?php echo $sort_column === 'end_date' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'end_date' ? strtolower($sort_order) : ''; ?>">End Date</a></th>
                <th><a href="?sort=location&order=<?php echo $sort_column === 'location' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'location' ? strtolower($sort_order) : ''; ?>">Location</a></th>
                <th><a href="?sort=owner_name&order=<?php echo $sort_column === 'owner_name' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'owner_name' ? strtolower($sort_order) : ''; ?>">Owner</a></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rentals as $rental): ?>
                <tr class="<?php echo $rental['end_date'] >= date('Y-m-d') ? 'current' : 'past'; ?>">
                    <td><a href="flat_detail.php?id=<?php echo $rental['flat_id']; ?>" class="flat-ref-button" target="_blank"><?php echo htmlspecialchars($rental['ref_number']); ?></a></td>
                    <td><?php echo htmlspecialchars($rental['monthly_rent']); ?></td>
                    <td><?php echo htmlspecialchars($rental['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($rental['end_date']); ?></td>
                    <td><?php echo htmlspecialchars($rental['location']); ?></td>
                    <td><a href="user_card.php?id=<?php echo $rental['owner_id']; ?>" target="_blank"><?php echo htmlspecialchars($rental['owner_name']); ?></a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>

<?php require_once '../includes/footer.php'; ?>