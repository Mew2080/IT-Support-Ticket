<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Redirect admin to admin dashboard
if (isAdmin()) {
    redirect('admin/tickets.php');
}

$user_email = getCurrentUserEmail();
$conn = getDBConnection();
// Check if department column exists
$dept_check = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
$has_department = ($dept_check && $dept_check->num_rows > 0);

if ($has_department) {
    $stmt = $conn->prepare("SELECT email, full_name, phone, department, created_at, role FROM users WHERE email = ?");
} else {
    $stmt = $conn->prepare("SELECT email, full_name, phone, created_at, role FROM users WHERE email = ?");
}
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Check if tickets table has user_email column
$column_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'user_email'");
$has_user_email = ($column_check && $column_check->num_rows > 0);

if ($has_user_email) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE user_email = ?");
    $stmt->bind_param("s", $user_email);
} else {
    // Fallback to user_id if user_email column doesn't exist
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE user_id = (SELECT id FROM users WHERE email = ?)");
    $stmt->bind_param("s", $user_email);
}
$stmt->execute();
$ticket_result = $stmt->get_result();
$ticket_count = $ticket_result->fetch_assoc()['count'];
$stmt->close();

$open_tickets_count = 0;
if (isAdmin()) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tickets WHERE status = 'open'");
    $stmt->execute();
    $open_result = $stmt->get_result();
    $open_tickets_count = $open_result->fetch_assoc()['count'];
    $stmt->close();
}

$column_exists = false;
$check_result = $conn->query("SHOW COLUMNS FROM tickets LIKE 'resolved_by'");
if ($check_result && $check_result->num_rows > 0) {
    $column_exists = true;
}

// Check if tickets table has user_email column
$user_email_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'user_email'");
$has_user_email = ($user_email_check && $user_email_check->num_rows > 0);

