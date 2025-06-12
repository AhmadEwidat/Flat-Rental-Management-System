<?php

require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}

// Handle sorting
$sort_column = $_GET['sort'] ?? (isset($_COOKIE['sort_column']) ? $_COOKIE['sort_column'] : 'sent_at');
$sort_order = $_GET['order'] ?? (isset($_COOKIE['sort_order']) ? $_COOKIE['sort_order'] : 'DESC');
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';
$next_order = $sort_order === 'ASC' ? 'DESC' : 'ASC';

// Save sorting preferences in cookies
setcookie('sort_column', $sort_column, time() + (7 * 24 * 60 * 60), '/');
setcookie('sort_order', $sort_order, time() + (7 * 24 * 60 * 60), '/');

$valid_columns = ['title', 'sent_at', 'sender_name'];
$sort_column = in_array($sort_column, $valid_columns) ? $sort_column : 'sent_at';

// Fetch messages with sender name based on role
try {
    $query = "
        SELECT m.*, 
               CASE 
                   WHEN u.role = 'customer' THEN c.name
                   WHEN u.role = 'owner' THEN o.name
                   ELSE 'System'
               END AS sender_name,
               CASE 
                   WHEN u.role = 'customer' THEN c.mobile
                   WHEN u.role = 'owner' THEN o.mobile
                   ELSE NULL
               END AS sender_phone,
               CASE 
                   WHEN u.role = 'customer' THEN c.email
                   WHEN u.role = 'owner' THEN o.email
                   ELSE NULL
               END AS sender_email
        FROM messages m
        LEFT JOIN users u ON m.sender_user_id = u.user_id
        LEFT JOIN customers c ON u.user_id = c.user_id AND u.role = 'customer'
        LEFT JOIN owners o ON u.user_id = o.user_id AND u.role = 'owner'
        WHERE m.receiver_user_id = :receiver_user_id
    ";
    
    // Role-specific filtering
    if ($_SESSION['role'] === 'manager') {
        $query .= " AND (m.title LIKE '%Flat Approval%' OR m.title LIKE '%Flat Rented%')";
    } elseif ($_SESSION['role'] === 'owner') {
        $query .= " AND (m.title LIKE '%Preview Request%' OR m.title LIKE '%Rent Acceptance%')";
    } elseif ($_SESSION['role'] === 'customer') {
        $query .= " AND (m.title LIKE '%Preview Accepted%' OR m.title LIKE '%Rent Confirmed%')";
    }
    
    $query .= " ORDER BY m.$sort_column $sort_order";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['receiver_user_id' => $_SESSION['user_id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark messages as read
    if (!empty($messages)) {
        $unread_ids = array_map(function($msg) {
            return $msg['message_id'];
        }, array_filter($messages, function($msg) {
            return !$msg['is_read'];
        }));

        if (!empty($unread_ids)) {
            $update = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE message_id IN (" . implode(',', $unread_ids) . ")");
            $update->execute();
        }
    }
} catch (PDOException $e) {
    $error = "Error fetching messages: " . $e->getMessage();
}
?>

<main>
    <h2>Messages</h2>
    <?php if (isset($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    
    <?php if (empty($messages)): ?>
        <p>No messages found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th class="sortable <?php echo $sort_column === 'title' ? strtolower($sort_order) : ''; ?>">
                        <a href="?sort=title&order=<?php echo $sort_column === 'title' ? $next_order : 'ASC'; ?>">
                            Title
                        </a>
                    </th>
                    <th class="sortable <?php echo $sort_column === 'sent_at' ? strtolower($sort_order) : ''; ?>">
                        <a href="?sort=sent_at&order=<?php echo $sort_column === 'sent_at' ? $next_order : 'ASC'; ?>">
                            Date
                        </a>
                    </th>
                    <th class="sortable <?php echo $sort_column === 'sender_name' ? strtolower($sort_order) : ''; ?>">
                        <a href="?sort=sender_name&order=<?php echo $sort_column === 'sender_name' ? $next_order : 'ASC'; ?>">
                            Sender
                        </a>
                    </th>
                    <th>Body</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $message): ?>
                    <tr class="<?php echo $message['is_read'] ? '' : 'unread'; ?>">
                        <td>
                            <?php if (!$message['is_read']): ?>
                                <span class="unread-icon">🔔</span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($message['title']); ?>
                        </td>
                        <td class="text-center"><?php echo date('Y-m-d H:i', strtotime($message['sent_at'])); ?></td>
                        <td>
                            <a href="user_card.php?email=<?php echo urlencode($message['sender_email']); ?>" 
                               class="user-link" 
                               target="_blank">
                                <?php echo htmlspecialchars($message['sender_name']); ?>
                            </a>
                        </td>
                        <td><?php echo nl2br(htmlspecialchars($message['body'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>