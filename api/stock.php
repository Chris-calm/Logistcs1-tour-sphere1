<?php
// api/stock.php
// API endpoint for stock management and real-time data

require_once '../config/database.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get action
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        // Get stock levels for all products
        $limit = $_GET['limit'] ?? 100;
        $category = $_GET['category'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $query = "SELECT id, sku, product_name, category, current_stock, min_stock, max_stock, reorder_point, unit_price, unit_measure, status 
                  FROM products 
                  WHERE is_archived = 0";
        $params = [];
        
        if ($category) {
            $query .= " AND category = ?";
            $params[] = $category;
        }
        
        if ($status) {
            $query .= " AND status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY product_name LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        // Add status indicators
        foreach ($products as &$product) {
            if ($product['current_stock'] <= 0) {
                $product['stock_status'] = 'out_of_stock';
                $product['stock_status_label'] = 'Out of Stock';
            } elseif ($product['current_stock'] <= $product['reorder_point']) {
                $product['stock_status'] = 'low_stock';
                $product['stock_status_label'] = 'Low Stock';
            } elseif ($product['current_stock'] >= $product['max_stock']) {
                $product['stock_status'] = 'overstock';
                $product['stock_status_label'] = 'Overstock';
            } else {
                $product['stock_status'] = 'in_stock';
                $product['stock_status_label'] = 'In Stock';
            }
            
            $product['stock_value'] = $product['current_stock'] * $product['unit_price'];
        }
        
        echo json_encode([
            'success' => true,
            'count' => count($products),
            'products' => $products
        ]);
        break;
        
    case 'get':
        // Get stock for a specific product
        $id = $_GET['id'] ?? '';
        $sku = $_GET['sku'] ?? '';
        
        if (empty($id) && empty($sku)) {
            http_response_code(400);
            echo json_encode(['error' => 'Product ID or SKU is required']);
            exit();
        }
        
        $query = "SELECT p.*, 
                  (SELECT COUNT(*) FROM product_serial_numbers WHERE product_id = p.id AND status = 'available') as available_serials
                  FROM products p 
                  WHERE p.is_archived = 0 AND (p.id = ? OR p.sku = ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id, $sku]);
        $product = $stmt->fetch();
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            exit();
        }
        
        // Get recent transactions
        $stmt = $pdo->prepare("
            SELECT transaction_type, quantity, created_at, notes 
            FROM inventory_transactions 
            WHERE product_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$product['id']]);
        $transactions = $stmt->fetchAll();
        
        $product['stock_status'] = $product['current_stock'] <= $product['reorder_point'] ? 'low' : 'normal';
        $product['stock_value'] = $product['current_stock'] * $product['unit_price'];
        $product['recent_transactions'] = $transactions;
        
        echo json_encode([
            'success' => true,
            'product' => $product
        ]);
        break;
        
    case 'adjust':
        // Adjust stock level
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? $_POST['id'] ?? '';
        $quantity = $data['quantity'] ?? $_POST['quantity'] ?? 0;
        $type = $data['type'] ?? $_POST['type'] ?? 'adjustment';
        $notes = $data['notes'] ?? $_POST['notes'] ?? '';
        
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Product ID is required']);
            exit();
        }
        
        if ($quantity == 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Quantity must be non-zero']);
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get current stock
            $stmt = $pdo->prepare("SELECT current_stock, product_name FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception('Product not found');
            }
            
            $current_stock = $product['current_stock'];
            $new_stock = $current_stock + $quantity;
            
            // Prevent negative stock
            if ($new_stock < 0) {
                throw new Exception('Insufficient stock. Current stock: ' . $current_stock);
            }
            
            // Update product
            $stmt = $pdo->prepare("UPDATE products SET current_stock = ? WHERE id = ?");
            $stmt->execute([$new_stock, $id]);
            
            // Log transaction
            $transaction_type = $quantity > 0 ? 'receiving' : 'issuance';
            $stmt = $pdo->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, previous_balance, new_balance, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $transaction_type, abs($quantity), $current_stock, $new_stock, $notes, $_SESSION['user_id']]);
            
            $pdo->commit();
            
            logAudit($_SESSION['user_id'], 'adjust_stock_api', 'inventory', "Adjusted stock for product: " . $product['product_name'] . " by $quantity");
            
            echo json_encode([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'product_id' => $id,
                'previous_stock' => $current_stock,
                'new_stock' => $new_stock,
                'change' => $quantity
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'summary':
        // Get stock summary
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_products,
                SUM(current_stock) as total_units,
                SUM(unit_price * current_stock) as total_value,
                COUNT(CASE WHEN current_stock <= reorder_point THEN 1 END) as low_stock_count,
                COUNT(CASE WHEN current_stock = 0 THEN 1 END) as out_of_stock_count,
                COUNT(CASE WHEN current_stock >= max_stock AND max_stock > 0 THEN 1 END) as overstock_count
            FROM products 
            WHERE is_archived = 0
        ");
        $summary = $stmt->fetch();
        
        // Get category breakdown
        $stmt = $pdo->query("
            SELECT 
                category,
                COUNT(*) as product_count,
                SUM(current_stock) as total_units,
                SUM(unit_price * current_stock) as total_value
            FROM products 
            WHERE is_archived = 0 AND category IS NOT NULL
            GROUP BY category
            ORDER BY total_value DESC
        ");
        $categories = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'categories' => $categories
        ]);
        break;
        
    case 'movements':
        // Get recent stock movements
        $days = $_GET['days'] ?? 7;
        $productId = $_GET['product_id'] ?? '';
        
        $query = "SELECT it.*, p.sku, p.product_name 
                  FROM inventory_transactions it 
                  JOIN products p ON it.product_id = p.id 
                  WHERE DATE(it.created_at) >= DATE('now', '-' || ? || ' days')";
        $params = [$days];
        
        if ($productId) {
            $query .= " AND it.product_id = ?";
            $params[] = $productId;
        }
        
        $query .= " ORDER BY it.created_at DESC LIMIT 50";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $movements = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'count' => count($movements),
            'movements' => $movements
        ]);
        break;
        
    case 'alert':
        // Get stock alerts
        $stmt = $pdo->query("
            SELECT 
                id, sku, product_name, category, current_stock, reorder_point, max_stock,
                CASE 
                    WHEN current_stock = 0 THEN 'critical'
                    WHEN current_stock <= reorder_point THEN 'warning'
                    WHEN current_stock >= max_stock AND max_stock > 0 THEN 'overstock'
                    ELSE 'normal'
                END as alert_level
            FROM products 
            WHERE is_archived = 0 
            AND (current_stock <= reorder_point OR (current_stock >= max_stock AND max_stock > 0))
            ORDER BY alert_level ASC
        ");
        $alerts = $stmt->fetchAll();
        
        $critical = array_filter($alerts, function($a) { return $a['alert_level'] === 'critical'; });
        $warning = array_filter($alerts, function($a) { return $a['alert_level'] === 'warning'; });
        $overstock = array_filter($alerts, function($a) { return $a['alert_level'] === 'overstock'; });
        
        echo json_encode([
            'success' => true,
            'total_alerts' => count($alerts),
            'critical' => array_values($critical),
            'warning' => array_values($warning),
            'overstock' => array_values($overstock)
        ]);
        break;
        
    case 'forecast':
        // Get demand forecast (simple moving average)
        $id = $_GET['id'] ?? '';
        $days = $_GET['days'] ?? 30;
        
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Product ID is required']);
            exit();
        }
        
        // Get historical data
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                SUM(CASE WHEN transaction_type = 'issuance' THEN quantity ELSE 0 END) as demand
            FROM inventory_transactions 
            WHERE product_id = ? 
            AND DATE(created_at) >= DATE('now', '-' || ? || ' days')
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$id, $days]);
        $history = $stmt->fetchAll();
        
        // Calculate average daily demand
        $totalDemand = array_sum(array_column($history, 'demand'));
        $avgDailyDemand = count($history) > 0 ? $totalDemand / count($history) : 0;
        
        // Get current stock
        $stmt = $pdo->prepare("SELECT current_stock, reorder_point FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        $daysUntilReorder = $avgDailyDemand > 0 ? ($product['current_stock'] - $product['reorder_point']) / $avgDailyDemand : 0;
        
        echo json_encode([
            'success' => true,
            'product_id' => $id,
            'avg_daily_demand' => round($avgDailyDemand, 2),
            'days_until_reorder' => round($daysUntilReorder, 1),
            'history' => $history
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>