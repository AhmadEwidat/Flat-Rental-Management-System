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

        try {
            $pdo->beginTransaction();

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

            $pdo->commit();
            $success = "Registration successful! Welcome, {$data['name']}. Your Owner ID is: $owner_id";
            unset($_SESSION['reg_data']);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Registration failed. Please try again.";
        }
    }
}
?>

<main>
    <h1>Owner Registration</h1>
    
    <?php if (isset($success)): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($success); ?>
            <p>You can now <a href="login.php">login</a> to your account.</p>
        </div>
    <?php else: ?>
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="registration-steps">
            <div class="step <?php echo $step == 1 ? 'active' : ''; ?>">1. Personal Information</div>
            <div class="step <?php echo $step == 2 ? 'active' : ''; ?>">2. Account Details</div>
            <div class="step <?php echo $step == 3 ? 'active' : ''; ?>">3. Confirmation</div>
        </div>

        <?php if ($step == 1): ?>
            <form method="POST" action="owner_register.php?step=1" class="registration-form">
                <h2>Step 1: Personal Information</h2>
                
                <div class="form-group">
                    <label for="national_id" class="required">National ID</label>
                    <input type="text" id="national_id" name="national_id" required pattern="[0-9]{10}" maxlength="10">
                    <small>10 digits only</small>
                </div>

                <div class="form-group">
                    <label for="name" class="required">Full Name</label>
                    <input type="text" id="name" name="name" required pattern="[A-Za-z\s]+">
                    <small>Letters and spaces only</small>
                </div>

                <div class="form-group">
                    <label for="flat_number" class="required">Flat/House Number</label>
                    <input type="text" id="flat_number" name="flat_number" required>
                </div>

                <div class="form-group">
                    <label for="street_name" class="required">Street Name</label>
                    <input type="text" id="street_name" name="street_name" required>
                </div>

                <div class="form-group">
                    <label for="city" class="required">City</label>
                    <input type="text" id="city" name="city" required>
                </div>

                <div class="form-group">
                    <label for="postal_code" class="required">Postal Code</label>
                    <input type="text" id="postal_code" name="postal_code" required>
                </div>

                <div class="form-group">
                    <label for="dob" class="required">Date of Birth</label>
                    <input type="date" id="dob" name="dob" required>
                </div>

                <div class="form-group">
                    <label for="mobile" class="required">Mobile Number</label>
                    <input type="tel" id="mobile" name="mobile" required>
                </div>

                <div class="form-group">
                    <label for="telephone">Telephone Number</label>
                    <input type="tel" id="telephone" name="telephone">
                </div>

                <div class="form-group">
                    <label for="bank_name">Bank Name</label>
                    <input type="text" id="bank_name" name="bank_name">
                </div>

                <div class="form-group">
                    <label for="bank_branch">Bank Branch</label>
                    <input type="text" id="bank_branch" name="bank_branch">
                </div>

                <div class="form-group">
                    <label for="account_number">Account Number</label>
                    <input type="text" id="account_number" name="account_number">
                </div>

                <button type="submit" class="submit-button">Next Step</button>
            </form>

        <?php elseif ($step == 2): ?>
            <form method="POST" action="owner_register.php?step=2" class="registration-form">
                <h2>Step 2: Account Details</h2>
                
                <div class="form-group">
                    <label for="email" class="required">Email Address</label>
                    <input type="email" id="email" name="email" required>
                    <small>This will be your username for login</small>
                </div>

                <div class="form-group">
                    <label for="password" class="required">Password</label>
                    <input type="password" id="password" name="password" required 
                           pattern="^\d.*[a-z]$" minlength="6" maxlength="15"
                           title="Password must start with a digit, end with a lowercase letter, and be 6-15 characters long">
                    <small>Must be 6-15 characters, start with a digit, and end with a lowercase letter</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="required">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="submit-button">Next Step</button>
            </form>

        <?php elseif ($step == 3): ?>
            <form method="POST" action="owner_register.php?step=3" class="registration-form">
                <h2>Step 3: Confirm Your Information</h2>
                
                <div class="confirmation-details">
                    <?php foreach ($_SESSION['reg_data'] as $key => $value): ?>
                        <?php if ($key !== 'password'): ?>
                            <div class="detail-group">
                                <label><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</label>
                                <span><?php echo htmlspecialchars($value); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <a href="owner_register.php?step=2" class="back-button">Back</a>
                    <button type="submit" class="submit-button">Confirm Registration</button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>