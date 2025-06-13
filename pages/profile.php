<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($role === 'customer') {
    $stmt = $pdo->prepare("
        SELECT c.*, u.email 
        FROM customers c 
        JOIN users u ON c.user_id = u.user_id 
        WHERE c.user_id = ?
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT o.*, u.email 
        FROM owners o 
        JOIN users u ON o.user_id = u.user_id 
        WHERE o.user_id = ?
    ");
}
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../assets/images/profiles";
            
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create profiles directory");
                }
            }

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['profile_photo']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only JPG, PNG and GIF are allowed.");
            }

            $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $file_name = "profile_" . $user_id . "_" . time() . "." . $file_extension;
            
            $photo_url = "/AhmadEwidat1212596/assets/images/profiles/" . $file_name;
            
            $full_path = $upload_dir . "/" . $file_name;

            if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $full_path)) {
                throw new Exception("Failed to upload profile photo.");
            }

            $stmt = $pdo->prepare("UPDATE users SET photo = ? WHERE user_id = ?");
            $stmt->execute([$photo_url, $user_id]);
        }

        if ($role === 'customer') {
            $stmt = $pdo->prepare("
                UPDATE customers 
                SET name = ?, mobile = ?, telephone = ?, 
                    flat_number = ?, street_name = ?, city = ?, postal_code = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $_POST['name'],
                $_POST['mobile'],
                $_POST['telephone'],
                $_POST['flat_number'],
                $_POST['street_name'],
                $_POST['city'],
                $_POST['postal_code'],
                $user_id
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE owners 
                SET name = ?, mobile = ?, telephone = ?, 
                    flat_number = ?, street_name = ?, city = ?, postal_code = ?,
                    bank_name = ?, bank_branch = ?, account_number = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $_POST['name'],
                $_POST['mobile'],
                $_POST['telephone'],
                $_POST['flat_number'],
                $_POST['street_name'],
                $_POST['city'],
                $_POST['postal_code'],
                $_POST['bank_name'],
                $_POST['bank_branch'],
                $_POST['account_number'],
                $user_id
            ]);
        }

        if ($_POST['email'] !== $user['email']) {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            $stmt->execute([$_POST['email'], $user_id]);
        }

        if (!empty($_POST['new_password'])) {
            if (!preg_match('/^\d.*[a-z]$/', $_POST['new_password']) || 
                strlen($_POST['new_password']) < 6 || 
                strlen($_POST['new_password']) > 15) {
                throw new Exception("Password must start with a digit, end with a lowercase letter, and be 6-15 characters long");
            }
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception("Passwords do not match");
            }
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user_id]);
        }

        $pdo->commit();
        $success = "Profile updated successfully";
        
        if ($role === 'customer') {
            $stmt = $pdo->prepare("
                SELECT c.*, u.email, u.photo 
                FROM customers c 
                JOIN users u ON c.user_id = u.user_id 
                WHERE c.user_id = ?
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT o.*, u.email, u.photo 
                FROM owners o 
                JOIN users u ON o.user_id = u.user_id 
                WHERE o.user_id = ?
            ");
        }
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$stmt = $pdo->prepare("SELECT photo FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_photo = $stmt->fetchColumn();

if ($user_photo) {
    $base_url = "http://" . $_SERVER['HTTP_HOST'];
    if (!str_starts_with($user_photo, 'http://') && !str_starts_with($user_photo, 'https://')) {
        $user_photo = $base_url . $user_photo;
    }
}
?>

<main class="<?php echo $role; ?>">
    <h1>Profile</h1>
    
    <?php if (isset($success)): ?>
        <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="profile-form" enctype="multipart/form-data">
        <div class="profile-photo-section">
            <div class="current-photo">
                <?php if ($user_photo): ?>
                    <img src="<?php echo htmlspecialchars($user_photo); ?>" alt="Profile Photo" class="profile-photo">
                <?php else: ?>
                    <div class="no-photo">No Photo</div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="profile_photo">Change Profile Photo</label>
                <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/gif">
                <small>Allowed formats: JPG, PNG, GIF. Max size: 2MB</small>
            </div>
        </div>

        <div class="form-group">
            <label for="name" class="required">Full Name</label>
            <input type="text" id="name" name="name" required 
                   value="<?php echo htmlspecialchars($user['name']); ?>">
        </div>

        <div class="form-group">
            <label for="email" class="required">Email</label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo htmlspecialchars($user['email']); ?>">
        </div>

        <div class="form-group">
            <label for="mobile" class="required">Mobile Number</label>
            <input type="tel" id="mobile" name="mobile" required 
                   value="<?php echo htmlspecialchars($user['mobile']); ?>">
        </div>

        <div class="form-group">
            <label for="telephone">Telephone Number</label>
            <input type="tel" id="telephone" name="telephone" 
                   value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="flat_number">Flat/House Number</label>
            <input type="text" id="flat_number" name="flat_number" 
                   value="<?php echo htmlspecialchars($user['flat_number']); ?>">
        </div>

        <div class="form-group">
            <label for="street_name">Street Name</label>
            <input type="text" id="street_name" name="street_name" 
                   value="<?php echo htmlspecialchars($user['street_name']); ?>">
        </div>

        <div class="form-group">
            <label for="city">City</label>
            <input type="text" id="city" name="city" 
                   value="<?php echo htmlspecialchars($user['city']); ?>">
        </div>

        <div class="form-group">
            <label for="postal_code">Postal Code</label>
            <input type="text" id="postal_code" name="postal_code" 
                   value="<?php echo htmlspecialchars($user['postal_code']); ?>">
        </div>

        <?php if ($role === 'owner'): ?>
            <div class="form-group">
                <label for="bank_name">Bank Name</label>
                <input type="text" id="bank_name" name="bank_name" 
                       value="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="bank_branch">Bank Branch</label>
                <input type="text" id="bank_branch" name="bank_branch" 
                       value="<?php echo htmlspecialchars($user['bank_branch'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="account_number">Account Number</label>
                <input type="text" id="account_number" name="account_number" 
                       value="<?php echo htmlspecialchars($user['account_number'] ?? ''); ?>">
            </div>
        <?php endif; ?>

        <h2>Change Password</h2>
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" 
                   pattern="^\d.*[a-z]$" minlength="6" maxlength="15"
                   title="Password must start with a digit, end with a lowercase letter, and be 6-15 characters long">
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password">
        </div>

        <button type="submit" class="submit-button">Update Profile</button>
    </form>
</main>

<?php require_once '../includes/footer.php'; ?>