<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

// Check for success message from session (after redirect)
if (isset($_SESSION['ticket_success'])) {
    $success = $_SESSION['ticket_success'];
    unset($_SESSION['ticket_success']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $category_raw = trim($_POST['category'] ?? 'other');
    $allowed_categories = ['hardware', 'software', 'network', 'other'];
    $category = in_array($category_raw, $allowed_categories) ? $category_raw : 'other';
    $priority_raw = trim($_POST['priority'] ?? 'normal');
    $allowed_priorities = ['normal', 'urgent', 'critical', 'low', 'medium', 'high'];
    $priority = in_array($priority_raw, $allowed_priorities) ? $priority_raw : 'normal';
    
    $user_email = getCurrentUserEmail();
    $image_path = null;
    
    if (empty($subject) || empty($description) || empty($location)) {
        $error = 'กรุณากรอกหัวข้อ รายละเอียดปัญหา และสถานที่';
    } else {
        if (isset($_FILES['ticket_image']) && $_FILES['ticket_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['ticket_image'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024;
            $file_type = $file['type'];
            if (!in_array($file_type, $allowed_types)) {
                $error = 'ประเภทไฟล์ไม่รองรับ กรุณาอัปโหลดไฟล์รูปภาพ (JPG, PNG, GIF, WEBP)';
            } elseif ($file['size'] > $max_size) {
                $error = 'ขนาดไฟล์ใหญ่เกินไป กรุณาอัปโหลดไฟล์ไม่เกิน 5MB';
            } else {
                $upload_dir = __DIR__ . '/uploads/tickets/';
                

                $uploads_base = __DIR__ . '/uploads/';
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
                    $file_name = 'ticket_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {

                        @chmod($file_path, 0644);
                        $image_path = 'uploads/tickets/' . $file_name;
                    } else {
                        $error = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ: ' . (error_get_last()['message'] ?? 'ไม่ทราบสาเหตุ');
                    }
                }
            }
        } elseif (isset($_FILES['ticket_image']) && $_FILES['ticket_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ: ' . $_FILES['ticket_image']['error'];
        }
        

        if (empty($error)) {
            $conn = getDBConnection();
            
            // Ensure tickets.id is auto-increment to avoid insert errors
            $id_check = $conn->query("SHOW COLUMNS FROM tickets WHERE Field = 'id'");
            if ($id_check && $id_check->num_rows > 0) {
                $id_info = $id_check->fetch_assoc();
                $id_type = $id_info['Type'] ?? 'INT';
                $id_null = (isset($id_info['Null']) && strtoupper($id_info['Null']) === 'YES') ? 'NULL' : 'NOT NULL';
                $id_extra = $id_info['Extra'] ?? '';
                if (stripos($id_extra, 'auto_increment') === false) {
                    // Add auto_increment while keeping the existing type/nullability
                    $conn->query("ALTER TABLE tickets MODIFY id {$id_type} {$id_null} AUTO_INCREMENT");
                }
            }
            
            // Check if tickets table has user_email column
            $user_email_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'user_email'");
            $has_user_email = ($user_email_check && $user_email_check->num_rows > 0);

            // Check if tickets table has user_id column
            $user_id_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'user_id'");
            $has_user_id = ($user_id_check && $user_id_check->num_rows > 0);

            // Check if tickets table has location column
            $location_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'location'");
            $has_location = ($location_check && $location_check->num_rows > 0);

            // Check if tickets table has category column
            $category_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'category'");
            $has_category = ($category_check && $category_check->num_rows > 0);

            $column_check = $conn->query("SHOW COLUMNS FROM tickets WHERE Field = 'priority'");
            if ($column_check && $column_check->num_rows > 0) {
                $column_info = $column_check->fetch_assoc();
                $column_type = strtolower($column_info['Type']);
                

                if (strpos($column_type, 'enum') !== false) {
                    preg_match("/enum\s*\((.+)\)/i", $column_type, $matches);
                    if (isset($matches[1])) {
                        $enum_values = array_map(function($v) {
                            return trim(str_replace("'", "", $v));
                        }, explode(',', $matches[1]));
                        
                        // Ensure enum supports all priorities including "critical" (ด่วนมาก)
                        $desired_enum = ['low', 'medium', 'high', 'normal', 'urgent', 'critical'];
                        $missing = array_diff($desired_enum, $enum_values);
                        if (!empty($missing)) {
                            $enum_def = "ENUM('" . implode("','", $desired_enum) . "')";
                            $null_clause = (isset($column_info['Null']) && strtoupper($column_info['Null']) === 'YES') ? 'NULL' : 'NOT NULL';
                            $default_clause = "DEFAULT 'normal'";
                            $conn->query("ALTER TABLE tickets MODIFY priority {$enum_def} {$null_clause} {$default_clause}");
                            $enum_values = $desired_enum;
                        }
                        
                        // If still missing, fallback to closest allowed value
                        if (!in_array($priority, $enum_values)) {
                            $priority_map = [
                                'critical' => 'high',
                                'urgent' => 'medium',
                                'normal' => 'low'
                            ];
                            if (isset($priority_map[$priority]) && in_array($priority_map[$priority], $enum_values)) {
                                $priority = $priority_map[$priority];
                            } elseif (in_array('low', $enum_values)) {
                                $priority = 'low'; 
                            } else {
                                $priority = $enum_values[0]; 
                            }
                        }
                    }
                } elseif (strpos($column_type, 'varchar') !== false) {

                    preg_match("/varchar\s*\((\d+)\)/i", $column_type, $matches);
                    if (isset($matches[1])) {
                        $max_length = (int)$matches[1];
                        if (strlen($priority) > $max_length) {
                            $priority = substr($priority, 0, $max_length);
                        }
                    }
                }
            }
            
            if ($has_user_email) {
                // Use user_email column, but also include user_id if it exists and is required
                if ($has_user_id) {
                    // Get user_id for compatibility
                    $user_id_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $user_id_stmt->bind_param("s", $user_email);
                    $user_id_stmt->execute();
                    $user_id_result = $user_id_stmt->get_result();
                    $user_id_row = $user_id_result->fetch_assoc();
                    $user_id = $user_id_row ? $user_id_row['id'] : null;
                    $user_id_stmt->close();
                    
                    if (!$user_id) {
                        $error = 'ไม่พบข้อมูลผู้ใช้';
                    } else {
                        // Include both user_email and user_id
                        if ($has_location && $has_category) {
                            if ($image_path) {
                                $stmt = $conn->prepare("INSERT INTO tickets (user_id, user_email, subject, description, location, category, priority, status, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?)");
                                $stmt->bind_param("isssssss", $user_id, $user_email, $subject, $description, $location, $category, $priority, $image_path);
                            } else {
                                $stmt = $conn->prepare("INSERT INTO tickets (user_id, user_email, subject, description, location, category, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'open')");
                                $stmt->bind_param("issssss", $user_id, $user_email, $subject, $description, $location, $category, $priority);
                            }
                        } elseif ($has_location) {
                            if ($image_path) {
                                $stmt = $conn->prepare("INSERT INTO tickets (user_id, user_email, subject, description, location, priority, status, image_path) VALUES (?, ?, ?, ?, ?, ?, 'open', ?)");
                                $stmt->bind_param("issssss", $user_id, $user_email, $subject, $description, $location, $priority, $image_path);
                            } else {
                                $stmt = $conn->prepare("INSERT INTO tickets (user_id, user_email, subject, description, location, priority, status) VALUES (?, ?, ?, ?, ?, ?, 'open')");
                                $stmt->bind_param("isssss", $user_id, $user_email, $subject, $description, $location, $priority);
                            }
                        } elseif ($has_category) {
                            if ($image_path) {
                                $stmt = $conn->prepare("INSERT INTO tickets (user_id, user_email, subject, description, category, priority, status, image_path) VALUES (?, ?, ?, ?, ?, ?, 'open', ?)");
                                $stmt->bind_param("issssss", $user_id, $user_email, $subject, $description, $category, $priority, $image_path);
                            } else {
                                $stmt = $conn->prepare("INSERT INTO tickets (user_id, user_email, subject, description, category, priority, status) VALUES (?, ?, ?, ?, ?, ?, 'open')");
                                $stmt->bind_param("isssss", $user_id, $user_email, $subject, $description, $category, $priority);
                            }
                        } else {
                            if ($image_path) {
                                $stmt = $conn->prepare("INSERT INTO tickets (user_id, user_email, subject, description, priority, status, image_path) VALUES (?, ?, ?, ?, ?, 'open', ?)");
                                $stmt->bind_param("isssss", $user_id, $user_email, $subject, $description, $priority, $image_path);
                            } else {
                                $stmt = $conn->prepare("INSERT INTO tickets (user_id, user_email, subject, description, priority, status) VALUES (?, ?, ?, ?, ?, 'open')");
                                $stmt->bind_param("issss", $user_id, $user_email, $subject, $description, $priority);
                            }
                        }
                    }
                } else {
                    // Only user_email column exists
                    if ($has_location && $has_category) {
                        if ($image_path) {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_email, subject, description, location, category, priority, status, image_path) VALUES (?, ?, ?, ?, ?, ?, 'open', ?)");
                            $stmt->bind_param("sssssss", $user_email, $subject, $description, $location, $category, $priority, $image_path);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_email, subject, description, location, category, priority, status) VALUES (?, ?, ?, ?, ?, ?, 'open')");
                            $stmt->bind_param("ssssss", $user_email, $subject, $description, $location, $category, $priority);
                        }
                    } elseif ($has_location) {
                        if ($image_path) {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_email, subject, description, location, priority, status, image_path) VALUES (?, ?, ?, ?, ?, 'open', ?)");
                            $stmt->bind_param("ssssss", $user_email, $subject, $description, $location, $priority, $image_path);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_email, subject, description, location, priority, status) VALUES (?, ?, ?, ?, ?, 'open')");
                            $stmt->bind_param("sssss", $user_email, $subject, $description, $location, $priority);
                        }
                    } elseif ($has_category) {
                        if ($image_path) {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_email, subject, description, category, priority, status, image_path) VALUES (?, ?, ?, ?, ?, 'open', ?)");
                            $stmt->bind_param("ssssss", $user_email, $subject, $description, $category, $priority, $image_path);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_email, subject, description, category, priority, status) VALUES (?, ?, ?, ?, ?, 'open')");
                            $stmt->bind_param("sssss", $user_email, $subject, $description, $category, $priority);
                        }
                    } else {
                        if ($image_path) {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_email, subject, description, priority, status, image_path) VALUES (?, ?, ?, ?, 'open', ?)");
                            $stmt->bind_param("sssss", $user_email, $subject, $description, $priority, $image_path);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_email, subject, description, priority, status) VALUES (?, ?, ?, ?, 'open')");
                            $stmt->bind_param("ssss", $user_email, $subject, $description, $priority);
                        }
                    }
                }
            } else {
                // Fallback to user_id if user_email column doesn't exist
                $user_id_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $user_id_stmt->bind_param("s", $user_email);
                $user_id_stmt->execute();
                $user_id_result = $user_id_stmt->get_result();
                $user_id_row = $user_id_result->fetch_assoc();
                $user_id = $user_id_row ? $user_id_row['id'] : null;
                $user_id_stmt->close();
                
                if ($user_id) {
                    if ($has_location && $has_category) {
                        if ($image_path) {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_id, subject, description, location, category, priority, status, image_path) VALUES (?, ?, ?, ?, ?, ?, 'open', ?)");
                            $stmt->bind_param("issssss", $user_id, $subject, $description, $location, $category, $priority, $image_path);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_id, subject, description, location, category, priority, status) VALUES (?, ?, ?, ?, ?, ?, 'open')");
                            $stmt->bind_param("isssss", $user_id, $subject, $description, $location, $category, $priority);
                        }
                    } elseif ($has_location) {
                        if ($image_path) {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_id, subject, description, location, priority, status, image_path) VALUES (?, ?, ?, ?, ?, 'open', ?)");
                            $stmt->bind_param("isssss", $user_id, $subject, $description, $location, $priority, $image_path);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_id, subject, description, location, priority, status) VALUES (?, ?, ?, ?, ?, 'open')");
                            $stmt->bind_param("issss", $user_id, $subject, $description, $location, $priority);
                        }
                    } elseif ($has_category) {
                        if ($image_path) {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_id, subject, description, category, priority, status, image_path) VALUES (?, ?, ?, ?, ?, 'open', ?)");
                            $stmt->bind_param("isssss", $user_id, $subject, $description, $category, $priority, $image_path);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_id, subject, description, category, priority, status) VALUES (?, ?, ?, ?, ?, 'open')");
                            $stmt->bind_param("issss", $user_id, $subject, $description, $category, $priority);
                        }
                    } else {
                        if ($image_path) {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_id, subject, description, priority, status, image_path) VALUES (?, ?, ?, ?, 'open', ?)");
                            $stmt->bind_param("issss", $user_id, $subject, $description, $priority, $image_path);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO tickets (user_id, subject, description, priority, status) VALUES (?, ?, ?, ?, 'open')");
                            $stmt->bind_param("isss", $user_id, $subject, $description, $priority);
                        }
                    }
                } else {
                    $error = 'ไม่พบข้อมูลผู้ใช้';
                }
            }
            
            if (isset($stmt) && $stmt && $stmt->execute()) {
                $stmt->close();
                $conn->close();
                
                // Store success message in session and redirect to prevent duplicate submission
                $_SESSION['ticket_success'] = 'เปิด Ticket สำเร็จ!';
                redirect('dashboard.php');
                exit;
            } else {
                $error = 'เกิดข้อผิดพลาดในการเปิด Ticket: ' . $conn->error . ' (Priority value: ' . htmlspecialchars($priority) . ')';

                if ($image_path && file_exists(__DIR__ . '/' . $image_path)) {
                    unlink(__DIR__ . '/' . $image_path);
                }
            }
            
            if (isset($stmt)) {
                $stmt->close();
            }
            $conn->close();
        } else {

            if ($image_path && file_exists(__DIR__ . '/' . $image_path)) {
                unlink(__DIR__ . '/' . $image_path);
            }
        }
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
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="auth-box">
            <h2> เปิด Ticket แจ้งปัญหา</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="create_ticket.php" enctype="multipart/form-data" class="auth-form">
                <div class="form-group">
                    <label for="subject">หัวข้อปัญหา *</label>
                    <input type="text" id="subject" name="subject" required 
                           value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                           placeholder="ระบุหัวข้อปัญหาที่พบ เช่น อินเทอร์เน็ตไม่ขึ้นสัญญาณ">
                </div>
                
                <div class="form-group">
                    <label for="category">หมวดหมู่ *</label>
                    <select id="category" name="category" required class="form-group input">
                        <option value="hardware" <?php echo (($_POST['category'] ?? 'other') == 'hardware') ? 'selected' : ''; ?>>ฮาร์ดแวร์ (Hardware)</option>
                        <option value="software" <?php echo (($_POST['category'] ?? 'other') == 'software') ? 'selected' : ''; ?>>ซอฟต์แวร์ (Software)</option>
                        <option value="network" <?php echo (($_POST['category'] ?? 'other') == 'network') ? 'selected' : ''; ?>>เครือข่าย (Network)</option>
                        <option value="other" <?php echo (($_POST['category'] ?? 'other') == 'other') ? 'selected' : ''; ?>>อื่นๆ (Other)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="priority">ระดับความสำคัญ *</label>
                    <select id="priority" name="priority" required class="form-group input">
                        <option value="normal" <?php echo (($_POST['priority'] ?? 'normal') == 'normal') ? 'selected' : ''; ?>>ทั่วไป</option>
                        <option value="urgent" <?php echo (($_POST['priority'] ?? 'normal') == 'urgent') ? 'selected' : ''; ?>>ด่วน</option>
                        <option value="critical" <?php echo (($_POST['priority'] ?? 'normal') == 'critical') ? 'selected' : ''; ?>>ด่วนมาก</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="location">สถานที่ *</label>
                    <input type="text" id="location" name="location" required 
                           value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                           placeholder="กรุณาระบุสถานที่ที่พบปัญหา เช่น ชั้น ห้อง"
                           class="form-group input">
                    <small style="color: #666; font-size: 0.85em;">กรุณาระบุสถานที่ที่พบปัญหา</small>
                </div>
                
                <div class="form-group">
                    <label for="description">รายละเอียดปัญหา *</label>
                    <textarea id="description" name="description" required rows="8" 
                              class="form-group input" 
                              placeholder="อธิบายรายละเอียดของปัญหาที่พบ เช่น อินเทอร์เน็ตไม่ขึ้นสัญญาณ เครื่องคอมพิวเตอร์ไม่ทำงาน อื่นๆ..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="ticket_image">แนบรูปภาพ (ไม่บังคับ)</label>
                    <input type="file" id="ticket_image" name="ticket_image" 
                           accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                           class="form-group input file-input">
                    <small>รองรับไฟล์: JPG, PNG, GIF, WEBP (ขนาดไม่เกิน 5MB)</small>
                    <div id="image-preview" class="image-preview" style="display: none; margin-top: 16px; padding: 16px; background: #f8fafc; border: 1px dashed rgba(0,0,0,0.15); border-radius: 14px;">
                        <div style="display: flex; justify-content: center;">
                            <img id="preview-img" src="" alt="Preview" style="width: 100%; max-width: 100%; max-height: 200px; border-radius: 14px; box-shadow: 0 8px 18px rgba(0,0,0,0.12); object-fit: contain; cursor: default;">
                        </div>
                       
                        <div style="text-align: center;">
                            
                            <button type="button" id="remove-image" class="btn btn-secondary btn-sm" style="margin-top: 8px;">ลบรูปภาพ</button>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block"> ส่ง Ticket</button>
            </form>

        </div>
    </div>
    <script src="<?php echo getAssetUrl('assets/js/main.js'); ?>"></script>
    <script>

        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('ticket_image');
            const imagePreview = document.getElementById('image-preview');
            const previewImg = document.getElementById('preview-img');
            const removeBtn = document.getElementById('remove-image');
            const togglePreviewBtn = document.getElementById('toggle-preview-size');
            
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {

                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('กรุณาเลือกไฟล์รูปภาพ (JPG, PNG, GIF, WEBP)');
                            fileInput.value = '';
                            return;
                        }
                        

                        if (file.size > 5 * 1024 * 1024) {
                            alert('ขนาดไฟล์ใหญ่เกินไป กรุณาเลือกไฟล์ไม่เกิน 5MB');
                            fileInput.value = '';
                            return;
                        }
                        

                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.src = e.target.result;
                            imagePreview.style.display = 'block';
                            // Disable click-to-open: remove any previous handlers
                            previewImg.onclick = null;
                            imagePreview.onclick = null;
                            if (togglePreviewBtn && imagePreview) {
                                imagePreview.classList.remove('expanded');
                                togglePreviewBtn.textContent = 'แสดงภาพเต็ม';
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    fileInput.value = '';
                    imagePreview.style.display = 'none';
                    previewImg.src = '';
                    if (togglePreviewBtn) {
                        togglePreviewBtn.textContent = 'แสดงภาพเต็ม';
                        imagePreview.classList.remove('expanded');
                    }
                });
            }

            if (togglePreviewBtn && imagePreview && previewImg) {
                togglePreviewBtn.addEventListener('click', function() {
                    const isExpanded = imagePreview.classList.toggle('expanded');
                    togglePreviewBtn.textContent = isExpanded ? 'ย่อพรีวิว' : 'แสดงภาพเต็ม';
                });
            }
        });
    </script>
</body>
</html>
