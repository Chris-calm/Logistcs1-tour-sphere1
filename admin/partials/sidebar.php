<?php
// admin/partials/sidebar.php
// This file is included in all admin pages

// Get module visibility settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'show_%'");
    $visibility = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $visibility[$row['setting_key']] = $row['setting_value'] === 'true';
    }
} catch (Exception $e) {
    $visibility = [];
}

// Default to true if not set
$showWarehousing = isset($visibility['show_warehousing']) ? $visibility['show_warehousing'] : true;
$showInventory = isset($visibility['show_inventory']) ? $visibility['show_inventory'] : true;
$showProcurement = isset($visibility['show_procurement']) ? $visibility['show_procurement'] : true;
$showSuppliers = isset($visibility['show_suppliers']) ? $visibility['show_suppliers'] : true;
$showPurchaseOrders = isset($visibility['show_purchase_orders']) ? $visibility['show_purchase_orders'] : true;
$showLogistics = isset($visibility['show_logistics']) ? $visibility['show_logistics'] : true;

// Get theme
$theme = 'light';
try {
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'theme'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $theme = $result['setting_value'];
    }
} catch (Exception $e) {
    $theme = 'light';
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="sidebar" id="sidebar">
    <!-- Sidebar Brand -->
    <div class="sidebar-brand">
        <div>
            <h2>GlobalSCM</h2>
            <span>Supply Chain Management</span>
        </div>
        <button class="sidebar-close" id="sidebarClose" aria-label="Close Sidebar">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <!-- ===== MAIN NAVIGATION ===== -->
    <div class="nav-section">
        <div class="nav-section-title" onclick="toggleSection(this)">
            Main
            <span class="collapse-icon"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="nav-items">
            <a href="dashboard.php" class="nav-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <?php if (isAdmin()): ?>
            <a href="users.php" class="nav-item <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> User Management
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ===== WAREHOUSING MODULE ===== -->
    <?php if ($showWarehousing): ?>
    <div class="nav-section">
        <div class="nav-section-title" onclick="toggleSection(this)">
            Warehousing
            <span class="collapse-icon"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="nav-items">
            <a href="warehouses.php" class="nav-item <?php echo $currentPage === 'warehouses.php' ? 'active' : ''; ?>">
                <i class="fas fa-warehouse"></i> Warehouses
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM warehouses WHERE is_archived = 0");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    if ($count > 0): ?>
                    <span class="badge"><?php echo $count; ?></span>
                    <?php endif;
                } catch (Exception $e) {}
                ?>
            </a>
            <a href="warehouse-zones.php" class="nav-item sub-nav <?php echo $currentPage === 'warehouse-zones.php' ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i> Zone Management
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM warehouse_zones wz JOIN warehouses w ON wz.warehouse_id = w.id WHERE w.is_archived = 0");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    if ($count > 0): ?>
                    <span class="badge"><?php echo $count; ?></span>
                    <?php endif;
                } catch (Exception $e) {}
                ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ===== INVENTORY MODULE ===== -->
    <?php if ($showInventory): ?>
    <div class="nav-section">
        <div class="nav-section-title" onclick="toggleSection(this)">
            Inventory
            <span class="collapse-icon"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="nav-items">
            <a href="inventory.php" class="nav-item <?php echo $currentPage === 'inventory.php' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i> Products
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_archived = 0");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    if ($count > 0): ?>
                    <span class="badge"><?php echo $count; ?></span>
                    <?php endif;
                } catch (Exception $e) {}
                ?>
            </a>
            <a href="stock-movements.php" class="nav-item <?php echo $currentPage === 'stock-movements.php' ? 'active' : ''; ?>">
                <i class="fas fa-exchange-alt"></i> Stock Movements
            </a>
            <?php
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE current_stock <= reorder_point AND is_archived = 0");
                $lowStock = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                if ($lowStock > 0): ?>
                <a href="inventory.php?filter=low_stock" class="nav-item sub-nav" style="color: #DC2626;">
                    <i class="fas fa-exclamation-triangle"></i> Low Stock Alert
                    <span class="badge" style="background: #DC2626;"><?php echo $lowStock; ?></span>
                </a>
                <?php endif;
            } catch (Exception $e) {}
            ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ===== PROCUREMENT MODULE ===== -->
    <?php if ($showProcurement): ?>
    <div class="nav-section">
        <div class="nav-section-title" onclick="toggleSection(this)">
            Procurement
            <span class="collapse-icon"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="nav-items">
            <?php if ($showSuppliers): ?>
            <a href="suppliers.php" class="nav-item <?php echo $currentPage === 'suppliers.php' ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i> Suppliers
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM suppliers WHERE is_archived = 0");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    if ($count > 0): ?>
                    <span class="badge"><?php echo $count; ?></span>
                    <?php endif;
                } catch (Exception $e) {}
                ?>
            </a>
            <?php endif; ?>
            
            <?php if ($showPurchaseOrders): ?>
            <a href="purchase-orders.php" class="nav-item <?php echo $currentPage === 'purchase-orders.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice"></i> Purchase Orders
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status IN ('pending', 'approved') AND is_archived = 0");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    if ($count > 0): ?>
                    <span class="badge" style="background: #F59E0B;"><?php echo $count; ?></span>
                    <?php endif;
                } catch (Exception $e) {}
                ?>
            </a>
            <?php endif; ?>
            
            <a href="requisitions.php" class="nav-item <?php echo $currentPage === 'requisitions.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i> Requisitions
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM purchase_requisitions WHERE status = 'pending_review' AND is_archived = 0");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    if ($count > 0): ?>
                    <span class="badge" style="background: #F59E0B;"><?php echo $count; ?></span>
                    <?php endif;
                } catch (Exception $e) {}
                ?>
            </a>
            
            <a href="contracts.php" class="nav-item <?php echo $currentPage === 'contracts.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-signature"></i> Contracts
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM procurement_contracts WHERE status = 'active' AND is_archived = 0");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    if ($count > 0): ?>
                    <span class="badge" style="background: var(--accent);"><?php echo $count; ?></span>
                    <?php endif;
                } catch (Exception $e) {}
                ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ===== LOGISTICS MODULE ===== -->
    <?php if ($showLogistics): ?>
    <div class="nav-section">
        <div class="nav-section-title" onclick="toggleSection(this)">
            Logistics
            <span class="collapse-icon"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="nav-items">
            <a href="shipments.php" class="nav-item <?php echo $currentPage === 'shipments.php' ? 'active' : ''; ?>">
                <i class="fas fa-ship"></i> Shipments
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM shipments WHERE status IN ('pending', 'in_transit') AND is_archived = 0");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    if ($count > 0): ?>
                    <span class="badge" style="background: #1E40AF;"><?php echo $count; ?></span>
                    <?php endif;
                } catch (Exception $e) {}
                ?>
            </a>
            <a href="documents.php" class="nav-item <?php echo $currentPage === 'documents.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Documents
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM documents WHERE status = 'pending' AND is_archived = 0");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    if ($count > 0): ?>
                    <span class="badge" style="background: #F59E0B;"><?php echo $count; ?></span>
                    <?php endif;
                } catch (Exception $e) {}
                ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ===== ANALYTICS ===== -->
    <div class="nav-section">
        <div class="nav-section-title" onclick="toggleSection(this)">
            Analytics
            <span class="collapse-icon"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="nav-items">
            <a href="reports.php" class="nav-item <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reports
                <?php
                try {
                    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'report_ai_enabled'");
                    $aiEnabled = $stmt->fetch(PDO::FETCH_ASSOC)['setting_value'] ?? 'true';
                    if ($aiEnabled === 'true'): ?>
                    <span class="badge" style="background: var(--accent);">AI</span>
                    <?php endif;
                } catch (Exception $e) {}
                ?>
            </a>
            <a href="logs.php" class="nav-item <?php echo $currentPage === 'logs.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Audit Logs
            </a>
        </div>
    </div>
    
    <!-- ===== SYSTEM ===== -->
    <div class="nav-section">
        <div class="nav-section-title" onclick="toggleSection(this)">
            System
            <span class="collapse-icon"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="nav-items">
            <?php if (isAdmin()): ?>
            <a href="archive.php" class="nav-item <?php echo $currentPage === 'archive.php' ? 'active' : ''; ?>">
                <i class="fas fa-archive"></i> Archive
                <?php
                try {
                    $tables = ['users', 'suppliers', 'products', 'purchase_orders', 'shipments', 'documents'];
                    $totalArchived = 0;
                    foreach ($tables as $table) {
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table WHERE is_archived = 1");
                        $totalArchived += $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                    }
                    if ($totalArchived > 0): ?>
                    <span class="badge" style="background: #6B7280;"><?php echo $totalArchived; ?></span>
                    <?php endif;
                } catch (Exception $e) {}
                ?>
            </a>
            <a href="settings.php" class="nav-item <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
            <?php endif; ?>
            <a href="../logout.php" class="nav-item" onclick="return confirm('Are you sure you want to logout?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- ===== QUICK CREATE SECTION ===== -->
    <div class="quick-create-section">
        <div class="quick-create-dropdown">
            <button class="quick-create-btn" onclick="toggleQuickCreate()">
                <i class="fas fa-plus-circle"></i> Create New
                <i class="fas fa-chevron-down" style="font-size: 12px; margin-left: auto;"></i>
            </button>
            <div class="dropdown-content" id="quickCreateDropdown">
                <a href="purchase-orders.php?action=create">
                    <i class="fas fa-file-invoice"></i> Purchase Order
                </a>
                <a href="requisitions.php?action=create">
                    <i class="fas fa-clipboard-list"></i> Requisition
                </a>
                <a href="inventory.php?action=create">
                    <i class="fas fa-box"></i> Product
                </a>
                <a href="suppliers.php?action=create">
                    <i class="fas fa-truck"></i> Supplier
                </a>
                <a href="warehouses.php?action=create">
                    <i class="fas fa-warehouse"></i> Warehouse
                </a>
                <a href="shipments.php?action=create">
                    <i class="fas fa-ship"></i> Shipment
                </a>
            </div>
        </div>
    </div>
    
    <!-- ===== USER PROFILE ===== -->
    <div class="sidebar-profile">
        <div class="user-avatar">
            <?php echo isset($_SESSION['full_name']) ? substr($_SESSION['full_name'], 0, 1) : 'U'; ?>
        </div>
        <div class="user-info">
            <div class="name">
                <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'User'; ?>
            </div>
            <div class="role">
                <?php echo isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'User'; ?>
            </div>
        </div>
        <div class="status-dot" title="Online"></div>
    </div>
