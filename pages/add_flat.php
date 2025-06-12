<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

// Check if user is logged in and is an owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit;
}

// Get owner details
$stmt = $pdo->prepare("SELECT owner_id FROM owners WHERE user_id = :user_id");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$owner = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$owner) {
    die("Owner not found.");
}
$owner_id = $owner['owner_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Validate inputs
        $errors = [];
        
        // Validate title
        if (empty($_POST['title']) || strlen($_POST['title']) < 5) {
            $errors[] = "Title must be at least 5 characters long.";
        }

        // Validate description
        if (empty($_POST['description']) || strlen($_POST['description']) < 20) {
            $errors[] = "Description must be at least 20 characters long.";
        }

        // Validate location
        if (empty($_POST['location'])) {
            $errors[] = "Location is required.";
        }

        // Validate address
        if (empty($_POST['address'])) {
            $errors[] = "Address is required.";
        }

        // Validate price
        if (!is_numeric($_POST['price']) || $_POST['price'] <= 0) {
            $errors[] = "Price must be a positive number.";
        }

        // Validate bedrooms
        if (!is_numeric($_POST['bedrooms']) || $_POST['bedrooms'] < 0) {
            $errors[] = "Number of bedrooms must be a non-negative number.";
        }

        // Validate bathrooms
        if (!is_numeric($_POST['bathrooms']) || $_POST['bathrooms'] < 0) {
            $errors[] = "Number of bathrooms must be a non-negative number.";
        }

        // Validate area
        if (!is_numeric($_POST['area']) || $_POST['area'] <= 0) {
            $errors[] = "Area must be a positive number.";
        }

        // Validate images
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $max_files = 5;

            if (count($_FILES['images']['name']) > $max_files) {
                $errors[] = "Maximum {$max_files} images allowed.";
            }

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_type = $_FILES['images']['type'][$key];
                    $file_size = $_FILES['images']['size'][$key];

                    if (!in_array($file_type, $allowed_types)) {
                        $errors[] = "Only JPG and PNG images are allowed.";
                    }

                    if ($file_size > $max_size) {
                        $errors[] = "Image size must be less than 5MB.";
                    }
                }
            }
        }

        if (empty($errors)) {
            // Generate flat reference number
            $ref_number = 'FLAT' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Insert flat
            $stmt = $pdo->prepare("
                INSERT INTO flats (
                    ref_number, owner_id, location, flat_number, street_name,
                    city, postal_code, monthly_rent, bedrooms, bathrooms,
                    size_sqm, heating, air_conditioning, access_control,
                    parking, backyard, playground, storage, is_furnished,
                    available_from, available_to, status
                ) VALUES (
                    :ref_number, :owner_id, :location, :flat_number, :street_name,
                    :city, :postal_code, :monthly_rent, :bedrooms, :bathrooms,
                    :size_sqm, :heating, :air_conditioning, :access_control,
                    :parking, :backyard, :playground, :storage, :is_furnished,
                    :available_from, :available_to, 'pending'
                )
            ");

            // Parse address components
            $address_parts = explode(',', $_POST['address']);
            $flat_number = trim($address_parts[0] ?? '');
            $street_name = trim($address_parts[1] ?? '');
            $city = trim($address_parts[2] ?? '');
            $postal_code = trim($address_parts[3] ?? '');

            $stmt->execute([
                'ref_number' => $ref_number,
                'owner_id' => $owner_id,
                'location' => $_POST['location'],
                'flat_number' => $flat_number,
                'street_name' => $street_name,
                'city' => $city,
                'postal_code' => $postal_code,
                'monthly_rent' => $_POST['price'],
                'bedrooms' => $_POST['bedrooms'],
                'bathrooms' => $_POST['bathrooms'],
                'size_sqm' => $_POST['area'],
                'heating' => isset($_POST['heating']) ? 1 : 0,
                'air_conditioning' => isset($_POST['air_conditioning']) ? 1 : 0,
                'access_control' => isset($_POST['access_control']) ? 1 : 0,
                'parking' => $_POST['parking'] ?? null,
                'backyard' => $_POST['backyard'] ?? null,
                'playground' => isset($_POST['playground']) ? 1 : 0,
                'storage' => isset($_POST['storage']) ? 1 : 0,
                'is_furnished' => isset($_POST['is_furnished']) ? 1 : 0,
                'available_from' => $_POST['available_from'],
                'available_to' => $_POST['available_to']
            ]);

            $flat_id = $pdo->lastInsertId();

            // Handle image upload
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $upload_dir = '../assets/images/flats/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $filename = $ref_number . '_' . ($key + 1) . '.jpg';
                        $filepath = $upload_dir . $filename;

                        // Compress and resize image
                        $image = imagecreatefromstring(file_get_contents($tmp_name));
                        $max_width = 1200;
                        $max_height = 800;
                        $width = imagesx($image);
                        $height = imagesy($image);

                        if ($width > $max_width || $height > $max_height) {
                            $ratio = min($max_width / $width, $max_height / $height);
                            $new_width = $width * $ratio;
                            $new_height = $height * $ratio;
                            $new_image = imagecreatetruecolor($new_width, $new_height);
                            imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                            $image = $new_image;
                        }

                        // Save compressed image
                        imagejpeg($image, $filepath, 80);
                        imagedestroy($image);

                        // Insert image record
                        $stmt = $pdo->prepare("
                            INSERT INTO flat_photos (flat_id, photo_url) 
                            VALUES (:flat_id, :photo_url)
                        ");
                        $stmt->execute([
                            'flat_id' => $flat_id,
                            'photo_url' => 'assets/images/flats/' . $filename
                        ]);
                    }
                }
            }

            $pdo->commit();
            $success = "Flat added successfully! Reference Number: " . $ref_number;
        } else {
            $error = implode("<br>", $errors);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to add flat. Please try again.";
    }
}
?>

