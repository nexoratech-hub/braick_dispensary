// ================================================================
// FILE: frontend/assets/js/global_stats.js
// GLOBAL STATS AUTO-UPDATE - WORKS ON ALL ROLES
// UPDATES EVERY 3 SECONDS
// SUPPORTS: admin, reception, doctor, pharmacy, laboratory, cashier
// BRAICK DISPENSARY
// ================================================================

(function() {
    'use strict';
    
    // ================================================================
    // CONFIGURATION
    // ================================================================
    var CONFIG = {
        updateInterval: 3000, // 3 seconds
        apiEndpoint: '/dispensary_system/frontend/api/get_global_stats.php',
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
        initialized: false,
        userRole: null,
        branchId: null
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
                console.log('[GlobalStats]', message, data);
            } else {
                console.log('[GlobalStats]', message);
            }
        }
    }
    
    function getElement(selector) {
        if (!selector) return null;
        return document.querySelector(selector);
    }
    
    function getElements(selector) {
        if (!selector) return [];
        return document.querySelectorAll(selector);
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
    
    function safeNumber(element, value) {
        if (element && element.textContent !== undefined) {
            element.textContent = value !== undefined && value !== null ? value.toString() : '0';
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
    
    function formatTime(date) {
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
    
    function timeAgo(timestamp) {
        if (!timestamp) return 'Just now';
        var now = new Date();
        var past = new Date(timestamp);
        var diff = Math.floor((now - past) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return past.toLocaleDateString();
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
    
    // ================================================================
    // FIND ELEMENTS BY PAGE TYPE AND ROLE
    // ================================================================
    function findPageElements() {
        var pageElements = {};
        
        // ================================================================
        // COMMON ELEMENTS (All Pages)
        // ================================================================
        pageElements.onlineDoctorBadge = getElement('#onlineDoctorBadge');
        pageElements.onlineDoctorCount = getElement('#onlineDoctorCount');
        pageElements.onlineDoctorsStat = getElement('#onlineDoctorsStat');
        pageElements.onlineDoctorCountText = getElement('#onlineDoctorCountText');
        pageElements.onlineDoctorsStatTime = getElement('#onlineDoctorsStatTime');
        pageElements.updateBadge = getElement('#updateBadge');
        pageElements.doctorStatusMessage = getElement('#doctorStatusMessage');
        pageElements.notifDot = getElement('.notif-dot');
        pageElements.footerTimestamp = getElement('#footerTimestamp');
        pageElements.liveTime = getElement('#liveTime');
        pageElements.currentDateTime = getElement('#currentDateTime');
        
        // ================================================================
        // RECEPTION DASHBOARD ELEMENTS
        // ================================================================
        pageElements.todayPatientsTotal = getElement('#todayPatientsTotal');
        pageElements.todayPatientsPending = getElement('#todayPatientsPending');
        pageElements.todayPatientsCompleted = getElement('#todayPatientsCompleted');
        pageElements.todayPatientsProgress = getElement('#todayPatientsProgress');
        pageElements.todayVisitsTotal = getElement('#todayVisitsTotal');
        pageElements.todayVisitsPending = getElement('#todayVisitsPending');
        pageElements.todayVisitsCompleted = getElement('#todayVisitsCompleted');
        pageElements.todayVisitsProgress = getElement('#todayVisitsProgress');
        pageElements.todayAppointmentsTotal = getElement('#todayAppointmentsTotal');
        pageElements.todayAppointmentsPending = getElement('#todayAppointmentsPending');
        pageElements.todayAppointmentsCompleted = getElement('#todayAppointmentsCompleted');
        pageElements.todayAppointmentsProgress = getElement('#todayAppointmentsProgress');
        pageElements.totalAppointments = getElement('#totalAppointments');
        pageElements.totalPatients = getElement('#totalPatients');
        pageElements.totalVisits = getElement('#totalVisits');
        pageElements.pendingAppointments = getElement('#pendingAppointments');
        pageElements.onlineDoctors = getElement('#onlineDoctors');
        pageElements.appointmentsList = getElement('#appointmentsList');
        pageElements.appointmentsCount = getElement('#appointmentsCount');
        pageElements.recentPatientsList = getElement('#recentPatientsList');
        pageElements.recentActivities = getElement('#recentActivities');
        pageElements.onlineDoctorsList = getElement('#onlineDoctorsList');
        pageElements.onlineDoctorsCount = getElement('#onlineDoctorsCount');
        
        // ================================================================
        // DOCTOR DASHBOARD ELEMENTS
        // ================================================================
        pageElements.myPendingPatients = getElement('#myPendingPatients');
        pageElements.myTodayPatients = getElement('#myTodayPatients');
        pageElements.myTotalPatients = getElement('#myTotalPatients');
        pageElements.myAppointments = getElement('#myAppointments');
        pageElements.myPatientsList = getElement('#myPatientsList');
        
        // ================================================================
        // PHARMACY DASHBOARD ELEMENTS
        // ================================================================
        pageElements.pendingPrescriptions = getElement('#pendingPrescriptions');
        pageElements.todayDispensed = getElement('#todayDispensed');
        pageElements.lowStock = getElement('#lowStock');
        pageElements.totalMedications = getElement('#totalMedications');
        pageElements.prescriptionsList = getElement('#prescriptionsList');
        
        // ================================================================
        // LABORATORY DASHBOARD ELEMENTS
        // ================================================================
        pageElements.pendingLabTests = getElement('#pendingLabTests');
        pageElements.todayCompleted = getElement('#todayCompleted');
        pageElements.totalLabTests = getElement('#totalLabTests');
        pageElements.labTestsList = getElement('#labTestsList');
        
        // ================================================================
        // CASHIER DASHBOARD ELEMENTS
        // ================================================================
        pageElements.todayRevenue = getElement('#todayRevenue');
        pageElements.pendingBills = getElement('#pendingBills');
        pageElements.todayPayments = getElement('#todayPayments');
        pageElements.cashierTotalPatients = getElement('#cashierTotalPatients');
        pageElements.recentPaymentsList = getElement('#recentPaymentsList');
        
        // ================================================================
        // ADMIN DASHBOARD ELEMENTS
        // ================================================================
        pageElements.totalBranches = getElement('#totalBranches');
        pageElements.totalUsers = getElement('#totalUsers');
        pageElements.globalRevenue = getElement('#globalRevenue');
        pageElements.adminTotalPatients = getElement('#adminTotalPatients');
        pageElements.adminTotalVisits = getElement('#adminTotalVisits');
        pageElements.adminTotalAppointments = getElement('#adminTotalAppointments');
        pageElements.branchesList = getElement('#branchesList');
        
        return pageElements;
    }
    
    // ================================================================
    // UPDATE FUNCTIONS BY ROLE
    // ================================================================
    function updateStats(data) {
        var stats = data.stats || {};
        var lists = data.lists || {};
        var roleStats = stats.role_stats || {};
        var user = data.user || {};
        var timestamp = data.timestamp || new Date().toISOString();
        var userRole = user.role || 'reception';
        
        log('Updating stats for role: ' + userRole, stats);
        
        // ================================================================
        // UPDATE ONLINE DOCTORS (All Roles)
        // ================================================================
        if (elements.onlineDoctorCount) {
            safeText(elements.onlineDoctorCount, stats.online_doctors);
        }
        if (elements.onlineDoctorsStat) {
            safeText(elements.onlineDoctorsStat, stats.online_doctors);
        }
        if (elements.onlineDoctorCountText) {
            safeText(elements.onlineDoctorCountText, stats.online_doctors);
        }
        if (elements.onlineDoctors) {
            safeText(elements.onlineDoctors, stats.online_doctors);
        }
        if (elements.onlineDoctorsCount) {
            safeText(elements.onlineDoctorsCount, '(' + stats.online_doctors + ' online)');
        }
        
        // Online doctors list
        if (elements.onlineDoctorsList && lists.online_doctors) {
            if (lists.online_doctors.length > 0) {
                var listHtml = '';
                lists.online_doctors.forEach(function(doc) {
                    var initial = doc.full_name.charAt(0).toUpperCase();
                    listHtml += `
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-primary-bg transition mb-1">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white text-sm font-bold">
                                    ${escapeHtml(initial)}
                                </div>
                                <div>
                                    <p class="font-medium text-sm text-gray-800">${escapeHtml(doc.full_name)}</p>
                                    <p class="text-xs text-gray-500">${escapeHtml(doc.specialty || 'General Practitioner')}</p>
                                </div>
                            </div>
                            <span class="online-dot" title="Online"></span>
                        </div>
                    `;
                });
                safeHTML(elements.onlineDoctorsList, listHtml);
            } else {
                safeHTML(elements.onlineDoctorsList, `
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-user-md text-2xl block mb-2"></i>
                        <p class="text-sm">No doctors online</p>
                    </div>
                `);
            }
        }
        
        // Doctor status message
        if (elements.doctorStatusMessage) {
            if (stats.online_doctors > 0) {
                safeHTML(elements.doctorStatusMessage, `
                    <p class="text-xs text-green-500">
                        <i class="fas fa-check-circle mr-1"></i> 
                        ${stats.online_doctors} doctor(s) currently online
                        ${stats.total_doctors > 0 ? '| ' + (stats.total_doctors - stats.online_doctors) + ' offline' : ''}
                    </p>
                `);
            } else {
                safeHTML(elements.doctorStatusMessage, `
                    <p class="text-xs text-yellow-500">
                        <i class="fas fa-exclamation-triangle mr-1"></i> 
                        No doctors are currently online.
                    </p>
                `);
            }
        }
        
        // ================================================================
        // UPDATE NOTIFICATIONS
        // ================================================================
        if (elements.notifDot) {
            if (stats.unread_notifications > 0) {
                elements.notifDot.className = 'notif-dot has-notif';
            } else {
                elements.notifDot.className = 'notif-dot no-notif';
            }
        }
        
        // ================================================================
        // UPDATE COMMON STATS (All Roles)
        // ================================================================
        if (elements.todayPatientsTotal) {
            safeText(elements.todayPatientsTotal, stats.today_patients || 0);
        }
        if (elements.todayVisitsTotal) {
            safeText(elements.todayVisitsTotal, stats.today_visits || 0);
        }
        if (elements.todayAppointmentsTotal) {
            safeText(elements.todayAppointmentsTotal, stats.today_appointments || 0);
        }
        if (elements.totalAppointments) {
            safeText(elements.totalAppointments, formatNumber(stats.total_appointments || 0));
        }
        if (elements.totalPatients) {
            safeText(elements.totalPatients, formatNumber(stats.total_patients || 0));
        }
        if (elements.totalVisits) {
            safeText(elements.totalVisits, formatNumber(stats.total_visits || 0));
        }
        if (elements.pendingAppointments) {
            safeText(elements.pendingAppointments, stats.pending_appointments || 0);
        }
        
        // Progress bars
        if (elements.todayPatientsProgress) {
            var pct1 = stats.today_patients > 0 ? Math.min(100, ((stats.today_patients - stats.pending_visits) / stats.today_patients) * 100) : 0;
            elements.todayPatientsProgress.style.width = pct1 + '%';
        }
        
        // ================================================================
        // UPDATE RECEPTION STATS
        // ================================================================
        if (userRole === 'reception' || userRole === 'admin') {
            var receptionStats = roleStats.reception || {};
            // Already updated above
        }
        
        // ================================================================
        // UPDATE DOCTOR STATS
        // ================================================================
        if (userRole === 'doctor' || userRole === 'admin') {
            var doctorStats = roleStats.doctor || {};
            if (elements.myPendingPatients) {
                safeText(elements.myPendingPatients, doctorStats.my_pending_patients || 0);
            }
            if (elements.myTodayPatients) {
                safeText(elements.myTodayPatients, doctorStats.my_today_patients || 0);
            }
            if (elements.myTotalPatients) {
                safeText(elements.myTotalPatients, doctorStats.my_total_patients || 0);
            }
            if (elements.myAppointments) {
                safeText(elements.myAppointments, doctorStats.my_appointments || 0);
            }
        }
        
        // ================================================================
        // UPDATE PHARMACY STATS
        // ================================================================
        if (userRole === 'pharmacy' || userRole === 'admin') {
            var pharmacyStats = roleStats.pharmacy || {};
            if (elements.pendingPrescriptions) {
                safeText(elements.pendingPrescriptions, pharmacyStats.pending_prescriptions || 0);
            }
            if (elements.todayDispensed) {
                safeText(elements.todayDispensed, pharmacyStats.today_dispensed || 0);
            }
            if (elements.lowStock) {
                safeText(elements.lowStock, pharmacyStats.low_stock || 0);
            }
            if (elements.totalMedications) {
                safeText(elements.totalMedications, pharmacyStats.total_medications || 0);
            }
        }
        
        // ================================================================
        // UPDATE LABORATORY STATS
        // ================================================================
        if (userRole === 'laboratory' || userRole === 'admin') {
            var labStats = roleStats.laboratory || {};
            if (elements.pendingLabTests) {
                safeText(elements.pendingLabTests, labStats.pending_lab_tests || 0);
            }
            if (elements.todayCompleted) {
                safeText(elements.todayCompleted, labStats.today_completed || 0);
            }
            if (elements.totalLabTests) {
                safeText(elements.totalLabTests, labStats.total_lab_tests || 0);
            }
        }
        
        // ================================================================
        // UPDATE CASHIER STATS
        // ================================================================
        if (userRole === 'cashier' || userRole === 'admin') {
            var cashierStats = roleStats.cashier || {};
            if (elements.todayRevenue) {
                safeText(elements.todayRevenue, formatCurrency(cashierStats.today_revenue || 0));
            }
            if (elements.pendingBills) {
                safeText(elements.pendingBills, cashierStats.pending_bills || 0);
            }
            if (elements.todayPayments) {
                safeText(elements.todayPayments, cashierStats.today_payments || 0);
            }
            if (elements.cashierTotalPatients) {
                safeText(elements.cashierTotalPatients, cashierStats.total_patients || 0);
            }
        }
        
        // ================================================================
        // UPDATE ADMIN STATS
        // ================================================================
        if (userRole === 'admin') {
            var adminStats = roleStats.admin || {};
            if (elements.totalBranches) {
                safeText(elements.totalBranches, adminStats.total_branches || 0);
            }
            if (elements.totalUsers) {
                safeText(elements.totalUsers, adminStats.total_users || 0);
            }
            if (elements.globalRevenue) {
                safeText(elements.globalRevenue, formatCurrency(adminStats.global_revenue || 0));
            }
            if (elements.adminTotalPatients) {
                safeText(elements.adminTotalPatients, adminStats.total_patients || 0);
            }
            if (elements.adminTotalVisits) {
                safeText(elements.adminTotalVisits, adminStats.total_visits || 0);
            }
            if (elements.adminTotalAppointments) {
                safeText(elements.adminTotalAppointments, adminStats.total_appointments || 0);
            }
        }
        
        // ================================================================
        // UPDATE APPOINTMENTS LIST
        // ================================================================
        if (elements.appointmentsList && lists.today_appointments) {
            if (lists.today_appointments.length > 0) {
                var apptHtml = '';
                lists.today_appointments.forEach(function(appt) {
                    var time = appt.appointment_date ? new Date(appt.appointment_date).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : 'N/A';
                    var status = appt.status || 'scheduled';
                    apptHtml += `
                        <div class="appointment-item">
                            <span class="appointment-time">${time}</span>
                            <div class="appointment-patient flex-1 ml-3">
                                <span class="name">${escapeHtml(appt.patient_name)}</span>
                                <span class="doctor block">${escapeHtml(appt.doctor_name || 'N/A')}</span>
                            </div>
                            <span class="appointment-status ${status}">
                                ${capitalize(status)}
                            </span>
                        </div>
                    `;
                });
                safeHTML(elements.appointmentsList, apptHtml);
            } else {
                safeHTML(elements.appointmentsList, `
                    <div class="text-center py-6 text-gray-400">
                        <i class="fas fa-calendar-check text-2xl block mb-2"></i>
                        <p class="text-sm">No appointments scheduled for today</p>
                    </div>
                `);
            }
        }
        if (elements.appointmentsCount) {
            safeText(elements.appointmentsCount, '(' + (lists.today_appointments ? lists.today_appointments.length : 0) + ')');
        }
        
        // ================================================================
        // UPDATE RECENT ACTIVITIES
        // ================================================================
        if (elements.recentActivities && lists.recent_activities) {
            if (lists.recent_activities.length > 0) {
                var activityHtml = '';
                lists.recent_activities.forEach(function(activity) {
                    activityHtml += `
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-circle text-[6px]"></i>
                            </div>
                            <div class="activity-content">
                                <p class="action">${escapeHtml(activity.action || 'Action')}</p>
                                <p class="details">${escapeHtml(activity.details || '')}</p>
                                <p class="time">${activity.created_at ? timeAgo(activity.created_at) : 'Just now'}</p>
                            </div>
                        </div>
                    `;
                });
                safeHTML(elements.recentActivities, activityHtml);
            }
        }
        
        // ================================================================
        // UPDATE TIMESTAMPS
        // ================================================================
        var now = new Date();
        var timeStr = formatTime(now);
        var dateStr = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        var dateTimeStr = dateStr + ' • ' + timeStr;
        
        if (elements.onlineDoctorsStatTime) {
            safeText(elements.onlineDoctorsStatTime, 'Updated ' + timeStr);
        }
        if (elements.footerTimestamp) {
            safeText(elements.footerTimestamp, 'Last updated: ' + timeStr);
        }
        if (elements.liveTime) {
            safeHTML(elements.liveTime, '<i class="fas fa-clock mr-1"></i> ' + timeStr);
        }
        if (elements.currentDateTime) {
            safeText(elements.currentDateTime, dateTimeStr);
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
                        state.userRole = data.user ? data.user.role : null;
                        state.branchId = data.user ? data.user.branch_id : null;
                        updateStats(data);
                        state.updateCount++;
                    }
                }
                state.isUpdating = false;
            })
            .catch(function(error) {
                console.error('[GlobalStats] Error fetching data:', error);
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
        
        log('Initializing Global Stats System');
        elements = findPageElements();
        
        // Start auto-update after 1 second
        setTimeout(function() {
            startAutoUpdate();
        }, 1000);
        
        state.initialized = true;
        log('Global Stats System initialized');
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
    window.GlobalStats = {
        start: startAutoUpdate,
        stop: stopAutoUpdate,
        refresh: fetchStats,
        state: state,
        config: CONFIG,
        elements: elements
    };
    
    console.log('%c📊 Global Stats System Initialized', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔄 Auto-update every ' + CONFIG.updateInterval / 1000 + ' seconds', 'font-size:12px; color:#34D399;');
    console.log('%c👤 Role: ' + (state.userRole || 'Unknown'), 'font-size:12px; color:#64748B;');
    console.log('%c💡 Type GlobalStats.start() or GlobalStats.stop() to control', 'font-size:12px; color:#64748B;');
    
})();