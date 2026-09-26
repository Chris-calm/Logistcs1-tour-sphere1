<?php
/**
 * api/barcode.php
 * Barcode generation API endpoint
 */

require_once '../config/database.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get action
$action = isset($_GET['action']) ? $_GET['action'] : 'generate';

// Check if barcode library exists
$barcodeLibPath = __DIR__ . '/../vendor/autoload.php';
$useLibrary = false;

if (file_exists($barcodeLibPath)) {
    try {
        require_once $barcodeLibPath;
        if (class_exists('Picqer\\Barcode\\BarcodeGeneratorPNG')) {
            $useLibrary = true;
        }
    } catch (Exception $e) {
        $useLibrary = false;
    }
}

/**
 * Generate barcode image
 */
function generateBarcodeImage($code, $type = 'CODE128', $width = 200, $height = 80) {
    global $useLibrary;
    
    // If library is available, use it
    if ($useLibrary) {
        try {
            $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
            $barcode = $generator->getBarcode($code, $generator::TYPE_CODE_128, $width, $height);
            return base64_encode($barcode);
        } catch (Exception $e) {
            // Fallback to manual generation
        }
    }
    
    // Fallback: Generate simple barcode using GD
    return generateSimpleBarcode($code, $width, $height);
}

/**
 * Generate simple barcode (fallback when library not available)
 */
function generateSimpleBarcode($code, $width = 200, $height = 80) {
    // Check if GD extension is available
    if (!extension_loaded('gd')) {
        return generateTextBarcode($code);
    }
    
    $code = (string)$code;
    $image = imagecreate($width, $height);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    
    // Simple barcode representation
    $barWidth = $width / (strlen($code) * 2 + 1);
    $x = 0;
    
    for ($i = 0; $i < strlen($code); $i++) {
        $char = ord($code[$i]);
        $barCount = ($char % 5) + 2;
        $barWidthCurrent = $barWidth * $barCount;
        
        // Draw bar
        if ($i % 2 == 0) {
            imagefilledrectangle($image, $x, 0, $x + $barWidthCurrent, $height, $black);
        }
        $x += $barWidthCurrent;
    }
    
    // Add text below barcode
    $textColor = imagecolorallocate($image, 80, 80, 80);
    $fontSize = 5;
    $textWidth = imagefontwidth($fontSize) * strlen($code);
    $textX = ($width - $textWidth) / 2;
    imagestring($image, $fontSize, $textX, $height - 20, $code, $textColor);
    
    ob_start();
    imagepng($image);
    $imageData = ob_get_clean();
    imagedestroy($image);
    
    return base64_encode($imageData);
}

/**
 * Generate text-based barcode fallback (when GD is not available)
 */
function generateTextBarcode($code) {
    // Create a simple HTML/CSS barcode representation
    $html = '<div style="font-family: monospace; font-size: 12px; text-align: center; padding: 10px; background: white; border: 1px solid #ccc; border-radius: 4px;">';
    $html .= '<div style="display: flex; align-items: flex-end; height: 60px; justify-content: center;">';
    
    for ($i = 0; $i < strlen($code); $i++) {
        $char = ord($code[$i]);
        $height = 20 + ($char % 30);
        $width = 4 + ($char % 6);
        $color = $i % 2 == 0 ? '#000' : '#fff';
        $html .= '<div style="width: ' . $width . 'px; height: ' . $height . 'px; background: ' . $color . '; border-left: 1px solid #ddd;"></div>';
    }
    
    $html .= '</div>';
    $html .= '<div style="margin-top: 10px; font-size: 14px; letter-spacing: 2px; color: #333;">' . htmlspecialchars($code) . '</div>';
    $html .= '</div>';
    
    return base64_encode($html);
}

/**
 * Generate serial number
 */
