<?php
require_once '../config.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('users.php');
}

$user_email = trim($_POST['user_email'] ?? '');
$current_user_email = getCurrentUserEmail();

if (empty($user_email)) {
    redirect('users.php');
}

if ($user_email == $current_user_email) {
    $_SESSION['user_deleted'] = 'ไม่สามารถลบบัญชีของคุณเองได้';
    redirect('users.php');
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT email, role FROM users WHERE email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    redirect('users.php');
}

$user = $result->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT id, image_path FROM tickets WHERE user_email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($tickets as $ticket) {
    $ticket_id = $ticket['id'];
    

    $stmt = $conn->prepare("SELECT image_path FROM ticket_replies WHERE ticket_id = ?");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $replies = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    

    foreach ($replies as $reply) {
        if (!empty($reply['image_path']) && file_exists(__DIR__ . '/../' . $reply['image_path'])) {
            unlink(__DIR__ . '/../' . $reply['image_path']);
        }
    }
    

    if (!empty($ticket['image_path']) && file_exists(__DIR__ . '/../' . $ticket['image_path'])) {
        unlink(__DIR__ . '/../' . $ticket['image_path']);
    }
    

    $stmt = $conn->prepare("DELETE FROM ticket_replies WHERE ticket_id = ?");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $stmt->close();
}

$stmt = $conn->prepare("DELETE FROM tickets WHERE user_email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("DELETE FROM users WHERE email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$stmt->close();
$conn->close();

$_SESSION['user_deleted'] = 'ลบ User "' . htmlspecialchars($user['email']) . '" สำเร็จ';
redirect('users.php');
?>
