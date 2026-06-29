<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$ticket_id = intval($_GET['ticket_id'] ?? 0);
$last_reply_id = intval($_GET['last_reply_id'] ?? 0);

if (!$ticket_id) {
    echo json_encode(['error' => 'Invalid ticket ID']);
    exit;
}

$user_email = getCurrentUserEmail();
$conn = getDBConnection();

// Check if tickets table has user_email column
$user_email_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'user_email'");
$has_user_email = ($user_email_check && $user_email_check->num_rows > 0);

if (!isAdmin()) {
    if ($has_user_email) {
        $stmt = $conn->prepare("SELECT id FROM tickets WHERE id = ? AND user_email = ?");
        $stmt->bind_param("is", $ticket_id, $user_email);
    } else {
        $stmt = $conn->prepare("SELECT id FROM tickets WHERE id = ? AND user_id = (SELECT id FROM users WHERE email = ?)");
        $stmt->bind_param("is", $ticket_id, $user_email);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    $stmt->close();
}

// Check if ticket_replies table has user_email column
$reply_user_email_check = $conn->query("SHOW COLUMNS FROM ticket_replies LIKE 'user_email'");
$has_reply_user_email = ($reply_user_email_check && $reply_user_email_check->num_rows > 0);

// Check if users table has department column
$dept_check = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
$has_department = ($dept_check && $dept_check->num_rows > 0);

if ($has_reply_user_email) {
    if ($has_department) {
        $stmt = $conn->prepare("SELECT tr.*, u.email, u.full_name, u.role, u.department 
                                FROM ticket_replies tr 
                                JOIN users u ON tr.user_email = u.email 
                                WHERE tr.ticket_id = ? AND tr.id > ?
                                ORDER BY tr.created_at ASC");
    } else {
        $stmt = $conn->prepare("SELECT tr.*, u.email, u.full_name, u.role 
                                FROM ticket_replies tr 
                                JOIN users u ON tr.user_email = u.email 
                                WHERE tr.ticket_id = ? AND tr.id > ?
                                ORDER BY tr.created_at ASC");
    }
    $stmt->bind_param("ii", $ticket_id, $last_reply_id);
} else {
    if ($has_department) {
        $stmt = $conn->prepare("SELECT tr.*, u.email, u.full_name, u.role, u.department 
                                FROM ticket_replies tr 
                                JOIN users u ON tr.user_id = u.id 
                                WHERE tr.ticket_id = ? AND tr.id > ?
                                ORDER BY tr.created_at ASC");
    } else {
        $stmt = $conn->prepare("SELECT tr.*, u.email, u.full_name, u.role 
                                FROM ticket_replies tr 
                                JOIN users u ON tr.user_id = u.id 
                                WHERE tr.ticket_id = ? AND tr.id > ?
                                ORDER BY tr.created_at ASC");
    }
    $stmt->bind_param("ii", $ticket_id, $last_reply_id);
}
$stmt->execute();
$result = $stmt->get_result();
$replies = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT status, updated_at FROM tickets WHERE id = ?");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();
$ticket = $result->fetch_assoc();
$stmt->close();

$conn->close();

echo json_encode([
    'success' => true,
    'replies' => $replies,
    'ticket_status' => $ticket['status'] ?? null,
    'ticket_updated_at' => $ticket['updated_at'] ?? null
]);
?>