function generateSerialNumber($productId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT serial_number_prefix, last_serial_number FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $prefix = !empty($product['serial_number_prefix']) ? $product['serial_number_prefix'] : 'SN';
            $last = !empty($product['last_serial_number']) ? (int)$product['last_serial_number'] : 0;
            $newSerial = $last + 1;
            
            // Update last serial number
            $stmt = $pdo->prepare("UPDATE products SET last_serial_number = ? WHERE id = ?");
            $stmt->execute([$newSerial, $productId]);
            
            return $prefix . str_pad($newSerial, 6, '0', STR_PAD_LEFT);
        }
    } catch (Exception $e) {
        // If there's an error, generate a random serial
    }
    
    return 'SN' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

// Handle different actions
try {
    switch ($action) {
        case 'generate':
            // Generate barcode for a specific code
            $code = isset($_GET['code']) ? $_GET['code'] : (isset($_POST['code']) ? $_POST['code'] : '');
            $productId = isset($_GET['product_id']) ? $_GET['product_id'] : (isset($_POST['product_id']) ? $_POST['product_id'] : null);
            
            if (empty($code) && empty($productId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Code or product_id is required']);
                exit();
            }
            
            // If product_id is provided, get the barcode from product
            $productName = null;
            if ($productId) {
                try {
                    $stmt = $pdo->prepare("SELECT sku, barcode, product_name FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($product) {
                        $code = !empty($product['barcode']) ? $product['barcode'] : $product['sku'];
                        $productName = $product['product_name'];
                    }
                } catch (Exception $e) {
                    // If product lookup fails, use the provided code
                }
            }
            
            // Get barcode type
            $barcodeType = 'CODE128';
            try {
                $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'barcode_format'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && !empty($result['setting_value'])) {
                    $barcodeType = $result['setting_value'];
                }
            } catch (Exception $e) {
                // Use default if settings table doesn't exist
            }
            
            // Generate barcode
            $barcodeImage = generateBarcodeImage($code, $barcodeType);
            
            echo json_encode([
                'success' => true,
                'code' => $code,
                'barcode' => $barcodeImage,
                'type' => $barcodeType,
                'product_id' => $productId,
                'product_name' => $productName,
                'format' => 'png_base64'
            ]);
            break;
            
        case 'generate_serial':
            // Generate serial number for a product
            $productId = isset($_GET['product_id']) ? $_GET['product_id'] : (isset($_POST['product_id']) ? $_POST['product_id'] : null);
            
            if (!$productId) {
                http_response_code(400);
                echo json_encode(['error' => 'product_id is required']);
                exit();
            }
            
            $serialNumber = generateSerialNumber($productId);
            
            echo json_encode([
                'success' => true,
                'serial_number' => $serialNumber,
                'product_id' => $productId
            ]);
            break;
            
        case 'batch':
            // Generate multiple barcodes
            $codes = isset($_POST['codes']) ? $_POST['codes'] : (isset($_GET['codes']) ? $_GET['codes'] : []);
            
            if (!is_array($codes) || empty($codes)) {
                http_response_code(400);
                echo json_encode(['error' => 'codes array is required']);
                exit();
            }
            
            $results = [];
            foreach ($codes as $code) {
                $barcodeImage = generateBarcodeImage($code);
                $results[] = [
                    'code' => $code,
                    'barcode' => $barcodeImage
                ];
            }
            
            echo json_encode([
                'success' => true,
                'count' => count($results),
                'results' => $results
            ]);
            break;
            
        case 'product_barcode':
            // Get product barcode
            $productId = isset($_GET['product_id']) ? $_GET['product_id'] : (isset($_POST['product_id']) ? $_POST['product_id'] : null);
            
            if (!$productId) {
                http_response_code(400);
                echo json_encode(['error' => 'product_id is required']);
                exit();
            }
            
            try {
                $stmt = $pdo->prepare("SELECT id, sku, product_name, barcode, current_stock FROM products WHERE id = ? AND is_archived = 0");
                $stmt->execute([$productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Product not found']);
                    exit();
                }
                
                $code = !empty($product['barcode']) ? $product['barcode'] : $product['sku'];
                $barcodeImage = generateBarcodeImage($code);
                
                echo json_encode([
                    'success' => true,
                    'product' => $product,
                    'barcode' => $barcodeImage
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}