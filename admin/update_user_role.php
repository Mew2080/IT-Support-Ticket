<?php
require_once '../config.php';

requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_email = trim($_POST['user_email'] ?? '');
$new_role = trim($_POST['role'] ?? '');

if (empty($user_email)) {
    echo json_encode(['success' => false, 'message' => 'กรุณาระบุอีเมล']);
    exit;
}

if (!in_array($new_role, ['admin', 'user'])) {
    echo json_encode(['success' => false, 'message' => 'Role ไม่ถูกต้อง']);
    exit;
}

$current_user_email = getCurrentUserEmail();

// Prevent user from changing their own role
if ($user_email === $current_user_email) {
    echo json_encode(['success' => false, 'message' => 'ไม่สามารถเปลี่ยนสิทธิ์ของบัญชีตัวเองได้']);
    exit;
}

$conn = getDBConnection();

// Check if user exists
$stmt = $conn->prepare("SELECT email, role FROM users WHERE email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'ไม่พบผู้ใช้']);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Check if this is the last admin (prevent removing last admin)
if ($user['role'] === 'admin' && $new_role === 'user') {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    $admin_count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    if ($admin_count <= 1) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบสิทธิ์ Admin คนสุดท้ายได้']);
        exit;
    }
}

// Update role
$stmt = $conn->prepare("UPDATE users SET role = ? WHERE email = ?");
$stmt->bind_param("ss", $new_role, $user_email);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    echo json_encode([
        'success' => true,
        'message' => 'อัพเดทสิทธิ์สำเร็จ',
        'role' => $new_role
    ]);
} else {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัพเดทสิทธิ์: ' . $conn->error]);
}
?>

