// ================================================================
// FILE: frontend/assets/js/doctor_global_stats.js
// DOCTOR GLOBAL STATS AUTO-UPDATE - UPDATES EVERY 3 SECONDS
// BRAICK DISPENSARY
// ================================================================

(function() {
    'use strict';
    
    // ================================================================
    // CONFIGURATION
    // ================================================================
    var CONFIG = {
        updateInterval: 3000, // 3 seconds
        apiEndpoint: '/dispensary_system/frontend/pages/doctor/get_doctor_stats.php',
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
                console.log('[DoctorStats]', message, data);
            } else {
                console.log('[DoctorStats]', message);
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
    
    function capitalize(text) {
        if (!text) return '';
        return text.charAt(0).toUpperCase() + text.slice(1);
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
    
    function formatTime(datetime) {
        if (!datetime) return 'N/A';
        var d = new Date(datetime);
        if (isNaN(d.getTime())) return 'N/A';
        return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    
    // ================================================================
    // FIND DOCTOR PAGE ELEMENTS
    // ================================================================
    function findPageElements() {
        var pageElements = {};
        
        // Header elements
        pageElements.updateBadge = getElement('#lastUpdateBadge');
        pageElements.footerTimestamp = getElement('#footerTimestamp');
        pageElements.currentDateTime = getElement('#currentDateTime');
        pageElements.doctorName = getElement('#doctorName');
        
        // Mini stats (welcome section)
        pageElements.miniAppointments = getElement('#miniAppointments');
        pageElements.miniPending = getElement('#miniPending');
        pageElements.miniTotalPatients = getElement('#miniTotalPatients');
        pageElements.btnPulse = getElement('#btnPulse');
        pageElements.btnPulseText = getElement('#btnPulseText');
        
        // Stats cards - Top 4
        pageElements.todayPatientsTotal = getElement('#todayPatientsTotal');
        pageElements.todayPatientsPending = getElement('#todayPatientsPending');
        pageElements.todayPatientsCompleted = getElement('#todayPatientsCompleted');
        pageElements.todayPatientsProgress = getElement('#todayPatientsProgress');
        
        pageElements.todayVisitsTotal = getElement('#todayVisitsTotal');
        pageElements.todayVisitsPending = getElement('#todayVisitsPending');
        pageElements.todayVisitsCompleted = getElement('#todayVisitsCompleted');
        pageElements.todayVisitsProgress = getElement('#todayVisitsProgress');
        
        pageElements.totalPatients = getElement('#totalPatients');
        pageElements.totalVisits = getElement('#totalVisits');
        
        // Stats cards - Bottom 4
        pageElements.todayAppointmentsTotal = getElement('#todayAppointmentsTotal');
        pageElements.todayAppointmentsPending = getElement('#todayAppointmentsPending');
        pageElements.todayAppointmentsCompleted = getElement('#todayAppointmentsCompleted');
        pageElements.todayAppointmentsProgress = getElement('#todayAppointmentsProgress');
        
        pageElements.totalAppointments = getElement('#totalAppointments');
        pageElements.totalPrescriptions = getElement('#totalPrescriptions');
        
        pageElements.labTestsTotal = getElement('#labTestsTotal');
        pageElements.labTestsPending = getElement('#labTestsPending');
        pageElements.labTestsCompleted = getElement('#labTestsCompleted');
        pageElements.labTestsProgress = getElement('#labTestsProgress');
        pageElements.labTestsBadge = getElement('#labTestsBadge');
        pageElements.cardLabTests = getElement('#cardLabTests');
        
        // Lists
        pageElements.appointmentsList = getElement('#appointmentsList');
        pageElements.appointmentsCount = getElement('#appointmentsCount');
        pageElements.queueList = getElement('#queueList');
        pageElements.queueCount = getElement('#queueCount');
        pageElements.activitiesList = getElement('#activitiesList');
        
        // Chart
        pageElements.chartCanvas = getElement('#appointmentsChart');
        
        // Refresh button
        pageElements.refreshBtn = getElement('#refreshBtn');
        
        return pageElements;
    }
    
    // ================================================================
    // UPDATE FUNCTIONS
    // ================================================================
    function updateStats(data) {
        var todayPatients = data.today_patients || { total: 0, pending: 0, completed: 0 };
        var todayVisits = data.today_visits || { total: 0, pending: 0, completed: 0 };
        var todayAppointments = data.today_appointments || { total: 0, pending: 0, completed: 0, list: [] };
        var labTests = data.lab_tests || { total: 0, pending: 0, completed: 0 };
        var timestamp = data.timestamp || new Date().toISOString();
        
        log('Updating doctor stats:', data);
        
        // ================================================================
        // UPDATE MINI STATS (WELCOME SECTION)
        // ================================================================
        if (elements.miniAppointments) {
            safeText(elements.miniAppointments, todayAppointments.total || 0);
        }
        if (elements.miniPending) {
            safeText(elements.miniPending, todayPatients.pending || 0);
        }
        if (elements.miniTotalPatients) {
            safeText(elements.miniTotalPatients, formatNumber(data.total_patients || 0));
        }
        
        // Pulse button
        if (elements.btnPulse) {
            if (todayPatients.pending > 0) {
                elements.btnPulse.style.display = 'inline-flex';
                if (elements.btnPulseText) {
                    safeText(elements.btnPulseText, todayPatients.pending + ' Patient(s) Waiting');
                }
            } else {
                elements.btnPulse.style.display = 'none';
            }
        }
        
        // ================================================================
        // UPDATE CARD 1: Today's Patients
        // ================================================================
        if (elements.todayPatientsTotal) {
            safeText(elements.todayPatientsTotal, todayPatients.total || 0);
        }
        if (elements.todayPatientsPending) {
            safeHTML(elements.todayPatientsPending, '<i class="fas fa-clock"></i> ' + (todayPatients.pending || 0) + ' Pending');
        }
        if (elements.todayPatientsCompleted) {
            safeHTML(elements.todayPatientsCompleted, '<i class="fas fa-check-circle"></i> ' + (todayPatients.completed || 0) + ' Complete');
        }
        if (elements.todayPatientsProgress) {
            var pct = todayPatients.total > 0 ? Math.min(100, (todayPatients.completed / Math.max(todayPatients.total, 1)) * 100) : 0;
            elements.todayPatientsProgress.style.width = pct + '%';
        }
        
        // ================================================================
        // UPDATE CARD 2: Today's Visits
        // ================================================================
        if (elements.todayVisitsTotal) {
            safeText(elements.todayVisitsTotal, todayVisits.total || 0);
        }
        if (elements.todayVisitsPending) {
            safeHTML(elements.todayVisitsPending, '<i class="fas fa-clock"></i> ' + (todayVisits.pending || 0) + ' Pending');
        }
        if (elements.todayVisitsCompleted) {
            safeHTML(elements.todayVisitsCompleted, '<i class="fas fa-check-circle"></i> ' + (todayVisits.completed || 0) + ' Complete');
        }
        if (elements.todayVisitsProgress) {
            var pct = todayVisits.total > 0 ? Math.min(100, (todayVisits.completed / Math.max(todayVisits.total, 1)) * 100) : 0;
            elements.todayVisitsProgress.style.width = pct + '%';
        }
        
        // ================================================================
        // UPDATE CARD 3: Total Patients
        // ================================================================
        if (elements.totalPatients) {
            safeText(elements.totalPatients, formatNumber(data.total_patients || 0));
        }
        
        // ================================================================
        // UPDATE CARD 4: Total Visits
        // ================================================================
        if (elements.totalVisits) {
            safeText(elements.totalVisits, formatNumber(data.total_visits || 0));
        }
        
        // ================================================================
        // UPDATE CARD 5: Today's Appointments
        // ================================================================
        if (elements.todayAppointmentsTotal) {
            safeText(elements.todayAppointmentsTotal, todayAppointments.total || 0);
        }
        if (elements.todayAppointmentsPending) {
            safeHTML(elements.todayAppointmentsPending, '<i class="fas fa-clock"></i> ' + (todayAppointments.pending || 0) + ' Pending');
        }
        if (elements.todayAppointmentsCompleted) {
            safeHTML(elements.todayAppointmentsCompleted, '<i class="fas fa-check-circle"></i> ' + (todayAppointments.completed || 0) + ' Complete');
        }
        if (elements.todayAppointmentsProgress) {
            var pct = todayAppointments.total > 0 ? Math.min(100, (todayAppointments.completed / Math.max(todayAppointments.total, 1)) * 100) : 0;
            elements.todayAppointmentsProgress.style.width = pct + '%';
        }
        
        // ================================================================
        // UPDATE CARD 6: Total Appointments
        // ================================================================
        if (elements.totalAppointments) {
            safeText(elements.totalAppointments, formatNumber(data.total_appointments || 0));
        }
        
        // ================================================================
        // UPDATE CARD 7: Prescriptions
        // ================================================================
        if (elements.totalPrescriptions) {
            safeText(elements.totalPrescriptions, formatNumber(data.total_prescriptions || 0));
        }
        
        // ================================================================
        // UPDATE CARD 8: Lab Tests
        // ================================================================
        if (elements.labTestsTotal) {
            safeText(elements.labTestsTotal, formatNumber(labTests.total || 0));
        }
        if (elements.labTestsPending) {
            safeHTML(elements.labTestsPending, '<i class="fas fa-clock"></i> ' + (labTests.pending || 0) + ' Pending');
        }
        if (elements.labTestsCompleted) {
            safeHTML(elements.labTestsCompleted, '<i class="fas fa-check-circle"></i> ' + (labTests.completed || 0) + ' Complete');
        }
        if (elements.labTestsProgress) {
            var pct = labTests.total > 0 ? Math.min(100, (labTests.completed / Math.max(labTests.total, 1)) * 100) : 0;
            elements.labTestsProgress.style.width = pct + '%';
        }
        
        // Lab badge
        if (elements.labTestsBadge) {
            if (labTests.pending > 0) {
                elements.labTestsBadge.textContent = labTests.pending;
                elements.labTestsBadge.style.display = 'inline-block';
            } else {
                elements.labTestsBadge.style.display = 'none';
            }
        }
        if (elements.cardLabTests) {
            if (labTests.pending > 0) {
                elements.cardLabTests.classList.add('has-badge');
            } else {
                elements.cardLabTests.classList.remove('has-badge');
            }
        }
        
        // ================================================================
        // UPDATE APPOINTMENTS COUNT
        // ================================================================
        if (elements.appointmentsCount) {
            safeText(elements.appointmentsCount, '(' + (todayAppointments.total || 0) + ')');
        }
        
        // ================================================================
        // UPDATE QUEUE COUNT
        // ================================================================
        if (elements.queueCount) {
            safeText(elements.queueCount, '(' + (data.pending_visits || 0) + ' waiting)');
        }
        
        // ================================================================
        // UPDATE APPOINTMENTS LIST
        // ================================================================
        if (elements.appointmentsList) {
            var apptList = todayAppointments.list || [];
            if (apptList.length > 0) {
                var listHtml = '';
                apptList.forEach(function(appt) {
                    var statusClass = appt.status || 'scheduled';
                    var statusLabel = capitalize(statusClass);
                    listHtml += `
                        <div class="appointment-item">
                            <div class="appointment-time">${formatTime(appt.appointment_date)}</div>
                            <div class="appointment-patient">
                                <div class="appointment-name">${escapeHtml(appt.patient_name)}</div>
                                <div class="appointment-id">${escapeHtml(appt.patient_id || 'N/A')}</div>
                            </div>
                            <span class="appointment-status ${statusClass}">${statusLabel}</span>
                        </div>
                    `;
                });
                safeHTML(elements.appointmentsList, listHtml);
            } else {
                safeHTML(elements.appointmentsList, `
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <p>No appointments scheduled for today</p>
                    </div>
                `);
            }
        }
        
        // ================================================================
        // UPDATE QUEUE LIST
        // ================================================================
        if (elements.queueList) {
            var queueList = data.pending_patients || [];
            if (queueList.length > 0) {
                var listHtml = '';
                queueList.forEach(function(patient, index) {
                    var isFirst = index === 0 ? 'queue-item-first' : '';
                    var waitingTime = patient.waiting_time > 0 ? patient.waiting_time + ' min' : 'Just now';
                    var isLong = patient.waiting_time > 30 ? 'queue-time-long' : '';
                    var statusClass = patient.status || 'pending';
                    
                    listHtml += `
                        <div class="queue-item ${isFirst}">
                            <div class="queue-number">#${index + 1}</div>
                            <div class="queue-patient">
                                <div class="queue-name">
                                    ${escapeHtml(patient.patient_name)}
                                    ${index === 0 ? '<span class="queue-badge">Next</span>' : ''}
                                </div>
                                <div class="queue-details">
                                    ${escapeHtml(patient.patient_number || 'N/A')} • ${escapeHtml(patient.phone || '')}
                                </div>
                            </div>
                            <div class="queue-waiting">
                                <span class="queue-time ${isLong}">${waitingTime}</span>
                                <span class="queue-status ${statusClass}">${capitalize(statusClass)}</span>
                            </div>
                            <div class="queue-action">
                                <a href="consultation.php?visit_id=${patient.id}" class="btn-consult">
                                    <i class="fas fa-stethoscope"></i> Consult
                                </a>
                            </div>
                        </div>
                    `;
                });
                safeHTML(elements.queueList, listHtml);
            } else {
                safeHTML(elements.queueList, `
                    <div class="empty-state empty-state-large">
                        <i class="fas fa-check-circle text-green-500"></i>
                        <p class="text-gray-400">No patients waiting! All clear.</p>
                        <p class="text-xs text-gray-400">Take a break or review completed cases</p>
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
                        
                        // Show notification on change (every 3rd update)
                        if (state.updateCount > 1 && state.updateCount % 3 === 0) {
                            console.log('[DoctorStats] Auto-updated at ' + data.data.timestamp);
                        }
                    }
                }
                state.isUpdating = false;
            })
            .catch(function(error) {
                console.error('[DoctorStats] Error fetching data:', error);
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
        var btn = document.getElementById('refreshBtn');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Loading...';
            btn.disabled = true;
        }
        
        // Force reset hash to force update
        state.hash = null;
        fetchStats();
        
        setTimeout(function() {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                btn.disabled = false;
            }
        }, 1000);
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
        
        log('Initializing Doctor Stats System');
        elements = findPageElements();
        
        // Start auto-update after 1.5 seconds
        setTimeout(function() {
            startAutoUpdate();
        }, 1500);
        
        state.initialized = true;
        log('Doctor Stats System initialized');
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
    window.DoctorStats = {
        start: startAutoUpdate,
        stop: stopAutoUpdate,
        refresh: manualRefresh,
        fetch: fetchStats,
        state: state,
        config: CONFIG,
        elements: elements
    };
    
    console.log('%c👨‍⚕️ Doctor Stats System Initialized', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔄 Auto-update every ' + CONFIG.updateInterval / 1000 + ' seconds', 'font-size:12px; color:#34D399;');
    console.log('%c💡 Type DoctorStats.start() or DoctorStats.stop() to control', 'font-size:12px; color:#64748B;');
    console.log('%c💡 Type DoctorStats.refresh() for manual refresh', 'font-size:12px; color:#64748B;');
    
})();