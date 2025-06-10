<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $error = null;

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Query users table with name from customers or owners
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.email, u.password, u.role, 
                   COALESCE(c.name, o.name, u.email) AS name
            FROM users u
            LEFT JOIN customers c ON u.user_id = c.user_id
            LEFT JOIN owners o ON u.user_id = o.user_id
            WHERE u.email = :email
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Email not found.";
        } elseif (!password_verify($password, $user['password'])) {
            $error = "Incorrect password.";
        } else {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            // Redirect
            if (isset($_SESSION['redirect_url'])) {
                $url = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']);
                header("Location: $url");
            } else {
                header("Location: index.php");
            }
            exit;
        }
    }
}
?>

<main>
    <h2>Login</h2>
    <?php if (isset($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="POST">
        <label>Email<input type="email" name="email" required></label>
        <label>Password<input type="password" name="password" required></label>
        <button type="submit">Login</button>
    </form>
    <p>Don't have an account? <a href="customer_register.php">Sign Up</a></p>
</main>

<?php require_once '../includes/footer.php'; ?>