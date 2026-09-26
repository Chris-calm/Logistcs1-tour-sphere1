<?php
// api/settings.php
// API endpoint for system settings management

require_once '../config/database.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get action
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        // Get all settings
        $stmt = $pdo->query("SELECT setting_key, setting_value, setting_group, description FROM system_settings ORDER BY setting_group, setting_key");
        $settings = $stmt->fetchAll();
        
        // Group settings
        $grouped = [];
        foreach ($settings as $setting) {
            $group = $setting['setting_group'] ?: 'general';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = [
                'key' => $setting['setting_key'],
                'value' => $setting['setting_value'],
                'description' => $setting['description']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'settings' => $grouped
        ]);
        break;
        
    case 'get':
        // Get a specific setting
        $key = $_GET['key'] ?? '';
        
        if (empty($key)) {
            http_response_code(400);
            echo json_encode(['error' => 'Setting key is required']);
            exit();
        }
        
        $stmt = $pdo->prepare("SELECT setting_key, setting_value, setting_group, description FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $setting = $stmt->fetch();
        
        if (!$setting) {
            http_response_code(404);
            echo json_encode(['error' => 'Setting not found']);
            exit();
        }
        
        echo json_encode([
            'success' => true,
            'setting' => $setting
        ]);
        break;
        
    case 'update':
        // Update a single setting
        $data = json_decode(file_get_contents('php://input'), true);
        $key = $data['key'] ?? $_POST['key'] ?? $_GET['key'] ?? '';
        $value = $data['value'] ?? $_POST['value'] ?? $_GET['value'] ?? '';
        
        if (empty($key)) {
            http_response_code(400);
            echo json_encode(['error' => 'Setting key is required']);
            exit();
        }
        
        // Validate key exists
        $stmt = $pdo->prepare("SELECT setting_key FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Setting not found']);
            exit();
        }
        
        // Update setting
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
        
        logAudit($_SESSION['user_id'], 'update_setting', 'system', "Updated setting: $key = $value");
        
        echo json_encode([
            'success' => true,
            'message' => 'Setting updated successfully',
            'key' => $key,
            'value' => $value
        ]);
        break;
        
    case 'update_bulk':
        // Update multiple settings
        $data = json_decode(file_get_contents('php://input'), true);
        $settings = $data['settings'] ?? $_POST['settings'] ?? [];
        
        if (empty($settings) || !is_array($settings)) {
            http_response_code(400);
            echo json_encode(['error' => 'Settings array is required']);
            exit();
        }
        
        $pdo->beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
            $pdo->commit();
            
            logAudit($_SESSION['user_id'], 'update_settings_bulk', 'system', "Updated " . count($settings) . " settings");
            
            echo json_encode([
                'success' => true,
                'message' => 'Settings updated successfully',
                'count' => count($settings)
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'theme':
        // Toggle theme
        $theme = $_POST['theme'] ?? $_GET['theme'] ?? '';
        
        if (!in_array($theme, ['light', 'dark'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Theme must be "light" or "dark"']);
            exit();
        }
        
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'theme'");
        $stmt->execute([$theme]);
        
        logAudit($_SESSION['user_id'], 'update_theme', 'system', "Theme changed to: $theme");
        
        echo json_encode([
            'success' => true,
            'theme' => $theme,
            'message' => 'Theme updated successfully'
        ]);
        break;
        
    case 'visibility':
        // Update module visibility
        $module = $_POST['module'] ?? $_GET['module'] ?? '';
        $visible = $_POST['visible'] ?? $_GET['visible'] ?? '';
        
        if (empty($module)) {
            http_response_code(400);
            echo json_encode(['error' => 'Module name is required']);
            exit();
        }
        
        $key = 'show_' . $module;
        $value = filter_var($visible, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        
        // Check if setting exists
        $stmt = $pdo->prepare("SELECT setting_key FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'ui')");
            $stmt->execute([$key, $value]);
        }
        
        logAudit($_SESSION['user_id'], 'update_visibility', 'system', "Module visibility: $module = $value");
        
        echo json_encode([
            'success' => true,
            'module' => $module,
            'visible' => $value === 'true',
            'message' => 'Visibility updated successfully'
        ]);
        break;
        
    case 'currency':
        // Update currency settings
        $currency_symbol = $_POST['currency_symbol'] ?? $_GET['currency_symbol'] ?? '';
        $currency_code = $_POST['currency_code'] ?? $_GET['currency_code'] ?? '';
        
        if (!empty($currency_symbol)) {
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'currency_symbol'");
            $stmt->execute([$currency_symbol]);
        }
        
        if (!empty($currency_code)) {
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'currency_code'");
            $stmt->execute([$currency_code]);
        }
        
        logAudit($_SESSION['user_id'], 'update_currency', 'system', "Currency updated: $currency_symbol ($currency_code)");
        
        echo json_encode([
            'success' => true,
            'currency_symbol' => $currency_symbol,
            'currency_code' => $currency_code,
            'message' => 'Currency settings updated successfully'
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>