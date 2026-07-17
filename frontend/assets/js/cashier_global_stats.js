// ================================================================
// FILE: frontend/assets/js/cashier_global_stats.js
// CASHIER GLOBAL STATS AUTO-UPDATE - UPDATES EVERY 3 SECONDS
// BRAICK DISPENSARY
// ================================================================

(function() {
    'use strict';
    
    // ================================================================
    // CONFIGURATION
    // ================================================================
    var CONFIG = {
        updateInterval: 3000, // 3 seconds
        apiEndpoint: '/dispensary_system/frontend/api/get_cashier_stats.php',
        debug: false // Set to true for console logs
    };
    
    // ================================================================
    // STATE
    // ================================================================
    var state = {
        data: null,
        hash: null,
        isUpdating: false,
        updateCount: 0,
        initialized: false
    };
    
    // ================================================================
    // DOM ELEMENT CACHE
    // ================================================================
    var elements = {};
    
    // ================================================================
    // HELPER FUNCTIONS
    // ================================================================
    function log(message, data) {
        if (CONFIG.debug) {
            if (data) {
                console.log('[CashierStats]', message, data);
            } else {
                console.log('[CashierStats]', message);
            }
        }
    }
    
    function getElement(selector) {
        if (!selector) return null;
        return document.querySelector(selector);
    }
    
    function safeText(element, text) {
        if (element && element.textContent !== undefined) {
            element.textContent = text !== undefined && text !== null ? text : '0';
        }
    }
    
    function safeHTML(element, html) {
        if (element && element.innerHTML !== undefined) {
            element.innerHTML = html || '';
        }
    }
    
    function formatNumber(num) {
        if (num === undefined || num === null) return '0';
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    function formatCurrency(amount) {
        if (amount === undefined || amount === null) return 'TSh 0';
        return 'TSh ' + formatNumber(amount);
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function timeAgo(timestamp) {
        if (!timestamp) return 'Just now';
        var now = new Date();
        var past = new Date(timestamp);
        var diff = Math.floor((now - past) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return past.toLocaleDateString();
    }
    
    // ================================================================
    // FIND CASHIER PAGE ELEMENTS
    // ================================================================
    function findPageElements() {
        var pageElements = {};
        
        // Header elements
        pageElements.updateBadge = getElement('#updateBadge');
        pageElements.footerTimestamp = getElement('#footerTimestamp');
        pageElements.currentDateTime = getElement('#currentDateTime');
        
        // Stats cards
        pageElements.pendingBills = getElement('#pendingBills');
        pageElements.todayRevenue = getElement('#todayRevenue');
        pageElements.todayPayments = getElement('#todayPayments');
        pageElements.totalPatients = getElement('#totalPatients');
        pageElements.totalBills = getElement('#totalBills');
        pageElements.paidBills = getElement('#paidBills');
        pageElements.pendingBillsCount = getElement('#pendingBillsCount');
        
        // Lists
        pageElements.pendingBillsList = getElement('#pendingBillsList');
        pageElements.recentPaymentsList = getElement('#recentPaymentsList');
        pageElements.paymentMethods = getElement('#paymentMethods');
        
        return pageElements;
    }
    
    // ================================================================
    // UPDATE FUNCTIONS
    // ================================================================
    function updateStats(data) {
        var stats = data.stats || {};
        var lists = data.lists || {};
        var timestamp = data.timestamp || new Date().toISOString();
        
        log('Updating cashier stats:', stats);
        
        // ================================================================
        // UPDATE STATS CARDS
        // ================================================================
        if (elements.pendingBills) {
            safeText(elements.pendingBills, stats.pending_bills || 0);
        }
        if (elements.todayRevenue) {
            safeText(elements.todayRevenue, formatCurrency(stats.today_revenue || 0));
        }
        if (elements.todayPayments) {
            safeText(elements.todayPayments, stats.today_payments || 0);
        }
        if (elements.totalPatients) {
            safeText(elements.totalPatients, formatNumber(stats.total_patients || 0));
        }
        if (elements.totalBills) {
            safeText(elements.totalBills, formatNumber(stats.total_bills || 0));
        }
        if (elements.paidBills) {
            safeText(elements.paidBills, formatNumber(stats.paid_bills || 0));
        }
        if (elements.pendingBillsCount) {
            safeText(elements.pendingBillsCount, '(' + (stats.pending_bills_count || 0) + ')');
        }
        
        // ================================================================
        // UPDATE PENDING BILLS LIST
        // ================================================================
        if (elements.pendingBillsList && lists.pending_bills) {
            if (lists.pending_bills.length > 0) {
                var listHtml = '';
                lists.pending_bills.forEach(function(bill) {
                    var statusClass = bill.status || 'pending';
                    var statusLabel = statusClass === 'pending' ? '⏳ Pending' : '🔶 Partial';
                    var visitType = bill.visit_type || 'N/A';
                    var doctorName = bill.doctor_name ? 'Dr. ' + bill.doctor_name : 'N/A';
                    var createdDate = bill.created_at ? new Date(bill.created_at).toLocaleString() : 'N/A';
                    
                    listHtml += `
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <td class="px-4 py-3 text-sm font-medium">${escapeHtml(bill.bill_number)}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-sm">${escapeHtml(bill.patient_name)}</div>
                                <div class="text-xs text-gray-400">${escapeHtml(bill.patient_id || 'N/A')}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">${escapeHtml(visitType)}</td>
                            <td class="px-4 py-3 text-sm">${escapeHtml(doctorName)}</td>
                            <td class="px-4 py-3 text-sm font-semibold">${formatCurrency(bill.total_amount || 0)}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full ${statusClass === 'pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'}">
                                    ${statusLabel}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">${createdDate}</td>
                            <td class="px-4 py-3">
                                <button onclick="window.location.href='view_bill.php?id=${bill.id}'" class="btn-primary btn-sm">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="window.location.href='process_payment.php?bill_id=${bill.id}'" class="btn-success btn-sm">
                                    <i class="fas fa-money-bill"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                safeHTML(elements.pendingBillsList, listHtml);
            } else {
                safeHTML(elements.pendingBillsList, `
                    <tr>
                        <td colspan="8" class="text-center py-8 text-gray-400">
                            <i class="fas fa-check-circle text-3xl block mb-2 text-green-500"></i>
                            <p>No pending bills found</p>
                            <p class="text-sm mt-1">All bills have been processed</p>
                        </td>
                    </tr>
                `);
            }
        }
        
        // ================================================================
        // UPDATE RECENT PAYMENTS
        // ================================================================
        if (elements.recentPaymentsList && lists.recent_payments) {
            if (lists.recent_payments.length > 0) {
                var listHtml = '';
                lists.recent_payments.forEach(function(payment) {
                    var method = payment.payment_method || 'cash';
                    var methodIcon = method === 'cash' ? '💵' : method === 'm-pesa' ? '📱' : '💳';
                    
                    listHtml += `
                        <div class="flex items-center justify-between p-2 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition">
                            <div>
                                <p class="font-medium text-sm text-gray-800 dark:text-gray-200">${escapeHtml(payment.patient_name)}</p>
                                <p class="text-xs text-gray-400">${escapeHtml(payment.bill_number)}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-sm text-green-600 dark:text-green-400">${formatCurrency(payment.amount || 0)}</p>
                                <p class="text-xs text-gray-400">${methodIcon} ${method.toUpperCase()} • ${payment.received_at ? timeAgo(payment.received_at) : 'N/A'}</p>
                            </div>
                        </div>
                    `;
                });
                safeHTML(elements.recentPaymentsList, listHtml);
            } else {
                safeHTML(elements.recentPaymentsList, `
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-clock text-2xl block mb-2"></i>
                        <p class="text-sm">No recent payments</p>
                    </div>
                `);
            }
        }
        
        // ================================================================
        // UPDATE PAYMENT METHODS
        // ================================================================
        if (elements.paymentMethods && lists.payment_methods) {
            if (lists.payment_methods.length > 0) {
                var listHtml = '';
                var methodIcons = {
                    'cash': '💵',
                    'm-pesa': '📱',
                    'airtel_money': '📱',
                    'tigo_pesa': '📱',
                    'halopesa': '📱',
                    'card': '💳',
                    'bank': '🏦',
                    'insurance': '🏥',
                    'other': '📦'
                };
                lists.payment_methods.forEach(function(method) {
                    var icon = methodIcons[method.payment_method] || '💵';
                    listHtml += `
                        <div class="flex items-center justify-between p-2 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-sm">${icon} ${escapeHtml(method.payment_method ? method.payment_method.toUpperCase() : 'CASH')}</span>
                            <span class="text-sm text-gray-500">${method.count} payments</span>
                            <span class="font-semibold text-sm text-green-600 dark:text-green-400">${formatCurrency(method.total || 0)}</span>
                        </div>
                    `;
                });
                safeHTML(elements.paymentMethods, listHtml);
            } else {
                safeHTML(elements.paymentMethods, `
                    <div class="text-center py-4 text-gray-400">
                        <p class="text-sm">No payments today</p>
                    </div>
                `);
            }
        }
        
        // ================================================================
        // UPDATE TIMESTAMPS
        // ================================================================
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        var dateStr = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        
        if (elements.footerTimestamp) {
            safeText(elements.footerTimestamp, 'Last updated: ' + timeStr);
        }
        if (elements.currentDateTime) {
            safeText(elements.currentDateTime, dateStr + ' • ' + timeStr);
        }
        if (elements.updateBadge) {
            safeHTML(elements.updateBadge, '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr);
        }
    }
    
    // ================================================================
    // FETCH DATA
    // ================================================================
    function fetchStats() {
        if (state.isUpdating) return;
        state.isUpdating = true;
        
        fetch(CONFIG.apiEndpoint + '?t=' + new Date().getTime())
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    // Check if data has changed
                    if (state.hash !== data.hash) {
                        state.hash = data.hash;
                        state.data = data;
                        updateStats(data);
                        state.updateCount++;
                    }
                }
                state.isUpdating = false;
            })
            .catch(function(error) {
                console.error('[CashierStats] Error fetching data:', error);
                state.isUpdating = false;
            });
    }
    
    // ================================================================
    // START / STOP
    // ================================================================
    var updateInterval = null;
    
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        log('Starting auto-update every ' + CONFIG.updateInterval + 'ms');
        fetchStats();
        updateInterval = setInterval(fetchStats, CONFIG.updateInterval);
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            log('Stopped auto-update');
        }
    }
    
    // ================================================================
    // VISIBILITY CHANGE - PAUSE WHEN HIDDEN
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });
    
    // ================================================================
    // INITIALIZE
    // ================================================================
    function init() {
        if (state.initialized) return;
        
        log('Initializing Cashier Stats System');
        elements = findPageElements();
        
        // Start auto-update after 1 second
        setTimeout(function() {
            startAutoUpdate();
        }, 1000);
        
        state.initialized = true;
        log('Cashier Stats System initialized');
    }
    
    // ================================================================
    // RUN ON DOM READY
    // ================================================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // ================================================================
    // EXPOSE FOR CONSOLE DEBUGGING
    // ================================================================
    window.CashierStats = {
        start: startAutoUpdate,
        stop: stopAutoUpdate,
        refresh: fetchStats,
        state: state,
        config: CONFIG,
        elements: elements
    };
    
    console.log('%c💰 Cashier Stats System Initialized', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔄 Auto-update every ' + CONFIG.updateInterval / 1000 + ' seconds', 'font-size:12px; color:#34D399;');
    console.log('%c💡 Type CashierStats.start() or CashierStats.stop() to control', 'font-size:12px; color:#64748B;');
    
})();