if ($column_exists && $has_user_email) {
    $stmt = $conn->prepare("SELECT t.id, t.subject, t.description, t.status, t.priority, t.image_path, t.created_at, t.updated_at, t.resolved_by,
                            r.email as resolved_by_email, r.full_name as resolved_by_full_name
                            FROM tickets t
                            LEFT JOIN users r ON t.resolved_by = r.email
                            WHERE t.user_email = ? AND t.status != 'closed' ORDER BY t.updated_at DESC, t.created_at DESC LIMIT 10");
    $stmt->bind_param("s", $user_email);
} elseif ($column_exists && !$has_user_email) {
    $stmt = $conn->prepare("SELECT t.id, t.subject, t.description, t.status, t.priority, t.image_path, t.created_at, t.updated_at, t.resolved_by,
                            r.email as resolved_by_email, r.full_name as resolved_by_full_name
                            FROM tickets t
                            LEFT JOIN users r ON t.resolved_by = r.id
                            WHERE t.user_id = (SELECT id FROM users WHERE email = ?) AND t.status != 'closed' ORDER BY t.updated_at DESC, t.created_at DESC LIMIT 10");
    $stmt->bind_param("s", $user_email);
} elseif (!$column_exists && $has_user_email) {
    $stmt = $conn->prepare("SELECT id, subject, description, status, priority, image_path, created_at, updated_at FROM tickets WHERE user_email = ? AND status != 'closed' ORDER BY updated_at DESC, created_at DESC LIMIT 10");
    $stmt->bind_param("s", $user_email);
} else {
    $stmt = $conn->prepare("SELECT id, subject, description, status, priority, image_path, created_at, updated_at FROM tickets WHERE user_id = (SELECT id FROM users WHERE email = ?) AND status != 'closed' ORDER BY updated_at DESC, created_at DESC LIMIT 10");
    $stmt->bind_param("s", $user_email);
}
$stmt->execute();
$tickets_result = $stmt->get_result();
$recent_tickets = $tickets_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$status_labels = [
    'open' => 'เปิด',
    'in_progress' => 'กำลังดำเนินการ',
    'closed' => 'ปิด'
];

$priority_labels = [
    'normal' => 'ทั่วไป',
    'urgent' => 'ด่วน',
    'critical' => 'ด่วนมาก',

    'low' => 'ทั่วไป',
    'medium' => 'ทั่วไป',
    'high' => 'ด่วน'
];

$priority_colors = [
    'normal' => '#54c5f8', // light blue for general
    'urgent' => '#f59e0b',
    'critical' => '#ef4444',

    'low' => '#54c5f8',
    'medium' => '#54c5f8',
    'high' => '#f59e0b'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SERVICE ENGINEERING</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="img/logo2.png"> 
    <link rel="shortcut icon" type="image/png" href="img/logo2.png">
    <link rel="stylesheet" href="<?php echo getAssetUrl('assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>หน้าแรก</h1>
            <div class="user-info">
                <span id="header-user-display"><?php 
                    $displayName = 'User';
                    if ($user && isset($user['full_name']) && $user['full_name']) {
                        $displayName = $user['full_name'];
                    } elseif ($user && isset($user['email']) && $user['email']) {
                        $displayName = $user['email'];
                    } elseif ($user_email) {
                        $displayName = $user_email;
                    }
                    echo htmlspecialchars($displayName);
                ?></span>
                <?php if (isAdmin()): ?>
                    <a href="profile.php" class="user-role-badge role-admin" aria-label="Admin">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2l7 4v6c0 5-3.5 9.1-7 10-3.5-.9-7-5-7-10V6l7-4z"></path>
                        </svg>
                    </a>
                <?php else: ?>
                    <a href="profile.php" class="user-role-badge role-user" aria-label="User">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 12c-2.2 0-4-1.8-4-4s1.8-4 4-4 4 1.8 4 4-1.8 4-4 4zm0 2c3.3 0 6 2.7 6 6H6c0-3.3 2.7-6 6-6z"></path>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        </header>
        
        <main class="dashboard-content">
            <?php if (isset($_SESSION['ticket_success'])): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <?php 
                    echo htmlspecialchars($_SESSION['ticket_success']); 
                    unset($_SESSION['ticket_success']);
                    ?>
                </div>
            <?php endif; ?>
            <div class="dashboard-grid">
                <div>
                    <div class="action-card">
                        <h2>Tickets</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Tickets ของฉัน:</label>
                                <span><?php echo $ticket_count; ?> tickets</span>
                            </div>
                        </div>
                        <div class="action-buttons" style="margin-top: 20px;">
                            <a href="create_ticket.php" class="btn btn-primary">เปิด Ticket ใหม่</a>
                            <a href="my_tickets.php" class="btn btn-secondary">Tickets ของฉัน</a>
                            <button type="button" class="btn btn-primary" onclick="openTroubleshootingModal()" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">FQA วิธีแก้ไขปัญหาที่พบได้บ่อย</button>
                        </div>
                        
                    </div>
                    
                    
                    <?php if (!empty($recent_tickets)): ?>
                        <div class="action-card" style="margin-top: 18px;">
                            <h2>Tickets ที่เคยเปิด (ล่าสุด)</h2>
                            <div class="tickets-list" id="recentTicketsList">
                                <?php foreach ($recent_tickets as $ticket): ?>
                                    <div class="ticket-item">
                                        <div class="ticket-header">
                                            <div class="ticket-subject">
                                                <h3>#<?php echo $ticket['id']; ?> - <?php echo htmlspecialchars($ticket['subject']); ?></h3>
                                            </div>
                                            <div class="ticket-badges">
                                                <span class="badge badge-priority" style="background-color: <?php echo $priority_colors[$ticket['priority']]; ?>">
                                                    <?php echo $priority_labels[$ticket['priority']]; ?>
                                                </span>
                                                <span class="badge badge-status badge-<?php echo $ticket['status']; ?>">
                                                    <?php echo $status_labels[$ticket['status']]; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ticket-description">
                                            <p><?php echo nl2br(htmlspecialchars(substr($ticket['description'], 0, 150))); ?><?php echo strlen($ticket['description']) > 150 ? '...' : ''; ?></p>
                                        </div>
                                        <div class="ticket-footer">
                                            <span class="ticket-date">สร้าง: <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></span>
                                            <?php if ($ticket['updated_at'] != $ticket['created_at']): ?>
                                                <span class="ticket-date">อัปเดต: <?php echo date('d/m/Y H:i', strtotime($ticket['updated_at'])); ?></span>
                                            <?php endif; ?>
                                            <?php if ($ticket['status'] == 'closed' && !empty($ticket['resolved_by']) && isset($ticket['resolved_by_email'])): ?>
                                                <span class="ticket-date" style="color: var(--primary-color); font-weight: 600;">
                                                    แก้ไขโดย: <?php echo htmlspecialchars($ticket['resolved_by_full_name'] ?? $ticket['resolved_by_email'] ?? 'N/A'); ?> (Admin)
                                                </span>
                                            <?php endif; ?>
                                            <a href="ticket_detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">ดูรายละเอียด</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($ticket_count > 10): ?>
                                <div style="margin-top: 15px; text-align: center;">
                                    <a href="my_tickets.php" class="btn btn-secondary">ดู Tickets ทั้งหมด (<?php echo $ticket_count; ?> tickets)</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="action-card" style="margin-top: 18px;">
                            <h2>Tickets ที่เคยเปิด</h2>
                            <p style="text-align: center; color: #666; font-size: 0.9em; padding: 20px 0;">
                                ยังไม่มี Tickets<br>
                                <a href="create_ticket.php" style="color: var(--primary-color); text-decoration: none; font-weight: 600;">เปิด Ticket ใหม่</a>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        
        
        <div id="userInfoModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>ข้อมูลผู้ใช้</h2>
                    <button type="button" class="modal-close" onclick="closeUserInfoModal()">&times;</button>
                </div>
                <div class="modal-body">
                    
                    <div id="userInfoView">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>อีเมล:</label>
                                <span id="view-email"><?php echo htmlspecialchars($user['email'] ?? $user_email ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>ชื่อ-นามสกุล:</label>
                                <span id="view-fullname"><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>เบอร์โทรศัพท์:</label>
                                <span id="view-phone"><?php 
                                    $phone_display = ($user && isset($user['phone'])) ? $user['phone'] : '-';
                                    if ($phone_display !== '-') {
                                        $phone_digits = preg_replace('/[^0-9]/', '', $phone_display);
                                        if (strlen($phone_digits) === 10) {
                                            $phone_display = substr($phone_digits, 0, 3) . '-' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6);
                                        } elseif (strlen($phone_digits) === 9) {
                                            $phone_display = substr($phone_digits, 0, 3) . '-' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6);
                                        }
                                    }
                                    echo htmlspecialchars($phone_display); 
                                ?></span>
                            </div>
                            <?php if ($has_department && $user && isset($user['department'])): ?>
                            <div class="info-item">
                                <label>แผนก:</label>
                                <span id="view-department"><?php echo htmlspecialchars($user['department'] ?: '-'); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <label>วันที่สมัคร:</label>
                                <span><?php echo ($user && isset($user['created_at'])) ? date('d/m/Y H:i', strtotime($user['created_at'])) : 'N/A'; ?></span>
                            </div>
                            <?php if (isAdmin()): ?>
                                <div class="info-item">
                                    <label>สิทธิ์:</label>
                                    <span style="color: var(--warning-color); font-weight: bold;">ผู้ดูแลระบบ (Admin)</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    
                    <div id="userInfoEdit" style="display: none;">
                        <form id="editUserForm" onsubmit="updateUserProfile(event)">
                            <div class="form-group">
                                <label for="edit-email">อีเมล *</label>
                                <input type="email" id="edit-email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? $user_email ?? ''); ?>" required class="form-group input" readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                                <small id="edit-email-check-message" style="display: none; font-weight: 500;"></small>
                                <small style="color: #666; font-size: 0.85em;">กรุณากรอกอีเมลที่ถูกต้อง</small>
                            </div>
                            <div class="form-group">
                                <label for="edit-fullname">ชื่อ-นามสกุล</label>
                                <input type="text" id="edit-fullname" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                                       class="form-group input" 
                                       placeholder="เช่น สมชาย ใจดี หรือ John Smith">
                                <small style="color: #666; font-size: 0.85em;">กรุณากรอกชื่อ-นามสกุล (รองรับทั้งภาษาไทยและอังกฤษ)</small>
                            </div>
                            <div class="form-group">
                                <label for="edit-phone">เบอร์โทรศัพท์</label>
                                <input type="tel" id="edit-phone" name="phone" value="<?php 
                                    $phone_edit = ($user && isset($user['phone'])) ? $user['phone'] : '';
                                    if ($phone_edit) {
                                        $phone_digits = preg_replace('/[^0-9]/', '', $phone_edit);
                                        if (strlen($phone_digits) === 10) {
                                            $phone_edit = substr($phone_digits, 0, 3) . '-' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6);
                                        } elseif (strlen($phone_digits) === 9) {
                                            $phone_edit = substr($phone_digits, 0, 3) . '-' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6);
                                        }
                                    }
                                    echo htmlspecialchars($phone_edit); 
                                ?>" 
                                       pattern="[0-9]{9,10}" 
                                       class="form-group input" 
                                       placeholder="เช่น 081-234-5678">
                                <small style="color: #666; font-size: 0.85em;">ไม่บังคับ (กรอกเฉพาะตัวเลข 9-10 หลัก)</small>
                            </div>
                            <?php if ($has_department && $user && isset($user['department']) && !empty($user['department'])): ?>
                            <div class="form-group">
                                <label>แผนก</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['department']); ?>" class="form-group input" readonly style="background-color: #f3f4f6; cursor: not-allowed;">
                                <small style="color: #666; font-size: 0.85em;">แผนกสามารถเลือกได้เฉพาะตอนสมัครสมาชิกเท่านั้น</small>
                            </div>
                            <?php endif; ?>
                            <div id="edit-error-message" style="display: none; color: var(--error-color); margin-top: 10px; padding: 10px; background: rgba(239, 68, 68, 0.1); border-radius: 6px; border: 1px solid var(--error-color);"></div>
                            <div id="edit-success-message" style="display: none; color: var(--success-color); margin-top: 10px; padding: 10px; background: rgba(16, 185, 129, 0.1); border-radius: 6px; border: 1px solid var(--success-color);"></div>
                        </form>
                    </div>
                    
                    
                    <div id="changePasswordMode" style="display: none;">
                        <form id="changePasswordForm" onsubmit="updatePassword(event)">
                            <div class="form-group">
                                <label for="current-password">รหัสผ่านปัจจุบัน *</label>
                                <div class="password-toggle-wrapper">
                                    <input type="password" id="current-password" name="current_password" required class="form-group input">
                                    <button type="button" class="password-toggle-btn" onclick="togglePassword('current-password')" aria-label="แสดงรหัสผ่าน">
                                        <span id="current-password-toggle-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>
                                    </button>
                                </div>
                                <small style="color: #666; font-size: 0.85em;">กรุณากรอกรหัสผ่านปัจจุบัน</small>
                            </div>
                            <div class="form-group">
                                <label for="new-password">รหัสผ่านใหม่ *</label>
                                <div class="password-toggle-wrapper">
                                    <input type="password" id="new-password" name="new_password" required minlength="6" class="form-group input">
                                    <button type="button" class="password-toggle-btn" onclick="togglePassword('new-password')" aria-label="แสดงรหัสผ่าน">
                                        <span id="new-password-toggle-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>
                                    </button>
                                </div>
                                <small style="color: #666; font-size: 0.85em;">รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร</small>
                            </div>
                            <div class="form-group">
                                <label for="confirm-new-password">ยืนยันรหัสผ่านใหม่ *</label>
                                <div class="password-toggle-wrapper">
                                    <input type="password" id="confirm-new-password" name="confirm_new_password" required minlength="6" class="form-group input">
                                    <button type="button" class="password-toggle-btn" onclick="togglePassword('confirm-new-password')" aria-label="แสดงรหัสผ่าน">
                                        <span id="confirm-new-password-toggle-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>
                                    </button>
                                </div>
                                <small id="password-match-message" style="display: none; color: var(--error-color); font-weight: 500;"></small>
                            </div>
                            <div id="password-error-message" style="display: none; color: var(--error-color); margin-top: 10px; padding: 10px; background: rgba(239, 68, 68, 0.1); border-radius: 6px; border: 1px solid var(--error-color);"></div>
                            <div id="password-success-message" style="display: none; color: var(--success-color); margin-top: 10px; padding: 10px; background: rgba(16, 185, 129, 0.1); border-radius: 6px; border: 1px solid var(--success-color);"></div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="viewModeButtons" style="display: flex; gap: 10px; width: 100%; justify-content: flex-end; flex-wrap: wrap;">
                        <button type="button" class="btn btn-primary" onclick="toggleEditMode()" style="flex-shrink: 0; white-space: nowrap;">แก้ไขข้อมูล</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleChangePasswordMode()" style="background: white; color: #1f2937; border: 1px solid rgba(0, 0, 0, 0.1); flex-shrink: 0; white-space: nowrap;">เปลี่ยนรหัสผ่าน</button>
                        <button type="button" class="btn btn-secondary" onclick="closeUserInfoModal()" style="flex-shrink: 0; white-space: nowrap;">ปิด</button>
                    </div>
                    <div id="editModeButtons" style="display: none;">
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('editUserForm').dispatchEvent(new Event('submit'))">บันทึก</button>
                        <button type="button" class="btn btn-secondary" onclick="cancelEditMode()">ยกเลิก</button>
                    </div>
                    <div id="changePasswordModeButtons" style="display: none;">
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('changePasswordForm').dispatchEvent(new Event('submit'))">บันทึก</button>
                        <button type="button" class="btn btn-secondary" onclick="cancelChangePasswordMode()">ยกเลิก</button>
                    </div>
                </div>
            </div>
        </div>
        
        
        <div id="troubleshootingModal" class="modal">
            <div class="modal-content" style="max-width: 800px;">
                <div class="modal-header">
                    <h2>
                    FQA วิธีแก้ไขปัญหาที่พบได้บ่อย</h2>
                    <button type="button" class="modal-close" onclick="closeTroubleshootingModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="troubleshooting-content">
                        
                        <div class="troubleshooting-section">
                            <div class="troubleshooting-header">
                                <h3 class="troubleshooting-title">NETWORK</h3>
                                <span class="troubleshooting-icon">▼</span>
                            </div>
                            <div class="troubleshooting-content-inner">
                                <ul class="troubleshooting-list">
                                    <li class="troubleshooting-item" onclick="showTroubleshootingDetail('internet')">
                                        <span>ไม่สามารถเชื่อมต่ออินเทอร์เน็ต</span>
                                        <div id="detail-internet" class="troubleshooting-detail" style="display: none;">
                                            <h4>ไม่สามารถเชื่อมต่ออินเทอร์เน็ต</h4>
                                            <div class="detail-section">
                                                <strong>สาเหตุที่เป็นไปได้:</strong>
                                                <ul>
                                                    <li>สาย LAN หลุดหรือเสียบไม่แน่น</li>
                                                    <li>Router หรือ Modem ปิดหรือเสีย</li>
                                                    <li>การ์ด LAN เสียหรือไดรเวอร์เสีย</li>
                                                    <li>ตั้งค่า IP Address ไม่ถูกต้อง</li>
                                                    <li>DNS Server ไม่ทำงาน</li>
                                                    <li>Firewall หรือ Antivirus บล็อกการเชื่อมต่อ</li>
                                                </ul>
                                            </div>
                                            <div class="detail-section">
                                                <strong>วิธีแก้ไข:</strong>
                                                <ul>
                                                    <li>ตรวจสอบสาย LAN ว่าต่อแน่นทั้งสองด้าน (คอมพิวเตอร์และ Router/Modem)</li>
                                                    <li>ตรวจสอบ Router/Modem ว่ามีไฟ LED แสดงสถานะหรือไม่</li>
                                                    <li>ลองรีสตาร์ท Router/Modem (ปิด 30 วินาที แล้วเปิดใหม่)</li>
                                                    <li>ตรวจสอบการ์ด LAN ใน Device Manager ว่าทำงานปกติหรือไม่</li>
                                                    <li>ลองใช้คำสั่ง ipconfig /release และ ipconfig /renew ใน Command Prompt</li>
                                                    <li>ตรวจสอบ IP Address ใน Network Settings ว่าตั้งค่าเป็น Auto หรือไม่</li>
                                                    <li>ลองเปลี่ยน DNS Server เป็น 8.8.8.8 และ 8.8.4.4 (Google DNS)</li>
                                                    <li>ตรวจสอบ Firewall และ Antivirus ว่าบล็อกการเชื่อมต่อหรือไม่</li>
                                                    <li>ลองรีสตาร์ทคอมพิวเตอร์</li>
                                                    <li>ลองใช้สาย LAN อื่นหรือพอร์ตอื่นใน Router</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="troubleshooting-item" onclick="showTroubleshootingDetail('network-connections')">
                                        <span>Network Connections ไม่มีอินเทอร์เน็ตที่ใช้งานได้</span>
                                        <div id="detail-network-connections" class="troubleshooting-detail" style="display: none;">
                                            <h4>Network Connections ไม่มีอินเทอร์เน็ตที่ใช้งานได้</h4>
                                            <div class="detail-section">
                                                <strong>สาเหตุที่เป็นไปได้:</strong>
                                                <ul>
                                                    <li>การ์ด LAN ถูกปิดการใช้งาน</li>
                                                    <li>ไดรเวอร์การ์ด LAN เสีย</li>
                                                    <li>การ์ด LAN เสีย</li>
                                                    <li>ตั้งค่า Network Adapter ไม่ถูกต้อง</li>
                                                    <li>Network Service ไม่ทำงาน</li>
                                                </ul>
                                            </div>
                                            <div class="detail-section">
                                                <strong>วิธีแก้ไข:</strong>
                                                <ul>
                                                    <li>เปิด Network Connections (ncpa.cpl) และตรวจสอบว่าการ์ด LAN ถูก Enable หรือไม่</li>
                                                    <li>คลิกขวาที่การ์ด LAN แล้วเลือก Enable ถ้าถูก Disable อยู่</li>
                                                    <li>ตรวจสอบการ์ด LAN ใน Device Manager ว่ามีเครื่องหมาย ! หรือ X หรือไม่</li>
                                                    <li>อัพเดทไดรเวอร์การ์ด LAN จาก Device Manager</li>
                                                    <li>ลอง Uninstall และ Install การ์ด LAN ใหม่ใน Device Manager</li>
                                                    <li>ตรวจสอบ Network Adapter Settings ว่าตั้งค่าเป็น Auto หรือไม่</li>
                                                    <li>ลองรีสตาร์ท Network Service (services.msc → Network Connections → Restart)</li>
                                                    <li>ลองใช้คำสั่ง netsh winsock reset และ netsh int ip reset ใน Command Prompt (Run as Administrator)</li>
                                                    <li>ลองรีสตาร์ทคอมพิวเตอร์</li>
                                                    <li>ถ้ายังไม่ได้ผล อาจต้องติดตั้งไดรเวอร์การ์ด LAN ใหม่จากเว็บไซต์ผู้ผลิต</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="troubleshooting-item" onclick="showTroubleshootingDetail('wifi')">
                                        <span>WiFi ไม่สามารถเชื่อมต่อได้</span>
                                        <div id="detail-wifi" class="troubleshooting-detail" style="display: none;">
                                            <h4>WiFi ไม่สามารถเชื่อมต่อได้</h4>
                                            <div class="detail-section">
                                                <strong>สาเหตุที่เป็นไปได้:</strong>
                                                <ul>
                                                    <li>WiFi Adapter ถูกปิดการใช้งาน</li>
                                                    <li>Airplane Mode เปิดอยู่</li>
                                                    <li>WiFi Router ปิดหรือเสีย</li>
                                                    <li>รหัสผ่าน WiFi ไม่ถูกต้อง</li>
                                                    <li>WiFi Adapter เสียหรือไดรเวอร์เสีย</li>
                                                    <li>สัญญาณ WiFi อ่อนเกินไป</li>
                                                    <li>WiFi Router จำกัดจำนวนอุปกรณ์</li>
                                                </ul>
                                            </div>
                                            <div class="detail-section">
                                                <strong>วิธีแก้ไข:</strong>
                                                <ul>
                                                    <li>ตรวจสอบว่า WiFi Adapter เปิดอยู่หรือไม่ (ดูที่ Network Settings)</li>
                                                    <li>ตรวจสอบ Airplane Mode ว่าปิดอยู่หรือไม่</li>
                                                    <li>ตรวจสอบ Router ว่ามีไฟ LED แสดงสถานะ WiFi หรือไม่</li>
                                                    <li>ลองรีสตาร์ท Router (ปิด 30 วินาที แล้วเปิดใหม่)</li>
                                                    <li>ลบ WiFi Network ที่บันทึกไว้แล้วเชื่อมต่อใหม่ด้วยรหัสผ่านที่ถูกต้อง</li>
                                                    <li>ตรวจสอบการ์ด WiFi ใน Device Manager ว่าทำงานปกติหรือไม่</li>
                                                    <li>อัพเดทไดรเวอร์การ์ด WiFi จาก Device Manager</li>
                                                    <li>ลองใช้คำสั่ง netsh wlan show profiles และ netsh wlan delete profile name="ชื่อ WiFi" ใน Command Prompt</li>
                                                    <li>ตรวจสอบสัญญาณ WiFi ว่าอยู่ใกล้ Router เพียงพอหรือไม่</li>
                                                    <li>ลองรีสตาร์ทคอมพิวเตอร์</li>
                                                    <li>ลองใช้คำสั่ง ipconfig /flushdns เพื่อล้าง DNS Cache</li>
                                                    <li>ถ้า Router จำกัดจำนวนอุปกรณ์ ลองปิดอุปกรณ์อื่นที่เชื่อมต่ออยู่</li>
                                                    <li>ลองรีเซ็ต Router เป็นค่า Factory Default (ระวัง: จะต้องตั้งค่าใหม่ทั้งหมด)</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        
                        <div class="troubleshooting-section">
                            <div class="troubleshooting-header">
                                <h3 class="troubleshooting-title">HARDWARE</h3>
                                <span class="troubleshooting-icon">▼</span>
                            </div>
                            <div class="troubleshooting-content-inner">
                                <ul class="troubleshooting-list">
                                    <li class="troubleshooting-item" onclick="showTroubleshootingDetail('keyboard')">
                                        <span>คีบอร์ดใช้งานไม่ได้</span>
                                        <div id="detail-keyboard" class="troubleshooting-detail" style="display: none;">
                                            <h4>คีย์บอร์ดใช้งานไม่ได้</h4>
                                            <div class="detail-section">
                                                <strong>สาเหตุที่เป็นไปได้:</strong>
                                                <ul>
                                                    <li>USB/PS2 เสียบไม่แน่น หรือพอร์ตเสีย</li>
                                                    <li>ไดรเวอร์คีย์บอร์ดเสีย</li>
                                                    <li>คีย์บอร์ดเสีย</li>
                                                </ul>
                                            </div>
                                            <div class="detail-section">
                                                <strong>วิธีแก้ไข:</strong>
                                                <ul>
                                                    <li>ถอดแล้วเสียบคีย์บอร์ดเข้าพอร์ตอื่น</li>
                                                    <li>ลองใช้คีย์บอร์ดอื่นดู</li>
                                                    <li>ถ้าเป็น USB ลองรีสตาร์ทคอมและตรวจสอบ Device Manager</li>
                                                    <li>ตรวจสอบ BIOS ว่าคีย์บอร์ดใช้งานได้หรือไม่ (กด DEL/F2 ตอนเปิดเครื่อง)</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="troubleshooting-item" onclick="showTroubleshootingDetail('mouse')">
                                        <span>เมาส์ใช้งานไม่ได้</span>
                                        <div id="detail-mouse" class="troubleshooting-detail" style="display: none;">
                                            <h4>เมาส์ใช้งานไม่ได้</h4>
                                            <div class="detail-section">
                                                <strong>สาเหตุที่เป็นไปได้:</strong>
                                                <ul>
                                                    <li>USB/PS2 เสียบไม่แน่น หรือพอร์ตเสีย</li>
                                                    <li>ไดรเวอร์เมาส์เสีย</li>
                                                    <li>เมาส์เสีย</li>
                                                    <li>เมาส์ไร้สายแบตเตอรี่หมด</li>
                                                </ul>
                                            </div>
                                            <div class="detail-section">
                                                <strong>วิธีแก้ไข:</strong>
                                                <ul>
                                                    <li>ถอดแล้วเสียบเมาส์เข้าพอร์ตอื่น</li>
                                                    <li>ลองใช้เมาส์อื่นดู</li>
                                                    <li>ถ้าเป็นเมาส์ไร้สาย ตรวจสอบแบตเตอรี่และสวิตช์เปิด/ปิด</li>
                                                    <li>ถ้าเป็น USB ลองรีสตาร์ทคอมและตรวจสอบ Device Manager</li>
                                                    <li>ตรวจสอบ BIOS ว่าเมาส์ใช้งานได้หรือไม่ (กด DEL/F2 ตอนเปิดเครื่อง)</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="troubleshooting-item" onclick="showTroubleshootingDetail('headphone')">
                                        <span>หูฟังไม่ได้ยินเสียง</span>
                                        <div id="detail-headphone" class="troubleshooting-detail" style="display: none;">
                                            <h4>หูฟังไม่ได้ยินเสียง</h4>
                                            <div class="detail-section">
                                                <strong>สาเหตุที่เป็นไปได้:</strong>
                                                <ul>
                                                    <li>เสียบหูฟังไม่ถูกพอร์ต (เสียบผิดพอร์ต)</li>
                                                    <li>หูฟังเสีย</li>
                                                    <li>ไดรเวอร์เสียงเสีย</li>
                                                    <li>เสียงถูกปิดหรือตั้งค่าไม่ถูกต้อง</li>
                                                    <li>หูฟังไร้สายแบตเตอรี่หมดหรือไม่ได้เชื่อมต่อ</li>
                                                </ul>
                                            </div>
                                            <div class="detail-section">
                                                <strong>วิธีแก้ไข:</strong>
                                                <ul>
                                                    <li>ตรวจสอบว่าเสียบหูฟังเข้าพอร์ตสีเขียว (Audio Out) หรือไม่</li>
                                                    <li>ลองใช้หูฟังอื่นดู</li>
                                                    <li>ตรวจสอบระดับเสียงในระบบ (Volume Control)</li>
                                                    <li>ตรวจสอบ Sound Settings ใน Control Panel หรือ Settings</li>
                                                    <li>ถ้าเป็นหูฟังไร้สาย ตรวจสอบการเชื่อมต่อ Bluetooth และแบตเตอรี่</li>
                                                    <li>อัพเดทไดรเวอร์เสียงจาก Device Manager</li>
                                                    <li>ลองรีสตาร์ทคอมพิวเตอร์</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="troubleshooting-item" onclick="showTroubleshootingDetail('monitor')">
                                        <span>จอคอมพิวเตอร์เปิดไม่ติด</span>
                                        <div id="detail-monitor" class="troubleshooting-detail" style="display: none;">
                                            <h4>จอคอมพิวเตอร์เปิดไม่ติด</h4>
                                            <div class="detail-section">
                                                <strong>สาเหตุที่เป็นไปได้:</strong>
                                                <ul>
                                                    <li>สายไฟหลุดหรือเสียบไม่แน่น</li>
                                                    <li>สวิตช์เปิด/ปิดจอเสีย</li>
                                                    <li>จอเสีย</li>
                                                    <li>สาย VGA/HDMI/DVI เสียบไม่แน่นหรือเสีย</li>
                                                    <li>การ์ดจอเสีย</li>
                                                    <li>จอเข้าสู่โหมด Sleep หรือ Standby</li>
                                                </ul>
                                            </div>
                                            <div class="detail-section">
                                                <strong>วิธีแก้ไข:</strong>
                                                <ul>
                                                    <li>ตรวจสอบสายไฟและปลั๊กไฟว่าต่อแน่นหรือไม่</li>
                                                    <li>กดปุ่มเปิด/ปิดจอหลายครั้ง</li>
                                                    <li>ตรวจสอบสาย VGA/HDMI/DVI ว่าต่อแน่นทั้งสองด้าน</li>
                                                    <li>ลองใช้สายอื่นหรือพอร์ตอื่น</li>
                                                    <li>ลองกดปุ่มใดๆ บนคีย์บอร์ดเพื่อออกจากโหมด Sleep</li>
                                                    <li>ตรวจสอบว่าจอมีไฟ LED แสดงสถานะหรือไม่</li>
                                                    <li>ลองเสียบจอเข้ากับคอมพิวเตอร์เครื่องอื่นเพื่อทดสอบ</li>
                                                    <li>ตรวจสอบการ์ดจอในคอมพิวเตอร์ว่าทำงานปกติหรือไม่</li>
                                                    <li>ถ้าจอมีไฟแต่ไม่มีภาพ ลองปรับความสว่างหรือ Contrast</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeTroubleshootingModal()">ปิด</button>
                </div>
            </div>
        </div>
        
        
        <div id="logoutModal" class="modal">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h2>ยืนยันการออกจากระบบ</h2>
                    <button type="button" class="modal-close" onclick="closeLogoutModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="text-align: center; font-size: 1.1em; color: var(--text-dark); margin: 20px 0;">
                        คุณต้องการออกจากระบบ
                    </p>
                </div>
                <div class="modal-footer" style="display: flex; justify-content: center; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeLogoutModal()">ยกเลิก</button>
                    <a href="logout.php" class="btn btn-primary" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">ยืนยัน</a>
                </div>
            </div>
        </div>
    </div>
    <script src="<?php echo getAssetUrl('assets/js/main.js'); ?>"></script>
    <script>

        document.addEventListener('DOMContentLoaded', function() {
            const ticketsList = document.getElementById('recentTicketsList');
            if (ticketsList) {
                const ticketItems = ticketsList.querySelectorAll('.ticket-item');
                if (ticketItems.length > 3) {

                    let totalHeight = 0;
                    let count = 0;
                    for (let i = 0; i < 3 && i < ticketItems.length; i++) {
                        const item = ticketItems[i];
                        totalHeight += item.offsetHeight;
                        count++;
                    }
                    const averageHeight = totalHeight / count;

                    const heightOfThreeTickets = (averageHeight * 3) + (2 * 12); 
                    

                    ticketsList.style.maxHeight = heightOfThreeTickets + 'px';
                    ticketsList.style.overflowY = 'auto';
                    ticketsList.style.overflowX = 'hidden';
                    ticketsList.style.paddingRight = '5px'; 
                    

                    ticketsList.style.scrollBehavior = 'smooth';
                }
            }
            
            // Full name validation (Thai and English, including vowels/diacritics) for edit profile
            const editFullNameInput = document.getElementById('edit-fullname');
            let isEditFullNameInvalid = false;
            
            if (editFullNameInput) {
                // Create error message element if it doesn't exist
                let editFullNameCheckMessage = document.getElementById('edit-fullname-check-message');
                if (!editFullNameCheckMessage) {
                    editFullNameCheckMessage = document.createElement('small');
                    editFullNameCheckMessage.id = 'edit-fullname-check-message';
                    editFullNameCheckMessage.style.display = 'none';
                    editFullNameCheckMessage.style.fontWeight = '500';
                    editFullNameInput.parentNode.insertBefore(editFullNameCheckMessage, editFullNameInput.nextSibling);
                }
                
                // Prevent default HTML5 validation message
                editFullNameInput.addEventListener('invalid', function(e) {
                    e.preventDefault();
                });
                
                editFullNameInput.addEventListener('input', function() {
                    const fullName = editFullNameInput.value.trim();
                    
                    if (fullName.length > 0) {
                        // Use Unicode letter + mark pattern to support Thai and English (including vowels/diacritics)
                        if (!/^[\p{L}\p{M}\s]+$/u.test(fullName)) {
                            isEditFullNameInvalid = true;
                            editFullNameCheckMessage.textContent = '⚠️ ชื่อ-นามสกุลต้องเป็นตัวอักษรเท่านั้น (รองรับทั้งภาษาไทยและอังกฤษ)';
                            editFullNameCheckMessage.style.color = 'var(--error-color)';
                            editFullNameCheckMessage.style.display = 'block';
                            editFullNameInput.style.borderColor = 'var(--error-color)';
                            editFullNameInput.setCustomValidity('ชื่อ-นามสกุลต้องเป็นตัวอักษรเท่านั้น');
                        } else {
                            isEditFullNameInvalid = false;
                            editFullNameInput.setCustomValidity('');
                            // Only show success message if it was previously invalid
                            if (editFullNameCheckMessage.textContent.includes('ต้องเป็น')) {
                                editFullNameCheckMessage.textContent = '✓ รูปแบบถูกต้อง';
                                editFullNameCheckMessage.style.color = 'var(--success-color)';
                                editFullNameCheckMessage.style.display = 'block';
                            } else {
                                // Hide success message while typing if it was already valid
                                editFullNameCheckMessage.style.display = 'none';
                            }
                            editFullNameInput.style.borderColor = 'var(--success-color)';
                        }
                    } else {
                        editFullNameInput.setCustomValidity('');
                        // Only hide if it's not an error message
                        if (!isEditFullNameInvalid) {
                            editFullNameCheckMessage.style.display = 'none';
                        }
                        editFullNameInput.style.borderColor = '';
                    }
                });
            }
            
            // Email duplicate check for edit profile (skip if readonly)
            const editEmailInput = document.getElementById('edit-email');
            const editEmailCheckMessage = document.getElementById('edit-email-check-message');
            const originalEmail = editEmailInput ? editEmailInput.value : '';
            let editEmailCheckTimeout;
            
            if (editEmailInput && editEmailCheckMessage && !editEmailInput.readOnly) {
                let lastCheckedEmail = '';
                let isEmailDuplicate = false;
                
                editEmailInput.addEventListener('input', function() {
                    const email = editEmailInput.value.trim();
                    
                    // Clear previous timeout
                    clearTimeout(editEmailCheckTimeout);
                    
                    // Only hide message if it's not a duplicate error
                    if (!isEmailDuplicate || email !== lastCheckedEmail) {
                        // Hide success message while typing, but keep error message if email is duplicate
                        if (editEmailCheckMessage.textContent.includes('สามารถใช้งานได้')) {
                            editEmailCheckMessage.style.display = 'none';
                            editEmailInput.style.borderColor = '';
                        }
                    }
                    
                    // Only check if email is different from original and valid format
                    if (email.length > 0 && email !== originalEmail && email.includes('@') && email.includes('.')) {
                        // Debounce: wait 500ms after user stops typing
                        editEmailCheckTimeout = setTimeout(function() {
                            // Check email format first
                            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                            if (!emailRegex.test(email)) {
                                editEmailCheckMessage.textContent = '⚠️ รูปแบบอีเมลไม่ถูกต้อง';
                                editEmailCheckMessage.style.color = 'var(--error-color)';
                                editEmailCheckMessage.style.display = 'block';
                                editEmailInput.style.borderColor = 'var(--error-color)';
                                isEmailDuplicate = false;
                                lastCheckedEmail = email;
                                return;
                            }
                            
                            // Check for duplicate email via AJAX
                            fetch('check_email.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'email=' + encodeURIComponent(email)
                            })
                            .then(response => response.json())
                            .then(data => {
                                lastCheckedEmail = email;
                                if (data.exists) {
                                    isEmailDuplicate = true;
                                    editEmailCheckMessage.textContent = '❌ อีเมลนี้ถูกใช้งานแล้ว กรุณาใช้อีเมลอื่น';
                                    editEmailCheckMessage.style.color = 'var(--error-color)';
                                    editEmailCheckMessage.style.display = 'block';
                                    editEmailInput.style.borderColor = 'var(--error-color)';
                                } else {
                                    isEmailDuplicate = false;
                                    editEmailCheckMessage.textContent = '✓ อีเมลนี้สามารถใช้งานได้';
                                    editEmailCheckMessage.style.color = 'var(--success-color)';
                                    editEmailCheckMessage.style.display = 'block';
                                    editEmailInput.style.borderColor = 'var(--success-color)';
                                }
                            })
                            .catch(error => {
                                console.error('Error checking email:', error);
                            });
                        }, 500);
                    } else if (email === originalEmail) {
                        // Same as original email, hide message
                        editEmailCheckMessage.style.display = 'none';
                        editEmailInput.style.borderColor = '';
                        isEmailDuplicate = false;
                        lastCheckedEmail = '';
                    } else if (email.length === 0) {
                        // Clear message if email is empty
                        editEmailCheckMessage.style.display = 'none';
                        editEmailInput.style.borderColor = '';
                        isEmailDuplicate = false;
                        lastCheckedEmail = '';
                    }
                });
            }
        });
    </script>
</body>
</html>
