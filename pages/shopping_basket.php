<?php
session_start();
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php');
    exit;
}

// Placeholder: Implement logic to show unconfirmed rentals (e.g., stored in session or a temporary table)
?>

<main>
    <h2>Shopping Basket</h2>
    <p>Feature to display ongoing, unconfirmed rentals to be implemented.</p>
</main>

<?php require_once '../includes/footer.php'; ?>