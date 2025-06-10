<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$sort_column = $_GET['sort'] ?? (isset($_COOKIE['sort_column']) ? $_COOKIE['sort_column'] : 'sent_at');
$sort_order = $_GET['order'] ?? (isset($_COOKIE['sort_order']) ? $_COOKIE['sort_order'] : 'DESC');
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';
$next_order = $sort_order === 'ASC' ? 'DESC' : 'ASC';

setcookie('sort_column', $sort_column, time() + (7 * 24 * 60 * 60), '/');
setcookie('sort_order', $sort_order, time() + (7 * 24 * 60 * 60), '/');

$valid_columns = ['title', 'sent_at'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'sent_at';

$stmt = $pdo->prepare("SELECT m.*, u.name AS sender_name FROM messages m LEFT JOIN users u ON m.sender_user_id = u.user_id WHERE m.receiver_user_id = :receiver_user_id ORDER BY m.$sort_column $sort_order");
$stmt->execute(['receiver_user_id' => $_SESSION['user_id']]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main>
    <h2>Messages</h2>
    <table>
        <thead>
            <tr>
                <th><a href="?sort=title&order=<?php echo $sort_column === 'title' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'title' ? strtolower($sort_order) : ''; ?>">Title</a></th>
                <th><a href="?sort=sent_at&order=<?php echo $sort_column === 'sent_at' ? $next_order : 'ASC'; ?>" class="sort-icon <?php echo $sort_column === 'sent_at' ? strtolower($sort_order) : ''; ?>">Date</a></th>
                <th>Sender</th>
                <th>Body</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($messages as $message): ?>
                <tr class="<?php echo $message['is_read'] ? '' : 'unread'; ?>">
                    <td><?php echo htmlspecialchars($message['title']); ?></td>
                    <td><?php echo htmlspecialchars($message['sent_at']); ?></td>
                    <td><?php echo htmlspecialchars($message['sender_name'] ?? 'System'); ?></td>
                    <td><?php echo htmlspecialchars($message['body']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>

<?php require_once '../includes/footer.php'; ?>