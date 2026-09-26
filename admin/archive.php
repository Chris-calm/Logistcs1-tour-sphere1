<?php
// admin/archive.php
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Define color constants if not already defined
if (!defined('COLOR_PRIMARY')) define('COLOR_PRIMARY', '#2F80ED');
if (!defined('COLOR_SECONDARY')) define('COLOR_SECONDARY', '#56CCF2');
if (!defined('COLOR_ACCENT')) define('COLOR_ACCENT', '#27AE60');
if (!defined('COLOR_BG')) define('COLOR_BG', '#F8FAFC');
if (!defined('COLOR_CARD')) define('COLOR_CARD', '#FFFFFF');
if (!defined('COLOR_TEXT')) define('COLOR_TEXT', '#1F2937');
if (!defined('COLOR_SECONDARY_TEXT')) define('COLOR_SECONDARY_TEXT', '#6B7280');
if (!defined('COLOR_BORDER')) define('COLOR_BORDER', '#EEF2F7');

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sortField = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Get theme setting
$theme = getTheme();

// ============================================
// RETENTION RULES CONFIGURATION
// ============================================
$retentionDays = 90;
try {
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'archive_retention_days'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $retentionDays = (int)$result['setting_value'];
    }
} catch (Exception $e) {
    $retentionDays = 90;
}

// ============================================
// AUTO-PURGE FUNCTION
// ============================================
if ($action === 'purge' && isset($_GET['table'])) {
    $table = $_GET['table'];
    $days = isset($_GET['days']) ? (int)$_GET['days'] : $retentionDays;
    
    $allowedTables = ['users', 'suppliers', 'products', 'purchase_orders', 'shipments', 'purchase_requisitions', 'procurement_contracts', 'documents', 'warehouses'];
    if (in_array($table, $allowedTables)) {
        try {
            $purgeDate = date('Y-m-d H:i:s', strtotime("-$days days"));
            $stmt = $pdo->prepare("DELETE FROM $table WHERE is_archived = 1 AND created_at < ?");
            $stmt->execute([$purgeDate]);
            $deleted = $stmt->rowCount();
            
            logAudit($_SESSION['user_id'], 'auto_purge_archive', 'archive', "Purged $deleted records from $table older than $days days");
            $_SESSION['success'] = "Successfully purged $deleted records from " . ucfirst(str_replace('_', ' ', $table)) . "!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error purging records: " . $e->getMessage();
        }
    }
    header('Location: archive.php?tab=' . $tab);
    exit();
}

// ============================================
// BULK RESTORE
// ============================================
if ($action === 'bulk_restore' && isset($_POST['ids']) && isset($_POST['table'])) {
    $table = $_POST['table'];
    $ids = array_map('intval', $_POST['ids']);
    
    $allowedTables = ['users', 'suppliers', 'products', 'purchase_orders', 'shipments', 'purchase_requisitions', 'procurement_contracts', 'documents', 'warehouses'];
    if (in_array($table, $allowedTables) && !empty($ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE $table SET is_archived = 0 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $restored = $stmt->rowCount();
            
            logAudit($_SESSION['user_id'], 'bulk_restore_archive', 'archive', "Bulk restored $restored records from $table");
            $_SESSION['success'] = "Successfully restored $restored records!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error restoring records: " . $e->getMessage();
        }
    }
    header('Location: archive.php?tab=' . $tab);
    exit();
}

// ============================================
// EXPORT ARCHIVE LOGS
// ============================================
if ($action === 'export' && isset($_GET['table'])) {
    $table = $_GET['table'];
    $allowedTables = ['users', 'suppliers', 'products', 'purchase_orders', 'shipments', 'purchase_requisitions', 'procurement_contracts', 'documents', 'warehouses'];
    
    if (in_array($table, $allowedTables)) {
        try {
            $query = "SELECT * FROM $table WHERE is_archived = 1";
            $params = [];
            
            if (!empty($search)) {
                $searchFields = [];
                $stmt = $pdo->query("SHOW COLUMNS FROM $table");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($columns as $col) {
                    $searchFields[] = "$col LIKE ?";
                }
                if (!empty($searchFields)) {
                    $query .= " AND (" . implode(' OR ', $searchFields) . ")";
                    $searchParam = "%$search%";
                    $params = array_fill(0, count($searchFields), $searchParam);
                }
            }
            
            if (!empty($dateFrom)) {
                $query .= " AND DATE(created_at) >= ?";
                $params[] = $dateFrom;
            }
            if (!empty($dateTo)) {
                $query .= " AND DATE(created_at) <= ?";
                $params[] = $dateTo;
            }
            
            $query .= " ORDER BY created_at DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="archive_' . $table . '_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            if (!empty($data)) {
                fputcsv($output, array_keys($data[0]));
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
            }
            fclose($output);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Export failed: " . $e->getMessage();
            header('Location: archive.php?tab=' . $tab);
            exit();
        }
    }
}

// ============================================
// HANDLE RESTORE
// ============================================
if ($action === 'restore' && isset($_GET['table']) && isset($_GET['id'])) {
    $table = $_GET['table'];
    $id = (int)$_GET['id'];
    
    $allowedTables = ['users', 'suppliers', 'products', 'purchase_orders', 'shipments', 'purchase_requisitions', 'procurement_contracts', 'documents', 'warehouses'];
    if (in_array($table, $allowedTables)) {
        try {
            restoreRecord($table, $id);
            logAudit($_SESSION['user_id'], 'restore_record', 'archive', "Restored record from $table with ID $id");
            $_SESSION['success'] = "Record restored successfully!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Error restoring record: " . $e->getMessage();
        }
    }
    header('Location: archive.php?tab=' . $tab);
    exit();
}