<main>
    <div class="add-flat-container">
        <h1>Add New Flat</h1>

        <?php if (isset($success)): ?>
            <div class="success-message">
                <p><?php echo htmlspecialchars($success); ?></p>
                <a href="view_flats.php" class="btn btn-primary">View My Flats</a>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="add-flat-form" id="addFlatForm">
            <div class="form-section">
                <h2>Basic Information</h2>
                <div class="form-group">
                    <label for="location" class="required">Location</label>
                    <input type="text" id="location" name="location" required 
                           placeholder="e.g., Downtown, West Bank">
                </div>

                <div class="form-group">
                    <label for="address" class="required">Full Address</label>
                    <input type="text" id="address" name="address" required 
                           placeholder="Flat Number, Street Name, City, Postal Code">
                    <small>Please enter the complete address separated by commas</small>
                </div>

                <div class="form-group">
                    <label for="price" class="required">Monthly Rent ($)</label>
                    <input type="number" id="price" name="price" required min="0" step="0.01"
                           placeholder="Enter monthly rent amount">
                </div>
            </div>

            <div class="form-section">
                <h2>Flat Details</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="bedrooms" class="required">Bedrooms</label>
                        <input type="number" id="bedrooms" name="bedrooms" required min="0" max="10">
                    </div>

                    <div class="form-group">
                        <label for="bathrooms" class="required">Bathrooms</label>
                        <input type="number" id="bathrooms" name="bathrooms" required min="0" max="10">
                    </div>

                    <div class="form-group">
                        <label for="area" class="required">Area (m²)</label>
                        <input type="number" id="area" name="area" required min="0" step="0.01">
                    </div>
                </div>

                <div class="form-group">
                    <label>Features</label>
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="heating" value="1">
                            Heating
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="air_conditioning" value="1">
                            Air Conditioning
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="access_control" value="1">
                            Access Control
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="playground" value="1">
                            Playground
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="storage" value="1">
                            Storage
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_furnished" value="1">
                            Furnished
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="parking">Parking</label>
                        <select id="parking" name="parking">
                            <option value="">No Parking</option>
                            <option value="Street">Street Parking</option>
                            <option value="Garage">Garage</option>
                            <option value="Underground">Underground Parking</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="backyard">Backyard</label>
                        <select id="backyard" name="backyard">
                            <option value="">No Backyard</option>
                            <option value="Private">Private</option>
                            <option value="Shared">Shared</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>Availability</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="available_from" class="required">Available From</label>
                        <input type="date" id="available_from" name="available_from" required 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="available_to" class="required">Available To</label>
                        <input type="date" id="available_to" name="available_to" required 
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>Photos</h2>
                <div class="form-group">
                    <label for="images">Upload Photos (up to 5)</label>
                    <input type="file" id="images" name="images[]" accept="image/*" multiple
                           onchange="previewImages(this)">
                    <small>Maximum file size: 5MB per image. Supported formats: JPG, PNG</small>
                </div>
                <div id="imagePreview" class="image-preview"></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Flat</button>
                <button type="reset" class="btn btn-secondary">Reset Form</button>
            </div>
        </form>
    </div>
</main>

<script>
function previewImages(input) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    if (input.files) {
        const maxFiles = 5;
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        
        if (input.files.length > maxFiles) {
            alert(`Maximum ${maxFiles} images allowed.`);
            input.value = '';
            return;
        }

        Array.from(input.files).forEach((file, index) => {
            if (file.size > maxSize) {
                alert(`File ${file.name} is too large. Maximum size is 5MB.`);
                input.value = '';
                return;
            }

            if (!allowedTypes.includes(file.type)) {
                alert(`File ${file.name} is not a supported image type.`);
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview ${index + 1}">
                    <span class="preview-name">${file.name}</span>
                `;
                preview.appendChild(div);
            }
            reader.readAsDataURL(file);
        });
    }
}

// Form validation
document.getElementById('addFlatForm').addEventListener('submit', function(e) {
    const availableFrom = new Date(document.getElementById('available_from').value);
    const availableTo = new Date(document.getElementById('available_to').value);
    
    if (availableTo <= availableFrom) {
        e.preventDefault();
        alert('Available To date must be after Available From date.');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?> 