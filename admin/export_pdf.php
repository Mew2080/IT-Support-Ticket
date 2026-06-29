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
                    AND MONTH(t.updated_at) = ?
                    ORDER BY t.updated_at DESC, u.full_name";
    
    $stmt = $conn->prepare($report_query);
    $stmt->bind_param("ii", $selected_year, $selected_month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
        $total_tickets++;
    }
    $stmt->close();
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

$use_mpdf = false;
$mpdf = null;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once(__DIR__ . '/../vendor/autoload.php');
    if (class_exists('\Mpdf\Mpdf')) {
        $use_mpdf = true;
    }
}

if ($use_mpdf) {
    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => sys_get_temp_dir()
        ]);
        

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: 'Sarabun', 'Kanit', 'Noto Sans Thai', sans-serif;
                    font-size: 12pt;
                    line-height: 1.6;
                    color: #333;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 3px solid #10b981;
                    padding-bottom: 15px;
                }
                .header h1 {
                    margin: 0;
                    font-size: 20pt;
                    color: #10b981;
                    font-weight: bold;
                }
                .header .date {
                    margin-top: 10px;
                    font-size: 12pt;
                    color: #666;
                }
                .summary {
                    background: #f9fafb;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .summary h2 {
                    margin: 0 0 10px 0;
                    font-size: 14pt;
                    color: #10b981;
                    font-weight: bold;
                }
                .summary-item {
                    margin: 5px 0;
                    font-size: 11pt;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 15px;
                }
                th {
                    background: #10b981;
                    color: white;
                    padding: 10px;
                    text-align: left;
                    font-weight: bold;
                    border: 1px solid #059669;
                    font-size: 11pt;
                }
                th:nth-child(1) {
                    width: 30px;
                    text-align: center;
                }
                th:nth-child(2) {
                    width: 70px;
                    text-align: center;
                }
                th:nth-child(4) {
                    width: 150px;
                }
                td {
                    padding: 8px 10px;
                    border: 1px solid #e5e7eb;
                    font-size: 10pt;
                }
                tr:nth-child(even) {
                    background: #f9fafb;
                }
                td:nth-child(1), td:nth-child(2) {
                    text-align: center;
                }
                .admin-name {
                    font-weight: 500;
                }
                .admin-username {
                    color: #666;
                    font-size: 9pt;
                }
                .ticket-count {
                    font-weight: bold;
                    color: #10b981;
                    font-size: 12pt;
                }
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 9pt;
                    color: #666;
                    border-top: 1px solid #e5e7eb;
                    padding-top: 10px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>สรุปรายงาน Tickets แอดมิน</h1>
                <div class="date">
                    เดือน: <?php echo $month_names[$selected_month]; ?> <?php echo $selected_year; ?><br>
                    วันที่พิมพ์: <?php echo date('d/m/Y H:i'); ?>
                </div>
            </div>
            
            <div class="summary">
                <h2>สรุปยอดรวม</h2>
                <div class="summary-item"><strong>จำนวน Tickets ที่ปิดทั้งหมด:</strong> <?php echo number_format($total_tickets); ?></div>
                <div class="summary-item"><strong>จำนวนแอดมินที่ปิด Tickets:</strong> <?php 
                    $unique_admins = [];
                    foreach ($report_data as $row) {
                        if (!in_array($row['resolved_by'], $unique_admins)) {
                            $unique_admins[] = $row['resolved_by'];
                        }
                    }
                    echo count($unique_admins); 
                ?></div>
            </div>
            
            <?php if (empty($report_data)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>ไม่พบข้อมูลในเดือน <?php echo $month_names[$selected_month]; ?> <?php echo $selected_year; ?></p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Ticket ID</th>
                            <th>หัวข้อ Ticket</th>
                            <th>ชื่อแอดมิน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($report_data as $row): 
                        ?>
                            <tr>
                                <td style="text-align: center;"><?php echo $rank++; ?></td>
                                <td style="text-align: center;">#<?php echo $row['ticket_id']; ?></td>
                                <td>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($row['subject']); ?></div>
                                    <div style="font-size: 8pt; color: #666; margin-top: 2px;">
                                        แก้ไขเมื่อ: <?php echo date('d/m/Y H:i', strtotime($row['updated_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="admin-name"><?php echo htmlspecialchars($row['full_name'] ?: $row['email']); ?></div>
                                    <div class="admin-username"><?php echo htmlspecialchars($row['email']); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div class="footer">
                <p>พิมพ์จากระบบ SERVICE ENGINEERING Ticket System</p>
            </div>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        
        $mpdf->WriteHTML($html);
        

        $filename = 'รายงาน_Tickets_' . $selected_year . '_' . sprintf('%02d', $selected_month) . '.pdf';
        $mpdf->Output($filename, 'D'); 
        exit;
        
    } catch (Exception $e) {

        $use_mpdf = false;
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปรายงาน Tickets - <?php echo $month_names[$selected_month]; ?> <?php echo $selected_year; ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body {
            font-family: 'Sarabun', 'Kanit', 'Noto Sans Thai', sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background: #f3f4f6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .download-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14pt;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            z-index: 1000;
            font-family: 'Sarabun', 'Kanit', 'Noto Sans Thai', sans-serif;
        }
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
        }
        .download-btn:active {
            transform: translateY(0);
        }
        #pdf-content {
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #10b981;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 24pt;
            color: #10b981;
        }
        .header .date {
            margin-top: 10px;
            font-size: 14pt;
            color: #666;
        }
        .summary {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .summary h2 {
            margin: 0 0 10px 0;
            font-size: 16pt;
            color: #10b981;
        }
        .summary-item {
            margin: 5px 0;
            font-size: 12pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background: #10b981;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #059669;
        }
        th:nth-child(1) {
            width: 50px;
            text-align: center;
        }
        th:nth-child(2) {
            width: 80px;
            text-align: center;
        }
        th:nth-child(4) {
            width: 200px;
        }
        td {
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        td:nth-child(1), td:nth-child(2) {
            text-align: center;
        }
        .admin-name {
            font-weight: 500;
        }
        .admin-username {
            color: #666;
            font-size: 10pt;
        }
        .ticket-count {
            font-weight: 700;
            color: #10b981;
            font-size: 14pt;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10pt;
            color: #666;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
        }
        @media print {
            body {
                padding: 0;
                background: white;
            }
            .download-btn {
                display: none;
            }
            .container {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <button class="download-btn" onclick="downloadPDF()">ดาวน์โหลด PDF</button>
    
    <div class="container">
        <div id="pdf-content">
            <div class="header">
                <h1>สรุปรายงาน Tickets แอดมิน</h1>
                <div class="date">
                    เดือน: <?php echo $month_names[$selected_month]; ?> <?php echo $selected_year; ?><br>
                    วันที่พิมพ์: <?php echo date('d/m/Y H:i'); ?>
                </div>
            </div>
            
            <div class="summary">
                <h2>สรุปยอดรวม</h2>
                <div class="summary-item"><strong>จำนวน Tickets ที่ปิดทั้งหมด:</strong> <?php echo number_format($total_tickets); ?></div>
                <div class="summary-item"><strong>จำนวนแอดมินที่ปิด Tickets:</strong> <?php 
                    $unique_admins = [];
                    foreach ($report_data as $row) {
                        if (!in_array($row['resolved_by'], $unique_admins)) {
                            $unique_admins[] = $row['resolved_by'];
                        }
                    }
                    echo count($unique_admins); 
                ?></div>
            </div>
            
            <?php if (empty($report_data)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>ไม่พบข้อมูลในเดือน <?php echo $month_names[$selected_month]; ?> <?php echo $selected_year; ?></p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Ticket ID</th>
                            <th>หัวข้อ Ticket</th>
                            <th>ชื่อแอดมิน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($report_data as $row): 
                        ?>
                            <tr>
                                <td style="text-align: center;"><?php echo $rank++; ?></td>
                                <td style="text-align: center;">#<?php echo $row['ticket_id']; ?></td>
                                <td>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($row['subject']); ?></div>
                                    <div style="font-size: 0.85em; color: #666; margin-top: 4px;">
                                        แก้ไขเมื่อ: <?php echo date('d/m/Y H:i', strtotime($row['updated_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="admin-name"><?php echo htmlspecialchars($row['full_name'] ?: $row['email']); ?></div>
                                    <div class="admin-username"><?php echo htmlspecialchars($row['email']); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div class="footer">
                <p>พิมพ์จากระบบ SERVICE ENGINEERING Ticket System</p>
            </div>
        </div>
    </div>
    
    <script>
        function downloadPDF() {
            const button = document.querySelector('.download-btn');
            button.textContent = 'กำลังสร้าง PDF...';
            button.disabled = true;
            
            const element = document.getElementById('pdf-content');
            const filename = 'รายงาน_Tickets_<?php echo $selected_year; ?>_<?php echo sprintf('%02d', $selected_month); ?>.pdf';
            
            const opt = {
                margin: [15, 15, 15, 15],
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    letterRendering: true
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait',
                    compress: true
                }
            };
            
            html2pdf().set(opt).from(element).save().then(function() {
                button.textContent = 'ดาวน์โหลด PDF';
                button.disabled = false;
            }).catch(function(error) {
                console.error('Error generating PDF:', error);
                alert('เกิดข้อผิดพลาดในการสร้าง PDF กรุณาลองใหม่อีกครั้ง');
                button.textContent = 'ดาวน์โหลด PDF';
                button.disabled = false;
            });
        }
    </script>
</body>
</html>
