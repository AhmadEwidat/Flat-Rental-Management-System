<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';
?>

<main>
    <h2>Contact Us</h2>
    <p>Email: <a href="mailto:ahmadrealma7@gmail.com" class="external-link">ahmadrealma7@gmail.com</a></p>
    <p>Phone: +970-594-618-589</p>
    <form method="POST">
        <label>Name<input type="text" name="name" required></label>
        <label>Email<input type="email" name="email" required></label>
        <label>Message<textarea name="message" required></textarea></label>
        <button type="submit">Send</button>
    </form>
</main>

<?php require_once '../includes/footer.php'; ?>