<?php
require_once '../config.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tickets.php');
}

$ticket_id = intval($_POST['ticket_id'] ?? 0);

if (!$ticket_id) {
    redirect('tickets.php');
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT image_path FROM tickets WHERE id = ?");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    redirect('tickets.php');
}

$ticket = $result->fetch_assoc();
$stmt->close();

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

$stmt = $conn->prepare("DELETE FROM tickets WHERE id = ?");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$stmt->close();
$conn->close();

$_SESSION['ticket_deleted'] = 'ลบ Ticket สำเร็จ';
redirect('tickets.php');
?>
