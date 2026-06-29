<?php
require_once '../config.php';

requireAdmin();

$conn = getDBConnection();

if (isset($_SESSION['user_deleted'])) {
    $success = $_SESSION['user_deleted'];
    unset($_SESSION['user_deleted']);
}

if (isset($_SESSION['user_suspended'])) {
    $success = $_SESSION['user_suspended'];
    unset($_SESSION['user_suspended']);
}

if (isset($_SESSION['user_unsuspended'])) {
    $success = $_SESSION['user_unsuspended'];
    unset($_SESSION['user_unsuspended']);
}

// Handle suspend/unsuspend user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['suspend_user'])) {
    $user_email = trim($_POST['user_email'] ?? '');
    
    if (!empty($user_email)) {
        // Check if is_suspended column exists, if not create it
        $suspended_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_suspended'");
        $has_suspended = ($suspended_check && $suspended_check->num_rows > 0);
        
        if (!$has_suspended) {
            // Create is_suspended column
            $conn->query("ALTER TABLE users ADD COLUMN is_suspended TINYINT(1) DEFAULT 0");
        }
        
        // Suspend user
        $stmt = $conn->prepare("UPDATE users SET is_suspended = 1 WHERE email = ?");
        $stmt->bind_param("s", $user_email);
        
        if ($stmt->execute()) {
            $_SESSION['user_suspended'] = 'ระงับบัญชี ' . htmlspecialchars($user_email) . ' สำเร็จ';
            $stmt->close();
            redirect('users.php');
        } else {
            $error = 'เกิดข้อผิดพลาดในการระงับบัญชี';
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unsuspend_user'])) {
    $user_email = trim($_POST['user_email'] ?? '');
    
    if (!empty($user_email)) {
        // Check if is_suspended column exists, if not create it
        $suspended_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_suspended'");
        $has_suspended = ($suspended_check && $suspended_check->num_rows > 0);
        
        if (!$has_suspended) {
            // Create is_suspended column
            $conn->query("ALTER TABLE users ADD COLUMN is_suspended TINYINT(1) DEFAULT 0");
        }
        
        // Unsuspend user
        $stmt = $conn->prepare("UPDATE users SET is_suspended = 0 WHERE email = ?");
        $stmt->bind_param("s", $user_email);
        
        if ($stmt->execute()) {
            $_SESSION['user_unsuspended'] = 'เปิดใช้งานบัญชี ' . htmlspecialchars($user_email) . ' สำเร็จ';
            $stmt->close();
            redirect('users.php');
        } else {
            $error = 'เกิดข้อผิดพลาดในการเปิดใช้งานบัญชี';
            $stmt->close();
        }
    }
}

// Check if is_suspended column exists
$suspended_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_suspended'");
$has_suspended = ($suspended_check && $suspended_check->num_rows > 0);

if (!$has_suspended) {
    // Create is_suspended column
    $conn->query("ALTER TABLE users ADD COLUMN is_suspended TINYINT(1) DEFAULT 0");
    $has_suspended = true;
}

$filter_role = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "SELECT email, full_name, role, created_at";
if ($has_suspended) {
    $query .= ", is_suspended";
}
$query .= " FROM users WHERE 1=1";
$conditions = [];
$types = '';
$params = [];

if ($filter_role != 'all') {
    $conditions[] = "role = ?";
    $types .= 's';
    $params[] = $filter_role;
}

if (!empty($search)) {
    $conditions[] = "(email LIKE ? OR full_name LIKE ?)";
    $types .= 'ss';
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($conditions)) {
    $query .= " AND " . implode(" AND ", $conditions);
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$user_emails = array_column($users, 'email');
$ticket_counts = [];
if (!empty($user_emails)) {
    $placeholders = implode(',', array_fill(0, count($user_emails), '?'));
    $types = str_repeat('s', count($user_emails));
    $stmt = $conn->prepare("SELECT user_email, COUNT(*) as count FROM tickets WHERE user_email IN ($placeholders) GROUP BY user_email");
    $stmt->bind_param($types, ...$user_emails);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ticket_counts[$row['user_email']] = $row['count'];
    }
    $stmt->close();
}

$conn->close();

$current_user_email = getCurrentUserEmail();
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
    <link rel="icon" type="image/png" href="../img/logo.png">
    <link rel="shortcut icon" type="image/png" href="../img/logo.png">
    <link rel="stylesheet" href="<?php echo getAssetUrl('../assets/css/style.css'); ?>">
</head>
<body>
    <?php include __DIR__ . '/../navbar.php'; ?>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>ADMIN TICKET PANEL (<?php echo count($users); ?>)</h1>
            <div class="user-info">
                <a href="report.php" class="btn btn-secondary btn-sm" style="background: white; color: #1f2937; border: 1px solid rgba(0, 0, 0, 0.1); margin-bottom: 10px;">สรุปรายงาน</a>
                <a href="tickets.php" class="btn btn-secondary btn-sm" style="background: white; color: #1f2937; border: 1px solid rgba(0, 0, 0, 0.1); margin-bottom: 10px;">จัดการ Tickets</a>
                <a href="../dashboard.php" class="btn btn-secondary btn-sm" style="background: white; color: #1f2937; border: 1px solid rgba(0, 0, 0, 0.1); margin-bottom: 10px;">หน้าแรก</a>
            </div>
        </header>
        
        <main class="dashboard-content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            
            <div class="info-card" style="background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%); border: 1px solid #e5e7eb; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-top: 3px solid var(--primary-color);">
                <h2 style="margin-bottom: 20px; color: var(--text-dark); font-size: 1.3em; display: flex; align-items: center; gap: 10px; padding-bottom: 15px; border-bottom: 2px solid #e5e7eb;">
                    <span>ค้นหาและกรอง Users</span>
                </h2>
                <form method="GET" class="filter-form" style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: grid; grid-template-columns: 2fr 1fr auto; gap: 20px; align-items: start;">
                    <div class="filter-group" style="position: relative;">
                        <label style="margin-bottom: 10px; color: var(--text-dark); font-weight: 600; font-size: 0.95em; display: flex; align-items: center; gap: 6px;">
                            <span>ค้นหา</span>
                        </label>
                        <div style="position: relative;">
                            <input type="text" name="search" class="form-group input search-input" placeholder="ค้นหาจาก email หรือชื่อ" value="<?php echo htmlspecialchars($search); ?>" style="width: 100%; padding: 12px 15px; font-size: 0.95em; border: 2px solid #e5e7eb; border-radius: 10px; transition: all 0.3s ease; background: #fafafa;">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label style="margin-bottom: 10px; color: var(--text-dark); font-weight: 600; font-size: 0.95em; display: flex; align-items: center; gap: 6px;">
                            <span>สิทธิ์</span>
                        </label>
                        <select name="role" class="form-group input" style="width: 100%; padding: 12px 14px; font-size: 0.95em; border: 2px solid #e5e7eb; border-radius: 10px; transition: all 0.3s ease; cursor: pointer; background: #fafafa;">
                            <option value="all" <?php echo $filter_role == 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                            <option value="user" <?php echo $filter_role == 'user' ? 'selected' : ''; ?>>User</option>
                            <option value="admin" <?php echo $filter_role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: flex-end; padding-top: 37px;">
                        <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 0.95em; font-weight: 600; border-radius: 10px; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25); transition: all 0.3s ease; white-space: nowrap; height: 44px; display: flex; align-items: center; justify-content: center;">
                            <span>ค้นหา</span>
                        </button>
                        <a href="users.php" class="btn btn-secondary" style="padding: 12px 24px; font-size: 0.95em; font-weight: 600; border-radius: 10px; transition: all 0.3s ease; white-space: nowrap; text-decoration: none; height: 44px; display: flex; align-items: center; justify-content: center;">
                            <span>รีเซ็ต</span>
                        </a>
                    </div>
                </form>
            </div>
            
            
            <div class="info-card">
                <h2>รายการ Users</h2>
                <?php if (empty($users)): ?>
                    <p style="text-align: center; color: #666; font-size: 1.1em;">ไม่พบ Users</p>
                <?php else: ?>
                    <div class="tickets-list">
                        <?php foreach ($users as $user): ?>
                            <div class="ticket-item">
                                <div class="ticket-header">
                                    <div class="ticket-subject">
                                        <h3>
                                            <?php echo htmlspecialchars($user['email']); ?>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <span class="badge" style="background-color: var(--warning-color); color: white; margin-left: 10px; padding: 4px 8px; border-radius: 4px; font-size: 0.85em;">Admin</span>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="ticket-user">
                                            <?php if ($user['full_name']): ?>
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="ticket-badges">
                                        <?php if ($has_suspended && !empty($user['is_suspended'])): ?>
                                            <span class="badge" style="background-color: #ef4444; color: white;">⚠️ ระงับบัญชี</span>
                                        <?php else: ?>
                                            <span class="badge" style="background-color: #10b981; color: white;">✓ ปกติ</span>
                                        <?php endif; ?>
                                        <span class="badge" style="background-color: #6c757d; color: white;">
                                            Tickets: <?php echo $ticket_counts[$user['email']] ?? 0; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ticket-footer">
                                    <span class="ticket-date">สมัครเมื่อ: <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></span>
                                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                        <div style="display: flex; gap: 8px; align-items: center;">
                                            <label style="color: #666; font-size: 0.9em; margin-right: 5px;">สิทธิ์:</label>
                                            <select class="role-select" data-user-email="<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>" 
                                                    style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9em; cursor: pointer; background: white; min-width: 100px;"
                                                    <?php if ($user['email'] === $current_user_email): ?>disabled title="ไม่สามารถเปลี่ยนสิทธิ์ของบัญชีตัวเองได้"<?php endif; ?>>
                                                <option value="user" <?php echo ($user['role'] === 'user' || empty($user['role'])) ? 'selected' : ''; ?>>User</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                            <span class="role-update-message" data-user-email="<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>" style="display: none; font-size: 0.85em; margin-left: 5px;"></span>
                                        </div>
                                        <?php if ($user['email'] != $current_user_email): ?>
                                            <?php if ($has_suspended && !empty($user['is_suspended'])): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($user['email']); ?>">
                                                    <input type="hidden" name="unsuspend_user" value="1">
                                                    <button type="submit" class="btn btn-sm" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">เปิดใช้งาน</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($user['email']); ?>">
                                                    <input type="hidden" name="suspend_user" value="1">
                                                    <button type="submit" class="btn btn-sm" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white;" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการระงับบัญชี <?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>?');">ระงับบัญชี</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="delete_user.php" onsubmit="return confirmDeleteUser('<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>');" style="display: inline;">
                                                <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($user['email']); ?>">
                                                <button type="submit" class="btn btn-sm" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;">ลบ User</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #666; font-size: 0.9em;">(บัญชีของคุณ)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="<?php echo getAssetUrl('../assets/js/main.js'); ?>"></script>
    <style>
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1) !important;
            transform: translateY(-1px);
        }
        
        .search-input:hover {
            border-color: #d1d5db !important;
        }
        
        .filter-form select:focus {
            outline: none;
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1) !important;
        }
        
        .filter-form select:hover {
            border-color: #d1d5db !important;
        }
        
        .filter-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3) !important;
        }
        
        .filter-form a:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
        }
        
        .info-card[style*="gradient"] {
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
    <script>
        function confirmDeleteUser(email) {
            return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบ User "' + email + '"?\n\nการลบ User จะลบข้อมูลทั้งหมดรวมถึง:\n- Tickets ทั้งหมดของ User นี้\n- การตอบกลับทั้งหมด\n- รูปภาพที่เกี่ยวข้อง\n\nไม่สามารถกู้คืนได้');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const roleSelects = document.querySelectorAll('.role-select');
            
            roleSelects.forEach(select => {
                // Store original value on page load
                const currentRole = select.value;
                select.setAttribute('data-original-role', currentRole);
                
                select.addEventListener('change', function() {
                    const userEmail = this.getAttribute('data-user-email');
                    const newRole = this.value;
                    const messageSpan = document.querySelector('.role-update-message[data-user-email="' + userEmail + '"]');
                    const originalValue = this.getAttribute('data-original-role');
                    
                    // Disable select while updating
                    this.disabled = true;
                    
                    // Show loading message
                    if (messageSpan) {
                        messageSpan.textContent = 'กำลังอัพเดท...';
                        messageSpan.style.color = '#666';
                        messageSpan.style.display = 'inline';
                    }
                    
                    // Send AJAX request
                    const formData = new FormData();
                    formData.append('user_email', userEmail);
                    formData.append('role', newRole);
                    
                    fetch('update_user_role.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            if (messageSpan) {
                                messageSpan.textContent = '✓ ' + data.message;
                                messageSpan.style.color = 'var(--success-color)';
                                messageSpan.style.display = 'inline';
                            }
                            
                            // Update badge if needed
                            const userCard = this.closest('.ticket-item');
                            const badge = userCard.querySelector('.badge[style*="warning-color"]');
                            
                            if (newRole === 'admin') {
                                // Add or update admin badge
                                const emailHeader = userCard.querySelector('h3');
                                if (emailHeader && !badge) {
                                    const adminBadge = document.createElement('span');
                                    adminBadge.className = 'badge';
                                    adminBadge.style.cssText = 'background-color: var(--warning-color); color: white; margin-left: 10px; padding: 4px 8px; border-radius: 4px; font-size: 0.85em;';
                                    adminBadge.textContent = 'Admin';
                                    emailHeader.appendChild(adminBadge);
                                }
                            } else {
                                // Remove admin badge
                                if (badge) {
                                    badge.remove();
                                }
                            }
                            
                            // Update original value
                            this.setAttribute('data-original-role', newRole);
                            
                            // Hide message after 3 seconds
                            setTimeout(() => {
                                if (messageSpan) {
                                    messageSpan.style.display = 'none';
                                }
                            }, 3000);
                            
                            // Reload page after 1 second to update filter if needed
                            setTimeout(() => {
                                const urlParams = new URLSearchParams(window.location.search);
                                const currentFilter = urlParams.get('role');
                                if (currentFilter && currentFilter !== 'all' && currentFilter !== newRole) {
                                    // If filtered by role and role changed, reload to update list
                                    window.location.reload();
                                }
                            }, 1000);
                        } else {
                            // Revert to original value
                            this.value = originalValue;
                            
                            // Show error message
                            if (messageSpan) {
                                messageSpan.textContent = '⚠️ ' + (data.message || 'เกิดข้อผิดพลาด');
                                messageSpan.style.color = 'var(--error-color)';
                                messageSpan.style.display = 'inline';
                            }
                            
                            // Hide message after 5 seconds
                            setTimeout(() => {
                                if (messageSpan) {
                                    messageSpan.style.display = 'none';
                                }
                            }, 5000);
                        }
                    })
                    .catch(error => {
                        // Revert to original value
                        this.value = originalValue;
                        
                        // Show error message
                        if (messageSpan) {
                            messageSpan.textContent = '⚠️ เกิดข้อผิดพลาดในการเชื่อมต่อ';
                            messageSpan.style.color = 'var(--error-color)';
                            messageSpan.style.display = 'inline';
                        }
                        
                        // Hide message after 5 seconds
                        setTimeout(() => {
                            if (messageSpan) {
                                messageSpan.style.display = 'none';
                            }
                        }, 5000);
                    })
                    .finally(() => {
                        // Re-enable select
                        this.disabled = false;
                    });
                });
            });
        });
    </script>
</body>
</html>
