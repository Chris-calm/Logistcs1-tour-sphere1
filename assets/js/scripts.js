/**
 * GlobalSCM - Main JavaScript Functions
 * Includes API calls, UI interactions, and utility functions
 */

// ============================================
// 1. API Configuration & Helpers
// ============================================

const API = {
    baseUrl: '/api/',
    
    /**
     * Make a GET request to the API
     */
    get: function(endpoint, params = {}) {
        const url = new URL(this.baseUrl + endpoint, window.location.origin);
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.append(key, params[key]);
            }
        });
        
        return fetch(url.toString(), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        });
    },
    
    /**
     * Make a POST request to the API
     */
    post: function(endpoint, data = {}) {
        return fetch(this.baseUrl + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        });
    },
    
    /**
     * Make a PUT request to the API
     */
    put: function(endpoint, data = {}) {
        return fetch(this.baseUrl + endpoint, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        });
    }
};

// ============================================
// 2. Barcode Functions
// ============================================

const BarcodeManager = {
    /**
     * Generate a barcode for a product
     */
    generate: function(code, productId = null) {
        var params = {
            action: 'generate',
            code: code
        };
        if (productId) {
            params.product_id = productId;
        }
        return API.get('barcode.php', params);
    },
    
    /**
     * Generate a serial number for a product
     */
    generateSerial: function(productId) {
        return API.get('barcode.php', {
            action: 'generate_serial',
            product_id: productId
        });
    },
    
    /**
     * Display a barcode in an element
     */
    displayBarcode: function(elementId, code, productId) {
        var self = this;
        this.generate(code, productId)
            .then(function(data) {
                if (data.success) {
                    var img = document.getElementById(elementId);
                    if (img) {
                        img.src = 'data:image/png;base64,' + data.barcode;
                        img.alt = 'Barcode: ' + data.code;
                        img.title = 'Barcode: ' + data.code;
                    }
                }
            })
            .catch(function(error) {
                console.error('Error generating barcode:', error);
            });
    },
    
    /**
     * Print barcode label
     */
    printLabel: function(productId) {
        var self = this;
        this.generate(null, productId)
            .then(function(data) {
                if (data.success) {
                    // Open print window
                    var printWindow = window.open('', '_blank', 'width=400,height=300');
                    if (printWindow) {
                        printWindow.document.write(
                            '<html>' +
                                '<head><title>Barcode Label</title></head>' +
                                '<body style="text-align:center;font-family:Arial;padding:30px;">' +
                                    '<div style="border:1px solid #ccc;padding:20px;max-width:300px;margin:0 auto;">' +
                                        '<h3>' + (data.product_name || 'Product') + '</h3>' +
                                        '<img src="data:image/png;base64,' + data.barcode + '" style="width:100%;">' +
                                        '<p style="font-size:14px;margin-top:10px;">' + data.code + '</p>' +
                                        '<p style="font-size:12px;color:#666;">Generated: ' + new Date().toLocaleString() + '</p>' +
                                    '</div>' +
                                    '<script>' +
                                        'window.onload = function() {' +
                                            'window.print();' +
                                            'window.close();' +
                                        '};' +
                                    '<\/script>' +
                                '</body>' +
                            '</html>'
                        );
                        printWindow.document.close();
                    }
                }
            })
            .catch(function(error) {
                console.error('Error printing label:', error);
                showNotification('Error printing barcode label', 'error');
            });
    }
};

// ============================================
// 3. Stock Management Functions
// ============================================

