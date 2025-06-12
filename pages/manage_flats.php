<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/session.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

// التحقق من تسجيل الدخول وصلاحيات المدير
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('Location: unauthorized.php');
    exit;
}

// معالجة حذف الشقة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_flat'])) {
    try {
        $flat_id = $_POST['flat_id'];
        
        // حذف الصور أولاً
        $stmt = $pdo->prepare("DELETE FROM flat_photos WHERE flat_id = :flat_id");
        $stmt->execute(['flat_id' => $flat_id]);
        
        // حذف طلبات المعاينة
        $stmt = $pdo->prepare("DELETE FROM preview_requests WHERE flat_id = :flat_id");
        $stmt->execute(['flat_id' => $flat_id]);
        
        // حذف الشقة
        $stmt = $pdo->prepare("DELETE FROM flats WHERE flat_id = :flat_id");
        $stmt->execute(['flat_id' => $flat_id]);
        
        setFlashMessage('success', 'تم حذف الشقة بنجاح.');
    } catch (PDOException $e) {
        setFlashMessage('error', 'خطأ في حذف الشقة: ' . $e->getMessage());
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// جلب جميع الشقق
try {
    $stmt = $pdo->prepare("
        SELECT 
            f.*,
            o.name as owner_name,
            COUNT(DISTINCT pr.id) as preview_requests,
            COUNT(DISTINCT r.rent_id) as active_rents
        FROM flats f
        LEFT JOIN owners o ON f.owner_id = o.owner_id
        LEFT JOIN preview_requests pr ON f.flat_id = pr.flat_id AND pr.status = 'pending'
        LEFT JOIN rents r ON f.flat_id = r.flat_id 
            AND r.start_date <= CURRENT_DATE 
            AND r.end_date >= CURRENT_DATE
        GROUP BY f.flat_id
        ORDER BY f.flat_id DESC
    ");
    $stmt->execute();
    $flats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setFlashMessage('error', 'خطأ في جلب الشقق: ' . $e->getMessage());
    $flats = [];
}
?>

<link rel="stylesheet" href="../assets/css/styles.css">

<main>
    <div class="page-header">
        <h2>إدارة الشقق</h2>
        <a href="add_flat.php" class="btn-primary">إضافة شقة جديدة</a>
    </div>

    <?php
    $flash = getFlashMessage();
    if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($flats)): ?>
        <p>لا توجد شقق في النظام.</p>
    <?php else: ?>
        <div class="flats-grid">
            <?php foreach ($flats as $flat): ?>
                <div class="flat-card">
                    <div class="flat-image">
                        <?php
                        // جلب الصورة الرئيسية للشقة
                        $stmt = $pdo->prepare("SELECT photo_url FROM flat_photos WHERE flat_id = :flat_id LIMIT 1");
                        $stmt->execute(['flat_id' => $flat['flat_id']]);
                        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <img src="<?php echo $photo ? htmlspecialchars($photo['photo_url']) : '../assets/images/no-image.jpg'; ?>" 
                             alt="شقة <?php echo htmlspecialchars($flat['ref_number']); ?>">
                    </div>
                    
                    <div class="flat-info">
                        <h3>
                            <a href="flat_detail.php?ref=<?php echo htmlspecialchars($flat['ref_number']); ?>" 
                               target="_blank">
                                <?php echo htmlspecialchars($flat['ref_number']); ?>
                            </a>
                        </h3>
                        
                        <p class="location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($flat['location']); ?>
                        </p>
                        
                        <p class="price">
                            <i class="fas fa-dollar-sign"></i>
                            <?php echo number_format($flat['monthly_rent'], 2); ?> / شهر
                        </p>

                        <p class="owner">
                            <i class="fas fa-user"></i>
                            المالك: <?php echo htmlspecialchars($flat['owner_name']); ?>
                        </p>
                        
                        <div class="flat-status">
                            <?php if ($flat['active_rents'] > 0): ?>
                                <span class="status rented">مؤجرة</span>
                            <?php elseif ($flat['preview_requests'] > 0): ?>
                                <span class="status pending">طلبات معاينة معلقة</span>
                            <?php else: ?>
                                <span class="status available">متاحة</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flat-actions">
                            <a href="edit_flat.php?id=<?php echo $flat['flat_id']; ?>" 
                               class="btn-edit">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                            
                            <form method="POST" class="delete-form" 
                                  onsubmit="return confirm('هل أنت متأكد من حذف هذه الشقة؟');">
                                <input type="hidden" name="flat_id" value="<?php echo $flat['flat_id']; ?>">
                                <button type="submit" name="delete_flat" class="btn-delete">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?> 