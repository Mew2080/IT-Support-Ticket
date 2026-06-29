<?php
require_once 'config.php';

if (isLoggedIn()) {
    // Redirect admin to admin dashboard
    if (isAdmin()) {
        redirect('admin/tickets.php');
    } else {
    redirect('dashboard.php');
    }
}

$error = '';
$success = '';
$active_tab = $_GET['tab'] ?? 'login'; 
$show_forgot_form = false;

// Check if password was changed
if (isset($_GET['password_changed']) && $_GET['password_changed'] == '1') {
    $success = 'เปลี่ยนรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบใหม่อีกครั้ง';
    $active_tab = 'login';
} 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_submit'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'กรุณากรอกอีเมลและรหัสผ่าน';
        $active_tab = 'login';
    } else {
        $conn = getDBConnection();
        
        // Check if is_suspended column exists
        $suspended_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_suspended'");
        $has_suspended = ($suspended_check && $suspended_check->num_rows > 0);
        
        $query = "SELECT email, password, full_name, role";
        if ($has_suspended) {
            $query .= ", is_suspended";
        }
        $query .= " FROM users WHERE email = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check if account is suspended
            if ($has_suspended && !empty($user['is_suspended'])) {
                $error = 'บัญชีของคุณถูกระงับ เนื้องจากคุณไม่มีสิทธิ์เข้าถึงเนื้อหา';
                $active_tab = 'login';
                $stmt->close();
                $conn->close();
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'] ?? 'user';
                
                $stmt->close();
                $conn->close();
                
                // Redirect admin to admin dashboard
                if (isset($user['role']) && $user['role'] === 'admin') {
                    redirect('admin/tickets.php');
                } else {
                redirect('dashboard.php');
                }
            } else {
                $error = 'รหัสผ่านไม่ถูกต้อง';
                $active_tab = 'login';
                $stmt->close();
                $conn->close();
            }
        } else {
            $error = 'ไม่พบอีเมลนี้';
            $active_tab = 'login';
        $stmt->close();
        $conn->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_submit'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    // Remove dashes from phone number for validation and storage
    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
    $department = trim($_POST['department'] ?? '');
    

    if (empty($email) || empty($password) || empty($confirm_password) || empty($phone_clean) || empty($department)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        $active_tab = 'register';
    } elseif (!preg_match('/^[0-9]{9,10}$/', $phone_clean)) {
        $error = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลัก';
        $active_tab = 'register';
    } elseif ($password !== $confirm_password) {
        $error = 'รหัสผ่านไม่ตรงกัน';
        $active_tab = 'register';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
        $active_tab = 'register';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'รหัสผ่านต้องมีตัวอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว';
        $active_tab = 'register';
    } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $error = 'รหัสผ่านต้องมีตัวอักษรพิเศษอย่างน้อย 1 ตัว (!@#$%^&*()_+-=[]{}|;:,.<>?)';
        $active_tab = 'register';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
        $active_tab = 'register';
    } elseif (!empty($full_name) && !preg_match('/^[\p{L}\p{M}\s]+$/u', $full_name)) {
        $error = 'ชื่อ-นามสกุลต้องเป็นตัวอักษรเท่านั้น (รองรับทั้งภาษาไทยและอังกฤษ)';
        $active_tab = 'register';
    } else {
        $conn = getDBConnection();
        

        $stmt = $conn->prepare("SELECT email FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'อีเมลนี้ถูกใช้งานแล้ว กรุณาใช้อีเมลอื่น';
            $active_tab = 'register';
            $stmt->close();
        } else {
            $stmt->close();
            

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Check if department column exists
            $dept_check = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
            $has_department = ($dept_check && $dept_check->num_rows > 0);
            
                if ($has_department) {
                    $stmt = $conn->prepare("INSERT INTO users (email, password, full_name, phone, department) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $email, $hashed_password, $full_name, $phone_clean, $department);
                } else {
                    $stmt = $conn->prepare("INSERT INTO users (email, password, full_name, phone) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $email, $hashed_password, $full_name, $phone_clean);
                }
            
            if ($stmt->execute()) {
                // ตั้งค่า Session เพื่อเข้าสู่ระบบอัตโนมัติ
                $_SESSION['user_email'] = $email;
                $_SESSION['email'] = $email;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['role'] = 'user'; // ผู้ใช้ใหม่จะเป็น user เสมอ
                
                $stmt->close();
                $conn->close();
                
                // Redirect ไป dashboard โดยไม่ต้องแสดงข้อความ
                redirect('dashboard.php');
            } else {
                $error = 'เกิดข้อผิดพลาดในการสมัครสมาชิก: ' . $conn->error;
                $active_tab = 'register';
                $stmt->close();
            }
        }
        
        $conn->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['forgot_password_submit'])) {
    $email = trim($_POST['forgot_email'] ?? '');
    
    if (empty($email)) {
        $error = 'กรุณากรอกอีเมล';
        $active_tab = 'login';
        $show_forgot_form = true;
    } else {
        $conn = getDBConnection();
        

        $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close();
            

            $new_password = bin2hex(random_bytes(4)); 
            

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            

            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $user['email']);
            
            if ($stmt->execute()) {

                $active_tab = 'login';
                $show_forgot_form = false;

                $_SESSION['new_password'] = $new_password;
                $_SESSION['new_password_email'] = $email;

                $_POST = array();
            } else {
                $error = 'เกิดข้อผิดพลาดในการอัพเดทรหัสผ่าน: ' . $conn->error;
                $active_tab = 'login';
                $show_forgot_form = true;
            }
            
            $stmt->close();
        } else {
            $error = 'ไม่พบอีเมลนี้ กรุณาตรวจสอบอีกครั้ง';
            $active_tab = 'login';
            $show_forgot_form = true;
            $stmt->close();
        }
        
        $conn->close();
    }
}
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
<body class="home-page">
    <?php include 'navbar.php'; ?>
    <div class="home-container">
        
        

        
        <div class="auth-section">
            <div class="auth-card">
                
                <div class="auth-tabs">
                    <button type="button" class="auth-tab <?php echo $active_tab === 'login' ? 'active' : ''; ?>" onclick="switchTab('login')">
                        เข้าสู่ระบบ
                    </button>
                    <button type="button" class="auth-tab <?php echo $active_tab === 'register' ? 'active' : ''; ?>" onclick="switchTab('register')">
                        สมัครสมาชิก
                    </button>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                
                <div id="login-tab" class="auth-tab-content <?php echo $active_tab === 'login' ? 'active' : ''; ?>">
                    
                    <form method="POST" action="index.php" class="auth-form" id="loginForm" style="<?php echo $show_forgot_form ? 'display: none;' : ''; ?>">
                        <input type="hidden" name="login_submit" value="1">
                        
                        <?php 
                        $new_password_display = isset($_SESSION['new_password']) ? $_SESSION['new_password'] : null;
                        $new_password_email = isset($_SESSION['new_password_email']) ? $_SESSION['new_password_email'] : null;
                        if ($new_password_display && $new_password_email): 
                        ?>
                            <div style="background: rgba(16, 185, 129, 0.15); border: 2px solid var(--primary-color); border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                                <p style="margin: 0 0 10px 0; color: var(--text-dark); font-size: 0.95em; line-height: 1.5;">
                                    <strong style="color: var(--primary-color);">รหัสผ่านใหม่ของคุณ:</strong>
                                </p>
                                <div style="background: white; border: 2px solid var(--primary-color); border-radius: 6px; padding: 12px; margin-bottom: 10px; text-align: center;">
                                    <strong style="font-size: 1.5em; color: var(--primary-color); letter-spacing: 2px; font-family: monospace;"><?php echo htmlspecialchars($new_password_display); ?></strong>
                                </div>
                                <p style="margin: 0; color: var(--text-dark); font-size: 0.9em; line-height: 1.5;">
                                    กรุณาใช้รหัสผ่านนี้เข้าสู่ระบบ
                                </p>
                            </div>
                            <?php 

                            unset($_SESSION['new_password']);
                            unset($_SESSION['new_password_email']);
                            ?>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="login_email">อีเมล</label>
                            <input type="email" id="login_email" name="email" required 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ($new_password_email ?? '')); ?>" class="form-group input">
                        </div>
                        
                        <div class="form-group">
                            <label for="login_password">รหัสผ่าน</label>
                            <div class="password-toggle-wrapper">
                                <input type="<?php echo $new_password_display ? 'text' : 'password'; ?>" id="login_password" name="password" required 
                                       value="<?php echo $new_password_display ? htmlspecialchars($new_password_display) : ''; ?>" 
                                       class="form-group input" 
                                       style="<?php echo $new_password_display ? 'background: rgba(16, 185, 129, 0.1); border: 2px solid var(--primary-color); font-weight: bold; font-size: 1.1em; letter-spacing: 1px;' : ''; ?>">
                                <?php if (!$new_password_display): ?>
                                    <button type="button" class="password-toggle-btn" onclick="togglePassword('login_password')" aria-label="แสดงรหัสผ่าน">
                                        <span id="login_password-toggle-icon"><svg width="18" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if ($new_password_display): ?>
                                <small style="color: var(--primary-color); font-weight: 600;">รหัสผ่านใหม่ของคุณ</small>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">เข้าสู่ระบบ</button>
                        <div style="text-align: center; margin-top: 15px;">
                            <button type="button" class="btn btn-link" onclick="showForgotPassword()" style="color: var(--primary-color); text-decoration: none; background: none; border: none; cursor: pointer; font-size: 0.9em; padding: 0;">
                                ลืมรหัสผ่าน?
                            </button>
                        </div>
                    </form>
                    
                    
                    <form method="POST" action="index.php" class="auth-form" id="forgotPasswordForm" style="<?php echo $show_forgot_form ? '' : 'display: none;'; ?>">
                        <input type="hidden" name="forgot_password_submit" value="1">
                        
                        <div class="form-group">
                            <label for="forgot_email">อีเมล *</label>
                            <input type="email" id="forgot_email" name="forgot_email" required 
                                   value="<?php echo htmlspecialchars($_POST['forgot_email'] ?? ''); ?>" class="form-group input">
                            <small style="color: #666; font-size: 0.85em;">กรุณากรอกอีเมลที่ใช้สมัครสมาชิก</small>
                        </div>
                        
                        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--primary-color); border-radius: 8px; padding: 12px; margin-bottom: 15px;">
                            <p style="margin: 0; color: var(--text-dark); font-size: 0.9em; line-height: 1.5;">
                                <strong>⚠️ คำเตือน:</strong> ระบบจะสร้างรหัสผ่านใหม่ให้คุณทันที<br>
                                กรุณากรอกอีเมลให้ตรงกับข้อมูลที่ใช้สมัครสมาชิก
                            </p>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">ยืนยัน</button>
                        <div style="text-align: center; margin-top: 15px;">
                            <button type="button" class="btn btn-link" onclick="showLoginForm()" style="color: var(--primary-color); text-decoration: none; background: none; border: none; cursor: pointer; font-size: 0.9em; padding: 0;">
                                กลับไปเข้าสู่ระบบ
                            </button>
                        </div>
                    </form>
                </div>
                
                
                <div id="register-tab" class="auth-tab-content <?php echo $active_tab === 'register' ? 'active' : ''; ?>">
                    <form method="POST" action="index.php" class="auth-form" id="registerForm">
                        <input type="hidden" name="register_submit" value="1">
                        
                        <div class="form-group">
                            <label for="register_email">อีเมล *</label>
                            <input type="email" id="register_email" name="email" required 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" class="form-group input">
                            <small id="email-check-message" style="display: none; font-weight: 500;"></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="register_password">รหัสผ่าน *</label>
                            <div class="password-toggle-wrapper">
                                <input type="password" id="register_password" name="password" required 
                                       minlength="6" class="form-group input">
                                <button type="button" class="password-toggle-btn" onclick="togglePassword('register_password')" aria-label="แสดงรหัสผ่าน">
                                    <span id="register_password-toggle-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>
                                </button>
                            </div>
                            <small>รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร และต้องมีตัวอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว และตัวอักษรพิเศษอย่างน้อย 1 ตัว</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">ยืนยันรหัสผ่าน *</label>
                            <div class="password-toggle-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" required class="form-group input">
                                <button type="button" class="password-toggle-btn" onclick="togglePassword('confirm_password')" aria-label="แสดงรหัสผ่าน">
                                    <span id="confirm_password-toggle-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>
                                </button>
                            </div>
                            <small id="password-match-message" style="display: none; color: var(--error-color); font-weight: 500;"></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">ชื่อ-นามสกุล</label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                   class="form-group input">
                            <small id="fullname-check-message" style="display: none; font-weight: 500;"></small>
                            <small style="color: #666; font-size: 0.85em;">กรุณากรอกชื่อ-นามสกุล (รองรับทั้งภาษาไทยและอังกฤษ)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="register_phone">เบอร์โทรศัพท์ *</label>
                            <input type="tel" id="register_phone" name="phone" required
                                   value="<?php 
                                       $phone_value = $_POST['phone'] ?? '';
                                       if ($phone_value) {
                                           $phone_digits = preg_replace('/[^0-9]/', '', $phone_value);
                                           if (strlen($phone_digits) === 10) {
                                               $phone_value = substr($phone_digits, 0, 3) . '-' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6);
                                           } elseif (strlen($phone_digits) === 9) {
                                               $phone_value = substr($phone_digits, 0, 3) . '-' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6);
                                           }
                                       }
                                       echo htmlspecialchars($phone_value); 
                                   ?>" 
                                   class="form-group input" 
                                   placeholder="เช่น 081-234-5678">
                            <small style="color: #666; font-size: 0.85em;">กรอกเฉพาะตัวเลข 9-10 หลัก</small>
                            <small id="phone-validation-message" style="display: none; color: var(--error-color); font-weight: 500;"></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="register_department">แผนก *</label>
                            <select id="register_department" name="department" required class="form-group input">
                                <option value="">-- เลือกแผนก --</option>
                                <option value="Engineering" <?php echo (($_POST['department'] ?? '') == 'Engineering') ? 'selected' : ''; ?>>แผนกวิศวกรรม (Engineering)</option>
                                <option value="Project Management" <?php echo (($_POST['department'] ?? '') == 'Project Management') ? 'selected' : ''; ?>>แผนกบริหารโครงการ (Project Management)</option>
                                <option value="Construction / Site" <?php echo (($_POST['department'] ?? '') == 'Construction / Site') ? 'selected' : ''; ?>>แผนกก่อสร้าง / หน้างาน (Construction / Site)</option>
                                <option value="Estimation / Tender" <?php echo (($_POST['department'] ?? '') == 'Estimation / Tender') ? 'selected' : ''; ?>>แผนกประมาณราคาและประมูลงาน (Estimation / Tender)</option>
                                <option value="Procurement & Purchasing" <?php echo (($_POST['department'] ?? '') == 'Procurement & Purchasing') ? 'selected' : ''; ?>>แผนกจัดซื้อและจัดจ้าง (Procurement & Purchasing)</option>
                                <option value="QA/QC" <?php echo (($_POST['department'] ?? '') == 'QA/QC') ? 'selected' : ''; ?>>แผนกควบคุมและประกันคุณภาพ (QA/QC)</option>
                                <option value="Business Development / BD" <?php echo (($_POST['department'] ?? '') == 'Business Development / BD') ? 'selected' : ''; ?>>แผนกพัฒนาธุรกิจ (Business Development / BD)</option>
                                <option value="Maintenance / Service" <?php echo (($_POST['department'] ?? '') == 'Maintenance / Service') ? 'selected' : ''; ?>>แผนกซ่อมบำรุง (Maintenance / Service)</option>
                                <option value="HR & Admin" <?php echo (($_POST['department'] ?? '') == 'HR & Admin') ? 'selected' : ''; ?>>แผนกทรัพยากรบุคคลและธุรการ (HR & Admin)</option>
                                <option value="Accounting & Finance" <?php echo (($_POST['department'] ?? '') == 'Accounting & Finance') ? 'selected' : ''; ?>>แผนกบัญชีและการเงิน (Accounting & Finance)</option>
                                <option value="IT Support" <?php echo (($_POST['department'] ?? '') == 'IT Support') ? 'selected' : ''; ?>>แผนกไอที (IT Support)</option>
                            </select>
                            <small style="color: #666; font-size: 0.85em;">กรุณาเลือกแผนก</small>
                        </div>
                        
                        <div id="register-error-message" style="display: none; color: var(--error-color); margin-top: 10px; padding: 10px; background: rgba(239, 68, 68, 0.1); border-radius: 6px; border: 1px solid var(--error-color);"></div>
                        
                        <button type="submit" class="btn btn-primary btn-block">สมัครสมาชิก</button>
                    </form>
                </div>
                
            </div>
        </div>
    </div>
    <script>

        function switchTab(tab) {

            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
            

            document.querySelectorAll('.auth-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            

            document.querySelectorAll('.auth-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            

            document.getElementById(tab + '-tab').classList.add('active');
            

            event.target.classList.add('active');
            

            if (tab === 'login') {
                showLoginForm();
            }
        }
        

        function showForgotPassword() {
            const loginForm = document.getElementById('loginForm');
            const forgotForm = document.getElementById('forgotPasswordForm');
            
            if (loginForm && forgotForm) {
                loginForm.style.display = 'none';
                forgotForm.style.display = 'block';
            }
        }
        

        function showLoginForm() {
            const loginForm = document.getElementById('loginForm');
            const forgotForm = document.getElementById('forgotPasswordForm');
            
            if (loginForm && forgotForm) {
                loginForm.style.display = 'block';
                forgotForm.style.display = 'none';
            }
        }
        

        document.addEventListener('DOMContentLoaded', function() {
            const registerForm = document.getElementById('registerForm');
            
            if (registerForm) {
                const passwordInput = document.getElementById('register_password');
                const confirmPasswordInput = document.getElementById('confirm_password');
                const passwordMatchMessage = document.getElementById('password-match-message');
                const registerErrorMessage = document.getElementById('register-error-message');
                

                function checkPasswordMatch() {
                    const password = passwordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    
                    if (confirmPassword.length === 0) {
                        passwordMatchMessage.style.display = 'none';
                        confirmPasswordInput.style.borderColor = '';
                        return true;
                    }
                    
                    if (password !== confirmPassword) {
                        passwordMatchMessage.textContent = '⚠️ รหัสผ่านไม่ตรงกัน กรุณากรอกใหม่อีกครั้ง';
                        passwordMatchMessage.style.display = 'block';
                        passwordMatchMessage.style.color = 'var(--error-color)';
                        confirmPasswordInput.style.borderColor = 'var(--error-color)';
                        return false;
                    } else {
                        passwordMatchMessage.textContent = '✓ รหัสผ่านตรงกัน';
                        passwordMatchMessage.style.display = 'block';
                        passwordMatchMessage.style.color = 'var(--success-color)';
                        confirmPasswordInput.style.borderColor = 'var(--success-color)';
                        return true;
                    }
                }
                

                if (passwordInput && confirmPasswordInput) {
                    passwordInput.addEventListener('input', checkPasswordMatch);
                    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
                }
                
                // Full name validation (Thai and English, including vowels/diacritics)
                const fullNameInput = document.getElementById('full_name');
                const fullNameCheckMessage = document.getElementById('fullname-check-message');
                let isFullNameInvalid = false;
                
                if (fullNameInput && fullNameCheckMessage) {
                    // Prevent default HTML5 validation message
                    fullNameInput.addEventListener('invalid', function(e) {
                        e.preventDefault();
                    });
                    
                    fullNameInput.addEventListener('input', function() {
                        const fullName = fullNameInput.value.trim();
                        
                        if (fullName.length > 0) {
                            // Use Unicode letter + mark pattern to support Thai and English (including vowels/diacritics)
                            if (!/^[\p{L}\p{M}\s]+$/u.test(fullName)) {
                                isFullNameInvalid = true;
                                fullNameCheckMessage.textContent = '⚠️ ชื่อ-นามสกุลต้องเป็นตัวอักษรเท่านั้น (รองรับทั้งภาษาไทยและอังกฤษ)';
                                fullNameCheckMessage.style.color = 'var(--error-color)';
                                fullNameCheckMessage.style.display = 'block';
                                fullNameInput.style.borderColor = 'var(--error-color)';
                                fullNameInput.setCustomValidity('ชื่อ-นามสกุลต้องเป็นตัวอักษรเท่านั้น');
                            } else {
                                isFullNameInvalid = false;
                                fullNameInput.setCustomValidity('');
                                // Only show success message if it was previously invalid
                                if (fullNameCheckMessage.textContent.includes('ต้องเป็น')) {
                                    fullNameCheckMessage.textContent = '✓ รูปแบบถูกต้อง';
                                    fullNameCheckMessage.style.color = 'var(--success-color)';
                                    fullNameCheckMessage.style.display = 'block';
                                } else {
                                    // Hide success message while typing if it was already valid
                                    fullNameCheckMessage.style.display = 'none';
                                }
                                fullNameInput.style.borderColor = 'var(--success-color)';
                            }
                        } else {
                            fullNameInput.setCustomValidity('');
                            // Only hide if it's not an error message
                            if (!isFullNameInvalid) {
                                fullNameCheckMessage.style.display = 'none';
                            }
                            fullNameInput.style.borderColor = '';
                        }
                    });
                }
                
                // Email duplicate check
                const emailInput = document.getElementById('register_email');
                const emailCheckMessage = document.getElementById('email-check-message');
                let emailCheckTimeout;
                
                if (emailInput && emailCheckMessage) {
                    let lastCheckedEmail = '';
                    let isEmailDuplicate = false;
                    
                    emailInput.addEventListener('input', function() {
                        const email = emailInput.value.trim();
                        
                        // Clear previous timeout
                        clearTimeout(emailCheckTimeout);
                        
                        // Only hide message if it's not a duplicate error
                        if (!isEmailDuplicate || email !== lastCheckedEmail) {
                            // Hide success message while typing, but keep error message if email is duplicate
                            if (emailCheckMessage.textContent.includes('สามารถใช้งานได้')) {
                                emailCheckMessage.style.display = 'none';
                                emailInput.style.borderColor = '';
                            }
                        }
                        
                        // Only check if email is valid format and not empty
                        if (email.length > 0 && email.includes('@') && email.includes('.')) {
                            // Debounce: wait 500ms after user stops typing
                            emailCheckTimeout = setTimeout(function() {
                                // Check email format first
                                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                                if (!emailRegex.test(email)) {
                                    emailCheckMessage.textContent = '⚠️ รูปแบบอีเมลไม่ถูกต้อง';
                                    emailCheckMessage.style.color = 'var(--error-color)';
                                    emailCheckMessage.style.display = 'block';
                                    emailInput.style.borderColor = 'var(--error-color)';
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
                                        emailCheckMessage.textContent = 'อีเมลนี้ถูกใช้งานแล้วโปรดตรวจสอบอีเมลใหม่อีกครั้ง';
                                        emailCheckMessage.style.color = 'var(--error-color)';
                                        emailCheckMessage.style.display = 'block';
                                        emailInput.style.borderColor = 'var(--error-color)';
                                    } else {
                                        isEmailDuplicate = false;
                                        emailCheckMessage.textContent = '✓ อีเมลนี้สามารถใช้งานได้';
                                        emailCheckMessage.style.color = 'var(--success-color)';
                                        emailCheckMessage.style.display = 'block';
                                        emailInput.style.borderColor = 'var(--success-color)';
                                    }
                                })
                                .catch(error => {
                                    console.error('Error checking email:', error);
                                });
                            }, 500);
                        } else if (email.length === 0) {
                            // Clear message if email is empty
                            emailCheckMessage.style.display = 'none';
                            emailInput.style.borderColor = '';
                            isEmailDuplicate = false;
                            lastCheckedEmail = '';
                        }
                    });
                }
                

                registerForm.addEventListener('submit', function(e) {
                    const email = emailInput ? emailInput.value.trim() : '';
                    const password = passwordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    const fullNameInput = document.getElementById('full_name');
                    const fullName = fullNameInput ? fullNameInput.value.trim() : '';
                    const phoneInput = document.getElementById('register_phone');
                    const phone = phoneInput ? phoneInput.value.trim() : '';
                    const phoneValidationMessage = document.getElementById('phone-validation-message');
                    const departmentInput = document.getElementById('register_department');
                    const department = departmentInput ? departmentInput.value : '';

                    if (registerErrorMessage) {
                        registerErrorMessage.style.display = 'none';
                        registerErrorMessage.textContent = '';
                    }
                    
                    // Check phone number
                    if (phone) {
                        const phoneDigits = phone.replace(/\D/g, '');
                        if (phoneDigits.length < 9 || phoneDigits.length > 10) {
                            e.preventDefault();
                            if (registerErrorMessage) {
                                registerErrorMessage.textContent = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลัก';
                                registerErrorMessage.style.display = 'block';
                            }
                            if (phoneValidationMessage) {
                                phoneValidationMessage.textContent = '⚠️ เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลัก';
                                phoneValidationMessage.style.display = 'block';
                            }
                            if (phoneInput) {
                                phoneInput.focus();
                                phoneInput.style.borderColor = 'var(--error-color)';
                            }
                            return false;
                        }
                    } else {
                        e.preventDefault();
                        if (registerErrorMessage) {
                            registerErrorMessage.textContent = 'กรุณากรอกเบอร์โทรศัพท์';
                            registerErrorMessage.style.display = 'block';
                        }
                        if (phoneInput) {
                            phoneInput.focus();
                            phoneInput.style.borderColor = 'var(--error-color)';
                        }
                        return false;
                    }
                    
                    // Check department
                    if (!department) {
                        e.preventDefault();
                        if (registerErrorMessage) {
                            registerErrorMessage.textContent = 'กรุณาเลือกแผนก';
                            registerErrorMessage.style.display = 'block';
                        }
                        if (departmentInput) {
                            departmentInput.focus();
                            departmentInput.style.borderColor = 'var(--error-color)';
                        }
                        return false;
                    }
                    
                    // Check full name (Thai and English, including vowels/diacritics)
                    if (fullName && !/^[\p{L}\p{M}\s]+$/u.test(fullName)) {
                        e.preventDefault();
                        if (registerErrorMessage) {
                            registerErrorMessage.textContent = 'ชื่อ-นามสกุลต้องเป็นตัวอักษรเท่านั้น (รองรับทั้งภาษาไทยและอังกฤษ)';
                            registerErrorMessage.style.display = 'block';
                        }
                        if (fullNameInput) {
                            fullNameInput.focus();
                            fullNameInput.style.borderColor = 'var(--error-color)';
                        }
                        return false;
                    }
                    
                    // Check email format
                    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        e.preventDefault();
                        if (registerErrorMessage) {
                            registerErrorMessage.textContent = 'รูปแบบอีเมลไม่ถูกต้อง';
                            registerErrorMessage.style.display = 'block';
                        }
                        if (emailInput) {
                            emailInput.focus();
                            emailInput.style.borderColor = 'var(--error-color)';
                        }
                        return false;
                    }
                    
                    // Final email duplicate check before submit
                    if (email && emailCheckMessage && emailCheckMessage.textContent.includes('ถูกใช้งานแล้ว')) {
                        e.preventDefault();
                        if (registerErrorMessage) {
                            registerErrorMessage.textContent = 'อีเมลนี้ถูกใช้งานแล้วโปรดตรวจสอบอีเมลใหม่อีกครั้ง';
                            registerErrorMessage.style.display = 'block';
                        }
                        if (emailInput) {
                            emailInput.focus();
                            emailInput.style.borderColor = 'var(--error-color)';
                        }
                        return false;
                    }

                    if (password !== confirmPassword) {
                        e.preventDefault();
                        

                        if (registerErrorMessage) {
                            registerErrorMessage.textContent = 'รหัสผ่านไม่ตรงกัน กรุณาตรวจสอบและกรอกใหม่อีกครั้ง';
                            registerErrorMessage.style.display = 'block';
                        }
                        

                        confirmPasswordInput.focus();
                        confirmPasswordInput.style.borderColor = 'var(--error-color)';
                        

                        registerErrorMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        
                        return false;
                    }
                    

                    if (password.length < 6) {
                        e.preventDefault();
                        
                        if (registerErrorMessage) {
                            registerErrorMessage.textContent = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
                            registerErrorMessage.style.display = 'block';
                        }
                        
                        passwordInput.focus();
                        passwordInput.style.borderColor = 'var(--error-color)';
                        
                        return false;
                    }
                    
                    if (!/[A-Z]/.test(password)) {
                        e.preventDefault();
                        
                        if (registerErrorMessage) {
                            registerErrorMessage.textContent = 'รหัสผ่านต้องมีตัวอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว';
                            registerErrorMessage.style.display = 'block';
                        }
                        
                        passwordInput.focus();
                        passwordInput.style.borderColor = 'var(--error-color)';
                        
                        return false;
                    }
                    
                    if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
                        e.preventDefault();
                        
                        if (registerErrorMessage) {
                            registerErrorMessage.textContent = 'รหัสผ่านต้องมีตัวอักษรพิเศษอย่างน้อย 1 ตัว (!@#$%^&*()_+-=[]{}|;:,.<>?)';
                            registerErrorMessage.style.display = 'block';
                        }
                        
                        passwordInput.focus();
                        passwordInput.style.borderColor = 'var(--error-color)';
                        
                        return false;
                    }
                    
                    // Remove dashes from phone before submit (handled by setupPhoneAutoFormat in main.js)
                    // But also ensure it's done here as backup
                    if (phoneInput) {
                        phoneInput.value = phone.replace(/\D/g, '');
                    }

                    return true;
                });
            }
        });
    </script>
    <script src="<?php echo getAssetUrl('assets/js/main.js'); ?>"></script>
</body>
</html>
