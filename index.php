<?php
require_once 'includes/dbconfig.inc.php';
require_once 'includes/header.php';
require_once 'includes/nav.php';

// Fetch approved flats
$stmt = $pdo->prepare("
    SELECT f.flat_id, f.ref_number, f.location, f.monthly_rent, f.bedrooms, f.bathrooms, 
           f.size_sqm, m.title, p.photo_url
    FROM flats f
    LEFT JOIN marketing_info m ON f.flat_id = m.flat_id
    LEFT JOIN flat_photos p ON f.flat_id = p.flat_id
    WHERE f.status = 'approved'
    GROUP BY f.flat_id
    ORDER BY f.available_from DESC
");
$stmt->execute();
$flats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container">
    <div class="info-section">
        <h1 class="section-title">Student Information</h1>
        <div class="info-card">
            <p><strong>Name:</strong> Ahmad Ewidat</p>
            <p><strong>Student ID:</strong> 1212596</p>
        </div>

        <h2 class="section-title">Project Navigation</h2>
        <div class="info-card">
            <p><a href="pages/home.php" class="btn btn-primary">Go to Main Page</a></p>
        </div>

        <h2 class="section-title">Database Info</h2>
        <div class="info-card">
            <p><strong>Database Name:</strong> prefix_1212596.sql</p>
            <p><strong>Database User:</strong> proj1212596</p>
            <p><strong>Database Password:</strong> pass1212596</p>
        </div>

        <h2 class="section-title">Test Users</h2>
        <div class="info-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Username</th>
                        <th>Password</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Manager</td>
                        <td>manager1</td>
                        <td>123456</td>
                    </tr>
                    <tr>
                        <td>Customer</td>
                        <td>customer1</td>
                        <td>123456</td>
                    </tr>
                    <tr>
                        <td>Owner</td>
                        <td>owner1</td>
                        <td>123456</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>