var StockManager = {
    /**
     * Get stock summary
     */
    getSummary: function() {
        return API.get('stock.php', { action: 'summary' });
    },
    
    /**
     * Get stock for a specific product
     */
    getProduct: function(id) {
        return API.get('stock.php', { action: 'get', id: id });
    },
    
    /**
     * Adjust stock level
     */
    adjustStock: function(productId, quantity, type, notes) {
        type = type || 'adjustment';
        notes = notes || '';
        return API.post('stock.php?action=adjust', {
            id: productId,
            quantity: quantity,
            type: type,
            notes: notes
        });
    },
    
    /**
     * Get stock alerts
     */
    getAlerts: function() {
        return API.get('stock.php', { action: 'alert' });
    },
    
    /**
     * Update stock display
     */
    updateStockDisplay: function() {
        var self = this;
        this.getSummary()
            .then(function(data) {
                if (data.success) {
                    // Update dashboard stats
                    document.querySelectorAll('[data-stock-stat]').forEach(function(el) {
                        var key = el.dataset.stockStat;
                        if (data.summary && data.summary[key] !== undefined) {
                            el.textContent = data.summary[key].toLocaleString();
                        }
                    });
                    
                    // Update category charts if they exist
                    if (data.categories && data.categories.length > 0) {
                        // Trigger chart update
                        document.dispatchEvent(new CustomEvent('stockUpdated', { 
                            detail: data 
                        }));
                    }
                }
            })
            .catch(function(error) {
                console.error('Error updating stock display:', error);
            });
    },
    
    /**
     * Check for low stock alerts
     */
    checkAlerts: function() {
        var self = this;
        this.getAlerts()
            .then(function(data) {
                if (data.success) {
                    var alertCount = (data.critical ? data.critical.length : 0) + (data.warning ? data.warning.length : 0);
                    var alertBadge = document.querySelector('[data-alert-badge]');
                    
                    if (alertBadge) {
                        if (alertCount > 0) {
                            alertBadge.textContent = alertCount;
                            alertBadge.style.display = 'inline';
                            alertBadge.style.backgroundColor = (data.critical && data.critical.length > 0) ? '#DC2626' : '#F59E0B';
                        } else {
                            alertBadge.style.display = 'none';
                        }
                    }
                    
                    // Show notifications for critical alerts
                    if (data.critical && data.critical.length > 0) {
                        data.critical.forEach(function(item) {
                            showNotification(
                                '⚠️ ' + item.product_name + ' is out of stock!',
                                'critical'
                            );
                        });
                    }
                }
            })
            .catch(function(error) {
                console.error('Error checking alerts:', error);
            });
    }
};

// ============================================
// 4. Report Functions
// ============================================

