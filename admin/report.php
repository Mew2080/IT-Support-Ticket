<?php
require_once '../config.php';

requireAdmin();

$conn = getDBConnection();

$column_exists = false;
$check_result = $conn->query("SHOW COLUMNS FROM tickets LIKE 'resolved_by'");
if ($check_result && $check_result->num_rows > 0) {
    $column_exists = true;
}

$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$selected_admin = isset($_GET['admin']) ? intval($_GET['admin']) : 0;

if ($selected_month < 1 || $selected_month > 12) {
    $selected_month = date('m');
}
if ($selected_year < 2000 || $selected_year > 2040) {
    $selected_year = date('Y');
}

$admins_query = "SELECT id, full_name, email FROM users WHERE role = 'admin' ORDER BY full_name, email";
$admins_result = $conn->query($admins_query);
$admins = [];
if ($admins_result) {
    while ($row = $admins_result->fetch_assoc()) {
        $admins[$row['id']] = $row;
    }
}

// Get ticket counts per admin for summary
$admin_ticket_counts = [];
if ($column_exists) {
    foreach ($admins as $admin_id => $admin_info) {
        $count_query = "SELECT COUNT(*) as count 
                        FROM tickets 
                        WHERE status = 'closed' 
                        AND resolved_by = ? 
                        AND YEAR(updated_at) = ? 
                        AND MONTH(updated_at) = ?";
        $count_stmt = $conn->prepare($count_query);
        $count_stmt->bind_param("iii", $admin_id, $selected_year, $selected_month);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $admin_ticket_counts[$admin_id] = $count_row['count'];
        $count_stmt->close();
    }
}

$report_data = [];
$total_tickets = 0;

if ($column_exists) {
    $report_query = "SELECT 
                        t.id as ticket_id,
                        t.subject,
                        t.resolved_by,
                        u.full_name,
                        u.email,
                        t.updated_at
                    FROM tickets t
                    JOIN users u ON t.resolved_by = u.id
                    WHERE t.status = 'closed'
                    AND t.resolved_by IS NOT NULL
                    AND YEAR(t.updated_at) = ?
                    AND MONTH(t.updated_at) = ?";
    
    $params = [$selected_year, $selected_month];
    $types = 'ii';
    
    // Add admin filter if selected
    if ($selected_admin > 0) {
        $report_query .= " AND t.resolved_by = ?";
        $params[] = $selected_admin;
        $types .= 'i';
    }
    
    $report_query .= " ORDER BY t.updated_at DESC, u.full_name";
    
    $stmt = $conn->prepare($report_query);
    if ($selected_admin > 0) {
        $stmt->bind_param($types, $selected_year, $selected_month, $selected_admin);
    } else {
        $stmt->bind_param($types, $selected_year, $selected_month);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
        $total_tickets++;
    }
    $stmt->close();
}

// Get years from database
$db_years = [];
if ($column_exists) {
    $years_query = "SELECT DISTINCT YEAR(updated_at) as year 
                    FROM tickets 
                    WHERE status = 'closed' AND resolved_by IS NOT NULL
                    ORDER BY year DESC";
    $years_result = $conn->query($years_query);
    if ($years_result) {
        while ($row = $years_result->fetch_assoc()) {
            $db_years[] = $row['year'];
        }
    }
}

// Generate years from current year to 2040
$current_year = date('Y');
$available_years = [];
for ($year = $current_year; $year <= 2040; $year++) {
    $available_years[] = $year;
}

// Merge with database years and remove duplicates, then sort descending
$available_years = array_unique(array_merge($db_years, $available_years));
rsort($available_years);

if (empty($available_years)) {
    $available_years = [date('Y')];
}

$conn->close();

