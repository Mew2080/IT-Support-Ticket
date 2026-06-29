<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$user_email = getCurrentUserEmail();
$conn = getDBConnection();

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_priority = $_GET['priority'] ?? 'all';
$allowed_priorities = ['all', 'normal', 'urgent', 'critical', 'low', 'medium', 'high'];
if (!in_array($filter_priority, $allowed_priorities)) {
    $filter_priority = 'all';
}
$filter_category = $_GET['category'] ?? 'all';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Check if tickets table has user_email column
$user_email_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'user_email'");
$has_user_email = ($user_email_check && $user_email_check->num_rows > 0);

// Check if tickets table has category column
$category_check = $conn->query("SHOW COLUMNS FROM tickets LIKE 'category'");
$has_category = ($category_check && $category_check->num_rows > 0);

$column_exists = false;
$check_result = $conn->query("SHOW COLUMNS FROM tickets LIKE 'resolved_by'");
if ($check_result && $check_result->num_rows > 0) {
    $column_exists = true;
}

// Build query with filters
$select_fields = "t.id, t.subject, t.description, t.status, t.priority, t.image_path, t.created_at, t.updated_at";
if ($has_category) {
    $select_fields .= ", t.category";
}
if ($column_exists) {
    $select_fields .= ", t.resolved_by";
}

$query = "SELECT " . $select_fields;
if ($column_exists && $has_user_email) {
    $query .= ", r.email as resolved_by_email, r.full_name as resolved_by_full_name
                FROM tickets t
                LEFT JOIN users r ON t.resolved_by = r.email
                WHERE t.user_email = ?";
} elseif ($column_exists && !$has_user_email) {
    $query .= ", r.email as resolved_by_email, r.full_name as resolved_by_full_name
                FROM tickets t
                LEFT JOIN users r ON t.resolved_by = r.id
                WHERE t.user_id = (SELECT id FROM users WHERE email = ?)";
} elseif (!$column_exists && $has_user_email) {
    $query .= " FROM tickets t WHERE t.user_email = ?";
} else {
    $query .= " FROM tickets t WHERE t.user_id = (SELECT id FROM users WHERE email = ?)";
}

$conditions = [];
$types = 's';
$params = [$user_email];

// Apply filters
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
        'critical' => ['critical', 'high'],
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

if ($has_category && $filter_category != 'all') {
    $conditions[] = "t.category = ?";
    $types .= 's';
    $params[] = $filter_category;
}

if (!empty($filter_date_from)) {
    $conditions[] = "DATE(t.created_at) >= ?";
    $types .= 's';
    $params[] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $conditions[] = "DATE(t.created_at) <= ?";
    $types .= 's';
    $params[] = $filter_date_to;
}

if (!empty($conditions)) {
    $query .= " AND " . implode(" AND ", $conditions);
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
    'normal' => '#10b981',
    'urgent' => '#f59e0b',
    'critical' => '#ef4444',

    'low' => '#10b981',
    'medium' => '#10b981',
    'high' => '#f59e0b'
];