var ReportManager = {
    /**
     * Generate AI report
     */
    generateReport: function(period) {
        period = period || 'daily';
        return API.get('report.php', { period: period });
    },
    
    /**
     * Display AI report in the UI
     */
    displayReport: function(containerId, period) {
        period = period || 'daily';
        var container = document.getElementById(containerId);
        if (!container) return;
        
        container.innerHTML = '<div class="loading">Generating AI Report...</div>';
        
        var self = this;
        this.generateReport(period)
            .then(function(data) {
                if (data.success && data.report) {
                    container.innerHTML = self.renderReport(data.report);
                } else {
                    container.innerHTML = '<div class="error">Failed to generate report</div>';
                }
            })
            .catch(function(error) {
                console.error('Error generating report:', error);
                container.innerHTML = '<div class="error">Error generating report</div>';
            });
    },
    
    /**
     * Render report HTML
     */
    renderReport: function(report) {
        var html = '';
        html += '<div class="ai-report">';
        html += '<div class="report-header">';
        html += '<h3>📊 AI-Generated Daily Report</h3>';
        html += '<div class="report-meta">';
        html += '<span>' + (report.date || '') + '</span>';
        html += '<span>Generated: ' + (report.generated_at || '') + '</span>';
        html += '</div>';
        html += '</div>';
        
        // Insights
        if (report.insights && report.insights.length > 0) {
            html += '<div class="report-section">';
            html += '<h4>💡 AI Insights</h4>';
            html += '<ul class="insight-list">';
            report.insights.forEach(function(i) {
                html += '<li class="insight-item">' + i + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        // Summary Stats
        if (report.summary) {
            html += '<div class="report-section">';
            html += '<h4>📈 Summary</h4>';
            html += '<div class="summary-grid">';
            html += '<div class="summary-item">';
            html += '<span class="label">Stock Movement</span>';
            html += '<span class="value ' + ((report.summary.net_change || 0) > 0 ? 'positive' : 'negative') + '">';
            html += (report.summary.net_change || 0) > 0 ? '+' : '';
            html += report.summary.net_change || 0;
            html += '</span>';
            html += '</div>';
            html += '<div class="summary-item">';
            html += '<span class="label">Low Stock Items</span>';
            html += '<span class="value ' + ((report.summary.low_stock || 0) > 0 ? 'warning' : 'ok') + '">';
            html += report.summary.low_stock || 0;
            html += '</span>';
            html += '</div>';
            html += '<div class="summary-item">';
            html += '<span class="label">Pending POs</span>';
            html += '<span class="value">' + ((report.summary.pending_pos && report.summary.pending_pos.pending_count) || 0) + '</span>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
        }
        
        // Risks
        if (report.risks && report.risks.length > 0) {
            html += '<div class="report-section">';
            html += '<h4>⚠️ Risk Assessment</h4>';
            html += '<ul class="risk-list">';
            report.risks.forEach(function(r) {
                html += '<li class="risk-item">' + r + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        // Recommendations
        if (report.recommendations && report.recommendations.length > 0) {
            html += '<div class="report-section">';
            html += '<h4>💡 Recommendations</h4>';
            html += '<ul class="recommendation-list">';
            report.recommendations.forEach(function(r) {
                html += '<li class="recommendation-item">' + r + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        // Predictions
        if (report.predictions) {
            html += '<div class="report-section">';
            html += '<h4>🔮 Predictions</h4>';
            html += '<div class="predictions-grid">';
            html += '<div class="prediction-item">';
            html += '<span class="label">Stockout Probability</span>';
            html += '<span class="value">' + (report.predictions.stockout_probability || 'N/A') + '</span>';
            html += '<span class="status ' + (report.predictions.stockout_risk_level || 'low').toLowerCase() + '">';
            html += (report.predictions.stockout_risk_level || 'Low') + ' Risk';
            html += '</span>';
            html += '</div>';
            html += '<div class="prediction-item">';
            html += '<span class="label">Avg On-Time Delivery</span>';
            html += '<span class="value">' + (report.predictions.avg_on_time_delivery || 'N/A') + '</span>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
        }
        
        html += '</div>';
        return html;
    }
};

// ============================================
// 5. Settings Functions
// ============================================

var SettingsManager = {
    /**
     * Get all settings
     */
    getAll: function() {
        return API.get('settings.php', { action: 'list' });
    },
    
    /**
     * Get a specific setting
     */
    get: function(key) {
        return API.get('settings.php', { action: 'get', key: key });
    },
    
    /**
     * Update a setting
     */
    update: function(key, value) {
        return API.post('settings.php?action=update', {
            key: key,
            value: value
        });
    },
    
    /**
     * Update multiple settings
     */
    updateBulk: function(settings) {
        return API.post('settings.php?action=update_bulk', {
            settings: settings
        });
    },
    
    /**
     * Toggle theme
     */
    toggleTheme: function(theme) {
        return API.post('settings.php?action=theme', {
            theme: theme
        });
    },
    
    /**
     * Update module visibility
     */
    updateVisibility: function(module, visible) {
        return API.post('settings.php?action=visibility', {
            module: module,
            visible: visible
        });
    },
    
    /**
     * Update currency settings
     */
    updateCurrency: function(symbol, code) {
        return API.post('settings.php?action=currency', {
            currency_symbol: symbol,
            currency_code: code
        });
    }
};

// ============================================
// 6. UI Helper Functions
// ============================================

/**
 * Show a notification toast
 */
function showNotification(message, type, duration) {
    type = type || 'info';
    duration = duration || 5000;
    
    var container = document.getElementById('notificationContainer');
    if (!container) {
        // Create container if it doesn't exist
        var newContainer = document.createElement('div');
        newContainer.id = 'notificationContainer';
        newContainer.style.cssText = 
            'position: fixed;' +
            'top: 20px;' +
            'right: 20px;' +
            'z-index: 9999;' +
            'max-width: 400px;' +
            'width: 100%;';
        document.body.appendChild(newContainer);
        container = newContainer;
    }
    
    var notification = document.createElement('div');
    var colors = {
        success: { bg: '#D1FAE5', text: '#065F46', icon: '✅' },
        error: { bg: '#FEE2E2', text: '#DC2626', icon: '❌' },
        warning: { bg: '#FEF3C7', text: '#92400E', icon: '⚠️' },
        critical: { bg: '#FEE2E2', text: '#DC2626', icon: '🚨' },
        info: { bg: '#DBEAFE', text: '#1E40AF', icon: 'ℹ️' }
    };
    
    var color = colors[type] || colors.info;
    
    notification.style.cssText = 
        'background: ' + color.bg + ';' +
        'color: ' + color.text + ';' +
        'padding: 15px 20px;' +
        'border-radius: 10px;' +
        'margin-bottom: 10px;' +
        'box-shadow: 0 4px 12px rgba(0,0,0,0.1);' +
        'font-family: Poppins, sans-serif;' +
        'font-size: 14px;' +
        'display: flex;' +
        'align-items: center;' +
        'gap: 12px;' +
        'animation: slideIn 0.3s ease;' +
        'border-left: 4px solid ' + color.text + ';';
    
    notification.innerHTML = 
        '<span style="font-size: 20px;">' + color.icon + '</span>' +
        '<span style="flex: 1;">' + message + '</span>' +
        '<button onclick="this.parentElement.remove()" style="background:none;border:none;font-size:18px;cursor:pointer;color:' + color.text + ';">×</button>';
    
    container.appendChild(notification);
    
    // Auto-remove after duration
    setTimeout(function() {
        if (notification.parentElement) {
            notification.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(function() {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 300);
        }
    }, duration);
}

/**
 * Format currency (PHP)
 */
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Format date
 */
function formatDate(dateString) {
    var date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    wait = wait || 300;
    var timeout;
    return function executedFunction() {
        var context = this;
        var args = arguments;
        var later = function() {
            clearTimeout(timeout);
            func.apply(context, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Get query parameter from URL
 */
function getQueryParam(param) {
    var urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
}

/**
 * Confirm action with modal
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// ============================================
// 7. Auto-Initialize Functions
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Add notification container styles
    var style = document.createElement('style');
    style.textContent = 
        '@keyframes slideIn {' +
            'from { transform: translateX(100%); opacity: 0; }' +
            'to { transform: translateX(0); opacity: 1; }' +
        '}' +
        '@keyframes slideOut {' +
            'from { transform: translateX(0); opacity: 1; }' +
            'to { transform: translateX(100%); opacity: 0; }' +
        '}' +
        '.loading {' +
            'text-align: center;' +
            'padding: 40px;' +
            'color: #6B7280;' +
        '}' +
        '.loading::after {' +
            "content: '...';" +
            'animation: dots 1.5s steps(4) infinite;' +
        '}' +
        '@keyframes dots {' +
            "0% { content: ''; }" +
            "25% { content: '.'; }" +
            "50% { content: '..'; }" +
            "75% { content: '...'; }" +
        '}';
    document.head.appendChild(style);
    
    // Auto-initialize barcode displays
    document.querySelectorAll('[data-barcode]').forEach(function(el) {
        var code = el.dataset.barcode;
        var productId = el.dataset.productId || null;
        var elementId = el.id || 'barcode-' + Date.now();
        if (!el.id) {
            el.id = elementId;
        }
        BarcodeManager.displayBarcode(elementId, code, productId);
    });
    
    // Auto-initialize stock updates
    var stockStats = document.querySelectorAll('[data-stock-stat]');
    if (stockStats.length > 0) {
        StockManager.updateStockDisplay();
        
        // Update every 60 seconds
        setInterval(function() {
            StockManager.updateStockDisplay();
        }, 60000);
    }
    
    // Auto-check alerts
    if (document.querySelector('[data-alert-badge]')) {
        StockManager.checkAlerts();
        
        // Check every 30 seconds
        setInterval(function() {
            StockManager.checkAlerts();
        }, 30000);
    }
    
    // Auto-initialize reports
    document.querySelectorAll('[data-report]').forEach(function(el) {
        var period = el.dataset.period || 'daily';
        var elementId = el.id || 'report-' + Date.now();
        if (!el.id) {
            el.id = elementId;
        }
        ReportManager.displayReport(elementId, period);
    });
    
    // Theme toggle
    var themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            var html = document.documentElement;
            var currentTheme = html.getAttribute('data-theme') || 'light';
            var newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            SettingsManager.toggleTheme(newTheme)
                .then(function() {
                    html.setAttribute('data-theme', newTheme);
                    themeToggle.innerHTML = newTheme === 'light' ? 
                        '<i class="fas fa-moon"></i>' : 
                        '<i class="fas fa-sun"></i>';
                    
                    showNotification('Theme changed to ' + newTheme + ' mode', 'success');
                })
                .catch(function(error) {
                    console.error('Error toggling theme:', error);
                    showNotification('Error changing theme', 'error');
                });
        });
    }
    
    // Handle AJAX form submissions
    document.querySelectorAll('[data-ajax-form]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var action = this.action;
            var method = this.method || 'POST';
            var data = new FormData(this);
            
            // Convert to JSON
            var jsonData = {};
            data.forEach(function(value, key) {
                jsonData[key] = value;
            });
            
            var submitBtn = this.querySelector('[type="submit"]');
            var originalText = submitBtn ? submitBtn.innerHTML : 'Submit';
            
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                submitBtn.disabled = true;
            }
            
            fetch(action, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(jsonData)
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    showNotification(data.message || 'Operation successful', 'success');
                    
                    // Reload page after 1 second
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.error || 'Operation failed', 'error');
                }
            })
            .catch(function(error) {
                console.error('Error submitting form:', error);
                showNotification('Error submitting form', 'error');
            })
            .finally(function() {
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            });
        });
    });
    
    // Handle quick stock adjustments
    document.querySelectorAll('[data-stock-adjust]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var productId = this.dataset.productId;
            var action = this.dataset.stockAction || 'adjust';
            
            // Show modal or prompt
            var quantity = prompt('Enter quantity to ' + action + ':');
            if (quantity !== null && !isNaN(quantity) && parseInt(quantity) !== 0) {
                var qty = parseInt(quantity);
                var type = qty > 0 ? 'receiving' : 'issuance';
                
                StockManager.adjustStock(productId, qty, type, 'Quick adjustment from UI')
                    .then(function(data) {
                        if (data.success) {
                            showNotification('Stock adjusted successfully', 'success');
                            StockManager.updateStockDisplay();
                        } else {
                            showNotification(data.error || 'Failed to adjust stock', 'error');
                        }
                    })
                    .catch(function(error) {
                        console.error('Error adjusting stock:', error);
                        showNotification('Error adjusting stock', 'error');
                    });
            }
        });
    });
});

// ============================================
// 8. Export for use in other scripts
// ============================================

// Make functions globally available
window.API = API;
window.BarcodeManager = BarcodeManager;
window.StockManager = StockManager;
window.ReportManager = ReportManager;
window.SettingsManager = SettingsManager;
window.showNotification = showNotification;
window.formatCurrency = formatCurrency;
window.formatDate = formatDate;
window.debounce = debounce;
window.getQueryParam = getQueryParam;
window.confirmAction = confirmAction;

// Initialize on DOM ready
console.log('GlobalSCM Scripts Loaded');