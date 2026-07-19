// ================================================================
// FILE: frontend/assets/js/lab_global_stats.js
// LABORATORY GLOBAL STATS AUTO-UPDATE - UPDATES EVERY 3 SECONDS
// BRAICK DISPENSARY
// ================================================================

(function() {
    'use strict';
    
    // ================================================================
    // CONFIGURATION
    // ================================================================
    var CONFIG = {
        updateInterval: 3000, // 3 seconds
        apiEndpoint: '/dispensary_system/frontend/pages/laboratory/get_lab_stats.php',
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
    // CHART INSTANCES
    // ================================================================
    var chartInstances = {
        daily: null,
        monthly: null
    };
    
    // ================================================================
    // HELPER FUNCTIONS
    // ================================================================
    function log(message, data) {
        if (CONFIG.debug) {
            if (data) {
                console.log('[LabStats]', message, data);
            } else {
                console.log('[LabStats]', message);
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
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function capitalize(text) {
        if (!text) return '';
        return text.charAt(0).toUpperCase() + text.slice(1);
    }
    
    function formatDate(datetime) {
        if (!datetime) return 'N/A';
        var d = new Date(datetime);
        if (isNaN(d.getTime())) return 'N/A';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
    
    // ================================================================
    // FIND PAGE ELEMENTS
    // ================================================================
    function findPageElements() {
        var pageElements = {};
        
        // Header elements
        pageElements.updateBadge = getElement('#updateBadge');
        pageElements.footerTimestamp = getElement('#footerTimestamp');
        pageElements.currentDateTime = getElement('#currentDateTime');
        
        // Stats cards
        pageElements.pending = getElement('#statPending');
        pageElements.inProgress = getElement('#statInProgress');
        pageElements.completedToday = getElement('#statCompletedToday');
        pageElements.todayTests = getElement('#statTodayTests');
        pageElements.totalTests = getElement('#statTotalTests');
        pageElements.totalRequests = getElement('#statTotalRequests');
        pageElements.completionRate = getElement('#statCompletionRate');
        
        // Chart containers
        pageElements.dailyChart = getElement('#dailyChart');
        pageElements.monthlyChart = getElement('#monthlyChart');
        
        // Recent requests table
        pageElements.recentTableBody = getElement('#recentTableBody');
        
        // Most requested tests
        pageElements.mostRequested = getElement('#mostRequested');
        
        return pageElements;
    }
    
    // ================================================================
    // CHART FUNCTIONS
    // ================================================================
    function renderDailyChart(labels, values) {
        var canvas = elements.dailyChart;
        if (!canvas) return;
        
        var ctx = canvas.getContext('2d');
        if (!ctx) return;
        
        // Destroy existing chart
        if (chartInstances.daily) {
            chartInstances.daily.destroy();
            chartInstances.daily = null;
        }
        
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var gridColor = isDark ? '#334155' : '#E2E8F0';
        var textColor = isDark ? '#94A3B8' : '#64748B';
        
        var defaultLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        var defaultValues = [0, 0, 0, 0, 0, 0, 0];
        
        chartInstances.daily = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: (labels && labels.length > 0) ? labels : defaultLabels,
                datasets: [{
                    label: 'Tests Completed',
                    data: (values && values.length > 0) ? values : defaultValues,
                    backgroundColor: '#0B5ED7',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: textColor },
                        grid: { color: gridColor }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
                }
            }
        });
    }
    
    function renderMonthlyChart(labels, values) {
        var canvas = elements.monthlyChart;
        if (!canvas) return;
        
        var ctx = canvas.getContext('2d');
        if (!ctx) return;
        
        // Destroy existing chart
        if (chartInstances.monthly) {
            chartInstances.monthly.destroy();
            chartInstances.monthly = null;
        }
        
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var gridColor = isDark ? '#334155' : '#E2E8F0';
        var textColor = isDark ? '#94A3B8' : '#64748B';
        
        var defaultLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        var defaultValues = [0, 0, 0, 0, 0, 0];
        
        chartInstances.monthly = new Chart(ctx, {
            type: 'line',
            data: {
                labels: (labels && labels.length > 0) ? labels : defaultLabels,
                datasets: [{
                    label: 'Tests Completed',
                    data: (values && values.length > 0) ? values : defaultValues,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#059669',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: textColor },
                        grid: { color: gridColor }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
                }
            }
        });
    }
    
    // ================================================================
    // UPDATE FUNCTIONS
    // ================================================================
    function updateStats(data) {
        var stats = data.stats || {};
        var charts = data.charts || {};
        var lists = data.lists || {};
        
        log('Updating lab stats:', stats);
        
        // ================================================================
        // UPDATE STATS CARDS
        // ================================================================
        if (elements.pending) {
            safeText(elements.pending, stats.pending || 0);
        }
        if (elements.inProgress) {
            safeText(elements.inProgress, stats.in_progress || 0);
        }
        if (elements.completedToday) {
            safeText(elements.completedToday, stats.completed_today || 0);
        }
        if (elements.todayTests) {
            safeText(elements.todayTests, stats.today_tests || 0);
        }
        if (elements.totalTests) {
            safeText(elements.totalTests, formatNumber(stats.total_tests || 0));
        }
        if (elements.totalRequests) {
            safeText(elements.totalRequests, formatNumber(stats.total_requests || 0));
        }
        if (elements.completionRate) {
            safeText(elements.completionRate, (stats.completion_rate || 0) + '%');
        }
        
        // ================================================================
        // UPDATE CHARTS
        // ================================================================
        if (charts.daily_labels && charts.daily_tests) {
            renderDailyChart(charts.daily_labels, charts.daily_tests);
        }
        if (charts.monthly_labels && charts.monthly_tests) {
            renderMonthlyChart(charts.monthly_labels, charts.monthly_tests);
        }
        
        // ================================================================
        // UPDATE RECENT REQUESTS TABLE
        // ================================================================
        if (elements.recentTableBody && lists.recent_requests) {
            var requests = lists.recent_requests || [];
            if (requests.length > 0) {
                var tableHtml = '';
                requests.forEach(function(req) {
                    var statusClass = req.status || 'pending';
                    var statusLabel = capitalize(req.status || 'Pending');
                    var testInfo = req.test_count + ' tests';
                    if (req.completed_count > 0 && req.status !== 'completed') {
                        testInfo += ' <span class="text-xs text-gray-400">(' + req.completed_count + ' done)</span>';
                    }
                    
                    var badgeClass = 'badge-yellow';
                    if (statusClass === 'in_progress') badgeClass = 'badge-blue';
                    else if (statusClass === 'completed') badgeClass = 'badge-green';
                    else if (statusClass === 'cancelled') badgeClass = 'badge-red';
                    
                    tableHtml += `
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <td class="font-mono text-xs font-semibold text-blue-600">${escapeHtml(req.request_number || 'N/A')}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-sm">${escapeHtml(req.patient_name || 'Unknown')}</div>
                                <div class="text-xs text-gray-400">${escapeHtml(req.patient_id || 'N/A')}</div>
                            </td>
                            <td class="font-mono text-xs">${escapeHtml(req.visit_id || 'N/A')}</td>
                            <td>${escapeHtml(req.doctor_name || 'N/A')}</td>
                            <td>${testInfo}</td>
                            <td>
                                <span class="badge ${badgeClass}">
                                    ${statusLabel}
                                </span>
                            </td>
                            <td class="text-sm">${formatDate(req.requested_at)}</td>
                            <td>
                                <a href="view_request.php?id=${req.id}" class="btn btn-outline btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    `;
                });
                safeHTML(elements.recentTableBody, tableHtml);
            } else {
                safeHTML(elements.recentTableBody, `
                    <tr>
                        <td colspan="8" class="text-center py-8 text-gray-400">
                            <i class="fas fa-flask text-3xl block mb-2"></i>
                            <p>No laboratory requests yet</p>
                        </td>
                    </tr>
                `);
            }
        }
        
        // ================================================================
        // UPDATE MOST REQUESTED TESTS
        // ================================================================
        if (elements.mostRequested && lists.most_requested) {
            var tests = lists.most_requested || [];
            if (tests.length > 0) {
                var listHtml = '';
                tests.forEach(function(test, index) {
                    listHtml += `
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-bold text-gray-400">#${index + 1}</span>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">${escapeHtml(test.test_name)}</span>
                            </div>
                            <span class="badge badge-blue">${test.count}</span>
                        </div>
                    `;
                });
                safeHTML(elements.mostRequested, listHtml);
            } else {
                safeHTML(elements.mostRequested, `
                    <div class="text-center py-8 text-gray-400">
                        <i class="fas fa-flask text-2xl block mb-2"></i>
                        <p class="text-sm">No tests completed yet</p>
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
                        updateStats(data.data);
                        state.updateCount++;
                        
                        if (state.updateCount > 1 && state.updateCount % 3 === 0) {
                            console.log('[LabStats] Auto-updated at ' + data.data.timestamp);
                        }
                    }
                }
                state.isUpdating = false;
            })
            .catch(function(error) {
                console.error('[LabStats] Error fetching data:', error);
                if (elements.updateBadge) {
                    safeHTML(elements.updateBadge, '<i class="fas fa-exclamation-circle" style="color:#EF4444;"></i> Error');
                }
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
            if (elements.updateBadge) {
                safeHTML(elements.updateBadge, '<i class="fas fa-pause"></i> Paused');
            }
            log('Stopped auto-update');
        }
    }
    
    function manualRefresh() {
        // Force reset hash to force update
        state.hash = null;
        fetchStats();
        
        setTimeout(function() {
            log('Manual refresh completed');
        }, 500);
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
    // DARK MODE DETECTION FOR CHARTS
    // ================================================================
    var darkModeObserver = new MutationObserver(function() {
        // Redraw charts when dark mode changes
        if (state.data && state.data.data) {
            var charts = state.data.data.charts || {};
            if (charts.daily_labels && charts.daily_tests) {
                renderDailyChart(charts.daily_labels, charts.daily_tests);
            }
            if (charts.monthly_labels && charts.monthly_tests) {
                renderMonthlyChart(charts.monthly_labels, charts.monthly_tests);
            }
        }
    });
    
    // Observe changes to data-theme attribute
    var htmlElement = document.documentElement;
    if (htmlElement) {
        darkModeObserver.observe(htmlElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });
    }
    
    // ================================================================
    // INITIALIZE
    // ================================================================
    function init() {
        if (state.initialized) return;
        
        log('Initializing Laboratory Stats System');
        elements = findPageElements();
        
        // Wait for Chart.js to be available
        if (typeof Chart === 'undefined') {
            console.warn('[LabStats] Chart.js not loaded, waiting...');
            setTimeout(init, 1000);
            return;
        }
        
        // Initial chart render with default data
        // Charts will be updated from API data
        renderDailyChart(null, null);
        renderMonthlyChart(null, null);
        
        // Start auto-update after 1.5 seconds
        setTimeout(function() {
            startAutoUpdate();
        }, 1500);
        
        state.initialized = true;
        log('Laboratory Stats System initialized');
    }
    
    // ================================================================
    // RUN ON DOM READY
    // ================================================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already loaded, wait a bit for charts to render
        setTimeout(init, 500);
    }
    
    // ================================================================
    // EXPOSE FOR CONSOLE DEBUGGING
    // ================================================================
    window.LabStats = {
        start: startAutoUpdate,
        stop: stopAutoUpdate,
        refresh: manualRefresh,
        fetch: fetchStats,
        state: state,
        config: CONFIG,
        elements: elements,
        charts: chartInstances,
        renderDaily: renderDailyChart,
        renderMonthly: renderMonthlyChart
    };
    
    console.log('%c🧪 Laboratory Stats System Initialized', 'font-size:16px; font-weight:bold; color:#7C3AED;');
    console.log('%c🔄 Auto-update every ' + CONFIG.updateInterval / 1000 + ' seconds', 'font-size:12px; color:#34D399;');
    console.log('%c💡 Type LabStats.start() or LabStats.stop() to control', 'font-size:12px; color:#64748B;');
    console.log('%c💡 Type LabStats.refresh() for manual refresh', 'font-size:12px; color:#64748B;');
    
})();