$category_labels = [
    'hardware' => 'ฮาร์ดแวร์',
    'software' => 'ซอฟต์แวร์',
    'network' => 'เครือข่าย',
    'other' => 'อื่นๆ'
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
            <h1> Tickets ของฉัน</h1>
        </header>
        
        <main class="dashboard-content">
            
            <div class="info-card" style="margin-bottom: 20px;">
                <h2>กรอง Tickets</h2>
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="status">สถานะ:</label>
                        <select name="status" id="status" class="form-group input">
                            <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                            <option value="open" <?php echo $filter_status == 'open' ? 'selected' : ''; ?>>เปิด</option>
                            <option value="in_progress" <?php echo $filter_status == 'in_progress' ? 'selected' : ''; ?>>กำลังดำเนินการ</option>
                            <option value="closed" <?php echo ($filter_status == 'closed' || $filter_status == 'resolved') ? 'selected' : ''; ?>>ปิด</option>
                        </select>
                    </div>
                    
                    <?php if ($has_category): ?>
                    <div class="filter-group">
                        <label for="category">หมวดหมู่:</label>
                        <select name="category" id="category" class="form-group input">
                            <option value="all" <?php echo $filter_category == 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                            <option value="hardware" <?php echo $filter_category == 'hardware' ? 'selected' : ''; ?>>ฮาร์ดแวร์</option>
                            <option value="software" <?php echo $filter_category == 'software' ? 'selected' : ''; ?>>ซอฟต์แวร์</option>
                            <option value="network" <?php echo $filter_category == 'network' ? 'selected' : ''; ?>>เครือข่าย</option>
                            <option value="other" <?php echo $filter_category == 'other' ? 'selected' : ''; ?>>อื่นๆ</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <label for="priority">ระดับความสำคัญ:</label>
                        <select name="priority" id="priority" class="form-group input">
                            <option value="all" <?php echo $filter_priority == 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                            <option value="normal" <?php echo $filter_priority == 'normal' ? 'selected' : ''; ?>>ทั่วไป</option>
                            <option value="urgent" <?php echo $filter_priority == 'urgent' ? 'selected' : ''; ?>>ด่วน</option>
                            <option value="critical" <?php echo $filter_priority == 'critical' ? 'selected' : ''; ?>>ด่วนมาก</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from">วันที่เริ่มต้น:</label>
                        <input type="date" name="date_from" id="date_from" class="form-group input" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">วันที่สิ้นสุด:</label>
                        <input type="date" name="date_to" id="date_to" class="form-group input" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>
                    
                    <div class="filter-group button-actions">
                        <label aria-hidden="true">&nbsp;</label>
                        <div class="button-actions-inner">
                            <button type="submit" class="btn btn-primary">กรอง</button>
                            <a href="my_tickets.php" class="btn btn-secondary">รีเซ็ต</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if (empty($tickets)): ?>
                <div class="info-card">
                    <p style="text-align: center; color: #666; font-size: 1.1em;">
                        ไม่พบ Tickets ที่ตรงกับเงื่อนไขการกรอง<br>
                        <a href="create_ticket.php" style="color: var(--primary-color);">เปิด Ticket ใหม่</a>
                    </p>
                </div>
            <?php else: ?>
                <div class="info-card">
                    <h2> รายการ Tickets (<?php echo count($tickets); ?>)</h2>
                    <div class="tickets-list">
                        <?php foreach ($tickets as $ticket): ?>
                            <div class="ticket-item">
                                <div class="ticket-header">
                                    <div class="ticket-subject">
                                        <h3>#<?php echo $ticket['id']; ?> - <?php echo htmlspecialchars($ticket['subject']); ?></h3>
                                    </div>
                                    <div class="ticket-badges">
                                        <?php if ($has_category && isset($ticket['category'])): ?>
                                        <span class="badge badge-priority" style="background-color: #3b82f6;">
                                            <?php echo $category_labels[$ticket['category']] ?? $ticket['category']; ?>
                                        </span>
                                        <?php endif; ?>
                                        <span class="badge badge-priority" style="background-color: <?php echo $priority_colors[$ticket['priority']] ?? '#10b981'; ?>">
                                            <?php echo $priority_labels[$ticket['priority']] ?? $ticket['priority']; ?>
                                        </span>
                                        <span class="badge badge-status badge-<?php echo $ticket['status']; ?>">
                                            <?php echo $status_labels[$ticket['status']] ?? $ticket['status']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ticket-description">
                                    <p><?php echo nl2br(htmlspecialchars(substr($ticket['description'], 0, 200))); ?><?php echo strlen($ticket['description']) > 200 ? '...' : ''; ?></p>
                                </div>
                                <div class="ticket-footer">
                                    <span class="ticket-date"> สร้าง: <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></span>
                                    <?php if ($ticket['updated_at'] != $ticket['created_at']): ?>
                                        <span class="ticket-date"> อัปเดต: <?php echo date('d/m/Y H:i', strtotime($ticket['updated_at'])); ?></span>
                                    <?php endif; ?>
                                    <?php if ($ticket['status'] == 'closed' && !empty($ticket['resolved_by']) && isset($ticket['resolved_by_email'])): ?>
                                        <span class="ticket-date" style="color: var(--primary-color); font-weight: 600;">
                                            แก้ไขโดย: <?php echo htmlspecialchars($ticket['resolved_by_full_name'] ?: $ticket['resolved_by_email']); ?> (Admin)
                                        </span>
                                    <?php endif; ?>
                                    <a href="ticket_detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm"> ดูรายละเอียด</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="<?php echo getAssetUrl('assets/js/main.js'); ?>"></script>
</body>
</html>
