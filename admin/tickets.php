<?php
require_once '../config.php';

requireAdmin();

$conn = getDBConnection();

if (isset($_SESSION['ticket_deleted'])) {
    $success = $_SESSION['ticket_deleted'];
    unset($_SESSION['ticket_deleted']);
}

// Handle assign ticket
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_ticket'])) {
    $ticket_id = intval($_POST['ticket_id']);
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
        redirect('tickets.php');
    } else {
        $error = 'เกิดข้อผิดพลาดในการรับงาน: ' . $conn->error;
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $new_status = $_POST['status'];
    $admin_id = getCurrentUserId();
    

    $column_exists = false;
    $check_result = $conn->query("SHOW COLUMNS FROM tickets LIKE 'resolved_by'");
    if ($check_result && $check_result->num_rows > 0) {
        $column_exists = true;
    }
    

    if ($new_status == 'closed' && $column_exists) {
        $stmt = $conn->prepare("UPDATE tickets SET status = ?, resolved_by = ? WHERE id = ?");
        $stmt->bind_param("sii", $new_status, $admin_id, $ticket_id);
    } else {
        $stmt = $conn->prepare("UPDATE tickets SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $ticket_id);
    }
    
    $stmt->execute();
    $stmt->close();
}

// Check if assigned_to column exists, if not create it
$assigned_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'assigned_to'");
$has_assigned_to = ($assigned_check && $assigned_check->num_rows > 0);

if (!$has_assigned_to) {
    // Create assigned_to column
    $conn->query("ALTER TABLE tickets ADD COLUMN assigned_to VARCHAR(255) NULL");
    $has_assigned_to = true;
}

$filter_status = $_GET['status'] ?? 'all';
$filter_priority = $_GET['priority'] ?? 'all';
$allowed_priorities = ['all', 'normal', 'urgent', 'critical', 'low', 'medium', 'high'];
if (!in_array($filter_priority, $allowed_priorities)) {
    $filter_priority = 'all';
}
$admin_email = getCurrentUserEmail();

$query = "SELECT t.id, t.subject, t.description, t.status, t.priority, t.image_path, t.created_at, t.updated_at, 
          u.email, u.full_name";
          
if ($has_assigned_to) {
    $query .= ", t.assigned_to";
}

$query .= " FROM tickets t 
          JOIN users u ON t.user_email = u.email";
          
$conditions = [];
$types = '';
$params = [];

// Filter: Show only tickets assigned to current admin or unassigned tickets
if ($has_assigned_to) {
    $conditions[] = "(t.assigned_to IS NULL OR t.assigned_to = ?)";
    $types .= 's';
    $params[] = $admin_email;
}

if ($filter_status != 'all') {

    if ($filter_status == 'closed') {
        $conditions[] = "(t.status = 'closed' OR t.status = 'resolved')";
    } else {
        $conditions[] = "t.status = ?";
        $types .= 's';
        $params[] = $filter_status;
    }
}

if ($filter_priority != 'all') {
    // Support legacy priority values (low/medium/high) and new ones (normal/urgent/critical)
    $priority_filters = [
        'normal' => ['normal', 'low', 'medium'],
        'urgent' => ['urgent', 'high'],
        'critical' => ['critical'],
        'low' => ['low'],
        'medium' => ['medium'],
        'high' => ['high']
    ];
    $targets = $priority_filters[$filter_priority] ?? [$filter_priority];
    $placeholders = implode(',', array_fill(0, count($targets), '?'));
    $conditions[] = "t.priority IN ($placeholders)";
    $types .= str_repeat('s', count($targets));
    array_push($params, ...$targets);
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result->fetch_all(MYSQLI_ASSOC);
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
            <h1>ADMIN TICKET PANEL (<?php echo count($tickets); ?>)</h1>
            <div class="user-info">
                <a href="report.php" class="btn btn-secondary btn-sm" style="background: white; color: #1f2937; border: 1px solid rgba(0, 0, 0, 0.1); margin-bottom: 10px;">สรุปรายงาน</a>
                <a href="users.php" class="btn btn-secondary btn-sm" style="background: white; color: #1f2937; border: 1px solid rgba(0, 0, 0, 0.1); margin-bottom: 10px;">จัดการ Users</a>
                <a href="../dashboard.php" class="btn btn-secondary btn-sm" style="background: white; color: #1f2937; border: 1px solid rgba(0, 0, 0, 0.1); margin-bottom: 10px;">หน้าแรก</a>
            </div>
        </header>
        
        <main class="dashboard-content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            
            <div class="info-card">
                <h2>กรอง Tickets</h2>
                <form method="GET" class="filter-form" style="align-items: start;">
                    <div class="filter-group">
                        <label>สถานะ:</label>
                        <select name="status" class="form-group input">
                            <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                            <option value="open" <?php echo $filter_status == 'open' ? 'selected' : ''; ?>>เปิด</option>
                            <option value="in_progress" <?php echo $filter_status == 'in_progress' ? 'selected' : ''; ?>>กำลังดำเนินการ</option>
                            <option value="closed" <?php echo ($filter_status == 'closed' || $filter_status == 'resolved') ? 'selected' : ''; ?>>ปิด</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>ความสำคัญ:</label>
                        <select name="priority" class="form-group input">
                            <option value="all" <?php echo $filter_priority == 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                            <option value="normal" <?php echo $filter_priority == 'normal' ? 'selected' : ''; ?>>ทั่วไป</option>
                            <option value="urgent" <?php echo $filter_priority == 'urgent' ? 'selected' : ''; ?>>ด่วน</option>
                            <option value="critical" <?php echo $filter_priority == 'critical' ? 'selected' : ''; ?>>ด่วนมาก</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: flex-end; padding-top: 27px;">
                        <button type="submit" class="btn btn-primary">กรอง</button>
                        <a href="tickets.php" class="btn btn-secondary">รีเซ็ต</a>
                    </div>
                </form>
            </div>
            
            
            <div class="info-card">
                <h2>รายการ Tickets</h2>
                <?php if (empty($tickets)): ?>
                    <p style="text-align: center; color: #666; font-size: 1.1em;">ไม่มี Tickets</p>
                <?php else: ?>
                    <div class="tickets-list">
                        <?php foreach ($tickets as $ticket): ?>
                            <div class="ticket-item">
                                <div class="ticket-header">
                                    <div class="ticket-subject">
                                        <h3>
                                            <a href="ticket_detail.php?id=<?php echo $ticket['id']; ?>" style="color: inherit; text-decoration: none;">
                                                #<?php echo $ticket['id']; ?> - <?php echo htmlspecialchars($ticket['subject']); ?>
                                            </a>
                                        </h3>
                                        <p class="ticket-user"><?php echo htmlspecialchars($ticket['full_name'] ?: $ticket['email']); ?> (<?php echo htmlspecialchars($ticket['email']); ?>)</p>
                                    </div>
                                    <div class="ticket-badges">
                                        <?php if ($has_assigned_to && !empty($ticket['assigned_to'])): ?>
                                            <?php if ($ticket['assigned_to'] === $admin_email): ?>
                                                <span class="badge" style="background-color: #10b981; color: white;">✓ รับงานแล้ว</span>
                                            <?php else: ?>
                                                <span class="badge" style="background-color: #f59e0b; color: white;">รับงานโดย: <?php echo htmlspecialchars($ticket['assigned_to']); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge" style="background-color: #6b7280; color: white;">ยังไม่รับงาน</span>
                                        <?php endif; ?>
                                        <span class="badge badge-priority" style="background-color: <?php echo $priority_colors[$ticket['priority']]; ?>">
                                            <?php echo $priority_labels[$ticket['priority']]; ?>
                                        </span>
                                        <span class="badge badge-status badge-<?php echo $ticket['status']; ?>">
                                            <?php echo $status_labels[$ticket['status']]; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ticket-description">
                                    <p><?php echo nl2br(htmlspecialchars(substr($ticket['description'], 0, 200))); ?><?php echo strlen($ticket['description']) > 200 ? '...' : ''; ?></p>
                                </div>
                                <div class="ticket-footer">
                                    <span class="ticket-date">สร้าง: <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></span>
                                    <?php if ($ticket['updated_at'] != $ticket['created_at']): ?>
                                        <span class="ticket-date">อัปเดต: <?php echo date('d/m/Y H:i', strtotime($ticket['updated_at'])); ?></span>
                                    <?php endif; ?>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <?php if ($has_assigned_to && empty($ticket['assigned_to']) && $ticket['status'] != 'closed' && $ticket['status'] != 'resolved'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                <input type="hidden" name="assign_ticket" value="1">
                                                <button type="submit" class="btn btn-primary btn-sm">รับงาน</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($has_assigned_to && !empty($ticket['assigned_to']) && $ticket['assigned_to'] === $admin_email): ?>
                                            <a href="ticket_detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">ดูรายละเอียด</a>
                                        <?php elseif ($has_assigned_to && empty($ticket['assigned_to'])): ?>
                                            <a href="ticket_detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-secondary btn-sm">ดูรายละเอียด</a>
                                        <?php else: ?>
                                            <span class="btn btn-secondary btn-sm" style="opacity: 0.6; cursor: not-allowed;">ดูรายละเอียด</span>
                                        <?php endif; ?>
                                        <form method="POST" action="delete_ticket.php" onsubmit="return confirmDeleteTicket(<?php echo $ticket['id']; ?>, '<?php echo htmlspecialchars($ticket['subject'], ENT_QUOTES); ?>');" style="display: inline;">
                                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                            <button type="submit" class="btn btn-sm" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;">ลบ</button>
                                        </form>
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
    <script>

        function confirmDeleteTicket(ticketId, ticketSubject) {
            return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบ Ticket #' + ticketId + ' - ' + ticketSubject + '?\n\nการลบ Ticket จะลบข้อมูลทั้งหมดรวมถึงการตอบกลับและรูปภาพที่เกี่ยวข้อง\nไม่สามารถกู้คืนได้');
        }
    </script>
</body>
</html>
