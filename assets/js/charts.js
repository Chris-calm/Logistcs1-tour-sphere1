/**
 * Chart.js Integration for GlobalSCM
 * Handles all chart rendering for reports and dashboards
 */

class ChartManager {
    constructor() {
        this.charts = {};
        this.defaultColors = {
            primary: '#2F80ED',
            secondary: '#56CCF2',
            accent: '#27AE60',
            danger: '#DC2626',
            warning: '#F59E0B',
            purple: '#7C3AED',
            pink: '#EC4899',
            gray: '#6B7280'
        };
        this.chartDefaults = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        font: {
                            family: 'Poppins, sans-serif'
                        }
                    }
                }
            }
        };
    }

    /**
     * Create a bar chart
     */
    createBarChart(canvasId, data, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        const defaultOptions = {
            ...this.chartDefaults,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        };

        this.charts[canvasId] = new Chart(ctx, {
            type: 'bar',
            data: data,
            options: { ...defaultOptions, ...options }
        });

        return this.charts[canvasId];
    }

    /**
     * Create a line chart
     */
    createLineChart(canvasId, data, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        const defaultOptions = {
            ...this.chartDefaults,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            elements: {
                line: {
                    tension: 0.4
                }
            }
        };

        this.charts[canvasId] = new Chart(ctx, {
            type: 'line',
            data: data,
            options: { ...defaultOptions, ...options }
        });

        return this.charts[canvasId];
    }

    /**
     * Create a pie/doughnut chart
     */
    createPieChart(canvasId, data, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        const defaultOptions = {
            ...this.chartDefaults,
            plugins: {
                ...this.chartDefaults.plugins,
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            family: 'Poppins, sans-serif'
                        },
                        padding: 20
                    }
                }
            }
        };

        this.charts[canvasId] = new Chart(ctx, {
            type: 'doughnut',
            data: data,
            options: { ...defaultOptions, ...options }
        });

        return this.charts[canvasId];
    }

    /**
     * Create a horizontal bar chart
     */
    createHorizontalBarChart(canvasId, data, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
        }

        const defaultOptions = {
            ...this.chartDefaults,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            }
        };

        this.charts[canvasId] = new Chart(ctx, {
            type: 'bar',
            data: data,
            options: { ...defaultOptions, ...options }
        });

        return this.charts[canvasId];
    }

    /**
     * Create stock trend chart
     */
    createStockTrendChart(canvasId, productId, days = 30) {
        const chart = this;
        
        // Fetch stock data from API
        fetch(`/api/stock.php?action=forecast&id=${productId}&days=${days}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const labels = data.history.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    });
                    
                    const demandData = data.history.map(item => item.demand);
                    
                    const chartData = {
                        labels: labels,
                        datasets: [{
                            label: 'Daily Demand',
                            data: demandData,
                            borderColor: chart.defaultColors.primary,
                            backgroundColor: chart.defaultColors.primary + '33',
                            fill: true,
                            tension: 0.4
                        }, {
                            label: 'Average Daily Demand',
                            data: Array(labels.length).fill(data.avg_daily_demand),
                            borderColor: chart.defaultColors.danger,
                            borderDash: [5, 5],
                            pointRadius: 0,
                            fill: false
                        }]
                    };
                    
                    chart.createLineChart(canvasId, chartData);
                }
            })
            .catch(error => {
                console.error('Error fetching stock data:', error);
            });
    }

    /**
     * Create supplier performance chart
     */
    createSupplierPerformanceChart(canvasId) {
        const chart = this;
        
        fetch('/api/report.php?action=supplier_performance')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const suppliers = data.suppliers || [];
                    const labels = suppliers.map(s => s.company_name);
                    
                    const chartData = {
                        labels: labels,
                        datasets: [{
                            label: 'On-Time Delivery',
                            data: suppliers.map(s => s.on_time_delivery),
                            backgroundColor: chart.defaultColors.primary + '66',
                            borderColor: chart.defaultColors.primary,
                            borderWidth: 2
                        }, {
                            label: 'Quality Rate',
                            data: suppliers.map(s => s.quality_rate),
                            backgroundColor: chart.defaultColors.accent + '66',
                            borderColor: chart.defaultColors.accent,
                            borderWidth: 2
                        }, {
                            label: 'Response Time',
                            data: suppliers.map(s => s.response_time),
                            backgroundColor: chart.defaultColors.warning + '66',
                            borderColor: chart.defaultColors.warning,
                            borderWidth: 2
                        }]
                    };
                    
                    chart.createBarChart(canvasId, chartData);
                }
            })
            .catch(error => {
                console.error('Error fetching supplier data:', error);
            });
    }

    /**
     * Create inventory category distribution chart
     */
    createInventoryCategoryChart(canvasId) {
        const chart = this;
        
        fetch('/api/stock.php?action=summary')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.categories) {
                    const colors = [
                        chart.defaultColors.primary,
                        chart.defaultColors.secondary,
                        chart.defaultColors.accent,
                        chart.defaultColors.warning,
                        chart.defaultColors.purple,
                        chart.defaultColors.pink,
                        chart.defaultColors.danger,
                        chart.defaultColors.gray
                    ];
                    
                    const chartData = {
                        labels: data.categories.map(c => c.category || 'Uncategorized'),
                        datasets: [{
                            data: data.categories.map(c => c.total_value),
                            backgroundColor: colors.slice(0, data.categories.length),
                            borderWidth: 2,
                            borderColor: '#FFFFFF'
                        }]
                    };
                    
                    chart.createPieChart(canvasId, chartData);
                }
            })
            .catch(error => {
                console.error('Error fetching category data:', error);
            });
    }

    /**
     * Create stock movement trend chart
     */
    createStockMovementChart(canvasId, days = 7) {
        const chart = this;
        
        fetch(`/api/stock.php?action=movements&days=${days}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Group by date
                    const movementsByDate = {};
                    data.movements.forEach(m => {
                        const date = new Date(m.created_at).toLocaleDateString();
                        if (!movementsByDate[date]) {
                            movementsByDate[date] = { receiving: 0, issuance: 0 };
                        }
                        if (m.transaction_type === 'receiving') {
                            movementsByDate[date].receiving += parseInt(m.quantity);
                        } else {
                            movementsByDate[date].issuance += parseInt(m.quantity);
                        }
                    });
                    
                    const labels = Object.keys(movementsByDate);
                    const receivingData = labels.map(d => movementsByDate[d].receiving);
                    const issuanceData = labels.map(d => movementsByDate[d].issuance);
                    
                    const chartData = {
                        labels: labels,
                        datasets: [{
                            label: 'Received',
                            data: receivingData,
                            backgroundColor: chart.defaultColors.accent + '66',
                            borderColor: chart.defaultColors.accent,
                            borderWidth: 2
                        }, {
                            label: 'Issued',
                            data: issuanceData,
                            backgroundColor: chart.defaultColors.danger + '66',
                            borderColor: chart.defaultColors.danger,
                            borderWidth: 2
                        }]
                    };
                    
                    chart.createBarChart(canvasId, chartData);
                }
            })
            .catch(error => {
                console.error('Error fetching movement data:', error);
            });
    }

    /**
     * Destroy all charts
     */
    destroyAll() {
        Object.keys(this.charts).forEach(key => {
            if (this.charts[key]) {
                this.charts[key].destroy();
                delete this.charts[key];
            }
        });
    }

    /**
     * Destroy a specific chart
     */
    destroy(canvasId) {
        if (this.charts[canvasId]) {
            this.charts[canvasId].destroy();
            delete this.charts[canvasId];
        }
    }
}

// Initialize ChartManager globally
const chartManager = new ChartManager();

// Auto-initialize charts when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Check for charts on the page
    const chartContainers = document.querySelectorAll('[data-chart]');
    chartContainers.forEach(container => {
        const chartType = container.dataset.chart;
        const canvasId = container.id || 'chart-' + Date.now();
        
        // If canvas doesn't have an id, generate one
        if (!container.id) {
            container.id = canvasId;
        }
        
        switch (chartType) {
            case 'stock-trend':
                if (container.dataset.product) {
                    chartManager.createStockTrendChart(canvasId, container.dataset.product);
                }
                break;
            case 'supplier-performance':
                chartManager.createSupplierPerformanceChart(canvasId);
                break;
            case 'inventory-category':
                chartManager.createInventoryCategoryChart(canvasId);
                break;
            case 'stock-movement':
                const days = container.dataset.days || 7;
                chartManager.createStockMovementChart(canvasId, days);
                break;
            default:
                console.warn('Unknown chart type:', chartType);
        }
    });
});