</nav>

<!-- ===== SIDEBAR OVERLAY (Mobile) ===== -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
    .sidebar {
        display: flex;
        flex-direction: column;
        height: 100vh;
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
        width: 280px;
        box-shadow: var(--shadow);
    }
    
    .sidebar .nav-section:last-of-type {
        margin-bottom: 0;
    }
    
    .sub-nav {
        padding-left: 45px !important;
        font-size: 13px !important;
        padding-top: 6px !important;
        padding-bottom: 6px !important;
        font-weight: 400 !important;
    }
    
    .sub-nav i {
        width: 18px !important;
        font-size: 13px !important;
    }
    
    .sidebar-brand h2 {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 20px;
        font-weight: 700;
        color: var(--primary);
    }
    
    .sidebar-brand h2::after {
        content: '®';
        font-size: 10px;
        color: var(--secondary-text);
        font-weight: 400;
    }
    
    .sidebar-brand span {
        font-size: 11px;
        color: var(--secondary-text);
        font-weight: 400;
        letter-spacing: 1px;
        text-transform: uppercase;
        display: block;
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
    
    .sidebar-close {
        display: none;
        background: none;
        border: none;
        font-size: 20px;
        color: var(--secondary-text);
        cursor: pointer;
        padding: 4px 8px;
        transition: var(--transition);
    }
    
    .sidebar-close:hover {
        color: var(--text);
    }
    
    .nav-section {
        margin-bottom: 16px;
    }
    
    .nav-section-title {
        font-size: 10px;
        text-transform: uppercase;
        color: var(--secondary-text);
        font-weight: 600;
        letter-spacing: 1.2px;
        margin-bottom: 6px;
        padding: 6px 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: var(--transition);
        border-radius: var(--radius-sm);
    }
    
    .nav-section-title:hover {
        color: var(--text);
        background: rgba(47, 128, 237, 0.04);
    }
    
    .nav-section-title .collapse-icon {
        font-size: 10px;
        transition: var(--transition);
    }
    
    .nav-section-title .collapse-icon.collapsed {
        transform: rotate(-90deg);
    }
    
    .nav-items {
        overflow: hidden;
        transition: max-height 0.3s ease;
        max-height: 500px;
    }
    
    .nav-items.collapsed {
        max-height: 0 !important;
    }
    
    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 14px;
        border-radius: var(--radius-sm);
        color: var(--secondary-text);
        text-decoration: none;
        transition: var(--transition);
        margin-bottom: 2px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        position: relative;
    }
    
    .nav-item:hover {
        background: rgba(47, 128, 237, 0.08);
        color: var(--primary);
    }
    
    .nav-item i {
        width: 20px;
        font-size: 16px;
        text-align: center;
        flex-shrink: 0;
    }
    
    .nav-item .badge {
        margin-left: auto;
        background: var(--primary);
        color: white;
        padding: 1px 8px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 600;
        min-width: 18px;
        text-align: center;
    }
    
    .nav-item.active {
        background: rgba(47, 128, 237, 0.1);
        color: var(--primary);
    }
    
    .nav-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 24px;
        background: var(--primary);
        border-radius: 0 4px 4px 0;
    }
    
    .badge.danger {
        background: var(--danger);
    }
    
    .badge.warning {
        background: var(--warning);
    }
    
    .badge.success {
        background: var(--accent);
    }
    
    .badge.info {
        background: #1E40AF;
    }
    
    .badge.secondary {
        background: #6B7280;
    }
    
    /* Quick Create Section */
    .quick-create-section {
        margin-top: auto;
        padding-top: 16px;
        border-top: 1px solid var(--border);
    }
    
    .quick-create-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: var(--radius-sm);
        background: var(--primary);
        color: white;
        border: none;
        width: 100%;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Poppins', sans-serif;
        font-weight: 500;
        font-size: 14px;
        text-align: left;
    }
    
    .quick-create-btn:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(47, 128, 237, 0.3);
    }
    
    .quick-create-btn i {
        font-size: 16px;
    }
    
    .quick-create-btn .fa-chevron-down {
        margin-left: auto;
        font-size: 12px;
    }
    
    /* Quick Create Dropdown */
    .quick-create-dropdown {
        position: relative;
    }
    
    .quick-create-dropdown .dropdown-content {
        display: none;
        position: absolute;
        bottom: 100%;
        left: 0;
        right: 0;
        background: var(--card);
        min-width: 100%;
        box-shadow: var(--shadow-lg);
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        padding: 6px 0;
        z-index: 10;
        margin-bottom: 4px;
    }
    
    .quick-create-dropdown .dropdown-content.show {
        display: block;
    }
    
    .quick-create-dropdown .dropdown-content a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 16px;
        color: var(--text);
        text-decoration: none;
        font-size: 13px;
        transition: var(--transition);
    }
    
    .quick-create-dropdown .dropdown-content a:hover {
        background: rgba(47, 128, 237, 0.05);
        color: var(--primary);
    }
    
    .quick-create-dropdown .dropdown-content a i {
        width: 18px;
        color: var(--secondary-text);
    }
    
    /* User Profile */
    .sidebar-profile {
        padding-top: 16px;
        border-top: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 12px 0;
        margin-top: 8px;
    }
    
    .sidebar-profile .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
        flex-shrink: 0;
    }
    
    .sidebar-profile .user-info {
        flex: 1;
        min-width: 0;
    }
    
    .sidebar-profile .user-info .name {
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .sidebar-profile .user-info .role {
        font-size: 11px;
        color: var(--secondary-text);
    }
    
    .sidebar-profile .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--accent);
        flex-shrink: 0;
        animation: pulse-dot 2s infinite;
    }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    /* Scrollbar */
    .sidebar::-webkit-scrollbar {
        width: 4px;
    }
    
    .sidebar::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .sidebar::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 4px;
    }
    
    .sidebar::-webkit-scrollbar-thumb:hover {
        background: var(--secondary-text);
    }
    
    /* Sidebar Overlay */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        z-index: 99;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
    
    .sidebar-overlay.active {
        display: block;
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
        .sidebar {
            width: 0;
            padding: 0;
            overflow: hidden;
            position: fixed;
            left: -320px;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-lg);
        }
        
        .sidebar.open {
            left: 0;
            width: 300px;
            padding: 20px 16px;
        }
        
        .sidebar-close {
            display: block;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
    }
