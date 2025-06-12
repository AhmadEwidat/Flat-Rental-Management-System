<?php
$current_page = basename($_SERVER['PHP_SELF']);
$nav_links = [
    'customer' => [
        '/AhmadEwidat1212596/pages/search.php' => 'Search Flats',
        '/AhmadEwidat1212596/pages/view_rented_flats.php' => 'View Rented Flats',
        '/AhmadEwidat1212596/pages/view_messages.php' => 'View Messages'
    ],
    'owner' => [
    '/AhmadEwidat1212596/pages/offer_flat.php' => 'Offer Flat',
    '/AhmadEwidat1212596/pages/view_rented_flats.php' => 'View Rented Flats',
    '/AhmadEwidat1212596/pages/manage_previews.php' => 'Manage Previews',
    '/AhmadEwidat1212596/pages/view_messages.php' => 'View Messages'
],
  'manager' => [
    '/AhmadEwidat1212596/pages/manager_inquire.php' => 'Inquire Flats',
    '/AhmadEwidat1212596/pages/approve_flats.php' => 'Approve Flats',
    '/AhmadEwidat1212596/pages/view_messages.php' => 'View Messages'
],
    'guest' => [
        '/AhmadEwidat1212596/pages/search.php' => 'Search Flats',
        '/AhmadEwidat1212596/pages/about_us.php' => 'About Us'
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