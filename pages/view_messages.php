<?php

require_once '../includes/header.php';
require_once '../includes/nav.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}

// Handle message response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_id']) && isset($_POST['action'])) {
    $message_id = $_POST['message_id'];
    $action = $_POST['action'];
    $response_message = $_POST['response_message'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Get original message
        $stmt = $pdo->prepare("SELECT m.*, f.ref_number, f.location, o.mobile as owner_mobile, o.name as owner_name 
                              FROM messages m 
                              LEFT JOIN flats f ON m.flat_id = f.flat_id 
                              LEFT JOIN owners o ON f.owner_id = o.owner_id 
                              WHERE m.message_id = :message_id");
        $stmt->execute(['message_id' => $message_id]);
        $original_message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update original message status
        $stmt = $pdo->prepare("UPDATE messages SET status = :status WHERE message_id = :message_id");
        $stmt->execute([
            'message_id' => $message_id,
            'status' => $action
        ]);
        
        // Update rent status if it's a rent request
        if ($original_message['message_type'] === 'rent_request' && $original_message['rent_id']) {
            $stmt = $pdo->prepare("
                UPDATE rents 
                SET status = :status,
                    approval_status = :approval_status
                WHERE rent_id = :rent_id
            ");
            $stmt->execute([
                'rent_id' => $original_message['rent_id'],
                'status' => $action === 'approved' ? 'current' : 'rejected',
                'approval_status' => $action
            ]);
        
            // Update flat status if approved
            if ($action === 'approved') {
                $stmt = $pdo->prepare("
                    UPDATE flats 
                    SET status = 'rented'
                    WHERE flat_id = :flat_id
                ");
                $stmt->execute([
                    'flat_id' => $original_message['flat_id']
                ]);

                // Send confirmation message to customer
                $stmt = $pdo->prepare("
                    INSERT INTO messages (
                        receiver_user_id, 
                        sender_user_id, 
                        title, 
                        body, 
                        is_read,
                        message_type,
                        flat_id,
                        rent_id,
                        status
                    ) VALUES (
                        :receiver_user_id, 
                        NULL, 
                        :title, 
                        :body, 
                        0,
                        'rent_confirmation',
                        :flat_id,
                        :rent_id,
                        'approved'
                    )
                ");
                $stmt->execute([
                    'receiver_user_id' => $original_message['sender_user_id'],
                    'title' => "Rental Confirmation",
                    'body' => "Congratulations! Your rental request for flat {$original_message['ref_number']} has been approved.\n\n" .
                             "Rental Details:\n" .
                             "- Start Date: " . date('Y-m-d', strtotime($original_message['start_date'])) . "\n" .
                             "- End Date: " . date('Y-m-d', strtotime($original_message['end_date'])) . "\n" .
                             "- Monthly Rent: $" . number_format($original_message['monthly_rent'], 2) . "\n\n" .
                             "Key Collection:\n" .
                             "- Location: {$original_message['location']}\n" .
                             "- Date: " . date('Y-m-d', strtotime($original_message['start_date'])) . "\n" .
                             "- Time: Between 9:00 AM and 5:00 PM\n\n" .
                             "Please contact the owner {$original_message['owner_name']} at {$original_message['owner_mobile']} " .
                             "to arrange a specific time for key collection.\n\n" .
                             "Thank you for choosing our service!",
                    'flat_id' => $original_message['flat_id'],
                    'rent_id' => $original_message['rent_id']
                ]);

                // Notify manager about the rental
                $stmt = $pdo->prepare("
                    INSERT INTO messages (
                        receiver_user_id, 
                        sender_user_id, 
                        title, 
                        body, 
                        is_read,
                        message_type,
                        flat_id,
                        rent_id,
                        status
                    ) SELECT 
                        u.user_id, 
                        NULL, 
                        :title, 
                        :body, 
                        0,
                        'rent_notification',
                        :flat_id,
                        :rent_id,
                        'approved'
                    FROM users u 
                    WHERE u.role = 'manager'
                ");
                $stmt->execute([
                    'title' => "Flat Rented",
                    'body' => "A flat has been rented:\n\n" .
                             "Flat Details:\n" .
                             "- Reference: {$original_message['ref_number']}\n" .
                             "- Location: {$original_message['location']}\n" .
                             "- Monthly Rent: $" . number_format($original_message['monthly_rent'], 2) . "\n\n" .
                             "Rental Period:\n" .
                             "- Start Date: " . date('Y-m-d', strtotime($original_message['start_date'])) . "\n" .
                             "- End Date: " . date('Y-m-d', strtotime($original_message['end_date'])) . "\n\n" .
                             "Owner Details:\n" .
                             "- Name: {$original_message['owner_name']}\n" .
                             "- Phone: {$original_message['owner_mobile']}\n\n" .
                             "Customer Details:\n" .
                             "- Name: {$original_message['sender_name']}\n" .
                             "- Phone: {$original_message['sender_phone']}\n" .
                             "- Email: {$original_message['sender_email']}",
                    'flat_id' => $original_message['flat_id'],
                    'rent_id' => $original_message['rent_id']
                ]);
            }
        }
       
        // Create response message
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                receiver_user_id,
                sender_user_id,
                title,
                body,
                is_read,
                status,
                message_type,
                flat_id,
                rent_id,
                preview_request_id
            ) VALUES (
                :receiver_user_id,
                :sender_user_id,
                :title,
                :body,
                0,
                :status,
                :message_type,
                :flat_id,
                :rent_id,
                :preview_request_id
            )
        ");
        
        $message_type = $original_message['message_type'] === 'preview_request' ? 'preview_response' : 'rent_response';
        
        // Customize message based on type and action
        if ($message_type === 'preview_response' && $action === 'approved') {
            $title = "Preview Request Approved";
            $body = "Your preview request for flat {$original_message['ref_number']} has been approved.\n\n" .
                   "Appointment Details:\n" .
                   "- Date: " . date('Y-m-d', strtotime($original_message['requested_date'])) . "\n" .
                   "- Time: " . date('H:i', strtotime($original_message['requested_time'])) . "\n" .
                   "- Location: {$original_message['location']}\n\n" .
                   "Please arrive on time for your appointment.\n" .
                   "For any questions or to reschedule, contact the owner at: {$original_message['owner_mobile']}";
        } elseif ($message_type === 'preview_response' && $action === 'rejected') {
            $title = "Preview Request Rejected";
            $body = "Your preview request for flat {$original_message['ref_number']} has been rejected.\n\n" .
                   "Reason: " . $response_message . "\n\n" .
                   "You can submit a new request with a different date/time.";
        } elseif ($message_type === 'rent_response' && $action === 'approved') {
            $title = "Rent Request Approved";
            $body = "Congratulations! Your rent request for flat {$original_message['ref_number']} has been approved.\n\n" .
                   "Rental Details:\n" .
                   "- Start Date: " . date('Y-m-d', strtotime($original_message['start_date'])) . "\n" .
                   "- End Date: " . date('Y-m-d', strtotime($original_message['end_date'])) . "\n" .
                   "- Monthly Rent: $" . number_format($original_message['monthly_rent'], 2) . "\n\n" .
                   "Key Collection:\n" .
                   "- Location: {$original_message['location']}\n" .
                   "- Date: " . date('Y-m-d', strtotime($original_message['start_date'])) . "\n" .
                   "- Time: Between 9:00 AM and 5:00 PM\n\n" .
                   "Please contact the owner {$original_message['owner_name']} at {$original_message['owner_mobile']} " .
                   "to arrange a specific time for key collection.\n\n" .
                   $response_message;
        } else {
            $title = "Rent Request Rejected";
            $body = "Your rent request for flat {$original_message['ref_number']} has been rejected.\n\n" .
                   "Reason: " . $response_message . "\n\n" .
                   "You can submit a new request for a different period.";
        }
            
        $stmt->execute([
            'receiver_user_id' => $original_message['sender_user_id'],
            'sender_user_id' => $_SESSION['user_id'],
            'title' => $title,
            'body' => $body,
            'status' => $action,
            'message_type' => $message_type,
            'flat_id' => $original_message['flat_id'],
            'rent_id' => $original_message['rent_id'],
            'preview_request_id' => $original_message['preview_request_id']
        ]);
        
        $pdo->commit();
        header('Location: view_messages.php?success=1');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error processing request: " . $e->getMessage();
    }
}

// Handle sorting
$sort_column = $_GET['sort'] ?? (isset($_COOKIE['sort_column']) ? $_COOKIE['sort_column'] : 'sent_at');
$sort_order = $_GET['order'] ?? (isset($_COOKIE['sort_order']) ? $_COOKIE['sort_order'] : 'DESC');
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';
$next_order = $sort_order === 'ASC' ? 'DESC' : 'ASC';

// Save sorting preferences in cookies
setcookie('sort_column', $sort_column, time() + (7 * 24 * 60 * 60), '/');
setcookie('sort_order', $sort_order, time() + (7 * 24 * 60 * 60), '/');

$valid_columns = ['title', 'sent_at', 'sender_name', 'message_type', 'status'];
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
               END AS sender_email,
               f.ref_number,
               f.location,
               f.monthly_rent,
               r.start_date,
               r.end_date,
               r.total_amount,
               pr.requested_date,
               pr.requested_time,
               c.name as customer_name,
               c.mobile as customer_mobile,
               c.email as customer_email,
               o.name as owner_name,
               o.mobile as owner_mobile,
               o.email as owner_email
        FROM messages m
        LEFT JOIN users u ON m.sender_user_id = u.user_id
        LEFT JOIN customers c ON u.user_id = c.user_id AND u.role = 'customer'
        LEFT JOIN owners o ON u.user_id = o.user_id AND u.role = 'owner'
        LEFT JOIN flats f ON m.flat_id = f.flat_id
        LEFT JOIN rents r ON m.rent_id = r.rent_id
        LEFT JOIN preview_requests pr ON m.preview_request_id = pr.id
        WHERE m.receiver_user_id = :receiver_user_id
    ";
    
    // Role-specific filtering
    if ($_SESSION['role'] === 'manager') {
        $query .= " AND m.message_type IN ('flat_approval', 'rent_notification')";
    } elseif ($_SESSION['role'] === 'owner') {
        $query .= " AND m.message_type IN ('preview_request', 'rent_request')";
    } elseif ($_SESSION['role'] === 'customer') {
        $query .= " AND m.message_type IN ('preview_response', 'rent_response', 'preview_request', 'rent_request', 'rent_confirmation')";
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

// Get selected message if any
$selected_message = null;
if (isset($_GET['message_id'])) {
    foreach ($messages as $message) {
        if ($message['message_id'] == $_GET['message_id']) {
            $selected_message = $message;
            break;
        }
    }
}
?>

<main>
    <h2>Messages</h2>
    <?php if (isset($error)): ?>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <p class="success-message">Message processed successfully.</p>
    <?php endif; ?>
    
    <?php if (empty($messages)): ?>
        <p>No messages found.</p>
    <?php else: ?>
        <div class="messages-table-container">
            <table class="messages-table">
                <thead>
                    <tr>
                        <th>
                            <a href="?sort=title&order=<?php echo $sort_column === 'title' ? $next_order : 'DESC'; ?>" class="sort-link">
                                Title
                                <?php if ($sort_column === 'title'): ?>
                                    <?php echo $sort_order === 'ASC' ? '↑' : '↓'; ?>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=sender_name&order=<?php echo $sort_column === 'sender_name' ? $next_order : 'DESC'; ?>" class="sort-link">
                                From
                                <?php if ($sort_column === 'sender_name'): ?>
                                    <?php echo $sort_order === 'ASC' ? '↑' : '↓'; ?>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=message_type&order=<?php echo $sort_column === 'message_type' ? $next_order : 'DESC'; ?>" class="sort-link">
                                Type
                                <?php if ($sort_column === 'message_type'): ?>
                                    <?php echo $sort_order === 'ASC' ? '↑' : '↓'; ?>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=status&order=<?php echo $sort_column === 'status' ? $next_order : 'DESC'; ?>" class="sort-link">
                                Status
                                <?php if ($sort_column === 'status'): ?>
                                    <?php echo $sort_order === 'ASC' ? '↑' : '↓'; ?>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort=sent_at&order=<?php echo $sort_column === 'sent_at' ? $next_order : 'DESC'; ?>" class="sort-link">
                                Date
                                <?php if ($sort_column === 'sent_at'): ?>
                                    <?php echo $sort_order === 'ASC' ? '↑' : '↓'; ?>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message): ?>
                        <tr class="<?php echo $message['is_read'] ? 'read' : 'unread'; ?> <?php echo $message['status']; ?>">
                            <td>
                                <?php if (!$message['is_read']): ?>
                                    <span class="unread-icon">🔔</span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($message['title']); ?>
                            </td>
                            <td>
                                <a href="user_card.php?email=<?php echo urlencode($message['sender_email']); ?>" 
                                   class="user-link" 
                                   target="_blank">
                                    <?php echo htmlspecialchars($message['sender_name']); ?>
                                </a>
                            </td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $message['message_type'])); ?></td>
                            <td><?php echo ucfirst($message['status']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($message['sent_at'])); ?></td>
                            <td>
                                <a href="?message_id=<?php echo $message['message_id']; ?>" class="btn btn-primary">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($selected_message): ?>
            <div class="message-details">
                <div class="message-card <?php echo $selected_message['is_read'] ? 'read' : 'unread'; ?> <?php echo $selected_message['status']; ?>">
                    <div class="message-header">
                        <h3>
                            <?php if (!$selected_message['is_read']): ?>
                                <span class="unread-icon">🔔</span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($selected_message['title']); ?>
                        </h3>
                        <div class="message-meta">
                            <span class="date"><?php echo date('Y-m-d H:i', strtotime($selected_message['sent_at'])); ?></span>
                            <span class="sender">
                                From: 
                                <a href="user_card.php?email=<?php echo urlencode($selected_message['sender_email']); ?>" 
                                   class="user-link" 
                                   target="_blank">
                                    <?php echo htmlspecialchars($selected_message['sender_name']); ?>
                                </a>
                            </span>
                        </div>
                    </div>
                    
                    <div class="message-content">
                        <?php if ($selected_message['ref_number']): ?>
                            <div class="flat-info">
                                <h4>Flat Details</h4>
                                <p><strong>Reference:</strong> 
                                    <a href="flat_detail.php?id=<?php echo $selected_message['flat_id']; ?>" target="_blank">
                                        <?php echo htmlspecialchars($selected_message['ref_number']); ?>
                                    </a>
                                </p>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($selected_message['location']); ?></p>
                                <?php if ($selected_message['monthly_rent']): ?>
                                    <p><strong>Monthly Rent:</strong> $<?php echo number_format($selected_message['monthly_rent'], 2); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($selected_message['requested_date'] && $selected_message['requested_time']): ?>
                            <div class="preview-info">
                                <h4>Preview Details</h4>
                                <p><strong>Date:</strong> <?php echo date('Y-m-d', strtotime($selected_message['requested_date'])); ?></p>
                                <p><strong>Time:</strong> <?php echo date('H:i', strtotime($selected_message['requested_time'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($selected_message['start_date'] && $selected_message['end_date']): ?>
                            <div class="rent-info">
                                <h4>Rental Details</h4>
                                <p><strong>Period:</strong> 
                                    <?php echo date('Y-m-d', strtotime($selected_message['start_date'])); ?> to 
                                    <?php echo date('Y-m-d', strtotime($selected_message['end_date'])); ?>
                                </p>
                                <?php if ($selected_message['total_amount']): ?>
                                    <p><strong>Total Amount:</strong> $<?php echo number_format($selected_message['total_amount'], 2); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($_SESSION['role'] === 'manager'): ?>
                            <div class="user-details">
                                <h4>Customer Details</h4>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($selected_message['customer_name']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($selected_message['customer_mobile']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($selected_message['customer_email']); ?></p>
                                
                                <h4>Owner Details</h4>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($selected_message['owner_name']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($selected_message['owner_mobile']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($selected_message['owner_email']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($selected_message['sender_phone']): ?>
                            <div class="contact-info">
                                <h4>Contact Information</h4>
                                <p><strong>Phone:</strong> 
                                    <a href="tel:<?php echo htmlspecialchars($selected_message['sender_phone']); ?>">
                                        <?php echo htmlspecialchars($selected_message['sender_phone']); ?>
                                    </a>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="message-body">
                            <?php echo nl2br(htmlspecialchars($selected_message['body'])); ?>
                        </div>
                        
                        <?php if ($_SESSION['role'] === 'owner' && $selected_message['status'] === 'pending' && 
                                 ($selected_message['message_type'] === 'preview_request' || $selected_message['message_type'] === 'rent_request')): ?>
                            <div class="message-actions">
                                <form method="POST" class="response-form">
                                    <input type="hidden" name="message_id" value="<?php echo $selected_message['message_id']; ?>">
                                    <textarea name="response_message" placeholder="Enter your response message..." required></textarea>
                                    <div class="action-buttons">
                                        <button type="submit" name="action" value="approved" class="btn btn-success">
                                            Approve
                                        </button>
                                        <button type="submit" name="action" value="rejected" class="btn btn-danger">
                                            Reject
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>