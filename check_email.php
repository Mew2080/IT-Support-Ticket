<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['exists' => false, 'error' => 'Invalid request method']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['exists' => false]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['exists' => false, 'invalid' => true]);
    exit;
}

$conn = getDBConnection();

// Check if email exists (case-insensitive)
$stmt = $conn->prepare("SELECT email FROM users WHERE LOWER(email) = LOWER(?)");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$exists = $result->num_rows > 0;

$stmt->close();
$conn->close();

echo json_encode(['exists' => $exists]);
?>

