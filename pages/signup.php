<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';
?>

<main>
    <div class="container">
        <div class="signup-container">
            <h2>Choose Your Registration Type</h2>
            <div class="registration-options">
                <div class="registration-option">
                    <h3>Register as Customer</h3>
                    <p>Create an account to rent flats and manage your rentals</p>
                    <a href="customer_register.php" class="btn btn-primary">Sign Up as Customer</a>
                </div>
                <div class="registration-option">
                    <h3>Register as Owner</h3>
                    <p>Create an account to list your flats and manage your properties</p>
                    <a href="owner_register.php" class="btn btn-primary">Sign Up as Owner</a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?> 