// ============================================
// HANDLE PERMANENT DELETE
// ============================================
if ($action === 'delete' && isset($_GET['table']) && isset($_GET['id'])) {
    $table = $_GET['table'];
    $id = (int)$_GET['id'];
    
    $allowedTables = ['users', 'suppliers', 'products', 'purchase_orders', 'shipments', 'purchase_requisitions', 'procurement_contracts', 'documents', 'warehouses'];
    if (in_array($table, $allowedTables)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            logAudit($_SESSION['user_id'], 'permanent_delete', 'archive', "Permanently deleted record from $table with ID $id");
            $_SESSION['success'] = "Record permanently deleted!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Error deleting record: " . $e->getMessage();
        }
    }
    header('Location: archive.php?tab=' . $tab);
    exit();
}

// ============================================
// VIEW ARCHIVED RECORD DETAILS
// ============================================
$viewRecord = null;
$viewTable = null;
if ($action === 'view' && isset($_GET['table']) && isset($_GET['id'])) {
    $table = $_GET['table'];
    $id = (int)$_GET['id'];
    
    $allowedTables = ['users', 'suppliers', 'products', 'purchase_orders', 'shipments', 'purchase_requisitions', 'procurement_contracts', 'documents', 'warehouses'];
    if (in_array($table, $allowedTables)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ? AND is_archived = 1");
            $stmt->execute([$id]);
            $viewRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            $viewTable = $table;
        } catch (Exception $e) {
            $_SESSION['error'] = "Error fetching record details: " . $e->getMessage();
        }
    }
}

// ============================================
// GET ARCHIVED RECORDS FROM ALL TABLES
// ============================================
$tables = [
    'users' => ['label' => 'Users', 'icon' => 'fa-users', 'name_field' => 'full_name', 'id_field' => 'id', 'identifier' => 'username', 'module' => 'User Management', 'color' => '#6366F1'],
    'suppliers' => ['label' => 'Suppliers', 'icon' => 'fa-truck', 'name_field' => 'company_name', 'id_field' => 'id', 'identifier' => 'supplier_code', 'module' => 'Procurement', 'color' => '#F59E0B'],
    'products' => ['label' => 'Products', 'icon' => 'fa-box', 'name_field' => 'product_name', 'id_field' => 'id', 'identifier' => 'sku', 'module' => 'Inventory', 'color' => '#27AE60'],
    'warehouses' => ['label' => 'Warehouses', 'icon' => 'fa-warehouse', 'name_field' => 'name', 'id_field' => 'id', 'identifier' => 'warehouse_code', 'module' => 'Warehousing', 'color' => '#56CCF2'],
    'purchase_orders' => ['label' => 'Purchase Orders', 'icon' => 'fa-file-invoice', 'name_field' => 'po_number', 'id_field' => 'id', 'identifier' => 'po_number', 'module' => 'Procurement', 'color' => '#8B5CF6'],
    'shipments' => ['label' => 'Shipments', 'icon' => 'fa-ship', 'name_field' => 'shipment_id', 'id_field' => 'id', 'identifier' => 'tracking_number', 'module' => 'Logistics', 'color' => '#1E40AF'],
    'purchase_requisitions' => ['label' => 'Requisitions', 'icon' => 'fa-clipboard-list', 'name_field' => 'pr_number', 'id_field' => 'id', 'identifier' => 'pr_number', 'module' => 'Procurement', 'color' => '#EC4899'],
    'procurement_contracts' => ['label' => 'Contracts', 'icon' => 'fa-file-signature', 'name_field' => 'contract_number', 'id_field' => 'id', 'identifier' => 'contract_number', 'module' => 'Procurement', 'color' => '#7C3AED'],
    'documents' => ['label' => 'Documents', 'icon' => 'fa-file-alt', 'name_field' => 'document_number', 'id_field' => 'id', 'identifier' => 'document_number', 'module' => 'Logistics', 'color' => '#DC2626']
];

$archivedData = [];
$totalArchived = 0;
$tabCounts = [];
$tableFieldMap = [];

