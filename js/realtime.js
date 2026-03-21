/**
 * AR NovaLink — Real-Time Sync via AJAX Polling
 * 
 * Polls ajax/check_updates.php every 5 seconds.
 * When data changes, triggers page-specific refresh logic.
 * Does NOT interfere with existing functionality.
 */
(function() {
    'use strict';

    var POLL_INTERVAL = 5000; // 5 seconds
    var lastSnapshot = null;
    var pollTimer = null;
    var basePath = '';

    // Detect base path (handles /billing_system/ subfolder)
    var scripts = document.getElementsByTagName('script');
    for (var i = 0; i < scripts.length; i++) {
        var src = scripts[i].src || '';
        var idx = src.indexOf('js/realtime.js');
        if (idx !== -1) { basePath = src.substring(0, idx); break; }
    }

    // Detect which page we're on
    function getCurrentPage() {
        var path = window.location.pathname;
        if (path.indexOf('index.php') !== -1 || path.endsWith('/billing_system/') || path.endsWith('/billing_system')) return 'dashboard';
        if (path.indexOf('payments.php') !== -1) return 'payments';
        if (path.indexOf('billings.php') !== -1) return 'billings';
        if (path.indexOf('customers.php') !== -1) return 'customers';
        if (path.indexOf('unpaid.php') !== -1) return 'unpaid';
        if (path.indexOf('reports.php') !== -1) return 'reports';
        return 'other';
    }

    // Check for updates
    function poll() {
        fetch(basePath + 'ajax/check_updates.php', { cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) return; // not logged in
                if (lastSnapshot === null) {
                    // First load — store snapshot, no refresh
                    lastSnapshot = data;
                    return;
                }
                if (hasChanged(data)) {
                    lastSnapshot = data;
                    onDataChanged(data);
                }
            })
            .catch(function() { /* network error, skip silently */ });
    }

    // Compare snapshots to detect changes
    function hasChanged(newData) {
        if (!lastSnapshot) return true;
        return (
            newData.ts !== lastSnapshot.ts ||
            newData.total_customers !== lastSnapshot.total_customers ||
            newData.total_payments !== lastSnapshot.total_payments ||
            newData.total_billings !== lastSnapshot.total_billings ||
            newData.total_unpaid !== lastSnapshot.total_unpaid ||
            newData.active_customers !== lastSnapshot.active_customers ||
            newData.disconnected !== lastSnapshot.disconnected ||
            newData.monthly_revenue !== lastSnapshot.monthly_revenue
        );
    }

    // Handle data change — dispatch per-page refresh
    function onDataChanged(data) {
        var page = getCurrentPage();

        if (page === 'dashboard') {
            refreshDashboard(data);
        } else if (page === 'payments') {
            // Reload the page for payments (daily report + recent payments are server-rendered)
            location.reload();
        } else if (page === 'billings') {
            location.reload();
        } else if (page === 'customers') {
            refreshCustomers();
        } else if (page === 'unpaid') {
            location.reload();
        } else if (page === 'reports') {
            location.reload();
        }
    }

    // ── Dashboard: update stat cards without full reload ──
    function refreshDashboard(data) {
        var cards = document.querySelectorAll('.stat-details h3');
        if (cards.length >= 4) {
            cards[0].textContent = numberFormat(data.total_customers);
            cards[1].textContent = numberFormat(data.active_customers);
            cards[2].textContent = numberFormat(data.total_unpaid);
            cards[3].textContent = '₱' + numberFormat(data.monthly_revenue, 2);
        }
        // Reload activity table
        var actTable = document.querySelector('.activity-table');
        if (actTable) {
            fetch(location.href)
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var newTable = doc.querySelector('.activity-table');
                    if (newTable && actTable.parentNode) {
                        actTable.parentNode.replaceChild(
                            document.importNode(newTable, true),
                            actTable
                        );
                    }
                });
        }
    }

    // ── Customers: re-trigger existing AJAX search ──
    function refreshCustomers() {
        if (typeof window.loadCustomers === 'function') {
            window.loadCustomers();
        }
    }

    // Number formatting helper
    function numberFormat(n, decimals) {
        decimals = decimals || 0;
        return parseFloat(n).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    // Start polling when page is loaded
    function start() {
        poll(); // Initial snapshot
        pollTimer = setInterval(poll, POLL_INTERVAL);

        // Pause when tab is hidden, resume when visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(pollTimer);
                pollTimer = null;
            } else {
                if (!pollTimer) {
                    poll();
                    pollTimer = setInterval(poll, POLL_INTERVAL);
                }
            }
        });
    }

    // Don't run on print/login pages
    if (window.location.pathname.indexOf('login.php') === -1 &&
        window.location.pathname.indexOf('print_') === -1) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    }

})();
