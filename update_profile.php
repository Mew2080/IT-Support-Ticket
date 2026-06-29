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

$email = trim($_POST['email'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
// Remove dashes from phone number for storage
$phone_clean = preg_replace('/[^0-9]/', '', $phone);
// Department cannot be changed after registration

// Validate phone number if provided
if (!empty($phone_clean) && !preg_match('/^[0-9]{9,10}$/', $phone_clean)) {
    echo json_encode(['success' => false, 'message' => 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลัก']);
    exit;
}

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกอีเมล']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
    exit;
}

if (!empty($full_name) && !preg_match('/^[\p{L}\p{M}\s]+$/u', $full_name)) {
    echo json_encode(['success' => false, 'message' => 'ชื่อ-นามสกุลต้องเป็นตัวอักษรเท่านั้น (รองรับทั้งภาษาไทยและอังกฤษ)']);
    exit;
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT email FROM users WHERE LOWER(email) = LOWER(?) AND LOWER(email) != LOWER(?)");
$stmt->bind_param("ss", $email, $user_email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'อีเมลนี้ถูกใช้งานแล้ว']);
    exit;
}
$stmt->close();

// Check if department column exists
$dept_check = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
$has_department = ($dept_check && $dept_check->num_rows > 0);

// Get current user data to preserve existing values if new values are empty
if ($has_department) {
    $stmt_current = $conn->prepare("SELECT full_name, phone FROM users WHERE email = ?");
} else {
    $stmt_current = $conn->prepare("SELECT full_name, phone FROM users WHERE email = ?");
}
$stmt_current->bind_param("s", $user_email);
$stmt_current->execute();
$current_result = $stmt_current->get_result();
$current_user = $current_result->fetch_assoc();
$stmt_current->close();

// Preserve existing values if new values are empty
if (empty($full_name) && !empty($current_user['full_name'])) {
    $full_name = $current_user['full_name'];
}
if (empty($phone_clean) && !empty($current_user['phone'])) {
    $phone_clean = $current_user['phone'];
}

// Department cannot be changed after registration, so we don't update it
$stmt = $conn->prepare("UPDATE users SET email = ?, full_name = ?, phone = ? WHERE email = ?");
$stmt->bind_param("ssss", $email, $full_name, $phone_clean, $user_email);

if ($stmt->execute()) {

    if ($has_department) {
        $stmt2 = $conn->prepare("SELECT email, full_name, phone, department, created_at, role FROM users WHERE email = ?");
    } else {
        $stmt2 = $conn->prepare("SELECT email, full_name, phone, created_at, role FROM users WHERE email = ?");
    }
    $stmt2->bind_param("s", $email);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $updated_user = $result2->fetch_assoc();
    $stmt2->close();
    
    $stmt->close();
    $conn->close();
    
    // Format phone number for display
    $phone_display = $updated_user['phone'] ?: '';
    if ($phone_display) {
        $phone_digits = preg_replace('/[^0-9]/', '', $phone_display);
        if (strlen($phone_digits) === 10) {
            $phone_display = substr($phone_digits, 0, 3) . '-' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6);
        } elseif (strlen($phone_digits) === 9) {
            $phone_display = substr($phone_digits, 0, 3) . '-' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'อัพเดทข้อมูลสำเร็จ',
        'user' => [
            'email' => $updated_user['email'],
            'full_name' => $updated_user['full_name'] ?: '',
            'phone' => $phone_display,
            'department' => $has_department ? ($updated_user['department'] ?? '') : ''
        ]
    ]);
} else {
    $error_message = $conn->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัพเดทข้อมูล: ' . htmlspecialchars($error_message)]);
}
?>
