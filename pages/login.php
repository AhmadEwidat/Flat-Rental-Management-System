<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Get additional user information based on role
            if ($user['role'] === 'customer') {
                $stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                $userInfo = $stmt->fetch();
                $_SESSION['user_name'] = $userInfo['name'] ?? $user['email'];
            } elseif ($user['role'] === 'owner') {
                $stmt = $pdo->prepare("SELECT * FROM owners WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                $userInfo = $stmt->fetch();
                $_SESSION['user_name'] = $userInfo['name'] ?? $user['email'];
            } elseif ($user['role'] === 'manager') {
                $_SESSION['user_name'] = 'Manager';
            }

            // Redirect based on role
            switch ($user['role']) {
                case 'customer':
                    header('Location: view_flats.php');
                    break;
                case 'owner':
                    header('Location: my_flats.php');
                    break;
                case 'manager':
                    header('Location: manage_flats.php');
                    break;
                default:
                    header('Location: index.php');
            }
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<main>
    <div class="form-container">
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="submit-button">Login</button>
        </form>

        <div class="registration-links">
            <p>Don't have an account? Register as:</p>
            <div class="registration-buttons">
                <a href="customer_register.php" class="register-button">Customer</a>
                <a href="owner_register.php" class="register-button">Property Owner</a>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>