foreach ($tables as $table => $info) {
    try {
        // Get all columns for the table
        $stmt = $pdo->query("SHOW COLUMNS FROM $table");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $tableFieldMap[$table] = $columns;
        
        $query = "SELECT * FROM $table WHERE is_archived = 1";
        $params = [];
        
        if (!empty($search)) {
            $searchFields = [];
            foreach ($columns as $col) {
                $searchFields[] = "$col LIKE ?";
            }
            if (!empty($searchFields)) {
                $query .= " AND (" . implode(' OR ', $searchFields) . ")";
                $searchParam = "%$search%";
                $params = array_fill(0, count($searchFields), $searchParam);
            }
        }
        
        if (!empty($dateFrom)) {
            $query .= " AND DATE(created_at) >= ?";
            $params[] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $query .= " AND DATE(created_at) <= ?";
            $params[] = $dateTo;
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $archivedData[$table] = $records;
        $tabCounts[$table] = count($records);
        $totalArchived += count($records);
    } catch (Exception $e) {
        $archivedData[$table] = [];
        $tabCounts[$table] = 0;
    }
}

// Get current table data for display
$currentTable = $tab !== 'all' && isset($archivedData[$tab]) ? $tab : null;

// Build display data
if ($tab === 'all') {
    // Combine all records for "All" tab
    $allRecords = [];
    foreach ($archivedData as $table => $records) {
        foreach ($records as $record) {
            $record['_table'] = $table;
            $record['_label'] = $tables[$table]['label'];
            $record['_icon'] = $tables[$table]['icon'];
            $record['_name_field'] = $tables[$table]['name_field'];
            $record['_identifier'] = $tables[$table]['identifier'] ?? 'id';
            $record['_module'] = $tables[$table]['module'];
            $record['_color'] = $tables[$table]['color'];
            $allRecords[] = $record;
        }
    }
    
    // Sort all records
    usort($allRecords, function($a, $b) use ($sortField, $sortOrder) {
        $valA = $a[$sortField] ?? '';
        $valB = $b[$sortField] ?? '';
        if ($sortField === '_label') {
            $valA = $a['_label'] ?? '';
            $valB = $b['_label'] ?? '';
        }
        if ($sortOrder === 'ASC') {
            return strcmp($valA, $valB);
        } else {
            return strcmp($valB, $valA);
        }
    });
    $displayData = $allRecords;
    $displayCount = count($allRecords);
} else {
    // Get specific table records
    $displayData = $archivedData[$currentTable] ?? [];
    $displayCount = count($displayData);
}

// Pagination
$itemsPerPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$totalItems = $displayCount;
$totalPages = max(1, ceil($totalItems / $itemsPerPage));
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, min($currentPage, $totalPages));
$offset = ($currentPage - 1) * $itemsPerPage;

if (!empty($displayData)) {
    $displayData = array_slice($displayData, $offset, $itemsPerPage);
}

