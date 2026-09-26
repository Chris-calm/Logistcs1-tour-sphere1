<?php
// api/report.php
// AI-powered report generation API

require_once '../config/database.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get action
$action = $_GET['action'] ?? 'daily';

// Check if AI is enabled
$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'report_ai_enabled'");
$aiEnabled = $stmt->fetch()['setting_value'] ?? 'true';

if ($aiEnabled !== 'true') {
    http_response_code(403);
    echo json_encode(['error' => 'AI reports are disabled']);
    exit();
}

/**
 * Generate AI Daily Report
 */
function generateDailyReport($pdo, $userId) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $lastWeek = date('Y-m-d', strtotime('-7 days'));
    
    $report = [
        'date' => date('F d, Y'),
        'generated_at' => date('Y-m-d H:i:s'),
        'generated_by' => $userId,
        'summary' => [],
        'insights' => [],
        'risks' => [],
        'recommendations' => [],
        'performance' => [],
        'predictions' => []
    ];
    
    // 1. Stock Movements Summary
    $stmt = $pdo->prepare("
        SELECT 
            transaction_type,
            COUNT(*) as count,
            SUM(quantity) as total_quantity,
            SUM(CASE WHEN transaction_type IN ('receiving') THEN quantity ELSE 0 END) as received,
            SUM(CASE WHEN transaction_type IN ('issuance') THEN quantity ELSE 0 END) as issued
        FROM inventory_transactions 
        WHERE DATE(created_at) = ?
        GROUP BY transaction_type
    ");
    $stmt->execute([$today]);
    $movements = $stmt->fetchAll();
    
    $report['summary']['stock_movements'] = $movements;
    
    // Calculate totals
    $totalReceived = 0;
    $totalIssued = 0;
    foreach ($movements as $m) {
        if ($m['transaction_type'] === 'receiving') {
            $totalReceived += $m['total_quantity'];
        } elseif ($m['transaction_type'] === 'issuance') {
            $totalIssued += $m['total_quantity'];
        }
    }
    $report['summary']['total_received'] = $totalReceived;
    $report['summary']['total_issued'] = $totalIssued;
    $report['summary']['net_change'] = $totalReceived - $totalIssued;
    
    // 2. Pending POs
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as count,
            SUM(total_amount) as total_value,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count
        FROM purchase_orders 
        WHERE status IN ('pending', 'approved') AND is_archived = 0
    ");
    $stmt->execute();
    $pendingPOs = $stmt->fetch();
    $report['summary']['pending_pos'] = $pendingPOs;
    
    // 3. Low Stock Items
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as count,
            GROUP_CONCAT(CONCAT(sku, ' (', current_stock, ' remaining)') SEPARATOR ', ') as items
        FROM products 
        WHERE current_stock <= reorder_point AND is_archived = 0
    ");
    $stmt->execute();
    $lowStock = $stmt->fetch();
    $report['summary']['low_stock'] = $lowStock['count'];
    $report['summary']['low_stock_items'] = $lowStock['items'] ?: 'None';
    
    // 4. Supplier Performance Summary
    $stmt = $pdo->query("
        SELECT 
            s.company_name,
            sp.on_time_delivery,
            sp.quality_rate,
            sp.response_time,
            (sp.on_time_delivery + sp.quality_rate + sp.response_time) / 3 as overall_score
        FROM suppliers s
        JOIN supplier_performance sp ON s.id = sp.supplier_id
        WHERE s.is_archived = 0
        ORDER BY overall_score DESC
        LIMIT 5
    ");
    $report['performance']['top_suppliers'] = $stmt->fetchAll();
    
    // 5. High Velocity Items (last 30 days)
    $stmt = $pdo->prepare("
        SELECT 
            p.sku,
            p.product_name,
            COALESCE(SUM(it.quantity), 0) as movement,
            COUNT(DISTINCT DATE(it.created_at)) as active_days
        FROM products p
        LEFT JOIN inventory_transactions it ON p.id = it.product_id 
            AND DATE(it.created_at) >= DATE_SUB(?, INTERVAL 30 DAY)
        WHERE p.is_archived = 0
        GROUP BY p.id
        ORDER BY movement DESC
        LIMIT 10
    ");
    $stmt->execute([$today]);
    $report['performance']['high_velocity'] = $stmt->fetchAll();
    
    // 6. Generate AI Insights
    $insights = [];
    
    // Stock insight
    if ($report['summary']['net_change'] > 0) {
        $insights[] = "📈 Positive stock movement: Net increase of " . number_format($report['summary']['net_change']) . " units today.";
    } elseif ($report['summary']['net_change'] < 0) {
        $insights[] = "📉 Negative stock movement: Net decrease of " . number_format(abs($report['summary']['net_change'])) . " units today. Consider reviewing issuance patterns.";
    } else {
        $insights[] = "⚖️ Balanced stock movement: No net change in inventory today.";
    }
    
    // Low stock insight
    if ($report['summary']['low_stock'] > 0) {
        $insights[] = "⚠️ " . $report['summary']['low_stock'] . " items are below reorder point. Immediate attention required for: " . $report['summary']['low_stock_items'];
    }
    
    // PO insight
    if ($pendingPOs['pending_count'] > 0) {
        $insights[] = "📦 " . $pendingPOs['pending_count'] . " purchase orders are pending. Total value: ₱" . number_format($pendingPOs['total_value'], 2);
    }
    
    if ($pendingPOs['approved_count'] > 0) {
        $insights[] = "✅ " . $pendingPOs['approved_count'] . " purchase orders approved today. Total value: ₱" . number_format($pendingPOs['total_value'] ?? 0, 2);
    }
    
    // Supplier insight
    $topSupplier = $report['performance']['top_suppliers'][0] ?? null;
    if ($topSupplier) {
        $insights[] = "🏆 Top performing supplier: " . $topSupplier['company_name'] . " with overall score of " . number_format($topSupplier['overall_score'], 1) . "%";
    }
    
    $report['insights'] = $insights;
    
    // 7. Risk Assessment
    $risks = [];
    
    // Supplier risk
    $stmt = $pdo->query("
        SELECT s.company_name, sp.on_time_delivery
        FROM suppliers s
        JOIN supplier_performance sp ON s.id = sp.supplier_id
        WHERE sp.on_time_delivery < 75 AND s.is_archived = 0
        ORDER BY sp.on_time_delivery ASC
        LIMIT 3
    ");
    $riskySuppliers = $stmt->fetchAll();
    
    foreach ($riskySuppliers as $supplier) {
        $risks[] = "⚠️ " . $supplier['company_name'] . " has low on-time delivery rate (" . $supplier['on_time_delivery'] . "%). Consider reviewing contract terms.";
    }
    
    // Stockout risk
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM products 
        WHERE current_stock = 0 AND is_archived = 0
    ");
    $stmt->execute();
    $stockouts = $stmt->fetch()['count'];
    if ($stockouts > 0) {
        $risks[] = "🚨 " . $stockouts . " products are completely out of stock. Immediate reordering required.";
    }
    
    $report['risks'] = $risks;
    
    // 8. Recommendations
    $recommendations = [];
    
    if ($report['summary']['low_stock'] > 0) {
        $recommendations[] = "🔄 Generate purchase orders immediately for all " . $report['summary']['low_stock'] . " low-stock items.";
    }
    
    if ($stockouts > 0) {
        $recommendations[] = "🚨 Prioritize reordering for out-of-stock items to prevent revenue loss.";
    }
    
    // Check if any high velocity items are near low stock
    $highVelocity = $report['performance']['high_velocity'];
    foreach ($highVelocity as $item) {
        $stmt = $pdo->prepare("SELECT current_stock, reorder_point FROM products WHERE sku = ?");
        $stmt->execute([$item['sku']]);
        $product = $stmt->fetch();
        if ($product && $product['current_stock'] <= $product['reorder_point'] * 1.5) {
            $recommendations[] = "📦 " . $item['sku'] . " is high velocity and approaching reorder point. Consider increasing safety stock.";
        }
    }
    
    // Supplier recommendations
    if (!empty($riskySuppliers)) {
        $recommendations[] = "📊 Schedule performance review meetings with underperforming suppliers.";
    }
    
    $report['recommendations'] = $recommendations;
    
    // 9. Predictions
    $predictions = [];
    
    // Predict stockout probability
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN current_stock <= reorder_point THEN 1 ELSE 0 END) as at_risk
        FROM products 
        WHERE is_archived = 0
    ");
    $stmt->execute();
    $stockData = $stmt->fetch();
    $stockoutProbability = $stockData['total'] > 0 ? ($stockData['at_risk'] / $stockData['total']) * 100 : 0;
    $predictions['stockout_probability'] = round($stockoutProbability, 1) . '%';
    $predictions['stockout_risk_level'] = $stockoutProbability > 30 ? 'High' : ($stockoutProbability > 15 ? 'Medium' : 'Low');
    
    // Predict supplier risk
    $stmt = $pdo->query("
        SELECT AVG(sp.on_time_delivery) as avg_otif
        FROM supplier_performance sp
        WHERE sp.period = (SELECT MAX(period) FROM supplier_performance)
    ");
    $avgOTIF = $stmt->fetch()['avg_otif'] ?? 0;
    $predictions['avg_on_time_delivery'] = round($avgOTIF, 1) . '%';
    $predictions['supplier_risk_level'] = $avgOTIF < 85 ? 'High' : ($avgOTIF < 95 ? 'Medium' : 'Low');
    
    $report['predictions'] = $predictions;
    
    // Log report generation
    logAudit($userId, 'generate_ai_report', 'reporting', 'AI Daily Report Generated');
    
    return $report;
}

/**
 * Generate Weekly Report
 */
function generateWeeklyReport($pdo, $userId) {
    $report = generateDailyReport($pdo, $userId);
    $report['period'] = 'weekly';
    $report['date_range'] = date('F d, Y', strtotime('-7 days')) . ' - ' . date('F d, Y');
    
    // Add weekly trends
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            SUM(CASE WHEN transaction_type = 'receiving' THEN quantity ELSE 0 END) as received,
            SUM(CASE WHEN transaction_type = 'issuance' THEN quantity ELSE 0 END) as issued
        FROM inventory_transactions 
        WHERE DATE(created_at) >= DATE_SUB(?, INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([date('Y-m-d')]);
    $report['weekly_trends'] = $stmt->fetchAll();
    
    return $report;
}

/**
 * Generate Monthly Report
 */
function generateMonthlyReport($pdo, $userId) {
    $report = generateDailyReport($pdo, $userId);
    $report['period'] = 'monthly';
    $report['date_range'] = date('F d, Y', strtotime('-30 days')) . ' - ' . date('F d, Y');
    
    // Add monthly summary
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN transaction_type = 'receiving' THEN quantity ELSE 0 END) as total_received,
            SUM(CASE WHEN transaction_type = 'issuance' THEN quantity ELSE 0 END) as total_issued
        FROM inventory_transactions 
        WHERE DATE(created_at) >= DATE_SUB(?, INTERVAL 30 DAY)
    ");
    $stmt->execute([date('Y-m-d')]);
    $report['monthly_summary'] = $stmt->fetch();
    
    return $report;
}

// Handle request
$period = $_GET['period'] ?? 'daily';

try {
    switch ($period) {
        case 'daily':
            $report = generateDailyReport($pdo, $_SESSION['user_id']);
            break;
        case 'weekly':
            $report = generateWeeklyReport($pdo, $_SESSION['user_id']);
            break;
        case 'monthly':
            $report = generateMonthlyReport($pdo, $_SESSION['user_id']);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid period']);
            exit();
    }
    
    echo json_encode([
        'success' => true,
        'report' => $report
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>