<?php
$current_page = basename($_SERVER['PHP_SELF']);
$nav_links = [
    'customer' => [
        'search.php' => 'Search Flats',
        'view_rented_flats.php' => 'View Rented Flats',
        'view_messages.php' => 'View Messages'
    ],
    'owner' => [
        'offer_flat.php' => 'Offer Flat',
        'view_rented_flats.php' => 'View Rented Flats',
        'view_messages.php' => 'View Messages'
    ],
    'manager' => [
        'manager_inquire.php' => 'Inquire Flats',
        'view_messages.php' => 'View Messages'
    ],
    'guest' => [
        'search.php' => 'Search Flats',
        'about_us.php' => 'About Us'
    ]
];
$role = $_SESSION['role'] ?? 'guest';
?>
<nav>
    <?php foreach ($nav_links[$role] as $page => $label): ?>
        <a href="<?php echo $page; ?>" class="<?php echo $current_page === $page ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($label); ?>
        </a>
    <?php endforeach; ?>
</nav>