// Helper function to get record name
function getRecordName($record, $tableName, $tables) {
    if ($tableName && isset($tables[$tableName]['name_field'])) {
        $nameField = $tables[$tableName]['name_field'];
        return $record[$nameField] ?? 'N/A';
    }
    
    $possibleFields = ['full_name', 'company_name', 'product_name', 'name', 'po_number', 'shipment_id', 
                       'pr_number', 'contract_number', 'document_number', 'username', 'title', 'sku'];
    foreach ($possibleFields as $field) {
        if (isset($record[$field]) && !empty($record[$field])) {
            return $record[$field];
        }
    }
    return 'N/A';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Management - GlobalSCM</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: <?php echo COLOR_PRIMARY; ?>;
            --secondary: <?php echo COLOR_SECONDARY; ?>;
            --accent: <?php echo COLOR_ACCENT; ?>;
            --bg: <?php echo COLOR_BG; ?>;
            --card: <?php echo COLOR_CARD; ?>;
            --text: <?php echo COLOR_TEXT; ?>;
            --secondary-text: <?php echo COLOR_SECONDARY_TEXT; ?>;
            --border: <?php echo COLOR_BORDER; ?>;
            --radius: 12px;
            --radius-sm: 8px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --danger: #DC2626;
            --warning: #F59E0B;
        }
        
        [data-theme="dark"] {
            --bg: <?php echo COLOR_DARK_BG; ?>;
            --card: <?php echo COLOR_DARK_CARD; ?>;
            --text: <?php echo COLOR_DARK_TEXT; ?>;
            --secondary-text: <?php echo COLOR_DARK_SECONDARY_TEXT; ?>;
            --border: <?php echo COLOR_DARK_BORDER; ?>;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: var(--transition);
            line-height: 1.6;
            min-height: 100vh;
            width: 100%;
            overflow-x: hidden;
        }
        
        .admin-layout { display: flex; min-height: 100vh; width: 100%; }
        
        .sidebar {
            width: 280px;
            background: var(--card);
            border-right: 1px solid var(--border);
            padding: 24px 16px;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 100;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow);
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .sidebar-brand > div { display: flex; flex-direction: column; }
        .sidebar-brand h2 { font-size: 20px; font-weight: 700; color: var(--primary); }
        .sidebar-brand span { font-size: 11px; color: var(--secondary-text); font-weight: 400; letter-spacing: 1px; text-transform: uppercase; display: block; }
        
        .sidebar-toggle-btn {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: var(--secondary-text);
            cursor: pointer;
            padding: 4px 8px;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 99;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .sidebar-overlay.active { display: block; }
        
        .main-content {
            margin-left: 280px;
            padding: 24px 32px 40px;
            flex: 1;
            min-height: 100vh;
            width: calc(100% - 280px);
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding: 16px 24px;
            background: var(--card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            flex-wrap: wrap;
            gap: 12px;
            width: 100%;
        }
        
        .page-title h1 { 
            font-size: 22px; 
            font-weight: 600; 
            color: var(--text);
            display: flex;
            align-items: center;
        }
        
        .page-title h1 i {
            color: var(--primary);
            margin-right: 12px;
            font-size: 24px;
        }
        
        .page-title p { 
            color: var(--secondary-text); 
            font-size: 14px; 
            margin-top: 2px; 
            margin-left: 36px;
        }
        
        .top-bar-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 18px;
            border: none;
            border-radius: var(--radius-sm);
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #2563EB; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(47, 128, 237, 0.3); }
        .btn-success { background: var(--accent); color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-1px); }
        .btn-danger { background: #DC2626; color: white; }
        .btn-danger:hover { background: #B91C1C; transform: translateY(-1px); }
        .btn-warning { background: #F59E0B; color: white; }
        .btn-warning:hover { background: #D97706; transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        .btn-back { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-back:hover { border-color: var(--primary); color: var(--primary); }
        .btn-sm { padding: 4px 10px; font-size: 12px; border-radius: 6px; gap: 4px; }
        .btn-xs { padding: 2px 8px; font-size: 11px; border-radius: 4px; gap: 3px; }
        
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: var(--card);
            min-width: 180px;
            box-shadow: var(--shadow-lg);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            z-index: 10;
            padding: 6px 0;
        }
        
        .dropdown-content.show { display: block; }
        .dropdown-content a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            color: var(--text);
            text-decoration: none;
            font-size: 13px;
            transition: var(--transition);
        }
        .dropdown-content a:hover { background: rgba(47, 128, 237, 0.05); color: var(--primary); }
        .dropdown-content a i { width: 18px; color: var(--secondary-text); }
        
        /* Tabs */
        .tabs-container {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            background: var(--card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 6px 12px;
            box-shadow: var(--shadow);
        }
        
        .tab-btn {
            padding: 8px 16px;
            border: none;
            border-radius: var(--radius-sm);
            background: transparent;
            color: var(--secondary-text);
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .tab-btn:hover {
            color: var(--text);
            background: rgba(47, 128, 237, 0.04);
        }
        
        .tab-btn.active {
            background: var(--primary);
            color: white;
        }
        
        .tab-btn .badge {
            background: var(--bg);
            color: var(--secondary-text);
            padding: 0 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            min-width: 18px;
            text-align: center;
        }
        
        .tab-btn.active .badge {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
            width: 100%;
        }
        
        .filter-bar .search-input {
            flex: 1;
            min-width: 200px;
            padding: 10px 16px;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            background: var(--bg);
            color: var(--text);
            transition: var(--transition);
        }
        
        .filter-bar .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(47, 128, 237, 0.1);
        }
        
        .filter-bar input[type="date"] {
            padding: 10px 14px;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            background: var(--bg);
            color: var(--text);
            min-width: 150px;
            transition: var(--transition);
        }
        
        .filter-bar input[type="date"]:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .filter-bar .filter-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* Batch Bar */
        .batch-bar {
            display: none;
            gap: 10px;
            padding: 12px 16px;
            background: var(--bg);
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            flex-wrap: wrap;
            align-items: center;
            border: 1px dashed var(--border);
        }
        
        .batch-bar.show { display: flex; }
        .batch-bar .selected-info { font-size: 13px; color: var(--secondary-text); }
        .batch-bar .selected-info strong { color: var(--text); }
        
        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid transparent;
            width: 100%;
        }
        .alert-success { background: #D1FAE5; color: #065F46; border-left-color: var(--accent); }
        .alert-error { background: #FEE2E2; color: #DC2626; border-left-color: #DC2626; }
        .alert-warning { background: #FEF3C7; color: #92400E; border-left-color: #F59E0B; }
        
        /* Retention Settings */
        .retention-settings {
            background: var(--card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 16px 24px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .retention-settings .info {
            font-size: 13px;
            color: var(--secondary-text);
        }
        
        .retention-settings .info strong {
            color: var(--text);
        }
        
        /* Table */
        .table-container {
            background: var(--card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: var(--shadow);
            width: 100%;
        }
        
        .table-header {
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .table-header h2 { font-size: 16px; font-weight: 600; }
        
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding: 0;
            width: 100%;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 950px;
        }
        
        table thead { background: var(--bg); }
        table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--secondary-text);
            border-bottom: 2px solid var(--border);
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
            transition: var(--transition);
        }
        
        table th:hover { color: var(--primary); }
        table th .sort-icon { margin-left: 4px; opacity: 0.5; }
        table th.sorted .sort-icon { opacity: 1; color: var(--primary); }
        table th:first-child { text-align: center; width: 40px; }
        table td { padding: 12px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        table td:first-child { text-align: center; }
        table td:last-child { text-align: center; }
        table tbody tr { transition: var(--transition); }
        table tbody tr:hover { background: rgba(47, 128, 237, 0.04); }
        table tbody tr:last-child td { border-bottom: none; }
        
        .checkbox-cell input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            min-width: 70px;
        }
        .status-archived { background: #E5E7EB; color: #374151; }
        
        .module-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            background: rgba(47, 128, 237, 0.1);
            color: var(--primary);
        }
        
        .module-badge-custom {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .role-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            background: rgba(47, 128, 237, 0.1);
            color: var(--primary);
            min-width: 60px;
            text-align: center;
        }
        
        .action-buttons { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; }
        
        .empty-state { text-align: center; padding: 40px; color: var(--secondary-text); }
        .empty-state i { font-size: 40px; display: block; margin-bottom: 10px; opacity: 0.3; }
        
        .pagination-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .pagination-bar .info { font-size: 13px; color: var(--secondary-text); }
        .pagination-bar .info strong { color: var(--text); }
        
        .pagination-controls { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
        .pagination-controls .page-btn {
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--bg);
            color: var(--text);
            cursor: pointer;
            transition: var(--transition);
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            min-width: 36px;
            text-align: center;
        }
        .pagination-controls .page-btn:hover:not(.active) { background: rgba(47, 128, 237, 0.05); border-color: var(--primary); }
        .pagination-controls .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .pagination-controls .page-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .pagination-controls select { padding: 6px 10px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg); color: var(--text); font-family: 'Poppins', sans-serif; font-size: 13px; }
        
        .fullscreen-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 50;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 50%;
            width: 48px;
            height: 48px;
            font-size: 20px;
            color: var(--text);
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .fullscreen-toggle:hover { background: var(--primary); color: white; border-color: var(--primary); transform: scale(1.05); }
        
        /* View Record Modal */
        .modal-overlay {
            display: <?php echo ($viewRecord) ? 'flex' : 'none'; ?>;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        
        .modal {
            background: var(--card);
            border-radius: var(--radius);
            padding: 30px;
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalIn 0.3s ease;
            box-shadow: var(--shadow-lg);
        }
        
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95) translateY(-20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        
        .modal h3 { font-size: 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .modal .close-modal { margin-left: auto; background: none; border: none; font-size: 24px; color: var(--secondary-text); cursor: pointer; padding: 0 4px; transition: var(--transition); }
        .modal .close-modal:hover { color: var(--text); }
        
        .modal .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .modal .detail-row:last-child { border-bottom: none; }
        .modal .detail-row .label { font-weight: 500; color: var(--secondary-text); }
        .modal .detail-row .value { font-weight: 500; }
        
        @media (max-width: 1024px) {
            .main-content { padding: 20px 24px 32px; width: calc(100% - 280px); }
            .top-bar { flex-direction: column; align-items: stretch; }
            .top-bar-actions { justify-content: center; }
            .top-bar-actions .btn { flex: 1; justify-content: center; min-width: 120px; }
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 0; padding: 0; overflow: hidden; position: fixed; left: -320px; transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: var(--shadow-lg); }
            .sidebar.open { left: 0; width: 300px; padding: 24px 16px; }
            .sidebar-toggle-btn { display: block; }
            .sidebar-overlay.active { display: block; }
            .main-content { margin-left: 0; padding: 16px; width: 100%; padding-top: 16px; }
            .top-bar { padding: 16px; gap: 12px; }
            .page-title h1 { font-size: 18px; }
            .page-title p { font-size: 13px; margin-left: 0; }
            .page-title h1 i { font-size: 20px; }
            .top-bar-actions { width: 100%; flex-wrap: wrap; }
            .top-bar-actions .btn { flex: 1; min-width: 100px; justify-content: center; font-size: 13px; padding: 8px 14px; }
            .tabs-container { flex-wrap: nowrap; overflow-x: auto; padding: 4px 8px; }
            .tab-btn { font-size: 12px; padding: 6px 12px; white-space: nowrap; }
            .filter-bar { flex-direction: column; }
            .filter-bar .search-input { width: 100%; }
            .filter-bar input[type="date"] { width: 100%; }
            .table-header { flex-direction: column; align-items: flex-start; gap: 8px; }
            .table-header h2 { font-size: 15px; }
            table { font-size: 13px; min-width: 500px; }
            table th, table td { padding: 10px 12px; }
            .pagination-bar { flex-direction: column; align-items: stretch; gap: 8px; }
            .pagination-controls { justify-content: center; flex-wrap: wrap; }
            .fullscreen-toggle { bottom: 16px; right: 16px; width: 44px; height: 44px; font-size: 18px; }
            .retention-settings { flex-direction: column; align-items: stretch; text-align: center; }
            .modal { padding: 20px; margin: 10px; max-width: 100%; }
            .modal h3 { font-size: 18px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .top-bar { padding: 12px; }
            .top-bar-actions { flex-direction: column; align-items: stretch; }
            .top-bar-actions .btn { min-width: unset; width: 100%; justify-content: center; font-size: 13px; padding: 10px 14px; }
            .table-wrapper { margin: 0 -12px; }
            table th, table td { padding: 8px 10px; font-size: 12px; }
            .action-buttons { flex-direction: column; align-items: center; gap: 4px; }
            .action-buttons .btn-sm { width: 100%; justify-content: center; padding: 6px 12px; }
            .status-badge { min-width: 60px; font-size: 11px; padding: 2px 10px; }
            .role-badge { min-width: 50px; font-size: 10px; padding: 2px 10px; }
            .pagination-controls .page-btn { padding: 4px 8px; font-size: 12px; min-width: 30px; }
            .fullscreen-toggle { bottom: 12px; right: 12px; width: 40px; height: 40px; font-size: 16px; }
            .modal { padding: 16px; margin: 8px; }
            .modal h3 { font-size: 16px; }
        }
        
        @media print {
            .sidebar, .top-bar-actions, .btn, .no-print, .fullscreen-toggle, .filter-bar, .batch-bar, .tabs-container { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 20px !important; width: 100% !important; }
            .table-container { box-shadow: none !important; border: 1px solid #ddd !important; }
            body { background: white !important; color: black !important; }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <button class="fullscreen-toggle no-print" id="fullscreenToggle" title="Toggle Fullscreen">
        <i class="fas fa-expand"></i>
    </button>
    
    <div class="admin-layout">
        <?php include 'partials/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <div>
                        <h1>
                            <i class="fas fa-archive"></i>
                            Archive Management
                        </h1>
                        <p>View, restore, and manage all archived records across all modules</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <div class="dropdown">
                        <button class="btn btn-outline" onclick="toggleExportDropdown()">
                            <i class="fas fa-download"></i> Export Logs
                        </button>
                        <div class="dropdown-content" id="exportDropdown">
                            <?php foreach ($tables as $table => $info): ?>
                            <a href="archive.php?action=export&table=<?php echo $table; ?>&tab=<?php echo $tab; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . $dateFrom : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . $dateTo : ''; ?>">
                                <i class="fas fa-file-csv"></i> <?php echo $info['label']; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <a href="settings.php#retention" class="btn btn-outline">
                        <i class="fas fa-cog"></i> Retention Settings
                    </a>
                    <a href="dashboard.php" class="btn btn-back">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Retention Settings -->
            <div class="retention-settings">
                <div class="info">
                    <i class="fas fa-clock" style="color: var(--primary);"></i>
                    <strong>Retention Policy:</strong> Records archived for more than <strong><?php echo $retentionDays; ?> days</strong> are eligible for auto-purge.
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php foreach ($tables as $table => $info): ?>
                    <a href="archive.php?action=purge&table=<?php echo $table; ?>&days=<?php echo $retentionDays; ?>&tab=<?php echo $tab; ?>" 
                       class="btn btn-danger btn-sm" 
                       onclick="return confirm('⚠️ This will permanently delete all archived records from <?php echo $info['label']; ?> older than <?php echo $retentionDays; ?> days. Continue?');">
                        <i class="fas fa-trash"></i> Purge <?php echo $info['label']; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs-container">
                <button class="tab-btn <?php echo $tab === 'all' ? 'active' : ''; ?>" onclick="switchTab('all')">
                    <i class="fas fa-archive"></i> All
                    <span class="badge"><?php echo $totalArchived; ?></span>
                </button>
                <?php foreach ($tables as $table => $info): ?>
                <button class="tab-btn <?php echo $tab === $table ? 'active' : ''; ?>" onclick="switchTab('<?php echo $table; ?>')">
                    <i class="fas <?php echo $info['icon']; ?>"></i> <?php echo $info['label']; ?>
                    <span class="badge"><?php echo $tabCounts[$table] ?? 0; ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            
            <!-- Search & Filter Bar -->
            <div class="filter-bar">
                <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; width: 100%; align-items: center;">
                    <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                    
                    <input type="text" name="search" class="search-input" 
                           placeholder="Search across all archived records (ID, name, code, keyword...)" 
                           value="<?php echo htmlspecialchars($search); ?>">
                    
                    <input type="date" name="date_from" placeholder="From" value="<?php echo $dateFrom; ?>">
                    <input type="date" name="date_to" placeholder="To" value="<?php echo $dateTo; ?>">
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <?php if (!empty($search) || !empty($dateFrom) || !empty($dateTo)): ?>
                        <a href="archive.php?tab=<?php echo $tab; ?>" class="btn btn-outline">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Batch Operations Bar -->
            <?php if ($totalItems > 0): ?>
            <div class="batch-bar" id="batchBar">
                <span class="selected-info">
                    <strong id="selectedCount">0</strong> records selected
                </span>
                <button class="btn btn-success btn-sm" onclick="bulkRestore()">
                    <i class="fas fa-undo"></i> Restore Selected
                </button>
                <button class="btn btn-outline btn-sm" onclick="clearSelection()">
                    <i class="fas fa-times"></i> Clear Selection
                </button>
            </div>
            <?php endif; ?>
            
            <div class="table-container">
                <div class="table-header">
                    <h2>
                        <?php if ($tab !== 'all' && isset($tables[$tab])): ?>
                            <i class="fas <?php echo $tables[$tab]['icon']; ?>" style="color: <?php echo $tables[$tab]['color']; ?>;"></i>
                            <?php echo $tables[$tab]['label']; ?>
                        <?php else: ?>
                            <i class="fas fa-archive" style="color: var(--primary);"></i>
                            All Archived Records
                        <?php endif; ?>
                    </h2>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span class="role-badge"><?php echo $totalItems; ?> records</span>
                        <span class="role-badge">Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>
                    </div>
                </div>
                <div class="table-wrapper">
                    <?php if ($totalItems > 0): ?>
                    <form id="bulkForm" method="POST">
                        <input type="hidden" name="action" value="bulk_restore">
                        <input type="hidden" name="table" id="bulkTable" value="<?php echo $currentTable ?: 'all'; ?>">
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" onclick="toggleAll(this);">
                                    </th>
                                    <th onclick="sortTable('_module')" class="<?php echo $sortField === '_module' ? 'sorted' : ''; ?>">
                                        Module <span class="sort-icon"><?php echo $sortField === '_module' ? ($sortOrder === 'ASC' ? '▲' : '▼') : '⇅'; ?></span>
                                    </th>
                                    <th onclick="sortTable('id')" class="<?php echo $sortField === 'id' ? 'sorted' : ''; ?>">
                                        Record ID <span class="sort-icon"><?php echo $sortField === 'id' ? ($sortOrder === 'ASC' ? '▲' : '▼') : '⇅'; ?></span>
                                    </th>
                                    <th onclick="sortTable('created_by')" class="<?php echo $sortField === 'created_by' ? 'sorted' : ''; ?>">
                                        Archived By <span class="sort-icon"><?php echo $sortField === 'created_by' ? ($sortOrder === 'ASC' ? '▲' : '▼') : '⇅'; ?></span>
                                    </th>
                                    <th onclick="sortTable('created_at')" class="<?php echo $sortField === 'created_at' ? 'sorted' : ''; ?>">
                                        Archived Date <span class="sort-icon"><?php echo $sortField === 'created_at' ? ($sortOrder === 'ASC' ? '▲' : '▼') : '⇅'; ?></span>
                                    </th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($displayData as $record): 
                                    $recordId = $record['id'] ?? 'N/A';
                                    $tableName = $record['_table'] ?? '';
                                    $moduleLabel = $record['_module'] ?? 'Record';
                                    $icon = $record['_icon'] ?? 'fa-file';
                                    $color = $record['_color'] ?? '#6B7280';
                                    $identifier = $record['_identifier'] ?? 'id';
                                    $identifierValue = $record[$identifier] ?? 'N/A';
                                    $recordName = getRecordName($record, $tableName, $tables);
                                    $createdBy = isset($record['created_by']) ? 'User #' . $record['created_by'] : 'System';
                                    $createdDate = isset($record['created_at']) ? date('M d, Y', strtotime($record['created_at'])) : 'N/A';
                                    $tableForAction = $tableName ?: 'users';
                                ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" name="ids[]" value="<?php echo $record['id']; ?>" 
                                               class="row-checkbox" onchange="updateSelection();">
                                    </td>
                                    <td>
                                        <span class="module-badge-custom" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                            <?php echo $moduleLabel; ?>
                                        </span>
                                        <?php if ($identifierValue !== 'N/A'): ?>
                                        <div style="font-size: 10px; color: var(--secondary-text); margin-top: 2px;">
                                            <?php echo strtoupper(str_replace('_', ' ', $identifier)); ?>: <?php echo htmlspecialchars(substr($identifierValue, 0, 30)); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>#<?php echo $recordId; ?></strong>
                                        <div style="font-size: 12px; color: var(--secondary-text);">
                                            <?php echo htmlspecialchars(substr($recordName, 0, 40)) . (strlen($recordName) > 40 ? '...' : ''); ?>
                                        </div>
                                    </td>
                                    <td><?php echo $createdBy; ?></td>
                                    <td style="font-size: 13px; color: var(--secondary-text);"><?php echo $createdDate; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="archive.php?action=view&table=<?php echo $tableForAction; ?>&id=<?php echo $record['id']; ?>&tab=<?php echo $tab; ?>" 
                                               class="btn btn-primary btn-xs" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="archive.php?action=restore&table=<?php echo $tableForAction; ?>&id=<?php echo $record['id']; ?>&tab=<?php echo $tab; ?>" 
                                               class="btn btn-success btn-xs" 
                                               onclick="return confirm('Restore this record?');" title="Restore">
                                                <i class="fas fa-undo"></i>
                                            </a>
                                            <a href="archive.php?action=delete&table=<?php echo $tableForAction; ?>&id=<?php echo $record['id']; ?>&tab=<?php echo $tab; ?>" 
                                               class="btn btn-danger btn-xs" 
                                               onclick="return confirm('⚠️ This will permanently delete this record. Continue?');" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-archive"></i>
                        <p>No archived records found</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalItems > 0 && $totalPages > 1): ?>
                <div class="pagination-bar">
                    <div class="info">
                        Showing <strong><?php echo $totalItems > 0 ? $offset + 1 : 0; ?></strong> 
                        to <strong><?php echo min($offset + $itemsPerPage, $totalItems); ?></strong> 
                        of <strong><?php echo $totalItems; ?></strong> records
                    </div>
                    <div class="pagination-controls">
                        <select onchange="changePerPage(this.value);">
                            <option value="5" <?php echo $itemsPerPage == 5 ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $itemsPerPage == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $itemsPerPage == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $itemsPerPage == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $itemsPerPage == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                        <span style="margin: 0 8px; color: var(--secondary-text);">per page</span>
                        
                        <button class="page-btn" onclick="goToPage(<?php echo $currentPage - 1; ?>)" 
                                <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        
                        <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                            <button class="page-btn <?php echo $i == $currentPage ? 'active' : ''; ?>" 
                                    onclick="goToPage(<?php echo $i; ?>);">
                                <?php echo $i; ?>
                            </button>
                        <?php endfor; ?>
                        
                        <button class="page-btn" onclick="goToPage(<?php echo $currentPage + 1; ?>)" 
                                <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- View Record Modal -->
    <?php if ($viewRecord && $viewTable): ?>
    <div class="modal-overlay" style="display: flex;">
        <div class="modal">
            <h3>
                <i class="fas fa-eye" style="color: var(--primary);"></i>
                Record Details
                <button type="button" class="close-modal" onclick="window.location.href='archive.php?tab=<?php echo $tab; ?>'">&times;</button>
            </h3>
            
            <div style="margin-bottom: 20px; padding: 15px; background: var(--bg); border-radius: var(--radius-sm);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <span class="module-badge-custom" style="background: <?php echo $tables[$viewTable]['color']; ?>20; color: <?php echo $tables[$viewTable]['color']; ?>; padding: 4px 14px; font-size: 13px;">
                        <i class="fas <?php echo $tables[$viewTable]['icon']; ?>"></i>
                        <?php echo $tables[$viewTable]['label']; ?>
                    </span>
                    <span class="status-badge status-archived">
                        <i class="fas fa-archive"></i> Archived
                    </span>
                </div>
                <div style="font-size: 12px; color: var(--secondary-text);">
                    Archived on: <?php echo isset($viewRecord['created_at']) ? date('M d, Y h:i A', strtotime($viewRecord['created_at'])) : 'N/A'; ?>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <?php 
                // Display all fields from the record
                $excludeFields = ['is_archived', 'updated_at'];
                foreach ($viewRecord as $key => $value):
                    if (in_array($key, $excludeFields)) continue;
                    $label = ucfirst(str_replace('_', ' ', $key));
                    $displayValue = is_null($value) ? 'N/A' : (is_numeric($value) ? $value : htmlspecialchars($value));
                ?>
                <div class="detail-row" style="grid-column: <?php echo (in_array($key, ['description', 'notes', 'address', 'shipping_address'])) ? '1 / -1' : 'auto'; ?>;">
                    <span class="label"><?php echo $label; ?></span>
                    <span class="value"><?php echo $displayValue; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-actions" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <a href="archive.php?tab=<?php echo $tab; ?>" class="btn btn-outline">Close</a>
                <a href="archive.php?action=restore&table=<?php echo $viewTable; ?>&id=<?php echo $viewRecord['id']; ?>&tab=<?php echo $tab; ?>" 
                   class="btn btn-success" onclick="return confirm('Restore this record?');">
                    <i class="fas fa-undo"></i> Restore
                </a>
                <a href="archive.php?action=delete&table=<?php echo $viewTable; ?>&id=<?php echo $viewRecord['id']; ?>&tab=<?php echo $tab; ?>" 
                   class="btn btn-danger" onclick="return confirm('⚠️ This will permanently delete this record. Continue?');">
                    <i class="fas fa-trash"></i> Delete
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // ============================================
        // TAB SWITCHING
        // ============================================
        function switchTab(tab) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }
        
        // ============================================
        // DROPDOWN TOGGLES
        // ============================================
        function toggleExportDropdown() {
            var dropdown = document.getElementById('exportDropdown');
            dropdown.classList.toggle('show');
        }
        
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-content').forEach(function(el) {
                    el.classList.remove('show');
                });
            }
        });
        
        // ============================================
        // SORTING
        // ============================================
        function sortTable(field) {
            var currentSort = '<?php echo $sortField; ?>';
            var currentOrder = '<?php echo $sortOrder; ?>';
            var newOrder = (currentSort === field && currentOrder === 'ASC') ? 'DESC' : 'ASC';
            
            var url = new URL(window.location.href);
            url.searchParams.set('sort', field);
            url.searchParams.set('order', newOrder);
            window.location.href = url.toString();
        }
        
        // ============================================
        // BATCH OPERATIONS
        // ============================================
        function toggleAll(master) {
            var checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(function(cb) {
                cb.checked = master.checked;
            });
            updateSelection();
        }
        
        function updateSelection() {
            var checkboxes = document.querySelectorAll('.row-checkbox:checked');
            var count = checkboxes.length;
            document.getElementById('selectedCount').textContent = count;
            var bar = document.getElementById('batchBar');
            if (count > 0) {
                bar.classList.add('show');
            } else {
                bar.classList.remove('show');
            }
        }
        
        function clearSelection() {
            document.querySelectorAll('.row-checkbox').forEach(function(cb) {
                cb.checked = false;
            });
            document.getElementById('selectAll').checked = false;
            updateSelection();
        }
        
        function bulkRestore() {
            var checkboxes = document.querySelectorAll('.row-checkbox:checked');
            var ids = [];
            checkboxes.forEach(function(cb) {
                ids.push(cb.value);
            });
            
            if (ids.length === 0) {
                alert('Please select at least one record.');
                return;
            }
            
            if (confirm('Restore ' + ids.length + ' selected records?')) {
                var form = document.getElementById('bulkForm');
                var table = document.getElementById('bulkTable').value;
                
                document.querySelectorAll('#bulkForm input[name="ids[]"]').forEach(function(el) {
                    if (!el.classList.contains('row-checkbox')) {
                        el.remove();
                    }
                });
                ids.forEach(function(id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                form.submit();
            }
        }
        
        // ============================================
        // PAGINATION
        // ============================================
        function goToPage(page) {
            var totalPages = <?php echo max(1, $totalPages); ?>;
            if (page < 1 || page > totalPages) return;
            var url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }
        
        function changePerPage(value) {
            var url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }
        
        // ============================================
        // FULLSCREEN TOGGLE
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            var fullscreenBtn = document.getElementById('fullscreenToggle');
            var icon = fullscreenBtn.querySelector('i');
            
            fullscreenBtn.addEventListener('click', function() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(function(err) {
                        console.log('Fullscreen not supported');
                    });
                    icon.className = 'fas fa-compress';
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                        icon.className = 'fas fa-expand';
                    }
                }
            });
            
            document.addEventListener('fullscreenchange', function() {
                if (document.fullscreenElement) {
                    icon.className = 'fas fa-compress';
                } else {
                    icon.className = 'fas fa-expand';
                }
            });
        });
        
        // ============================================
        // SIDEBAR TOGGLE (Mobile)
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            var sidebar = document.querySelector('.sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            
            var brand = document.querySelector('.sidebar-brand');
            if (brand) {
                var toggleBtn = document.createElement('button');
                toggleBtn.className = 'sidebar-toggle-btn';
                toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                toggleBtn.setAttribute('aria-label', 'Toggle Sidebar');
                toggleBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('open');
                    overlay.classList.toggle('active');
                });
                brand.appendChild(toggleBtn);
            }
            
            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                });
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>