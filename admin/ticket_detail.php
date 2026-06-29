<?php
require_once '../config.php';

requireAdmin();

$ticket_id = intval($_GET['id'] ?? 0);

if (!$ticket_id) {
    redirect('tickets.php');
}

$conn = getDBConnection();

$error = '';
$success = '';

if (isset($_SESSION['reply_success'])) {
    $success = $_SESSION['reply_success'];
    unset($_SESSION['reply_success']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_reply'])) {
    // Check if assigned_to column exists
    $assigned_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'assigned_to'");
    $has_assigned_to = ($assigned_check && $assigned_check->num_rows > 0);
    
    if ($has_assigned_to) {
        // Check if ticket is assigned to current admin
        $check_stmt = $conn->prepare("SELECT assigned_to FROM tickets WHERE id = ?");
        $check_stmt->bind_param("i", $ticket_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        $admin_email = getCurrentUserEmail();
        if (empty($check_data['assigned_to']) || $check_data['assigned_to'] !== $admin_email) {
            $error = 'คุณต้องรับงาน Ticket นี้ก่อนถึงจะตอบกลับได้';
        }
    }
    
    $reply_text = trim($_POST['reply_text'] ?? '');
    $admin_email = getCurrentUserEmail();
    $image_path = null;
    
    if (empty($reply_text) && empty($error)) {
        $error = 'กรุณากรอกข้อความตอบกลับ';
    } elseif (empty($error)) {

        if (isset($_FILES['reply_image']) && $_FILES['reply_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['reply_image'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; 
            

            $file_type = $file['type'];
            if (!in_array($file_type, $allowed_types)) {
                $error = 'ประเภทไฟล์ไม่รองรับ กรุณาอัปโหลดไฟล์รูปภาพ (JPG, PNG, GIF, WEBP)';
            }

            elseif ($file['size'] > $max_size) {
                $error = 'ขนาดไฟล์ใหญ่เกินไป กรุณาอัปโหลดไฟล์ไม่เกิน 5MB';
            }

            else {
                $upload_dir = __DIR__ . '/../uploads/tickets/';
                

                $uploads_base = __DIR__ . '/../uploads/';
                if (!is_dir($uploads_base)) {
                    if (!mkdir($uploads_base, 0775, true)) {
                        $error = 'ไม่สามารถสร้างโฟลเดอร์ uploads ได้ กรุณาติดต่อผู้ดูแลระบบ';
                    } else {
                        chmod($uploads_base, 0775);
                    }
                } else {

                    if (!is_writable($uploads_base)) {
                        @chmod($uploads_base, 0775);
                    }
                }
                

                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0775, true)) {
                        $error = 'ไม่สามารถสร้างโฟลเดอร์ tickets ได้ กรุณาติดต่อผู้ดูแลระบบ';
                    } else {
                        chmod($upload_dir, 0775);
                    }
                } else {

                    if (!is_writable($upload_dir)) {
                        @chmod($upload_dir, 0775);
                    }
                }
                

                if (empty($error) && !is_writable($upload_dir)) {
                    $error = 'โฟลเดอร์ uploads ไม่สามารถเขียนได้ กรุณาติดต่อผู้ดูแลระบบเพื่อตรวจสอบสิทธิ์';
                }
                
                if (empty($error)) {
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $file_name = 'reply_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {

                        @chmod($file_path, 0644);
                        $image_path = 'uploads/tickets/' . $file_name;
                    } else {
                        $error = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ: ' . (error_get_last()['message'] ?? 'ไม่ทราบสาเหตุ');
                    }
                }
            }
        } elseif (isset($_FILES['reply_image']) && $_FILES['reply_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ: ' . $_FILES['reply_image']['error'];
        }
        

        if (empty($error)) {
            // Check if ticket_replies table has user_email column
            $reply_user_email_check = $conn->query("SHOW COLUMNS FROM ticket_replies LIKE 'user_email'");
            $has_reply_user_email = ($reply_user_email_check && $reply_user_email_check->num_rows > 0);
            
            // Check if ticket_replies table has user_id column
            $reply_user_id_check = $conn->query("SHOW COLUMNS FROM ticket_replies LIKE 'user_id'");
            $has_reply_user_id = ($reply_user_id_check && $reply_user_id_check->num_rows > 0);
            
            if ($has_reply_user_email) {
                // Use user_email column, but also include user_id if it exists and is required
                if ($has_reply_user_id) {
                    // Get user_id for compatibility
                    $user_id_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $user_id_stmt->bind_param("s", $admin_email);
                    $user_id_stmt->execute();
                    $user_id_result = $user_id_stmt->get_result();
                    $user_id_row = $user_id_result->fetch_assoc();
                    $admin_id = $user_id_row ? $user_id_row['id'] : null;
                    $user_id_stmt->close();
                    
                    if (!$admin_id) {
                        $error = 'ไม่พบข้อมูลผู้ใช้';
                    } else {
                        // Include both user_email and user_id
                        if ($image_path) {
                            $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, user_id, user_email, reply_text, image_path) VALUES (?, ?, ?, ?, ?)");
                            $stmt->bind_param("iisss", $ticket_id, $admin_id, $admin_email, $reply_text, $image_path);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, user_id, user_email, reply_text) VALUES (?, ?, ?, ?)");
                            $stmt->bind_param("iiss", $ticket_id, $admin_id, $admin_email, $reply_text);
                        }
                    }
                } else {
                    // Only user_email column exists
                    if ($image_path) {
                        $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, user_email, reply_text, image_path) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("isss", $ticket_id, $admin_email, $reply_text, $image_path);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, user_email, reply_text) VALUES (?, ?, ?)");
                        $stmt->bind_param("iss", $ticket_id, $admin_email, $reply_text);
                    }
                }
            } else {
                // Fallback to user_id if user_email column doesn't exist
                $user_id_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $user_id_stmt->bind_param("s", $admin_email);
                $user_id_stmt->execute();
                $user_id_result = $user_id_stmt->get_result();
                $user_id_row = $user_id_result->fetch_assoc();
                $admin_id = $user_id_row ? $user_id_row['id'] : null;
                $user_id_stmt->close();
                
                if ($admin_id) {
                    if ($image_path) {
                        $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, user_id, reply_text, image_path) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("iiss", $ticket_id, $admin_id, $reply_text, $image_path);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO ticket_replies (ticket_id, user_id, reply_text) VALUES (?, ?, ?)");
                        $stmt->bind_param("iis", $ticket_id, $admin_id, $reply_text);
                    }
                } else {
                    $error = 'ไม่พบข้อมูลผู้ใช้';
                }
            }
            
            if (isset($stmt) && $stmt && empty($error) && $stmt->execute()) {

                $stmt2 = $conn->prepare("UPDATE tickets SET status = 'in_progress' WHERE id = ? AND status = 'open'");
                $stmt2->bind_param("i", $ticket_id);
                $stmt2->execute();
                $stmt2->close();
                
                $stmt->close();
                $conn->close();
                

                $_SESSION['reply_success'] = 'ตอบกลับ Ticket สำเร็จ!';
                redirect('ticket_detail.php?id=' . $ticket_id);
            } else {
                $error = 'เกิดข้อผิดพลาดในการตอบกลับ: ' . $conn->error;

                if ($image_path && file_exists(__DIR__ . '/../' . $image_path)) {
                    unlink(__DIR__ . '/../' . $image_path);
                }
                $stmt->close();
            }
        } else {

            if ($image_path && file_exists(__DIR__ . '/../' . $image_path)) {
                unlink(__DIR__ . '/../' . $image_path);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    // Check if assigned_to column exists
    $assigned_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'assigned_to'");
    $has_assigned_to = ($assigned_check && $assigned_check->num_rows > 0);
    
    if ($has_assigned_to) {
        // Check if ticket is assigned to current admin
        $check_stmt = $conn->prepare("SELECT assigned_to FROM tickets WHERE id = ?");
        $check_stmt->bind_param("i", $ticket_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        $admin_email = getCurrentUserEmail();
        if (empty($check_data['assigned_to']) || $check_data['assigned_to'] !== $admin_email) {
            $error = 'คุณต้องรับงาน Ticket นี้ก่อนถึงจะอัปเดตสถานะได้';
        }
    }
    
    if (empty($error)) {
    $new_status = $_POST['status'];
    $admin_email = getCurrentUserEmail();
    

    $column_exists = false;
    $check_result = $conn->query("SHOW COLUMNS FROM tickets LIKE 'resolved_by'");
    if ($check_result && $check_result->num_rows > 0) {
        $column_exists = true;
    }
    

    if ($new_status == 'closed' && $column_exists) {
        // Get user_id from email since resolved_by is an integer column
        $user_id_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $user_id_stmt->bind_param("s", $admin_email);
        $user_id_stmt->execute();
        $user_id_result = $user_id_stmt->get_result();
        $user_id_row = $user_id_result->fetch_assoc();
        $admin_id = $user_id_row ? $user_id_row['id'] : null;
        $user_id_stmt->close();
        
        if ($admin_id) {
            $stmt = $conn->prepare("UPDATE tickets SET status = ?, resolved_by = ? WHERE id = ?");
            $stmt->bind_param("sii", $new_status, $admin_id, $ticket_id);
        } else {
            // Fallback if user not found
            $stmt = $conn->prepare("UPDATE tickets SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $ticket_id);
        }
    } else {
        $stmt = $conn->prepare("UPDATE tickets SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $ticket_id);
    }
    
    $stmt->execute();
    $stmt->close();
    }
}

// Handle assign ticket
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_ticket'])) {
    $admin_email = getCurrentUserEmail();
    
    // Check if assigned_to column exists, if not create it
    $assigned_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'assigned_to'");
    $has_assigned_to = ($assigned_check && $assigned_check->num_rows > 0);
    
    if (!$has_assigned_to) {
        // Create assigned_to column
        $conn->query("ALTER TABLE tickets ADD COLUMN assigned_to VARCHAR(255) NULL");
    }
    
    // Assign ticket to current admin
    $stmt = $conn->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
    $stmt->bind_param("si", $admin_email, $ticket_id);
    
    if ($stmt->execute()) {
        $_SESSION['ticket_assigned'] = 'รับงาน Ticket สำเร็จ';
        $stmt->close();
        redirect('ticket_detail.php?id=' . $ticket_id);
    } else {
        $error = 'เกิดข้อผิดพลาดในการรับงาน: ' . $conn->error;
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_ticket'])) {

    $stmt = $conn->prepare("SELECT image_path FROM tickets WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        redirect('tickets.php');
    }
    
    $ticket_data = $result->fetch_assoc();
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
    

    if (!empty($ticket_data['image_path']) && file_exists(__DIR__ . '/../' . $ticket_data['image_path'])) {
        unlink(__DIR__ . '/../' . $ticket_data['image_path']);
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
}

$column_exists = false;
$check_result = $conn->query("SHOW COLUMNS FROM tickets LIKE 'resolved_by'");
if ($check_result && $check_result->num_rows > 0) {
    $column_exists = true;
}

// Check if assigned_to column exists, if not create it
$assigned_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'assigned_to'");
$has_assigned_to = ($assigned_check && $assigned_check->num_rows > 0);

if (!$has_assigned_to) {
    // Create assigned_to column
    $conn->query("ALTER TABLE tickets ADD COLUMN assigned_to VARCHAR(255) NULL");
    $has_assigned_to = true;
}

// Check if tickets table has user_email column
$user_email_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'user_email'");
$has_user_email = ($user_email_check && $user_email_check->num_rows > 0);

// Check if users table has department column
$dept_check = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
$has_department = ($dept_check && $dept_check->num_rows > 0);

// Check if tickets table has location column
$location_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'location'");
$has_location = ($location_check && $location_check->num_rows > 0);

if ($column_exists && $has_user_email) {
    if ($has_department && $has_location) {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name, u.department,
                                r.email as resolved_by_email, r.full_name as resolved_by_full_name
                                FROM tickets t 
                                JOIN users u ON t.user_email = u.email 
                                LEFT JOIN users r ON t.resolved_by = r.id
                                WHERE t.id = ?");
    } elseif ($has_department) {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name, u.department,
                                r.email as resolved_by_email, r.full_name as resolved_by_full_name
                                FROM tickets t 
                                JOIN users u ON t.user_email = u.email 
                                LEFT JOIN users r ON t.resolved_by = r.id
                                WHERE t.id = ?");
    } elseif ($has_location) {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name,
                                r.email as resolved_by_email, r.full_name as resolved_by_full_name
                                FROM tickets t 
                                JOIN users u ON t.user_email = u.email 
                                LEFT JOIN users r ON t.resolved_by = r.id
                                WHERE t.id = ?");
    } else {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name,
                                r.email as resolved_by_email, r.full_name as resolved_by_full_name
                                FROM tickets t 
                                JOIN users u ON t.user_email = u.email 
                                LEFT JOIN users r ON t.resolved_by = r.id
                                WHERE t.id = ?");
    }
    $stmt->bind_param("i", $ticket_id);
} elseif ($column_exists && !$has_user_email) {
    if ($has_department && $has_location) {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name, u.department,
                                r.email as resolved_by_email, r.full_name as resolved_by_full_name
                                FROM tickets t 
                                JOIN users u ON t.user_id = u.id 
                                LEFT JOIN users r ON t.resolved_by = r.id
                                WHERE t.id = ?");
    } elseif ($has_department) {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name, u.department,
                                r.email as resolved_by_email, r.full_name as resolved_by_full_name
                                FROM tickets t 
                                JOIN users u ON t.user_id = u.id 
                                LEFT JOIN users r ON t.resolved_by = r.id
                                WHERE t.id = ?");
    } elseif ($has_location) {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name,
                                r.email as resolved_by_email, r.full_name as resolved_by_full_name
                                FROM tickets t 
                                JOIN users u ON t.user_id = u.id 
                                LEFT JOIN users r ON t.resolved_by = r.id
                                WHERE t.id = ?");
    } else {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name,
                                r.email as resolved_by_email, r.full_name as resolved_by_full_name
                                FROM tickets t 
                                JOIN users u ON t.user_id = u.id 
                                LEFT JOIN users r ON t.resolved_by = r.id
                                WHERE t.id = ?");
    }
    $stmt->bind_param("i", $ticket_id);
} elseif (!$column_exists && $has_user_email) {
    if ($has_department && $has_location) {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name, u.department
                                FROM tickets t 
                                JOIN users u ON t.user_email = u.email 
                                WHERE t.id = ?");
    } elseif ($has_department) {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name, u.department
                                FROM tickets t 
                                JOIN users u ON t.user_email = u.email 
                                WHERE t.id = ?");
    } elseif ($has_location) {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name
                                FROM tickets t 
                                JOIN users u ON t.user_email = u.email 
                                WHERE t.id = ?");
    } else {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name
                                FROM tickets t 
                                JOIN users u ON t.user_email = u.email 
                                WHERE t.id = ?");
    }
    $stmt->bind_param("i", $ticket_id);
} else {
    if ($has_department && $has_location) {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name, u.department
                                FROM tickets t 
                                JOIN users u ON t.user_id = u.id 
                                WHERE t.id = ?");
    } elseif ($has_department) {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name, u.department
                                FROM tickets t 
                                JOIN users u ON t.user_id = u.id 
                                WHERE t.id = ?");
    } elseif ($has_location) {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name
                                FROM tickets t 
                                JOIN users u ON t.user_id = u.id 
                                WHERE t.id = ?");
    } else {
        $stmt = $conn->prepare("SELECT t.*, u.email, u.full_name
                                FROM tickets t 
                                JOIN users u ON t.user_id = u.id 
                                WHERE t.id = ?");
    }
    $stmt->bind_param("i", $ticket_id);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    redirect('tickets.php');
}

$ticket = $result->fetch_assoc();
$stmt->close();

// Check if current admin has assigned this ticket
$admin_email = getCurrentUserEmail();
$ticket_assigned_to = $ticket['assigned_to'] ?? null;
$is_assigned_to_me = ($ticket_assigned_to === $admin_email);
$is_unassigned = empty($ticket_assigned_to);

// Check if ticket_replies table has user_email column
$reply_user_email_check = $conn->query("SHOW COLUMNS FROM ticket_replies LIKE 'user_email'");
$has_reply_user_email = ($reply_user_email_check && $reply_user_email_check->num_rows > 0);

if ($has_reply_user_email) {
    if ($has_department) {
        $stmt = $conn->prepare("SELECT tr.*, u.email, u.full_name, u.role, u.department 
                                FROM ticket_replies tr 
                                JOIN users u ON tr.user_email = u.email 
                                WHERE tr.ticket_id = ? 
                                ORDER BY tr.created_at ASC");
    } else {
        $stmt = $conn->prepare("SELECT tr.*, u.email, u.full_name, u.role 
                                FROM ticket_replies tr 
                                JOIN users u ON tr.user_email = u.email 
                                WHERE tr.ticket_id = ? 
                                ORDER BY tr.created_at ASC");
    }
    $stmt->bind_param("i", $ticket_id);
} else {
    if ($has_department) {
        $stmt = $conn->prepare("SELECT tr.*, u.email, u.full_name, u.role, u.department 
                                FROM ticket_replies tr 
                                JOIN users u ON tr.user_id = u.id 
                                WHERE tr.ticket_id = ? 
                                ORDER BY tr.created_at ASC");
    } else {
        $stmt = $conn->prepare("SELECT tr.*, u.email, u.full_name, u.role 
                                FROM ticket_replies tr 
                                JOIN users u ON tr.user_id = u.id 
                                WHERE tr.ticket_id = ? 
                                ORDER BY tr.created_at ASC");
    }
    $stmt->bind_param("i", $ticket_id);
}
$stmt->execute();
$replies_result = $stmt->get_result();
$replies = $replies_result->fetch_all(MYSQLI_ASSOC);
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
    <link rel="icon" type="image/png" href="../img/logo.png">
    <link rel="shortcut icon" type="image/png" href="../img/logo.png">
    <link rel="stylesheet" href="<?php echo getAssetUrl('../assets/css/style.css'); ?>">
</head>
<body>
    <?php include __DIR__ . '/../navbar.php'; ?>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1> Ticket #<?php echo $ticket['id']; ?></h1>
            <div class="user-info">
                <a href="users.php" class="btn btn-secondary btn-sm">จัดการ Users</a>
                <a href="tickets.php" class="btn btn-secondary btn-sm">กลับรายการ Tickets</a>
            </div>
        </header>
        
        <main class="dashboard-content">
            <?php if (isset($_SESSION['ticket_assigned'])): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <?php 
                    echo htmlspecialchars($_SESSION['ticket_assigned']); 
                    unset($_SESSION['ticket_assigned']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            
            <?php if (!$is_assigned_to_me && !$is_unassigned): ?>
                <div class="info-card" style="background: #fef3c7; border: 2px solid #f59e0b;">
                    <h2 style="color: #92400e;">⚠️ Ticket นี้ถูกรับงานโดยแอดมินคนอื่นแล้ว</h2>
                    <p style="color: #78350f; margin-top: 10px;">
                        Ticket นี้ถูกรับงานโดย: <strong><?php echo htmlspecialchars($ticket_assigned_to); ?></strong>
                    </p>
                    <p style="color: #78350f; margin-top: 10px;">
                        คุณสามารถดูรายละเอียดได้เฉพาะ Ticket ที่คุณรับงานเท่านั้น
                    </p>
                    <div style="margin-top: 20px;">
                        <a href="tickets.php" class="btn btn-secondary">กลับรายการ Tickets</a>
                    </div>
                </div>
            <?php elseif ($is_unassigned): ?>
                <?php if ($ticket['status'] == 'closed' || $ticket['status'] == 'resolved'): ?>
                    
                    <div class="info-card" style="background: #f3f4f6; border: 2px solid #9ca3af;">
                        <h2 style="color: #374151;">Ticket ถูกปิดแล้ว</h2>
                        <p style="color: #6b7280; margin-top: 10px;">
                            Ticket นี้ถูกปิดแล้ว ไม่สามารถรับงานได้
                        </p>
                        <div style="margin-top: 20px;">
                            <a href="tickets.php" class="btn btn-secondary">กลับรายการ Tickets</a>
                        </div>
                    </div>
                <?php else: ?>
                    
                    <div class="info-card" style="background: #eff6ff; border: 2px solid #3b82f6;">
                        <h2 style="color: #1e40af;">รับงาน Ticket</h2>
                        <p style="color: #1e3a8a; margin-top: 10px;">
                            คุณต้องรับงาน Ticket นี้ก่อนถึงจะดูเนื้อหาและจัดการได้
                        </p>
                        <form method="POST" style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
                            <input type="hidden" name="assign_ticket" value="1">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">รับงาน Ticket นี้</button>
                            <a href="tickets.php" class="btn btn-secondary" style="flex: 1; text-align: center;">ยกเลิก</a>
                        </form>
                    </div>
                <?php endif; ?>
                
                
                <div class="info-card">
                    <h2> ข้อมูล Ticket (เบื้องต้น)</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>หัวข้อ:</label>
                            <span><?php echo htmlspecialchars($ticket['subject']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>ผู้แจ้ง:</label>
                            <span><?php echo htmlspecialchars($ticket['full_name'] ?: $ticket['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>ความสำคัญ:</label>
                            <span class="badge badge-priority" style="background-color: <?php echo $priority_colors[$ticket['priority']]; ?>">
                                <?php echo $priority_labels[$ticket['priority']]; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>สถานะ:</label>
                            <span class="badge badge-status badge-<?php echo $ticket['status']; ?>">
                                <?php echo $status_labels[$ticket['status']]; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>วันที่สร้าง:</label>
                            <span><?php echo date('d/m/Y H:i:s', strtotime($ticket['created_at'])); ?></span>
                        </div>
                    </div>
                    <?php if ($ticket['status'] != 'closed' && $ticket['status'] != 'resolved'): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #fef3c7; border-radius: 8px; border: 1px solid #f59e0b;">
                        <p style="color: #92400e; margin: 0;">
                            <strong>⚠️ หมายเหตุ:</strong> คุณต้องรับงาน Ticket นี้ก่อนถึงจะดูรายละเอียดปัญหาและจัดการ Ticket ได้
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                
                
            <div class="info-card">
                <h2> ข้อมูล Ticket</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>หัวข้อ:</label>
                        <span><?php echo htmlspecialchars($ticket['subject']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>ผู้แจ้ง:</label>
                        <span><?php echo htmlspecialchars($ticket['full_name'] ?: $ticket['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>อีเมล:</label>
                        <span><?php echo htmlspecialchars($ticket['email']); ?></span>
                    </div>
                    <?php if (isset($ticket['department']) && !empty($ticket['department'])): ?>
                    <div class="info-item">
                        <label>แผนก:</label>
                        <span><?php echo htmlspecialchars($ticket['department']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($ticket['location']) && !empty($ticket['location'])): ?>
                    <div class="info-item">
                        <label>สถานที่:</label>
                        <span><?php echo htmlspecialchars($ticket['location']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <label>ความสำคัญ:</label>
                        <span class="badge badge-priority" style="background-color: <?php echo $priority_colors[$ticket['priority']]; ?>">
                            <?php echo $priority_labels[$ticket['priority']]; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>สถานะ:</label>
                        <span class="badge badge-status badge-<?php echo $ticket['status']; ?>">
                            <?php echo $status_labels[$ticket['status']]; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>วันที่สร้าง:</label>
                        <span><?php echo date('d/m/Y H:i:s', strtotime($ticket['created_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <label>อัปเดตล่าสุด:</label>
                        <span><?php echo date('d/m/Y H:i:s', strtotime($ticket['updated_at'])); ?></span>
                    </div>
                    <?php if ($ticket['status'] == 'closed' && !empty($ticket['resolved_by'])): ?>
                        <div class="info-item">
                            <label>แก้ไขโดย:</label>
                            <span style="color: var(--primary-color); font-weight: 600;">
                                <?php echo htmlspecialchars($ticket['resolved_by_full_name'] ?: $ticket['resolved_by_email']); ?>
                                <span style="color: #666; font-weight: normal; font-size: 0.9em;">(Admin)</span>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($is_assigned_to_me): ?>
            
            <div class="info-card">
                <h2> รายละเอียดปัญหา</h2>
                <div class="ticket-description-full">
                    <p><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                </div>
                
                <?php if (!empty($ticket['image_path']) && file_exists('../' . $ticket['image_path'])): ?>
                    <div class="ticket-image-section" style="margin-top: 20px;">
                        <h3 style="margin-bottom: 15px; color: var(--primary-color);"> รูปภาพที่แนบมา</h3>
                        <div class="ticket-image-container">
                            <img src="../<?php echo htmlspecialchars($ticket['image_path']); ?>" 
                                 alt="Ticket Image" 
                                 class="ticket-image"
                                 onclick="window.open('../<?php echo htmlspecialchars($ticket['image_path']); ?>', '_blank')">
                            <p class="image-caption">คลิกที่รูปเพื่อดูขนาดเต็ม</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            
            <div class="info-card">
                <h2> การตอบกลับ (<span id="reply-count"><?php echo count($replies); ?></span>)</h2>
                <?php if (empty($replies)): ?>
                    <p id="no-replies-message" style="text-align: center; color: #666; padding: 20px;">ยังไม่มีการตอบกลับ</p>
                    <div id="replies-list" class="replies-list" style="display: none;"></div>
                <?php else: ?>
                    <p id="no-replies-message" style="display: none; text-align: center; color: #666; padding: 20px;">ยังไม่มีการตอบกลับ</p>
                    <div id="replies-list" class="replies-list">
                        <?php foreach ($replies as $reply): ?>
                            <div class="reply-item <?php echo ($reply['role'] === 'admin') ? 'reply-admin' : 'reply-user'; ?>" data-reply-id="<?php echo $reply['id']; ?>">
                                <div class="reply-header">
                                    <div class="reply-author">
                                        <div class="reply-author-info">
                                            <?php if ($reply['role'] === 'admin'): ?>
                                                <span class="badge badge-admin"> Admin</span>
                                            <?php else: ?>
                                                <span class="badge badge-user"> ผู้ใช้</span>
                                            <?php endif; ?>
                                            <strong class="reply-name">
                                                <?php echo htmlspecialchars($reply['full_name'] ?: $reply['email']); ?>
                                            </strong>
                                            <?php if (isset($reply['department']) && !empty($reply['department'])): ?>
                                                <span class="reply-department" style="color: #666; font-size: 0.9em; margin-left: 5px;">(<?php echo htmlspecialchars($reply['department']); ?>)</span>
                                            <?php endif; ?>
                                            <?php if ($reply['role'] === 'admin'): ?>
                                                <span class="reply-email">(<?php echo htmlspecialchars($reply['email']); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="reply-date"> <?php echo date('d/m/Y H:i:s', strtotime($reply['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="reply-content">
                                    <p><?php echo nl2br(htmlspecialchars($reply['reply_text'])); ?></p>
                                    <?php if (!empty($reply['image_path']) && file_exists('../' . $reply['image_path'])): ?>
                                        <div class="reply-image-section" style="margin-top: 15px;">
                                            <img src="../<?php echo htmlspecialchars($reply['image_path']); ?>" 
                                                 alt="Reply Image" 
                                                 class="reply-image"
                                                 onclick="window.open('../<?php echo htmlspecialchars($reply['image_path']); ?>', '_blank')">
                                            <p class="reply-image-caption">คลิกที่รูปเพื่อดูขนาดเต็ม</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($is_assigned_to_me): ?>
            
            <div class="action-card">
                <h2> ตอบกลับ Ticket</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" class="reply-form">
                    <input type="hidden" name="submit_reply" value="1">
                    <div class="form-group">
                        <label for="reply_text">ข้อความตอบกลับ *</label>
                        <textarea id="reply_text" name="reply_text" required rows="6" 
                                  class="form-group input" 
                                  placeholder="พิมพ์ข้อความตอบกลับที่นี่..."><?php echo htmlspecialchars($_POST['reply_text'] ?? ''); ?></textarea>
                        <small>คุณสามารถตอบกลับ Ticket นี้เพื่อให้ข้อมูลเพิ่มเติม</small>
                    </div>
                    <div class="form-group">
                        <label for="reply_image">แนบรูปภาพ (ไม่บังคับ)</label>
                        <input type="file" id="reply_image" name="reply_image" 
                               accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                               class="form-group input file-input">
                        <small>รองรับไฟล์: JPG, PNG, GIF, WEBP (ขนาดไม่เกิน 5MB)</small>
                        <div id="reply-image-preview" class="image-preview" style="display: none; margin-top: 10px;">
                            <img id="reply-preview-img" src="" alt="Preview" style="max-width: 100%; max-height: 300px; border-radius: 8px; border: 2px solid var(--border-color);">
                            <button type="button" id="remove-reply-image" class="btn btn-secondary btn-sm" style="margin-top: 10px;"> ลบรูปภาพ</button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"> ส่งคำตอบ</button>
                </form>
            </div>
            
            <?php if ($is_assigned_to_me): ?>
            
            <div class="action-card">
                <h2> อัปเดตสถานะ</h2>
                <form method="POST" class="status-form">
                    <input type="hidden" name="update_status" value="1">
                    <div class="form-group">
                        <label for="status">สถานะ:</label>
                        <select id="status" name="status" required class="form-group input">
                            <option value="open" <?php echo $ticket['status'] == 'open' ? 'selected' : ''; ?>>เปิด</option>
                            <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>กำลังดำเนินการ</option>
                            <option value="closed" <?php echo ($ticket['status'] == 'closed' || $ticket['status'] == 'resolved') ? 'selected' : ''; ?>>ปิด</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"> บันทึกสถานะ</button>
                </form>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    <script src="<?php echo getAssetUrl('../assets/js/main.js'); ?>"></script>
    <script>

        function confirmDeleteTicket() {
            return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบ Ticket นี้?\n\nการลบ Ticket จะลบข้อมูลทั้งหมดรวมถึงการตอบกลับและรูปภาพที่เกี่ยวข้อง\nไม่สามารถกู้คืนได้');
        }
        

        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('reply_image');
            const imagePreview = document.getElementById('reply-image-preview');
            const previewImg = document.getElementById('reply-preview-img');
            const removeBtn = document.getElementById('remove-reply-image');
            const replyTextarea = document.getElementById('reply_text');
            

            function handleImageFile(file) {
                if (!file) return;
                

                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('กรุณาเลือกไฟล์รูปภาพ (JPG, PNG, GIF, WEBP)');
                    return false;
                }
                

                if (file.size > 5 * 1024 * 1024) {
                    alert('ขนาดไฟล์ใหญ่เกินไป กรุณาเลือกไฟล์ไม่เกิน 5MB');
                    return false;
                }
                

                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;
                

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
                
                return true;
            }
            
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        handleImageFile(file);
                    }
                });
            }
            

            if (replyTextarea) {
                replyTextarea.addEventListener('paste', function(e) {
                    const items = e.clipboardData.items;
                    
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            e.preventDefault();
                            const blob = items[i].getAsFile();
                            const file = new File([blob], 'pasted-image-' + Date.now() + '.png', { type: blob.type });
                            handleImageFile(file);
                            break;
                        }
                    }
                });
            }
            

            const form = document.querySelector('.reply-form');
            if (form) {
                form.addEventListener('paste', function(e) {
                    const items = e.clipboardData.items;
                    
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            e.preventDefault();
                            const blob = items[i].getAsFile();
                            const file = new File([blob], 'pasted-image-' + Date.now() + '.png', { type: blob.type });
                            handleImageFile(file);
                            break;
                        }
                    }
                });
            }
            
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    fileInput.value = '';
                    imagePreview.style.display = 'none';
                    previewImg.src = '';
                });
            }
            

            const ticketId = <?php echo $ticket_id; ?>;
            let lastReplyId = <?php 
                if (!empty($replies)) {
                    $ids = array_column($replies, 'id');
                    echo !empty($ids) ? max($ids) : 0;
                } else {
                    echo 0;
                }
            ?>;
            let pollingInterval = null;
            let isPolling = false;
            let statusSelectModified = false; 
            let originalStatusValue = null; 
            

            const replyForm = document.querySelector('.reply-form');
            if (replyForm) {
                replyForm.addEventListener('submit', function() {
                    if (pollingInterval) {
                        clearInterval(pollingInterval);
                        pollingInterval = null;
                    }
                    isPolling = false;
                });
            }
            

            const statusSelect = document.querySelector('select[name="status"]');
            if (statusSelect) {
                originalStatusValue = statusSelect.value;
                

                statusSelect.addEventListener('change', function() {
                    statusSelectModified = true;
                });
                

                const statusForm = statusSelect.closest('form');
                if (statusForm) {
                    statusForm.addEventListener('submit', function() {
                        statusSelectModified = false;
                        originalStatusValue = null;
                    });
                }
                

                statusSelect.addEventListener('blur', function() {
                    setTimeout(() => {

                        if (!statusSelectModified || statusSelect.value === originalStatusValue) {
                            statusSelectModified = false;
                            originalStatusValue = statusSelect.value;
                        }
                    }, 1000);
                });
            }
            
            function checkForNewReplies() {
                if (isPolling) return;
                isPolling = true;
                
                const indicator = document.getElementById('realtime-indicator');
                if (indicator) indicator.style.display = 'block';
                
                fetch(`../api/get_replies.php?ticket_id=${ticketId}&last_reply_id=${lastReplyId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.replies && data.replies.length > 0) {

                            const repliesList = document.getElementById('replies-list');
                            const noRepliesMsg = document.getElementById('no-replies-message');
                            
                            if (noRepliesMsg) noRepliesMsg.style.display = 'none';
                            if (repliesList) {
                                repliesList.style.display = 'flex';
                                
                                data.replies.forEach(reply => {

                                    const existingReply = document.querySelector(`[data-reply-id="${reply.id}"]`);
                                    if (existingReply) {
                                        return; 
                                    }
                                    

                                    const allReplyIds = Array.from(repliesList.querySelectorAll('[data-reply-id]')).map(el => parseInt(el.getAttribute('data-reply-id')));
                                    if (allReplyIds.includes(reply.id)) {
                                        return; 
                                    }
                                    
                                    const replyItem = createReplyElement(reply);
                                    repliesList.appendChild(replyItem);
                                    lastReplyId = Math.max(lastReplyId, reply.id);
                                    

                                    replyItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                    

                                    showNotification('มีข้อความตอบกลับใหม่!', 'success');
                                });
                                

                                const replyCount = document.getElementById('reply-count');
                                if (replyCount) {
                                    replyCount.textContent = repliesList.children.length;
                                }
                            }
                        }
                        

                        if (data.ticket_status) {
                            const statusSelect = document.querySelector('select[name="status"]');
                            if (statusSelect && !statusSelectModified) {

                                if (statusSelect.value !== data.ticket_status) {
                                    statusSelect.value = data.ticket_status;
                                    originalStatusValue = data.ticket_status;
                                }
                            }
                        }
                        
                        if (indicator) indicator.style.display = 'none';
                        isPolling = false;
                    })
                    .catch(error => {
                        if (indicator) indicator.style.display = 'none';
                        isPolling = false;
                    });
            }
            
            function createReplyElement(reply) {
                const div = document.createElement('div');
                div.className = 'reply-item ' + (reply.role === 'admin' ? 'reply-admin' : 'reply-user');
                div.setAttribute('data-reply-id', reply.id);
                
                const date = new Date(reply.created_at);
                const dateStr = date.toLocaleDateString('th-TH') + ' ' + date.toLocaleTimeString('th-TH');
                
                let imageHtml = '';
                if (reply.image_path) {
                    imageHtml = `
                        <div class="reply-image-section" style="margin-top: 15px;">
                            <img src="../${reply.image_path}" 
                                 alt="Reply Image" 
                                 class="reply-image"
                                 onclick="window.open('../${reply.image_path}', '_blank')">
                            <p class="reply-image-caption">คลิกที่รูปเพื่อดูขนาดเต็ม</p>
                        </div>
                    `;
                }
                
                div.innerHTML = `
                    <div class="reply-header">
                        <div class="reply-author">
                            <div class="reply-author-info">
                                ${reply.role === 'admin' ? '<span class="badge badge-admin"> Admin</span>' : '<span class="badge badge-user"> ผู้ใช้</span>'}
                                <strong class="reply-name">${escapeHtml(reply.full_name || reply.email)}</strong>
                                ${reply.department ? `<span class="reply-department" style="color: #666; font-size: 0.9em; margin-left: 5px;">(${escapeHtml(reply.department)})</span>` : ''}
                                ${reply.role === 'admin' ? `<span class="reply-email">(${escapeHtml(reply.email)})</span>` : ''}
                            </div>
                            <span class="reply-date"> ${dateStr}</span>
                        </div>
                    </div>
                    <div class="reply-content">
                        <p>${escapeHtml(reply.reply_text).replace(/\n/g, '<br>')}</p>
                        ${imageHtml}
                    </div>
                `;
                
                return div;
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function showNotification(message, type) {

                const notification = document.createElement('div');
                notification.className = 'alert alert-' + (type === 'success' ? 'success' : 'info');
                notification.style.position = 'fixed';
                notification.style.top = '20px';
                notification.style.right = '20px';
                notification.style.zIndex = '9999';
                notification.style.minWidth = '300px';
                notification.style.animation = 'slideInDown 0.3s ease';
                notification.textContent = message;
                
                document.body.appendChild(notification);
                

                setTimeout(() => {
                    notification.style.animation = 'slideOutUp 0.3s ease';
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }
            

            pollingInterval = setInterval(checkForNewReplies, 3000);
            

            setTimeout(checkForNewReplies, 1000);
            

            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    if (pollingInterval) clearInterval(pollingInterval);
                } else {
                    pollingInterval = setInterval(checkForNewReplies, 3000);
                    checkForNewReplies();
                }
            });
        });
    </script>
</body>
</html>

