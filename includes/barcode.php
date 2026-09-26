<?php
/**
 * includes/barcode.php
 * Barcode generation library wrapper with fallback support
 */

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
 * BarcodeGenerator Class
 * Handles barcode generation with fallback support
 */
class BarcodeGenerator {
    private $useLibrary = false;
    
    public function __construct() {
        global $useLibrary;
        $this->useLibrary = $useLibrary;
    }
    
    /**
     * Generate a barcode image
     */
    public function generate($code, $type = 'CODE128', $width = 200, $height = 80) {
        if ($this->useLibrary) {
            return $this->generateWithLibrary($code, $type, $width, $height);
        }
        return $this->generateSimple($code, $width, $height);
    }
    
    /**
     * Generate barcode using Picqer library
     */
    private function generateWithLibrary($code, $type, $width, $height) {
        try {
            $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
            
            // Map type to library constants
            $typeMap = [
                'CODE128' => Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_128,
                'CODE39' => Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_39,
                'EAN13' => Picqer\Barcode\BarcodeGeneratorPNG::TYPE_EAN_13,
                'UPC' => Picqer\Barcode\BarcodeGeneratorPNG::TYPE_UPC_A,
            ];
            
            $barcodeType = isset($typeMap[$type]) ? $typeMap[$type] : Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_128;
            
            $barcode = $generator->getBarcode($code, $barcodeType, $width, $height);
            return base64_encode($barcode);
        } catch (Exception $e) {
            // Fallback to simple generation
            return $this->generateSimple($code, $width, $height);
        }
    }
    
    /**
     * Generate simple barcode using GD (fallback)
     */
    private function generateSimple($code, $width, $height) {
        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            return $this->generateTextBarcode($code);
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
     * Generate text-based barcode (fallback when GD not available)
     */
    private function generateTextBarcode($code) {
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
     * Generate a barcode and save to file
     */
    public function saveToFile($code, $filepath, $type = 'CODE128', $width = 200, $height = 80) {
        $barcodeData = $this->generate($code, $type, $width, $height);
        $binaryData = base64_decode($barcodeData);
        return file_put_contents($filepath, $binaryData) !== false;
    }
    
    /**
     * Generate multiple barcodes at once
     */
    public function generateBatch($codes, $type = 'CODE128', $width = 200, $height = 80) {
        $results = [];
        foreach ($codes as $code) {
            $results[$code] = $this->generate($code, $type, $width, $height);
        }
        return $results;
    }
    
    /**
     * Get available barcode types
     */
    public function getAvailableTypes() {
        return [
            'CODE128' => 'Code 128',
            'CODE39' => 'Code 39',
            'EAN13' => 'EAN-13',
            'UPC' => 'UPC-A'
        ];
    }
    
    /**
     * Check if library is available
     */
    public function isLibraryAvailable() {
        return $this->useLibrary;
    }
}

/**
 * Helper function to generate barcode
 */
function generateBarcode($code, $type = 'CODE128', $width = 200, $height = 80) {
    $generator = new BarcodeGenerator();
    return $generator->generate($code, $type, $width, $height);
}

/**
 * Helper function to generate product barcode
 */
function generateProductBarcode($productId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT sku, barcode FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $code = !empty($product['barcode']) ? $product['barcode'] : $product['sku'];
            return generateBarcode($code);
        }
    } catch (Exception $e) {
        // Return null on error
    }
    
    return null;
}

/**
 * Helper function to generate serial number
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

/**
 * Display barcode HTML
 */
function displayBarcode($code, $type = 'CODE128', $width = 200, $height = 80) {
    $barcode = generateBarcode($code, $type, $width, $height);
    if ($barcode) {
        return '<img src="data:image/png;base64,' . $barcode . '" alt="Barcode: ' . htmlspecialchars($code) . '" title="' . htmlspecialchars($code) . '" class="barcode-image">';
    }
    return '<span class="text-muted">Barcode not available</span>';
}

/**
 * Display product barcode
 */
function displayProductBarcode($productId) {
    $barcode = generateProductBarcode($productId);
    if ($barcode) {
        return '<img src="data:image/png;base64,' . $barcode . '" alt="Product Barcode" class="barcode-image">';
    }
    return '<span class="text-muted">Barcode not available</span>';
}