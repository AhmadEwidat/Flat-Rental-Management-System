<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT owner_id FROM owners WHERE user_id = :user_id");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$owner = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$owner) {
    die("Owner not found.");
}
$owner_id = $owner['owner_id'];

$step = $_GET['step'] ?? 1;

if (isset($error_message)) {
    echo '<div class="error-message">' . $error_message . '</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        $required_fields = ['location', 'flat_number', 'street_name', 'city', 'postal_code', 
                          'monthly_rent', 'available_from', 'bedrooms', 'bathrooms', 
                          'size_sqm', 'backyard', 'rent_conditions', 'marketing_count', 'viewing_count'];
        
        $errors = [];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
            }
        }

        if (empty($errors)) {
            $uploaded_photos = [];
            if (isset($_FILES['photos']) && is_array($_FILES['photos']['tmp_name'])) {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/AhmadEwidat1212596/assets/images/flats";
                
                if (!file_exists($upload_dir)) {
              
                        $errors[] = "Failed to create flats directory";
                        error_log("Failed to create flats directory: " . $upload_dir);
                    
                }

                if (!is_writable($upload_dir)) {
                    $errors[] = "Upload directory is not writable";
                    error_log("Upload directory is not writable: " . $upload_dir);
                }

                foreach ($_FILES['photos']['tmp_name'] as $index => $tmp_name) {
                    if ($tmp_name && $_FILES['photos']['error'][$index] === UPLOAD_ERR_OK) {
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                        $file_type = $_FILES['photos']['type'][$index];
                        
                        if (!in_array($file_type, $allowed_types)) {
                            $errors[] = "Invalid file type for photo " . ($index + 1) . ". Only JPG, PNG and GIF are allowed.";
                            continue;
                        }

                        $file_extension = strtolower(pathinfo($_FILES['photos']['name'][$index], PATHINFO_EXTENSION));
                        $file_name = "flat_" . time() . "_" . uniqid() . "." . $file_extension;
                        
                        $photo_url = "/AhmadEwidat1212596/assets/images/flats/" . $file_name;
                        
                        $full_path = $upload_dir . "/" . $file_name;

                        error_log("Attempting to upload file to: " . $full_path);
                        
                        if (move_uploaded_file($tmp_name, $full_path)) {
                            $uploaded_photos[] = $photo_url;
                            error_log("File uploaded successfully: " . $file_name);
                        } else {
                            $error = error_get_last();
                            $errors[] = "Failed to upload photo " . ($index + 1) . ". Error: " . ($error ? $error['message'] : 'Unknown error');
                            error_log("Upload failed for file: " . $file_name . ". Error: " . ($error ? $error['message'] : 'Unknown error'));
                        }
                    } else {
                        $errors[] = "Error uploading photo " . ($index + 1) . ". Error code: " . $_FILES['photos']['error'][$index];
                    }
                }
            }

            if (empty($uploaded_photos)) {
                $errors[] = "At least one photo is required";
            }

            if (empty($errors)) {
                $_SESSION['flat_data'] = $_POST;
                $_SESSION['flat_photos'] = $uploaded_photos;
                $_SESSION['marketing_count'] = (int)$_POST['marketing_count'];
                $_SESSION['viewing_count'] = (int)$_POST['viewing_count'];
                header('Location: offer_flat.php?step=2');
                exit;
            } else {
                $error_message = implode("<br>", $errors);
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    } elseif ($step == 2) {
        $_SESSION['flat_data']['marketing'] = isset($_POST['marketing']) ? $_POST['marketing'] : [];
        header('Location: offer_flat.php?step=3');
        exit;
    } elseif ($step == 3) {
        $_SESSION['flat_data']['viewing_times'] = isset($_POST['viewing_times']) ? $_POST['viewing_times'] : [];
        $data = $_SESSION['flat_data'];

        do {
            $ref_number = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM flats WHERE ref_number = :ref_number");
            $stmt->execute(['ref_number' => $ref_number]);
        } while ($stmt->fetchColumn() > 0);

        $stmt = $pdo->prepare("INSERT INTO flats (owner_id, ref_number, location, flat_number, street_name, city, postal_code, monthly_rent, available_from, available_to, bedrooms, bathrooms, size_sqm, is_furnished, heating, air_conditioning, access_control, parking, backyard, playground, storage, rent_conditions, status) VALUES (:owner_id, :ref_number, :location, :flat_number, :street_name, :city, :postal_code, :monthly_rent, :available_from, :available_to, :bedrooms, :bathrooms, :size_sqm, :is_furnished, :heating, :air_conditioning, :access_control, :parking, :backyard, :playground, :storage, :rent_conditions, 'pending')");
        $stmt->execute([
            'owner_id' => $owner_id,
            'ref_number' => $ref_number,
            'location' => $data['location'] ?? '',
            'flat_number' => $data['flat_number'] ?? '',
            'street_name' => $data['street_name'] ?? '',
            'city' => $data['city'] ?? '',
            'postal_code' => $data['postal_code'] ?? '',
            'monthly_rent' => $data['monthly_rent'] ?? 0,
            'available_from' => $data['available_from'] ?? null,
            'available_to' => $data['available_to'] ?? null,
            'bedrooms' => $data['bedrooms'] ?? 0,
            'bathrooms' => $data['bathrooms'] ?? 0,
            'size_sqm' => $data['size_sqm'] ?? 0,
            'is_furnished' => isset($data['is_furnished']) ? 1 : 0,
            'heating' => isset($data['heating']) ? 1 : 0,
            'air_conditioning' => isset($data['air_conditioning']) ? 1 : 0,
            'access_control' => isset($data['access_control']) ? 1 : 0,
            'parking' => isset($data['parking']) ? 1 : 0,
            'backyard' => $data['backyard'] ?? 'none',
            'playground' => isset($data['playground']) ? 1 : 0,
            'storage' => isset($data['storage']) ? 1 : 0,
            'rent_conditions' => $data['rent_conditions'] ?? ''
        ]);
        $flat_id = $pdo->lastInsertId();

        if (isset($_SESSION['flat_photos']) && is_array($_SESSION['flat_photos'])) {
            foreach ($_SESSION['flat_photos'] as $photo_url) {
                $stmt = $pdo->prepare("INSERT INTO flat_photos (flat_id, photo_url) VALUES (:flat_id, :photo_url)");
                $stmt->execute([
                    'flat_id' => $flat_id,
                    'photo_url' => $photo_url
                ]);
            }
        }

        if (isset($data['marketing']) && is_array($data['marketing'])) {
            foreach ($data['marketing'] as $info) {
                if (!empty($info['title']) && !empty($info['description'])) {
                    $stmt = $pdo->prepare("INSERT INTO marketing_info (flat_id, title, description, url) VALUES (:flat_id, :title, :description, :url)");
                    $stmt->execute([
                        'flat_id' => $flat_id,
                        'title' => $info['title'],
                        'description' => $info['description'],
                        'url' => $info['url'] ?? null
                    ]);
                }
            }
        }

        if (isset($data['viewing_times']) && is_array($data['viewing_times'])) {
            foreach ($data['viewing_times'] as $time) {
                if (!empty($time['day_of_week']) && !empty($time['time_from']) && !empty($time['time_to']) && !empty($time['phone_number'])) {
                    $stmt = $pdo->prepare("INSERT INTO viewing_times (flat_id, day_of_week, time_from, time_to, phone_number) VALUES (:flat_id, :day_of_week, :time_from, :time_to, :phone_number)");
                    $stmt->execute([
                        'flat_id' => $flat_id,
                        'day_of_week' => $time['day_of_week'],
                        'time_from' => $time['time_from'],
                        'time_to' => $time['time_to'],
                        'phone_number' => $time['phone_number']
                    ]);
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO messages (receiver_user_id, sender_user_id, title, body, is_read) SELECT user_id, :sender_user_id, :title, :body, 0 FROM users WHERE role = 'manager'");
        $stmt->execute([
            'sender_user_id' => $_SESSION['user_id'],
            'title' => "New Flat Approval Request",
            'body' => "A new flat (Ref: $ref_number) has been submitted for approval."
        ]);

        unset($_SESSION['flat_data']);
        unset($_SESSION['flat_photos']);
        echo "<p>Flat submitted successfully! Ref Number: $ref_number. Awaiting manager approval.</p>";
        exit;
    }
}
?>

<main>
    <?php if ($step == 1): ?>
        <form method="POST" action="offer_flat.php?step=1" enctype="multipart/form-data" class="flat-form">
            <div class="form-section">
                <h2>Basic Information</h2>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" required>
                </div>
                <div class="form-group">
                    <label for="flat_number">Flat Number</label>
                    <input type="text" id="flat_number" name="flat_number" required>
                </div>
                <div class="form-group">
                    <label for="street_name">Street Name</label>
                    <input type="text" id="street_name" name="street_name" required>
                </div>
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" required>
                </div>
                <div class="form-group">
                    <label for="postal_code">Postal Code</label>
                    <input type="text" id="postal_code" name="postal_code" required>
                </div>
            </div>

            <div class="form-section">
                <h2>Rental Details</h2>
                <div class="form-group">
                    <label for="monthly_rent">Monthly Rent</label>
                    <input type="number" id="monthly_rent" name="monthly_rent" required min="0">
                </div>
                <div class="form-group">
                    <label for="available_from">Available From</label>
                    <input type="date" id="available_from" name="available_from" required>
                </div>
                <div class="form-group">
                    <label for="available_to">Available To (Optional)</label>
                    <input type="date" id="available_to" name="available_to">
                </div>
            </div>

            <div class="form-section">
                <h2>Flat Specifications</h2>
                <div class="form-group">
                    <label for="bedrooms">Bedrooms</label>
                    <input type="number" id="bedrooms" name="bedrooms" required min="0">
                </div>
                <div class="form-group">
                    <label for="bathrooms">Bathrooms</label>
                    <input type="number" id="bathrooms" name="bathrooms" required min="0">
                </div>
                <div class="form-group">
                    <label for="size_sqm">Size (sqm)</label>
                    <input type="number" id="size_sqm" name="size_sqm" required min="0">
                </div>
            </div>

            <div class="form-section">
                <h2>Amenities</h2>
                <div class="checkbox-group">
                    <label><input type="checkbox" name="is_furnished"> Furnished</label>
                    <label><input type="checkbox" name="heating"> Heating</label>
                    <label><input type="checkbox" name="air_conditioning"> Air Conditioning</label>
                    <label><input type="checkbox" name="access_control"> Access Control</label>
                    <label><input type="checkbox" name="parking"> Parking</label>
                    <label><input type="checkbox" name="playground"> Playground</label>
                    <label><input type="checkbox" name="storage"> Storage</label>
                </div>
                <div class="form-group">
                    <label for="backyard">Backyard</label>
                    <select id="backyard" name="backyard" required>
                        <option value="none">None</option>
                        <option value="individual">Individual</option>
                        <option value="shared">Shared</option>
                    </select>
                </div>
            </div>

            <div class="form-section">
                <h2>Additional Information</h2>
                <div class="form-group">
                    <label for="rent_conditions">Rental Conditions</label>
                    <textarea id="rent_conditions" name="rent_conditions" required rows="4"></textarea>
                </div>
            </div>

            <div class="form-section">
                <h2>Photos</h2>
                <div class="form-group">
                    <label for="photos">Upload Photos (JPG, PNG, or GIF)</label>
                    <input type="file" id="photos" name="photos[]" multiple accept="image/jpeg,image/png,image/gif" required>
                    <small>You can select multiple photos. At least one photo is required.</small>
                </div>
            </div>

            <div class="form-section">
                <h2>Additional Settings</h2>
                <div class="form-group">
                    <label for="marketing_count">Number of Marketing Information Fields</label>
                    <input type="number" id="marketing_count" name="marketing_count" min="0" max="5" value="1" required>
                    <small>How many marketing information fields do you want to add? (0-5)</small>
                </div>
                <div class="form-group">
                    <label for="viewing_count">Number of Viewing Time Fields</label>
                    <input type="number" id="viewing_count" name="viewing_count" min="1" max="7" value="1" required>
                    <small>How many viewing time slots do you want to add? (1-7)</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Next</button>
            </div>
        </form>
    <?php elseif ($step == 2): ?>
        <form method="POST" action="offer_flat.php?step=2">
            <h3>Add Marketing Information (Optional)</h3>
            <?php for ($i = 0; $i < $_SESSION['marketing_count']; $i++): ?>
            <div class="marketing-info">
                <label>Title<input type="text" name="marketing[<?php echo $i; ?>][title]"></label>
                <label>Description<textarea name="marketing[<?php echo $i; ?>][description]"></textarea></label>
                <label>URL<input type="url" name="marketing[<?php echo $i; ?>][url]"></label>
            </div>
            <?php endfor; ?>
            <button type="submit">Next</button>
        </form>
    <?php elseif ($step == 3): ?>
        <form method="POST" action="offer_flat.php?step=3">
            <h3>Add Viewing Times</h3>
            <?php for ($i = 0; $i < $_SESSION['viewing_count']; $i++): ?>
            <div class="viewing-time">
                <label>Day<select name="viewing_times[<?php echo $i; ?>][day_of_week]" required>
                    <option value="">Select Day</option>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                    <option value="Sunday">Sunday</option>
                </select></label>
                <label>From<input type="time" name="viewing_times[<?php echo $i; ?>][time_from]" required></label>
                <label>To<input type="time" name="viewing_times[<?php echo $i; ?>][time_to]" required></label>
                <label>Contact Number<input type="tel" name="viewing_times[<?php echo $i; ?>][phone_number]" required></label>
            </div>
            <?php endfor; ?>
            <button type="submit">Submit</button>
        </form>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>
