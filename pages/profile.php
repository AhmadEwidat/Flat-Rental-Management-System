<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user details
$user = null;
$user_id = null;
$table = $_SESSION['role'] === 'customer' ? 'customers' : 'owners';
$id_field = $_SESSION['role'] === 'customer' ? 'customer_id' : 'owner_id';

$stmt = $pdo->prepare("SELECT c.*, u.user_id FROM $table c JOIN users u ON c.user_id = u.user_id WHERE u.user_id = :user_id");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}
$user_id = $user[$id_field];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE $table SET name = :name, flat_number = :flat_number, street_name = :street_name, city = :city, postal_code = :postal_code, dob = :dob, mobile = :mobile, telephone = :telephone, email = :email WHERE $id_field = :user_id");
    $stmt->execute([
        'name' => $_POST['name'],
        'flat_number' => $_POST['flat_number'],
        'street_name' => $_POST['street_name'],
        'city' => $_POST['city'],
        'postal_code' => $_POST['postal_code'],
        'dob' => $_POST['dob'],
        'mobile' => $_POST['mobile'],
        'telephone' => $_POST['telephone'] ?? null,
        'email' => $_POST['email'],
        'user_id' => $user_id
    ]);

    // Update users table email
    $stmt = $pdo->prepare("UPDATE users SET email = :email WHERE user_id = :user_id");
    $stmt->execute([
        'email' => $_POST['email'],
        'user_id' => $_SESSION['user_id']
    ]);

    $_SESSION['user_name'] = $_POST['name'];
    echo "<p>Profile updated successfully.</p>";
}
?>

<main>
    <div class="user-card <?php echo $_SESSION['role']; ?>" style="border: 2px solid <?php echo $_SESSION['role'] === 'customer' ? '#007BFF' : '#28A745'; ?>; padding: 20px; background-color: #f8f9fa;">
        <img src="../assets/images/user_photo.png" alt="User Photo">
        <form method="POST">
            <p><strong>User ID:</strong> <?php echo htmlspecialchars($user_id); ?></p>
            <label>Name<input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required pattern="[A-Za-z\s]+"></label>
            <label>Flat Number<input type="text" name="flat_number" value="<?php echo htmlspecialchars($user['flat_number']); ?>" required></label>
            <label>Street Name<input type="text" name="street_name" value="<?php echo htmlspecialchars($user['street_name']); ?>" required></label>
            <label>City<input type="text" name="city" value="<?php echo htmlspecialchars($user['city']); ?>" required></label>
            <label>Postal Code<input type="text" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code']); ?>" required></label>
            <label>Date of Birth<input type="date" name="dob" value="<?php echo htmlspecialchars($user['dob']); ?>" required></label>
            <label>Mobile Number<input type="tel" name="mobile" value="<?php echo htmlspecialchars($user['mobile']); ?>" required></label>
            <label>Telephone Number<input type="tel" name="telephone" value="<?php echo htmlspecialchars($user['telephone']); ?>"></label>
            <label>Email<input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></label>
            <button type="submit">Update Profile</button>
        </form>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>