<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header('Location: login.php');
    exit;
}

// Get owner_id
$stmt = $pdo->prepare("SELECT owner_id FROM owners WHERE user_id = :user_id");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$owner = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$owner) {
    die("Owner not found.");
}
$owner_id = $owner['owner_id'];

$step = $_GET['step'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Validate required fields
        $required_fields = ['location', 'flat_number', 'street_name', 'city', 'postal_code', 
                          'monthly_rent', 'available_from', 'bedrooms', 'bathrooms', 
                          'size_sqm', 'backyard', 'rent_conditions'];
        
        $errors = [];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
            }
        }

        if (empty($errors)) {
            $_SESSION['flat_data'] = $_POST;
            header('Location: offer_flat.php?step=2');
            exit;
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

        // Generate unique 6-digit reference number
        do {
            $ref_number = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM flats WHERE ref_number = :ref_number");
            $stmt->execute(['ref_number' => $ref_number]);
        } while ($stmt->fetchColumn() > 0);

        // Insert flat
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

        // Insert photos
        if (isset($_FILES['photos']) && is_array($_FILES['photos']['tmp_name'])) {
            // إنشاء مجلد uploads إذا لم يكن موجوداً
            $upload_dir = "../assets/images";
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    error_log("Failed to create directory: " . $upload_dir);
                    die("Failed to create upload directory");
                }
            }

            // التأكد من صلاحيات المجلد
            if (!is_writable($upload_dir)) {
                error_log("Directory is not writable: " . $upload_dir);
                die("Upload directory is not writable");
            }

            foreach ($_FILES['photos']['tmp_name'] as $index => $tmp_name) {
                if ($tmp_name && $_FILES['photos']['error'][$index] === UPLOAD_ERR_OK) {
                    // التحقق من نوع الملف
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $file_type = $_FILES['photos']['type'][$index];
                    
                    if (!in_array($file_type, $allowed_types)) {
                        error_log("Invalid file type: " . $file_type);
                        continue;
                    }

                    // إنشاء اسم فريد للملف
                    $file_extension = strtolower(pathinfo($_FILES['photos']['name'][$index], PATHINFO_EXTENSION));
                    $file_name = "flat_" . $flat_id . "_" . time() . "_" . $index . "." . $file_extension;
                    $photo_url = "/AhmadEwidat1212596/assets/images/" . $file_name;
                    $full_path = "../" . $photo_url;

                    // محاولة نقل الملف
                    if (move_uploaded_file($tmp_name, $full_path)) {
                        try {
                            // التحقق من وجود الصورة في قاعدة البيانات
                            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM flat_photos WHERE flat_id = :flat_id AND photo_url = :photo_url");
                            $check_stmt->execute([
                                'flat_id' => $flat_id,
                                'photo_url' => $photo_url
                            ]);
                            
                            if ($check_stmt->fetchColumn() == 0) {
                                // إضافة الصورة إلى قاعدة البيانات
                                $stmt = $pdo->prepare("INSERT INTO flat_photos (flat_id, photo_url) VALUES (:flat_id, :photo_url)");
                                $result = $stmt->execute([
                                    'flat_id' => $flat_id,
                                    'photo_url' => $photo_url
                                ]);
                                
                                if (!$result) {
                                    error_log("Failed to insert photo into database: " . print_r($stmt->errorInfo(), true));
                                } else {
                                    error_log("Successfully inserted photo: " . $photo_url);
                                }
                            } else {
                                error_log("Photo already exists in database: " . $photo_url);
                            }
                        } catch (PDOException $e) {
                            error_log("Database error while inserting photo: " . $e->getMessage());
                        }
                    } else {
                        error_log("Failed to move uploaded file to: " . $full_path);
                        error_log("Upload error details: " . print_r($_FILES['photos']['error'][$index], true));
                    }
                } else {
                    error_log("File upload error: " . $_FILES['photos']['error'][$index]);
                }
            }
        } else {
            error_log("No photos were uploaded");
            error_log("FILES array: " . print_r($_FILES, true));
        }

        // Insert marketing info
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

        // Insert viewing times
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

        // Notify manager
        $stmt = $pdo->prepare("INSERT INTO messages (receiver_user_id, sender_user_id, title, body, is_read) SELECT user_id, :sender_user_id, :title, :body, 0 FROM users WHERE role = 'manager'");
        $stmt->execute([
            'sender_user_id' => $_SESSION['user_id'],
            'title' => "New Flat Approval Request",
            'body' => "A new flat (Ref: $ref_number) has been submitted for approval."
        ]);

        unset($_SESSION['flat_data']);
        echo "<p>Flat submitted successfully! Ref Number: $ref_number. Awaiting manager approval.</p>";
        exit;
    }
}
?>

<main>
    <?php if ($step == 1): ?>
        <form method="POST" action="offer_flat.php?step=1" enctype="multipart/form-data">
            <label>Location<input type="text" name="location" required></label>
            <label>Flat Number<input type="text" name="flat_number" required></label>
            <label>Street Name<input type="text" name="street_name" required></label>
            <label>City<input type="text" name="city" required></label>
            <label>Postal Code<input type="text" name="postal_code" required></label>
            <label>Monthly Rent<input type="number" name="monthly_rent" required></label>
            <label>Available From<input type="date" name="available_from" required></label>
            <label>Available To<input type="date" name="available_to"></label>
            <label>Bedrooms<input type="number" name="bedrooms" required></label>
            <label>Bathrooms<input type="number" name="bathrooms" required></label>
            <label>Size (sqm)<input type="number" name="size_sqm" required></label>
            <label><input type="checkbox" name="is_furnished"> Furnished</label>
            <label><input type="checkbox" name="heating"> Heating</label>
            <label><input type="checkbox" name="air_conditioning"> Air Conditioning</label>
            <label><input type="checkbox" name="access_control"> Access Control</label>
            <label><input type="checkbox" name="parking"> Parking</label>
            <label>Backyard<select name="backyard" required>
                <option value="none">None</option>
                <option value="individual">Individual</option>
                <option value="shared">Shared</option>
            </select></label>
            <label><input type="checkbox" name="playground"> Playground</label>
            <label><input type="checkbox" name="storage"> Storage</label>
            <label>Rental Conditions<textarea name="rent_conditions" required></textarea></label>
            <label>Photos<input type="file" name="photos[]" multiple accept="image/*" required></label>
            <button type="submit">Next</button>
        </form>
    <?php elseif ($step == 2): ?>
        <form method="POST" action="offer_flat.php?step=2">
            <h3>Add Marketing Information (Optional)</h3>
            <div class="marketing-info">
                <label>Title<input type="text" name="marketing[0][title]"></label>
                <label>Description<textarea name="marketing[0][description]"></textarea></label>
                <label>URL<input type="url" name="marketing[0][url]"></label>
            </div>
            <button type="submit">Next</button>
        </form>
    <?php elseif ($step == 3): ?>
        <form method="POST" action="offer_flat.php?step=3">
            <h3>Add Viewing Times</h3>
            <div class="viewing-time">
                <label>Day<select name="viewing_times[0][day_of_week]" required>
                    <option value="">Select Day</option>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                    <option value="Sunday">Sunday</option>
                </select></label>
                <label>From<input type="time" name="viewing_times[0][time_from]" required></label>
                <label>To<input type="time" name="viewing_times[0][time_to]" required></label>
                <label>Contact Number<input type="tel" name="viewing_times[0][phone_number]" required></label>
            </div>
            <button type="submit">Submit</button>
        </form>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>