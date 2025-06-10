<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

$step = $_GET['step'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        $_SESSION['reg_data'] = $_POST;
        header('Location: owner_register.php?step=2');
        exit;
    } elseif ($step == 2) {
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (!preg_match('/^[0-9].*[a-z]$/', $password) || strlen($password) < 6 || strlen($password) > 15) {
            $error = "Password must be 6-15 characters, start with a digit, and end with a lowercase letter.";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Email already exists.";
            } else {
                $_SESSION['reg_data']['email'] = $email;
                $_SESSION['reg_data']['password'] = password_hash($password, PASSWORD_DEFAULT);
                header('Location: owner_register.php?step=3');
                exit;
            }
        }
    } elseif ($step == 3) {
        $data = $_SESSION['reg_data'];

        // Insert into users table
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (:email, :password, 'owner')");
        $stmt->execute([
            'email' => $data['email'],
            'password' => $data['password']
        ]);
        $user_id = $pdo->lastInsertId();

        // Generate 9-digit owner_id
        $owner_id = sprintf('%09d', $user_id);

        // Insert into owners table
        $stmt = $pdo->prepare("INSERT INTO owners (owner_id, user_id, national_id, name, flat_number, street_name, city, postal_code, dob, email, mobile, telephone, bank_name, bank_branch, account_number) VALUES (:owner_id, :user_id, :national_id, :name, :flat_number, :street_name, :city, :postal_code, :dob, :email, :mobile, :telephone, :bank_name, :bank_branch, :account_number)");
        $stmt->execute([
            'owner_id' => $owner_id,
            'user_id' => $user_id,
            'national_id' => $data['national_id'],
            'name' => $data['name'],
            'flat_number' => $data['flat_number'],
            'street_name' => $data['street_name'],
            'city' => $data['city'],
            'postal_code' => $data['postal_code'],
            'dob' => $data['dob'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'telephone' => $data['telephone'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_branch' => $data['bank_branch'] ?? null,
            'account_number' => $data['account_number'] ?? null
        ]);

        unset($_SESSION['reg_data']);
        echo "<p>Registration successful! Welcome, {$data['name']}. Your Owner ID is: $owner_id</p>";
        exit;
    }
}
?>

<main>
    <?php if (isset($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if ($step == 1): ?>
        <form method="POST" action="owner_register.php?step=1">
            <label>National ID (10 digits)<input type="text" name="national_id" required pattern="[0-9]{10}"></label>
            <label>Name (letters only)<input type="text" name="name" required pattern="[A-Za-z\s]+"></label>
            <label>Flat Number<input type="text" name="flat_number" required></label>
            <label>Street Name<input type="text" name="street_name" required></label>
            <label>City<input type="text" name="city" required></label>
            <label>Postal Code<input type="text" name="postal_code" required></label>
            <label>Date of Birth<input type="date" name="dob" required></label>
            <label>Mobile Number<input type="tel" name="mobile" required></label>
            <label>Telephone Number<input type="tel" name="telephone"></label>
            <label>Bank Name<input type="text" name="bank_name"></label>
            <label>Bank Branch<input type="text" name="bank_branch"></label>
            <label>Account Number<input type="text" name="account_number"></label>
            <button type="submit">Next</button>
        </form>
    <?php elseif ($step == 2): ?>
        <form method="POST" action="owner_register.php?step=2">
            <label>Email<input type="email" name="email" required></label>
            <label>Password<input type="password" name="password" required></label>
            <label>Confirm Password<input type="password" name="confirm_password" required></label>
            <button type="submit">Next</button>
        </form>
    <?php elseif ($step == 3): ?>
        <form method="POST" action="owner_register.php?step=3">
            <?php foreach ($_SESSION['reg_data'] as $key => $value): ?>
                <?php if ($key !== 'password'): ?>
                    <p><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($value); ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
            <button type="submit">Confirm</button>
        </form>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>