</style>

<script>
    // ============================================
    // SIDEBAR COLLAPSIBLE SECTIONS
    // ============================================
    function toggleSection(title) {
        var items = title.nextElementSibling;
        if (items && items.classList.contains('nav-items')) {
            var isCollapsed = items.classList.toggle('collapsed');
            var icon = title.querySelector('.collapse-icon');
            if (icon) {
                icon.classList.toggle('collapsed');
            }
            
            // Store state in localStorage
            var sectionText = title.textContent.trim();
            localStorage.setItem('sidebar_section_' + sectionText, isCollapsed ? 'collapsed' : 'expanded');
        }
    }
    
    // Restore section states
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.nav-section-title').forEach(function(title) {
            var sectionText = title.textContent.trim();
            var state = localStorage.getItem('sidebar_section_' + sectionText);
            var items = title.nextElementSibling;
            
            if (state === 'collapsed' && items && items.classList.contains('nav-items')) {
                items.classList.add('collapsed');
                var icon = title.querySelector('.collapse-icon');
                if (icon) {
                    icon.classList.add('collapsed');
                }
            }
        });
    });
    
    // ============================================
    // QUICK CREATE DROPDOWN
    // ============================================
    function toggleQuickCreate() {
        var dropdown = document.getElementById('quickCreateDropdown');
        dropdown.classList.toggle('show');
    }
    
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.quick-create-dropdown')) {
            document.querySelectorAll('.quick-create-dropdown .dropdown-content').forEach(function(el) {
                el.classList.remove('show');
            });
        }
    });
    
    // ============================================
    // SIDEBAR TOGGLE (Mobile)
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var closeBtn = document.getElementById('sidebarClose');
        
        // Create toggle button in sidebar brand
        var brand = document.querySelector('.sidebar-brand');
        if (brand && !document.querySelector('.sidebar-toggle-btn')) {
            var toggleBtn = document.createElement('button');
            toggleBtn.className = 'sidebar-toggle-btn';
            toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
            toggleBtn.setAttribute('aria-label', 'Toggle Sidebar');
            toggleBtn.style.cssText = 'display:none; background: none; border: none; font-size: 20px; color: var(--secondary-text); cursor: pointer; padding: 4px 8px;';
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
            });
            brand.appendChild(toggleBtn);
            
            // Show toggle button on mobile
            if (window.innerWidth <= 768) {
                toggleBtn.style.display = 'block';
            }
            window.addEventListener('resize', function() {
                toggleBtn.style.display = window.innerWidth <= 768 ? 'block' : 'none';
                if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        }
        
        // Close button
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
        
        // Overlay click
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
        
        // Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
</script>