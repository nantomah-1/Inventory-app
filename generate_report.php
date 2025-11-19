<?php
// generate_report.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Get report type
$report_type = $_GET['type'] ?? 'summary';

// Get data for reports
$total_assets = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
$total_value = $pdo->query("SELECT SUM(current_value) as total_value FROM assets")->fetchColumn();

// Get assets by type
$assets_by_type = $pdo->query("
    SELECT asset_type, COUNT(*) as count, 
           SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
           SUM(CASE WHEN status = 'In Use' THEN 1 ELSE 0 END) as in_use,
           SUM(CASE WHEN status = 'Under Maintenance' THEN 1 ELSE 0 END) as maintenance,
           SUM(CASE WHEN status = 'Retired' THEN 1 ELSE 0 END) as retired,
           SUM(current_value) as total_value
    FROM assets 
    GROUP BY asset_type 
    ORDER BY count DESC
")->fetchAll();

// Get assets by status
$assets_by_status = $pdo->query("
    SELECT status, COUNT(*) as count, SUM(current_value) as total_value
    FROM assets 
    GROUP BY status 
    ORDER BY count DESC
")->fetchAll();

// Get all assets for detailed report
$all_assets = $pdo->query("
    SELECT a.*, 
           (SELECT COUNT(*) FROM maintenance_records mr WHERE mr.asset_id = a.id) as maintenance_count,
           (SELECT MAX(maintenance_date) FROM maintenance_records mr WHERE mr.asset_id = a.id) as last_maintenance_date
    FROM assets a 
    ORDER BY a.asset_type, a.asset_name
")->fetchAll();

// Get maintenance statistics
$maintenance_stats = $pdo->query("
    SELECT 
        COUNT(*) as total_records,
        SUM(cost) as total_cost,
        AVG(cost) as avg_cost,
        maintenance_type,
        COUNT(*) as type_count
    FROM maintenance_records 
    GROUP BY maintenance_type
")->fetchAll();

switch ($report_type) {
    case 'summary':
        generatePDFSummary();
        break;
    case 'detailed':
        generateExcelDetailed();
        break;
    case 'categorical':
        generateCSVCategorical();
        break;
    default:
        generatePDFSummary();
        break;
}

function generatePDFSummary() {
    global $total_assets, $total_value, $assets_by_type, $assets_by_status, $maintenance_stats;
    
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="NaconM_ICT_Lab_Asset_Summary_Report_' . date('Y-m-d') . '.pdf"');
    
    // Create simple PDF content (in a real application, you'd use a library like TCPDF or Dompdf)
    $pdf_content = generatePDFContent();
    echo $pdf_content;
    exit;
}

function generatePDFContent() {
    global $total_assets, $total_value, $assets_by_type, $assets_by_status, $maintenance_stats;
    
    $content = "%PDF-1.4\n";
    $content .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $content .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $content .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n";
    $content .= "4 0 obj\n<< /Length 1000 >>\nstream\n";
    
    // Simple PDF content - in production, use a proper PDF library
    $text = "NACONM ICT LAB - ASSET MANAGEMENT REPORT\n";
    $text .= "Generated on: " . date('F j, Y') . "\n\n";
    $text .= "SUMMARY STATISTICS\n";
    $text .= "Total Assets: " . $total_assets . "\n";
    $text .= "Total Asset Value: ₵" . number_format($total_value, 2) . "\n\n";
    
    $text .= "ASSETS BY CATEGORY\n";
    $text .= "=================================\n";
    foreach ($assets_by_type as $type) {
        $percentage = ($type['count'] / $total_assets) * 100;
        $text .= sprintf("%-20s: %3d (%5.1f%%) - Available: %2d, In Use: %2d, Maintenance: %2d, Retired: %2d\n",
            $type['asset_type'], $type['count'], $percentage, 
            $type['available'], $type['in_use'], $type['maintenance'], $type['retired']);
    }
    
    $text .= "\nASSETS BY STATUS\n";
    $text .= "=================================\n";
    foreach ($assets_by_status as $status) {
        $percentage = ($status['count'] / $total_assets) * 100;
        $text .= sprintf("%-20s: %3d (%5.1f%%) - Value: ₵%s\n",
            $status['status'], $status['count'], $percentage, 
            number_format($status['total_value'], 2));
    }
    
    $text .= "\nMAINTENANCE STATISTICS\n";
    $text .= "=================================\n";
    foreach ($maintenance_stats as $stat) {
        $text .= sprintf("%-15s: %2d records, Total Cost: ₵%s\n",
            $stat['maintenance_type'], $stat['type_count'], 
            number_format($stat['total_cost'], 2));
    }
    
    // Convert text to PDF stream (simplified)
    $content .= "BT /F1 12 Tf 50 750 Td (" . $text . ") Tj ET\n";
    $content .= "endstream\nendobj\n";
    $content .= "xref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000234 00000 n \n";
    $content .= "trailer\n<< /Size 5 /Root 1 0 R >>\n";
    $content .= "startxref\n" . strlen($content) . "\n%%EOF";
    
    return $content;
}

function generateExcelDetailed() {
    global $all_assets, $total_assets, $total_value;
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="NaconM_ICT_Lab_Detailed_Assets_' . date('Y-m-d') . '.xls"');
    
    // Excel header
    echo "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns=\"http://www.w3.org/TR/REC-html40\">";
    echo "<head>";
    echo "<meta name=ProgId content=Excel.Sheet>";
    echo "<meta name=Generator content=\"Microsoft Excel 11\">";
    echo "<style>";
    echo "td { mso-number-format:\\@; }";
    echo ".header { background-color: #2c3e50; color: white; font-weight: bold; }";
    echo ".summary { background-color: #ecf0f1; font-weight: bold; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    // Report Header
    echo "<table border='1' cellpadding='3' style='border-collapse: collapse;'>";
    echo "<tr><td colspan='12' class='header' style='text-align: center; font-size: 16px;'>NACONM ICT LAB - DETAILED ASSET REPORT</td></tr>";
    echo "<tr><td colspan='12' class='summary'>Generated on: " . date('F j, Y') . "</td></tr>";
    echo "<tr><td colspan='12' class='summary'>Total Assets: " . $total_assets . " | Total Value: ₵" . number_format($total_value, 2) . "</td></tr>";
    echo "<tr><td colspan='12'></td></tr>";
    
    // Column Headers
    echo "<tr class='header'>";
    echo "<th>ID</th>";
    echo "<th>Asset Name</th>";
    echo "<th>Type</th>";
    echo "<th>Serial No</th>";
    echo "<th>Brand</th>";
    echo "<th>Model</th>";
    echo "<th>Status</th>";
    echo "<th>Location</th>";
    echo "<th>Assigned To</th>";
    echo "<th>Purchase Date</th>";
    echo "<th>Current Value (₵)</th>";
    echo "<th>Maintenance Count</th>";
    echo "</tr>";
    
    // Data Rows
    $current_type = '';
    foreach ($all_assets as $asset) {
        if ($current_type != $asset['asset_type']) {
            $current_type = $asset['asset_type'];
            echo "<tr class='summary'><td colspan='12'>" . htmlspecialchars($current_type) . "</td></tr>";
        }
        
        echo "<tr>";
        echo "<td>" . $asset['id'] . "</td>";
        echo "<td>" . htmlspecialchars($asset['asset_name']) . "</td>";
        echo "<td>" . htmlspecialchars($asset['asset_type']) . "</td>";
        echo "<td>" . htmlspecialchars($asset['serial_number']) . "</td>";
        echo "<td>" . htmlspecialchars($asset['brand']) . "</td>";
        echo "<td>" . htmlspecialchars($asset['model']) . "</td>";
        echo "<td>" . $asset['status'] . "</td>";
        echo "<td>" . htmlspecialchars($asset['location']) . "</td>";
        echo "<td>" . htmlspecialchars($asset['assigned_to']) . "</td>";
        echo "<td>" . ($asset['purchase_date'] ? date('M j, Y', strtotime($asset['purchase_date'])) : 'N/A') . "</td>";
        echo "<td>₵" . number_format($asset['current_value'], 2) . "</td>";
        echo "<td>" . $asset['maintenance_count'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</body></html>";
    exit;
}

function generateCSVCategorical() {
    global $assets_by_type, $assets_by_status, $total_assets, $total_value;
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="NaconM_ICT_Lab_Category_Report_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    // Report Header
    fputcsv($output, ['NACONM ICT LAB - CATEGORICAL ASSET REPORT']);
    fputcsv($output, ['Generated on:', date('F j, Y')]);
    fputcsv($output, ['Total Assets:', $total_assets]);
    fputcsv($output, ['Total Asset Value:', '₵' . number_format($total_value, 2)]);
    fputcsv($output, []); // Empty line
    
    // Assets by Category
    fputcsv($output, ['ASSETS BY CATEGORY']);
    fputcsv($output, ['Category', 'Total Count', 'Available', 'In Use', 'Under Maintenance', 'Retired', 'Total Value (₵)', 'Percentage']);
    
    foreach ($assets_by_type as $type) {
        $percentage = ($type['count'] / $total_assets) * 100;
        fputcsv($output, [
            $type['asset_type'],
            $type['count'],
            $type['available'],
            $type['in_use'],
            $type['maintenance'],
            $type['retired'],
            number_format($type['total_value'], 2),
            number_format($percentage, 1) . '%'
        ]);
    }
    
    fputcsv($output, []); // Empty line
    
    // Assets by Status
    fputcsv($output, ['ASSETS BY STATUS']);
    fputcsv($output, ['Status', 'Count', 'Total Value (₵)', 'Percentage']);
    
    foreach ($assets_by_status as $status) {
        $percentage = ($status['count'] / $total_assets) * 100;
        fputcsv($output, [
            $status['status'],
            $status['count'],
            number_format($status['total_value'], 2),
            number_format($percentage, 1) . '%'
        ]);
    }
    
    fputcsv($output, []); // Empty line
    
    // Summary Statistics
    fputcsv($output, ['SUMMARY STATISTICS']);
    
    $available_count = 0;
    $in_use_count = 0;
    $maintenance_count = 0;
    $retired_count = 0;
    
    foreach ($assets_by_status as $status) {
        switch ($status['status']) {
            case 'Available': $available_count = $status['count']; break;
            case 'In Use': $in_use_count = $status['count']; break;
            case 'Under Maintenance': $maintenance_count = $status['count']; break;
            case 'Retired': $retired_count = $status['count']; break;
        }
    }
    
    fputcsv($output, ['Available Assets:', $available_count, number_format(($available_count/$total_assets)*100, 1) . '%']);
    fputcsv($output, ['Assets In Use:', $in_use_count, number_format(($in_use_count/$total_assets)*100, 1) . '%']);
    fputcsv($output, ['Assets Under Maintenance:', $maintenance_count, number_format(($maintenance_count/$total_assets)*100, 1) . '%']);
    fputcsv($output, ['Retired Assets:', $retired_count, number_format(($retired_count/$total_assets)*100, 1) . '%']);
    
    fclose($output);
    exit;
}

// If no report type matched, redirect to dashboard
redirect('dashboard.php');
?>