$month_names = [
    1 => 'มกราคม',
    2 => 'กุมภาพันธ์',
    3 => 'มีนาคม',
    4 => 'เมษายน',
    5 => 'พฤษภาคม',
    6 => 'มิถุนายน',
    7 => 'กรกฎาคม',
    8 => 'สิงหาคม',
    9 => 'กันยายน',
    10 => 'ตุลาคม',
    11 => 'พฤศจิกายน',
    12 => 'ธันวาคม'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปรายงาน Tickets - SERVICE ENGINEERING</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../img/logo.png">
    <link rel="shortcut icon" type="image/png" href="../img/logo.png">
    <link rel="stylesheet" href="<?php echo getAssetUrl('../assets/css/style.css'); ?>">
    <style>
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-group.button-group {
            gap: 0;
            align-items: flex-end;
        }
        .filter-group.button-group > div {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-group label {
            font-weight: 500;
            color: var(--text-dark);
        }
        .report-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            font-size: 0.9em;
            opacity: 0.9;
            font-weight: 400;
        }
        .summary-card .value {
            font-size: 1.8em;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }
        .report-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .report-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .report-table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        .report-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        .report-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        .report-table tbody tr:hover {
            background-color: #f9fafb;
        }
        .report-table tbody tr:last-child td {
            border-bottom: none;
        }
        .admin-name {
            font-weight: 500;
            color: var(--text-dark);
        }
        .admin-username {
            color: var(--text-light);
            font-size: 0.9em;
        }
        .ticket-count {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--primary-color);
        }
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }
        .no-data-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        @media (max-width: 768px) {
            .report-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .filter-form {
                width: 100%;
            }
            .filter-group {
                flex: 1;
                min-width: 150px;
            }
            .report-table {
                overflow-x: auto;
            }
            .report-table table {
                min-width: 500px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../navbar.php'; ?>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <h1>ADMIN TICKET PANEL</h1>
            <div class="user-info">
                <a href="tickets.php" class="btn btn-secondary btn-sm" style="background: white; color: #1f2937; border: 1px solid rgba(0, 0, 0, 0.1); margin-bottom: 10px;">จัดการ Tickets</a>
                <a href="users.php" class="btn btn-secondary btn-sm" style="background: white; color: #1f2937; border: 1px solid rgba(0, 0, 0, 0.1); margin-bottom: 10px;">จัดการ Users</a>
                <a href="../dashboard.php" class="btn btn-secondary btn-sm" style="background: white; color: #1f2937; border: 1px solid rgba(0, 0, 0, 0.1); margin-bottom: 10px;">หน้าแรก</a>
            </div>
        </header>
        
        <main class="dashboard-content">
            <div class="report-container">
                
                <div class="filter-card">
                    <h2 style="margin-top: 0; margin-bottom: 20px;">กรองรายงาน</h2>
                    <form method="GET" class="filter-form">
                        <div class="filter-group">
                            <label>ปี:</label>
                            <select name="year" class="form-group input">
                                <?php foreach ($available_years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $selected_year == $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>เดือน:</label>
                            <select name="month" class="form-group input">
                                <?php foreach ($month_names as $month_num => $month_name): ?>
                                    <option value="<?php echo $month_num; ?>" <?php echo $selected_month == $month_num ? 'selected' : ''; ?>>
                                        <?php echo $month_name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>แอดมิน:</label>
                            <select name="admin" class="form-group input">
                                <option value="0" <?php echo $selected_admin == 0 ? 'selected' : ''; ?>>ทั้งหมด</option>
                                <?php foreach ($admins as $admin_id => $admin_info): ?>
                                    <option value="<?php echo $admin_id; ?>" <?php echo $selected_admin == $admin_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($admin_info['full_name'] ?: $admin_info['email']); ?> 
                                        (<?php echo isset($admin_ticket_counts[$admin_id]) ? $admin_ticket_counts[$admin_id] : 0; ?> Tickets)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group button-group">
                            <div>
                                <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-weight: 600; border-radius: 8px; white-space: nowrap; min-width: 140px;">แสดงรายงาน</button>
                                <a href="report.php" class="btn btn-secondary" style="padding: 12px 28px; font-weight: 600; border-radius: 8px; white-space: nowrap; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; min-width: 100px;">รีเซ็ต</a>
                                <a href="export_pdf.php?year=<?php echo $selected_year; ?>&month=<?php echo $selected_month; ?><?php echo $selected_admin > 0 ? '&admin=' . $selected_admin : ''; ?>" 
                                   class="btn btn-secondary" 
                                   style="padding: 12px 28px; font-weight: 600; border-radius: 8px; white-space: nowrap; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; min-width: 120px;"
                                   target="_blank">
                                    Export PDF
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                
                

                
                <div class="report-summary">
                    <div class="summary-card">
                        <h3>เดือนที่เลือก</h3>
                        <p class="value"><?php echo $month_names[$selected_month]; ?></p>
                        <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 0.9em;"><?php echo $selected_year; ?></p>
                    </div>
                    <div class="summary-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
                        <h3>จำนวน Tickets ที่ปิดทั้งหมด</h3>
                        <p class="value"><?php echo number_format($total_tickets); ?></p>
                    </div>
                    <div class="summary-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);">
                        <h3><?php echo $selected_admin > 0 ? 'แอดมินที่เลือก' : 'จำนวนแอดมินที่ปิด Tickets'; ?></h3>
                        <p class="value"><?php 
                            if ($selected_admin > 0) {
                                $selected_admin_info = $admins[$selected_admin] ?? null;
                                if ($selected_admin_info) {
                                    echo htmlspecialchars($selected_admin_info['full_name'] ?: $selected_admin_info['email']);
                                } else {
                                    echo 'ไม่พบข้อมูล';
                                }
                            } else {
                                $unique_admins = [];
                                foreach ($report_data as $row) {
                                    if (!in_array($row['resolved_by'], $unique_admins)) {
                                        $unique_admins[] = $row['resolved_by'];
                                    }
                                }
                                echo count($unique_admins);
                            }
                        ?></p>
                        <?php if ($selected_admin > 0): ?>
                            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 0.9em;">จำนวน Tickets: <?php echo number_format($total_tickets); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                
                <div class="report-table">
                    <?php if (!$column_exists): ?>
                        <div class="no-data">
                            <div class="no-data-icon">⚠️</div>
                            <h3>ไม่พบ Column resolved_by</h3>
                            <p>กรุณารันไฟล์ <code>add_resolved_by_column.php</code> เพื่อเพิ่ม column resolved_by ในตาราง tickets</p>
                        </div>
                    <?php elseif (empty($report_data)): ?>
                        <div class="no-data">
                            <div class="no-data-icon">📊</div>
                            <h3>ไม่มีข้อมูล</h3>
                            <p>ไม่พบ Tickets ที่ปิดโดยแอดมินในเดือน <?php echo $month_names[$selected_month]; ?> <?php echo $selected_year; ?></p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th style="width: 80px;">Ticket ID</th>
                                    <th>หัวข้อ Ticket</th>
                                    <th style="width: 200px;">ชื่อแอดมิน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                foreach ($report_data as $row): 
                                ?>
                                    <tr>
                                        <td style="color: var(--text-light); text-align: center;"><?php echo $rank++; ?></td>
                                        <td style="text-align: center;">
                                            <a href="ticket_detail.php?id=<?php echo $row['ticket_id']; ?>" 
                                               style="color: var(--primary-color); text-decoration: none; font-weight: 600;">
                                                #<?php echo $row['ticket_id']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500; color: var(--text-dark);">
                                                <?php echo htmlspecialchars($row['subject']); ?>
                                            </div>
                                            <div style="font-size: 0.85em; color: var(--text-light); margin-top: 4px;">
                                                แก้ไขเมื่อ: <?php echo date('d/m/Y H:i', strtotime($row['updated_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="admin-name">
                                                <?php echo htmlspecialchars($row['full_name'] ?: $row['email']); ?>
                                            </div>
                                            <div class="admin-username">
                                                <?php echo htmlspecialchars($row['email']); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="<?php echo getAssetUrl('../assets/js/main.js'); ?>"></script>
</body>
</html>
