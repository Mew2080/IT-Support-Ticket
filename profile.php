<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
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
$conn->close();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
    
    if (!empty($full_name) && !preg_match('/^[\p{L}\p{M}\s]+$/u', $full_name)) {
        $error = 'ชื่อ-นามสกุลต้องเป็นตัวอักษรเท่านั้น (รองรับทั้งภาษาไทยและอังกฤษ)';
    } elseif (!empty($phone_clean) && !preg_match('/^[0-9]{9,10}$/', $phone_clean)) {
        $error = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลัก';
    } else {
        $conn = getDBConnection();
        
        // Update user profile (ไม่รวม email)
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE email = ?");
        $stmt->bind_param("sss", $full_name, $phone_clean, $user_email);
        
        if ($stmt->execute()) {
            // Update session (เฉพาะ full_name)
            $_SESSION['full_name'] = $full_name;
            
            $success = 'อัพเดทข้อมูลสำเร็จ';
            
            // Reload user data
            if ($has_department) {
                $stmt2 = $conn->prepare("SELECT email, full_name, phone, department, created_at, role FROM users WHERE email = ?");
            } else {
                $stmt2 = $conn->prepare("SELECT email, full_name, phone, created_at, role FROM users WHERE email = ?");
            }
            $stmt2->bind_param("s", $user_email);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $user = $result2->fetch_assoc();
            $stmt2->close();
        } else {
            $error = 'เกิดข้อผิดพลาดในการอัพเดทข้อมูล: ' . $conn->error;
        }
        
        $stmt->close();
        $conn->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif ($new_password !== $confirm_password) {
        $error = 'รหัสผ่านใหม่ไม่ตรงกัน';
    } elseif (strlen($new_password) < 6) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'รหัสผ่านต้องมีตัวอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว';
    } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $new_password)) {
        $error = 'รหัสผ่านต้องมีตัวอักษรพิเศษอย่างน้อย 1 ตัว (!@#$%^&*()_+-=[]{}|;:,.<>?)';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT password FROM users WHERE email = ?");
        $stmt->bind_param("s", $user_email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt_closed = false;
        
        if ($result->num_rows === 1) {
            $user_data = $result->fetch_assoc();
            
            if (password_verify($current_password, $user_data['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt->close();
                $stmt_closed = true;
                
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->bind_param("ss", $hashed_password, $user_email);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    $stmt_closed = true;
                    $conn->close();
                    
                    // Logout user after password change
                    session_destroy();
                    $_SESSION = [];
                    redirect('index.php?password_changed=1');
                    exit;
                } else {
                    $error = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน: ' . $conn->error;
                }
                
                $stmt->close();
                $stmt_closed = true;
            } else {
                $error = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
            }
        } else {
            $error = 'ไม่พบข้อมูลผู้ใช้';
        }
        
        if (isset($stmt) && $stmt && !$stmt_closed) {
            $stmt->close();
        }
        $conn->close();
    }
}

// Format phone for display
$phone_display = '';
if ($user && !empty($user['phone'])) {
    $phone_digits = preg_replace('/[^0-9]/', '', $user['phone']);
    if (strlen($phone_digits) === 10) {
        $phone_display = substr($phone_digits, 0, 3) . '-' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6);
    } elseif (strlen($phone_digits) === 9) {
        $phone_display = substr($phone_digits, 0, 3) . '-' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6);
    } else {
        $phone_display = $user['phone'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลผู้ใช้ - IT Support Ticket</title>
    <link rel="stylesheet" href="<?php echo getAssetUrl('assets/css/style.css'); ?>">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <main class="container" style="max-width: 900px; margin: 40px auto; padding: 0 20px;">
        <div class="action-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px;">
                <h1 style="margin: 0; color: var(--text-dark); display: flex; align-items: center; gap: 12px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-color);">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    ข้อมูลผู้ใช้
                </h1>
                
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            
            <div class="profile-tabs" style="display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 2px solid var(--border-color);">
                <button type="button" class="profile-tab-btn active" onclick="switchTab('info')" data-tab="info">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    ข้อมูล
                </button>
                <button type="button" class="profile-tab-btn" onclick="switchTab('edit')" data-tab="edit">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    แก้ไขข้อมูล
                </button>
                <button type="button" class="profile-tab-btn" onclick="switchTab('password')" data-tab="password">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    เปลี่ยนรหัสผ่าน
                </button>
            </div>
            
            
            <div class="profile-tab-content">
                
                <div id="tab-info" class="tab-pane active">
                    <div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div class="info-item">
                            <label>อีเมล:</label>
                            <span><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>ชื่อ-นามสกุล:</label>
                            <span><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>เบอร์โทรศัพท์:</label>
                            <span><?php echo htmlspecialchars($phone_display ?: '-'); ?></span>
                        </div>
                        <?php if ($has_department && $user && isset($user['department']) && !empty($user['department'])): ?>
                        <div class="info-item">
                            <label>แผนก:</label>
                            <span><?php echo htmlspecialchars($user['department']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <label>วันที่สมัครสมาชิก:</label>
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
                
                
                <div id="tab-edit" class="tab-pane" style="display: none;">
                    <form method="POST" action="" id="profileForm">
                        <div class="form-group">
                            <label for="email">อีเมล</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly class="form-group input" style="background-color: #f3f4f6; cursor: not-allowed;">
                            <small style="color: #666; font-size: 0.85em;">อีเมลไม่สามารถแก้ไขได้</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">ชื่อ-นามสกุล</label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                                   class="form-group input" placeholder="เช่น สมชาย ใจดี หรือ John Smith">
                            <small style="color: #666; font-size: 0.85em;">กรุณากรอกชื่อ-นามสกุล (รองรับทั้งภาษาไทยและอังกฤษ)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">เบอร์โทรศัพท์</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone_display); ?>" 
                                   class="form-group input" placeholder="เช่น 081-234-5678">
                            <small style="color: #666; font-size: 0.85em;">ไม่บังคับ (กรอกเฉพาะตัวเลข 9-10 หลัก หรือรูปแบบ xxx-xxx-xxxx)</small>
                        </div>
                        
                        <?php if ($has_department && $user && isset($user['department']) && !empty($user['department'])): ?>
                        <div class="form-group">
                            <label>แผนก</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['department']); ?>" 
                                   class="form-group input" readonly style="background-color: #f3f4f6; cursor: not-allowed;">
                            <small style="color: #666; font-size: 0.85em;">แผนกสามารถเลือกได้เฉพาะตอนสมัครสมาชิกเท่านั้น</small>
                        </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 25px;">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
                
                
                <div id="tab-password" class="tab-pane" style="display: none;">
                    <form method="POST" action="" id="passwordForm">
                        <div class="form-group">
                            <label for="current_password">รหัสผ่านปัจจุบัน *</label>
                            <div style="position: relative;">
                                <input type="password" id="current_password" name="current_password" required class="form-group input">
                                <button type="button" onclick="togglePassword('current_password')" 
                                        style="position: absolute; right: 10px; top: 40%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666;">
                                    <svg id="current_password_icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">รหัสผ่านใหม่ *</label>
                            <div style="position: relative;">
                                <input type="password" id="new_password" name="new_password" required minlength="6" class="form-group input">
                                <button type="button" onclick="togglePassword('new_password')" 
                                        style="position: absolute; right: 10px; top: 40%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666;">
                                    <svg id="new_password_icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                            <small style="color: #666; font-size: 0.85em;">รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร มีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว และมีตัวอักษรพิเศษอย่างน้อย 1 ตัว</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">ยืนยันรหัสผ่านใหม่ *</label>
                            <div style="position: relative;">
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="6" class="form-group input">
                                <button type="button" onclick="togglePassword('confirm_password')" 
                                        style="position: absolute; right: 10px; top: 40%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666;">
                                    <svg id="confirm_password_icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div style="margin-top: 25px;">
                            <button type="submit" name="change_password" class="btn btn-secondary" style="background: white; color: #1f2937; border: 1px solid rgba(0, 0, 0, 0.1);">
                                <!-- <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;"> -->
                                    <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>
                                </svg>
                                เปลี่ยนรหัสผ่าน
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <style>
        .profile-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .profile-tab-btn {
            padding: 12px 20px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--text-light);
            font-size: 0.95em;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            margin-bottom: -2px;
        }
        
        .profile-tab-btn:hover {
            color: var(--primary-color);
            background: rgba(16, 185, 129, 0.05);
        }
        
        .profile-tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 600;
        }
        
        .tab-pane {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .info-item label {
            font-size: 0.85em;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .info-item span {
            font-size: 1em;
            color: var(--text-dark);
            font-weight: 500;
            word-break: break-word;
        }
    </style>
    
    <script>
        function switchTab(tabName) {
            // Hide all tab panes
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.style.display = 'none';
                pane.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.profile-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab pane
            const selectedPane = document.getElementById('tab-' + tabName);
            if (selectedPane) {
                selectedPane.style.display = 'block';
                selectedPane.classList.add('active');
            }
            
            // Add active class to clicked button
            const clickedBtn = document.querySelector(`[data-tab="${tabName}"]`);
            if (clickedBtn) {
                clickedBtn.classList.add('active');
            }
        }
        
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '_icon');
            
            if (input && icon) {
                // Clear existing content
                while (icon.firstChild) {
                    icon.removeChild(icon.firstChild);
                }
                
                if (input.type === 'password') {
                    input.type = 'text';
                    // Show eye-off icon (password is visible)
                    icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
                } else {
                    input.type = 'password';
                    // Show eye icon (password is hidden)
                    icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
                }
            }
        }
        
        // Format phone number input
        document.getElementById('phone')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = value;
                } else if (value.length <= 6) {
                    value = value.substring(0, 3) + '-' + value.substring(3);
                } else {
                    value = value.substring(0, 3) + '-' + value.substring(3, 6) + '-' + value.substring(6, 10);
                }
            }
            e.target.value = value;
        });
        
        // Convert phone number to digits only before form submit
        document.getElementById('profileForm')?.addEventListener('submit', function(e) {
            const phoneInput = document.getElementById('phone');
            if (phoneInput && phoneInput.value) {
                // Convert formatted phone (xxx-xxx-xxxx) to digits only
                phoneInput.value = phoneInput.value.replace(/[^0-9]/g, '');
            }
        });
        
        // Validate password match and format
        function validatePasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (confirmPassword && newPassword !== confirmPassword) {
                confirmPasswordInput.setCustomValidity('รหัสผ่านไม่ตรงกัน');
                return false;
            } else {
                confirmPasswordInput.setCustomValidity('');
                return true;
            }
        }
        
        function validatePasswordFormat() {
            const newPassword = document.getElementById('new_password').value;
            const newPasswordInput = document.getElementById('new_password');
            
            if (newPassword.length > 0) {
                if (newPassword.length < 6) {
                    newPasswordInput.setCustomValidity('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
                    return false;
                } else if (!/[A-Z]/.test(newPassword)) {
                    newPasswordInput.setCustomValidity('รหัสผ่านต้องมีตัวอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว');
                    return false;
                } else if (!/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/.test(newPassword)) {
                    newPasswordInput.setCustomValidity('รหัสผ่านต้องมีตัวอักษรพิเศษอย่างน้อย 1 ตัว');
                    return false;
                } else {
                    newPasswordInput.setCustomValidity('');
                    return true;
                }
            }
            return true;
        }
        
        // Validate when new password changes
        document.getElementById('new_password')?.addEventListener('input', function() {
            validatePasswordFormat();
            validatePasswordMatch();
        });
        
        // Validate when confirm password changes
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            validatePasswordMatch();
        });
        
        // Validate on form submit
        document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
            const formatValid = validatePasswordFormat();
            const matchValid = validatePasswordMatch();
            
            if (!formatValid || !matchValid) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>

