<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_email = getCurrentUserEmail();
if (!$user_email) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้']);
    exit;
}

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_new_password'] ?? '';

if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'รหัสผ่านใหม่ไม่ตรงกัน']);
    exit;
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT password FROM users WHERE email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้']);
    exit;
}

if (!password_verify($current_password, $user['password'])) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง']);
    exit;
}

$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $hashed_password, $user_email);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'
    ]);
} else {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัพเดทรหัสผ่าน']);
}
?>
