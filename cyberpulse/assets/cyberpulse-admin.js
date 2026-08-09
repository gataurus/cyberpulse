/**
 * Cyber Pulse - Admin JavaScript
 */
(function() {
    'use strict';

    // ==================== STUBS FOR FUNCTIONS CALLED FROM HTML ====================
    // These prevent errors if HTML calls a function before this script loads
    window.swToggleTheme = window.swToggleTheme || function() {};
    window.sw_tab = window.sw_tab || function() {};
    window.runSecurityTest = window.runSecurityTest || function() {};
    window.setPreset = window.setPreset || function() {};
    window.filterAuditEvents = window.filterAuditEvents || function() {};
    window.exportAuditCSV = window.exportAuditCSV || function() {};

    // ==================== THEME TOGGLE ====================
    if (localStorage.getItem('cybersec_theme') === 'light') {
        document.body.classList.add('sw-light-mode');
        var cb = document.getElementById('sw-theme-checkbox');
        if (cb) cb.checked = true;
    }

    window.swToggleTheme = function() {
        document.body.classList.toggle('sw-light-mode');
        localStorage.setItem('cybersec_theme', document.body.classList.contains('sw-light-mode') ? 'light' : 'dark');
    };

    // ==================== TAB NAVIGATION ====================
    window.sw_tab = function(t) {
        document.querySelectorAll('.sw-tab').forEach(function(e) { e.style.display = 'none'; });
        var tabEl = document.getElementById('tab-' + t);
        if (tabEl) tabEl.style.display = 'block';
        document.querySelectorAll('.nav-tab').forEach(function(l) { l.classList.remove('nav-tab-active'); });
        var activeLink = document.querySelector('.nav-tab[data-tab="' + t + '"]');
        if (activeLink) activeLink.classList.add('nav-tab-active');

        // Initialize pagination for visible tab
        if (t === 'protection') {
            setTimeout(function() {
                var permWrap = document.getElementById('sw-permanent-wrap');
                if (permWrap) swPaginate('sw-permanent-wrap', 30);
                var offendersWrap = document.getElementById('sw-offenders-wrap');
                if (offendersWrap) swPaginate('sw-offenders-wrap', 30);
                var tempWrap = document.getElementById('sw-temp-blocks-wrap');
                if (tempWrap) swPaginate('sw-temp-blocks-wrap', 30);
            }, 300);
        }
        if (t === 'protection' || t === 'settings') {
            setTimeout(function() {
                var uaTable = document.getElementById('sw-ua-block-table');
                if (uaTable) swPaginate('sw-ua-block-table', 30);
            }, 300);
        }
    };

    document.querySelectorAll('.nav-tab').forEach(function(l) {
        l.addEventListener('click', function(e) {
            e.preventDefault();
            var t = this.getAttribute('data-tab');
            if (t) sw_tab(t);
        });
    });

    // Initialize pagination on page load for active tab
    (function() {
        var activeTab = document.querySelector('.nav-tab-active');
        if (activeTab) {
            var tab = activeTab.getAttribute('data-tab');
            if (tab === 'protection') {
                setTimeout(function() {
                    var permWrap = document.getElementById('sw-permanent-wrap');
                    if (permWrap) swPaginate('sw-permanent-wrap', 30);
                    var offendersWrap = document.getElementById('sw-offenders-wrap');
                    if (offendersWrap) swPaginate('sw-offenders-wrap', 30);
                    var tempWrap = document.getElementById('sw-temp-blocks-wrap');
                    if (tempWrap) swPaginate('sw-temp-blocks-wrap', 30);
                }, 500);
            }
            if (tab === 'settings') {
                setTimeout(function() {
                    var uaTable = document.getElementById('sw-ua-block-table');
                    if (uaTable) swPaginate('sw-ua-block-table', 30);
                }, 500);
            }
        }
    })();

    // Restore saved tab
    (function() {
        var savedTab = localStorage.getItem('cybersec_tab');
        if (savedTab) {
            localStorage.removeItem('cybersec_tab');
            if (document.getElementById('tab-' + savedTab)) sw_tab(savedTab);
        }
        var savedScroll = localStorage.getItem('cybersec_scroll');
        if (savedScroll) {
            localStorage.removeItem('cybersec_scroll');
            setTimeout(function() {
                var el = document.getElementById(savedScroll);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.style.boxShadow = '0 0 30px rgba(0,224,72,0.5)';
                    setTimeout(function() { el.style.boxShadow = ''; }, 2000);
                }
            }, 300);
            return;
        }
        var hash = window.location.hash;
        if (hash && hash.indexOf('tab-') !== -1) {
            var t = hash.replace('#tab-', '');
            if (document.getElementById('tab-' + t)) sw_tab(t);
        }
    })();

    // ==================== AJAX HELPER ====================
    window.cybersecAjax = {
        nonce: cybersecAdmin.nonce,
        url: cybersecAdmin.ajaxurl,

        post: function(action, data, callback) {
            var fd = new FormData();
            fd.append('action', action);
            fd.append('cybersec_ajax_nonce', this.nonce);
            for (var k in data) {
                if (data.hasOwnProperty(k)) fd.append(k, data[k]);
            }
            fetch(this.url, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) { if (callback) callback(d); })
                .catch(function(e) { console.error('CyberPulse AJAX error:', e); });
        },

        clear: function(subAction, data, callback) {
            var fd = new FormData();
            fd.append('action', 'cybersec_ajax_clear');
            fd.append('cybersec_ajax_nonce', this.nonce);
            fd.append('sub_action', subAction);
            for (var k in data) {
                if (data.hasOwnProperty(k)) fd.append(k, data[k]);
            }
            fetch(this.url, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) { if (callback) callback(d); })
                .catch(function(e) { console.error('CyberPulse AJAX error:', e); });
        },

        blockUA: function(ua, btn) {
            if (!confirm(cybersecAdmin.i18n.confirmBlockUA + '\n\n' + ua.substring(0, 120) + '\n\n' + cybersecAdmin.i18n.confirmBlockUA2)) return;
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="cybersec-spinner"></span>';
            btn.disabled = true;
            var self = this;
            var row = btn.closest('tr');
            this.post('cybersec_block_ua', { user_agent: ua }, function(d) {
                if (d.success) {
                    btn.innerHTML = cybersecAdmin.i18n.unblock;
                    btn.disabled = false;
                    btn.onclick = function() { self.unblockUA(ua, btn); };
                    if (row) {
                        var badge = row.querySelector('td:first-child span');
                        if (badge) {
                            badge.style.background = 'rgba(255,64,64,0.15)';
                            badge.style.color = '#ff4040';
                            badge.textContent = cybersecAdmin.i18n.blocked;
                        }
                    }
                } else {
                    alert(d.data.message);
                    btn.innerHTML = orig;
                    btn.disabled = false;
                }
            });
        },

        unblockUA: function(ua, btn) {
            if (!confirm(cybersecAdmin.i18n.confirmUnblockUA)) return;
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="cybersec-spinner"></span>';
            btn.disabled = true;
            var self = this;
            var row = btn.closest('tr');
            this.post('cybersec_unblock_ua', { user_agent: ua }, function(d) {
                if (d.success) {
                    btn.innerHTML = cybersecAdmin.i18n.block;
                    btn.disabled = false;
                    btn.style.background = '#00e048';
                    btn.style.color = '#1a1e18';
                    btn.style.borderColor = '#00e048';
                    btn.onclick = function() { self.blockUA(ua, btn); };
                } else {
                    alert(d.data.message);
                    btn.innerHTML = orig;
                    btn.disabled = false;
                }
            });
        },

        clearLogAndUpdate: function(slug, cid, counterId, btn) {
            if (!confirm(cybersecAdmin.i18n.confirmClearLog)) return;
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="cybersec-spinner"></span>';
            this.clear('clear_log', { slug: slug }, function() {
                var el = document.getElementById(cid);
                if (el) el.innerHTML = '<div class="sw-log-empty"><div style="font-size:40px">📭</div><div>' + cybersecAdmin.i18n.logEmpty + '</div></div>';
                if (counterId) {
                    var c = document.getElementById(counterId);
                    if (c) c.textContent = '0';
                }
                btn.innerHTML = '✅';
                setTimeout(function() { btn.innerHTML = orig; }, 1500);
            });
        },

        clearBlockedLogAndUpdate: function(cid, btn) {
            if (!confirm(cybersecAdmin.i18n.confirmClearBlockedLog)) return;
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="cybersec-spinner"></span>';
            this.clear('clear_blocked_log', {}, function() {
                var el = document.getElementById(cid);
                if (el) el.innerHTML = '<div class="sw-log-empty"><div style="font-size:40px">📭</div><div>' + cybersecAdmin.i18n.logEmpty + '</div></div>';
                btn.innerHTML = '✅';
                setTimeout(function() { btn.innerHTML = orig; }, 1500);
            });
        },

        clearAllowedLogAndUpdate: function(cid, btn) {
            if (!confirm(cybersecAdmin.i18n.confirmClearAllowedLog)) return;
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="cybersec-spinner"></span>';
            this.clear('clear_allowed_log', {}, function() {
                var el = document.getElementById(cid);
                if (el) el.innerHTML = '<div class="sw-log-empty"><div style="font-size:40px">📭</div><div>' + cybersecAdmin.i18n.logEmpty + '</div></div>';
                btn.innerHTML = '✅';
                setTimeout(function() { btn.innerHTML = orig; }, 1500);
            });
        },

        clearOffendersAndUpdate: function(cid, btn) {
            if (!confirm(cybersecAdmin.i18n.confirmClearOffenders)) return;
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="cybersec-spinner"></span>';
            this.clear('clear_offenders', {}, function() {
                var el = document.getElementById(cid);
                if (el) el.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--sw-text-secondary);">' + cybersecAdmin.i18n.listEmpty + '</td></tr>';
                btn.innerHTML = '✅';
                setTimeout(function() { btn.innerHTML = orig; }, 1500);
            });
        },

        clearUAStatsAndUpdate: function(btn) {
            if (!confirm(cybersecAdmin.i18n.confirmClearUA)) return;
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="cybersec-spinner"></span>';
            this.clear('clear_ua_stats', {}, function() {
                btn.innerHTML = '✅';
                setTimeout(function() { location.reload(); }, 800);
            });
        },

        clearBruteAndUpdate: function(cid, btn) {
            if (!confirm(cybersecAdmin.i18n.confirmClearBrute)) return;
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="cybersec-spinner"></span>';
            this.clear('clear_brute', {}, function() {
                var el = document.getElementById(cid);
                if (el) el.innerHTML = '<p style="color:var(--sw-text-secondary);">' + cybersecAdmin.i18n.noActiveBrute + '</p>';
                btn.innerHTML = '✅';
                setTimeout(function() { btn.innerHTML = orig; }, 1500);
            });
        },

        clearAllBotLogsAndUpdate: function(btn) {
            if (!confirm(cybersecAdmin.i18n.confirmClearAllBots)) return;
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="cybersec-spinner"></span>';
            this.clear('clear_all_bot_logs', {}, function() {
                btn.innerHTML = '✅';
                setTimeout(function() { location.reload(); }, 800);
            });
        },

        clearAllPageLogsAndUpdate: function(cid, btn) {
            if (!confirm(cybersecAdmin.i18n.confirmClearAllPages)) return;
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="cybersec-spinner"></span>';
            this.clear('clear_all_page_logs', {}, function() {
                var el = document.getElementById(cid);
                if (el) el.innerHTML = '<div class="sw-log-empty"><div style="font-size:40px">📭</div><div>' + cybersecAdmin.i18n.logEmpty + '</div></div>';
                document.querySelectorAll('.sw-page-counter').forEach(function(c) { c.textContent = '0'; });
                btn.innerHTML = '✅';
                setTimeout(function() { btn.innerHTML = orig; }, 1500);
            });
        },

        tempBlock: function(btn, prefix) {
            var subnet = document.getElementById(prefix + '-subnet').value.trim();
            var minutes = document.getElementById(prefix + '-minutes').value;
            if (!subnet) {
                alert(cybersecAdmin.i18n.enterIP);
                return;
            }
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="cybersec-spinner"></span>';
            this.post('cybersec_ajax_temp_block', { subnet: subnet, minutes: minutes }, function(d) {
                btn.innerHTML = d.success ? '✅ (' + d.data.blocked + ')' : '❌';
                setTimeout(function() { btn.innerHTML = orig; }, 2000);
            });
        },

        clearAuditLogAndUpdate: function(cid, btn) {
            if (!confirm(cybersecAdmin.i18n.confirmClearAudit)) return;
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="cybersec-spinner"></span> ' + cybersecAdmin.i18n.clearing;
            this.clear('clear_audit_log', {}, function() {
                var el = document.getElementById(cid);
                if (el) el.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;"><div style="text-align:center;"><div style="font-size:40px">📭</div><div>' + cybersecAdmin.i18n.noEvents + '</div></div></div>';
                btn.innerHTML = cybersecAdmin.i18n.done;
                setTimeout(function() { btn.innerHTML = orig; }, 1500);
            });
        }
    };

    // ==================== TABLE SORT ====================
    window.swSortTable = function(table, colIndex, isNumeric) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        var rows = Array.from(tbody.querySelectorAll('tr'));
        var asc = table.getAttribute('data-sort-dir') !== 'asc';
        table.setAttribute('data-sort-dir', asc ? 'asc' : 'desc');
        rows.sort(function(a, b) {
            var av = (a.cells[colIndex] || a.children[colIndex]).textContent.trim();
            var bv = (b.cells[colIndex] || b.children[colIndex]).textContent.trim();
            if (isNumeric) {
                av = parseFloat(av.replace(/[^0-9.-]/g, '')) || 0;
                bv = parseFloat(bv.replace(/[^0-9.-]/g, '')) || 0;
                return asc ? av - bv : bv - av;
            }
            return asc ? av.localeCompare(bv) : bv.localeCompare(av);
        });
        rows.forEach(function(r) { tbody.appendChild(r); });
        table.querySelectorAll('th .sw-sort-arrow').forEach(function(a) { a.textContent = ''; });
        var h = table.querySelectorAll('th')[colIndex];
        if (h) {
            var arrow = h.querySelector('.sw-sort-arrow');
            if (arrow) arrow.textContent = asc ? ' ▲' : ' ▼';
        }
    };

    // ==================== PAGINATION ====================
    window.swPaginate = function(containerId, rowsPerPage) {
        rowsPerPage = rowsPerPage || 20;
        var container = document.getElementById(containerId);
        if (!container) return;
        var table = container.querySelector('table');
        if (!table) return;
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        var rows = Array.from(tbody.querySelectorAll('tr'));
        if (rows.length <= rowsPerPage) {
            var en = container.querySelector('.sw-pagination');
            if (en) en.style.display = 'none';
            return;
        }
        var cp = 1, tp = Math.ceil(rows.length / rowsPerPage);

        function show(p) {
            cp = Math.max(1, Math.min(p, tp));
            rows.forEach(function(r, i) {
                r.style.display = (i >= (cp - 1) * rowsPerPage && i < cp * rowsPerPage) ? '' : 'none';
            });
            var info = container.querySelector('.sw-pagination-info');
            if (info) info.textContent = (cp - 1) * rowsPerPage + 1 + '-' + Math.min(cp * rowsPerPage, rows.length) + ' ' + cybersecAdmin.i18n.of + ' ' + rows.length;
            var prev = container.querySelector('.sw-pagination-prev'),
                next = container.querySelector('.sw-pagination-next');
            if (prev) { prev.disabled = cp === 1; prev.style.opacity = cp === 1 ? '0.4' : '1'; }
            if (next) { next.disabled = cp === tp; next.style.opacity = cp === tp ? '0.4' : '1'; }
        }

        var nav = container.querySelector('.sw-pagination');
        if (!nav) {
            nav = document.createElement('div');
            nav.className = 'sw-pagination';
            nav.innerHTML = '<button type="button" class="button button-small sw-pagination-prev">← ' + cybersecAdmin.i18n.back + '</button><span class="sw-pagination-info"></span><button type="button" class="button button-small sw-pagination-next">' + cybersecAdmin.i18n.forward + ' →</button>';
            container.appendChild(nav);
            nav.querySelector('.sw-pagination-prev').addEventListener('click', function() { show(cp - 1); });
            nav.querySelector('.sw-pagination-next').addEventListener('click', function() { show(cp + 1); });
        }
        show(1);
    };

    // ==================== BLOCK TAB SWITCH ====================
    window.swSwitchBlockTab = function(panelId, tabEl) {
        document.querySelectorAll('.sw-block-panel').forEach(function(p) { p.classList.remove('active'); });
        document.querySelectorAll('.sw-block-tab').forEach(function(t) { t.classList.remove('active'); });
        document.getElementById(panelId).classList.add('active');
        tabEl.classList.add('active');
    };

    // ==================== COPY TO CLIPBOARD ====================
    window.swCopyToClipboard = function(text, el) {
        navigator.clipboard.writeText(text).then(function() {
            var toast = document.getElementById('sw-copy-toast');
            if (!toast) return;
            toast.textContent = cybersecAdmin.i18n.copied;
            toast.classList.add('show');
            if (el) { el.style.background = 'rgba(0,224,72,0.2)'; setTimeout(function() { el.style.background = ''; }, 300); }
            setTimeout(function() { toast.classList.remove('show'); }, 2000);
        }).catch(function() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            var toast = document.getElementById('sw-copy-toast');
            if (!toast) return;
            toast.textContent = cybersecAdmin.i18n.copied;
            toast.classList.add('show');
            setTimeout(function() { toast.classList.remove('show'); }, 2000);
        });
    };

    window.swCopyAllLog = function(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var text = container.textContent || container.innerText || '';
        navigator.clipboard.writeText(text).then(function() {
            var toast = document.getElementById('sw-copy-toast');
            if (!toast) return;
            toast.textContent = cybersecAdmin.i18n.logCopied + ' (' + text.length + ' ' + cybersecAdmin.i18n.chars + ')';
            toast.classList.add('show');
            setTimeout(function() { toast.classList.remove('show'); }, 2500);
        }).catch(function() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            var toast = document.getElementById('sw-copy-toast');
            if (!toast) return;
            toast.textContent = cybersecAdmin.i18n.copied;
            toast.classList.add('show');
            setTimeout(function() { toast.classList.remove('show'); }, 2000);
        });
    };

    // ==================== LOG FILTERS ====================
    window.swPageFilterLog = function(query) {
        var ta = document.getElementById('sw-page-log-ta') || document.getElementById('sw-page-log-ta-view');
        if (!ta) return;
        var orig = ta.getAttribute('data-original');
        if (!orig) { orig = ta.innerHTML; ta.setAttribute('data-original', orig); }
        if (!query) { ta.innerHTML = orig; return; }
        var temp = document.createElement('div');
        temp.innerHTML = orig;
        var entries = temp.getElementsByClassName('sw-page-log-entry');
        var found = false;
        for (var i = 0; i < entries.length; i++) {
            if (entries[i].textContent.toLowerCase().indexOf(query.toLowerCase()) !== -1) {
                entries[i].style.display = '';
                found = true;
            } else {
                entries[i].style.display = 'none';
            }
        }
        ta.innerHTML = found ? temp.innerHTML : '<div style="text-align:center;padding:40px">' + cybersecAdmin.i18n.nothingFound + '</div>';
    };

    window.swFilterBlockedLog = function(query) {
        var ta = document.getElementById('sw-blocked-log-ta');
        if (!ta) return;
        var orig = ta.getAttribute('data-original');
        if (!orig) { orig = ta.innerHTML; ta.setAttribute('data-original', orig); }
        if (!query) { ta.innerHTML = orig; return; }
        var temp = document.createElement('div');
        temp.innerHTML = orig;
        var entries = temp.getElementsByClassName('sw-log-entry');
        var found = false;
        for (var i = 0; i < entries.length; i++) {
            if (entries[i].textContent.toLowerCase().indexOf(query.toLowerCase()) !== -1) {
                entries[i].style.display = '';
                found = true;
            } else {
                entries[i].style.display = 'none';
            }
        }
        ta.innerHTML = found ? temp.innerHTML : '<div style="text-align:center;padding:40px">' + cybersecAdmin.i18n.nothingFound + '</div>';
    };

    // ==================== SET PRESET ====================
    window.setPreset = function(mode, btn) {
        var presets = {
            strict: { history: 1, rate1: 2, rate5: 3, same: 2, noproxy: 2 },
            medium: { history: 1, rate1: 5, rate5: 10, same: 3, noproxy: 3 },
            soft: { history: 3, rate1: 8, rate5: 15, same: 5, noproxy: 5 }
        };
        var p = presets[mode];
        if (!p) return;
        var fields = {
            history: 'cybersec_referer_history_threshold',
            rate1: 'cybersec_referer_rate_1min',
            rate5: 'cybersec_referer_rate_5min',
            same: 'cybersec_referer_same_page',
            noproxy: 'cybersec_referer_no_proxy_rate'
        };
        for (var key in fields) {
            var el = document.getElementsByName(fields[key])[0];
            if (el) el.value = p[key];
        }
        document.querySelectorAll('.preset-btn').forEach(function(b) { b.style.opacity = '0.6'; b.style.fontWeight = 'normal'; });
        btn.style.opacity = '1';
        btn.style.fontWeight = 'bold';
    };

    // ==================== DOWNLOAD LOG ====================
    window.swDownloadLog = function(containerId, filename) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var text = container.textContent || container.innerText || '';
        var blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'log.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    // ==================== AUDIT FILTER ====================
    window.filterAuditEvents = function() {
        var filter = document.getElementById('audit-filter');
        if (!filter) return;
        var val = filter.value;
        var entries = document.querySelectorAll('#sw-audit-log-ta .sw-log-entry');
        entries.forEach(function(entry) {
            entry.style.display = (val === 'all' || entry.textContent.indexOf(val) !== -1) ? '' : 'none';
        });
    };

    // ==================== EXPORT AUDIT CSV ====================
    window.exportAuditCSV = function() {
        var container = document.getElementById('sw-audit-log-ta');
        if (!container) return;
        var entries = container.querySelectorAll('.sw-log-entry');
        var csv = 'Date,Event,User,IP\n';
        entries.forEach(function(entry) {
            var line = entry.textContent || '';
            var date = (line.match(/\[([^\]]+)\]/) || [])[1] || '';
            var ip = (line.match(/IP:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/) || [])[1] || '';
            var user = (line.match(/User:\s*(\S+)/) || [])[1] || '';
            var event = line.replace(/\[[^\]]+\]\s*/, '').match(/^(.*?)\s*\|/);
            event = event ? event[1].trim() : '';
            if (date && event) csv += '"' + date + '","' + event + '","' + user + '","' + ip + '"\n';
        });
        if (entries.length === 0) { alert(cybersecAdmin.i18n.noDataExport); return; }
        var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'audit-log-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    // ==================== SECURITY TEST ====================
    window.runSecurityTest = function() {
        var btn = document.getElementById('sw-test-btn');
        var empty = document.getElementById('sw-test-empty');
        var resultsDiv = document.getElementById('sw-test-results');
        var summary = document.getElementById('sw-test-summary');
        var list = document.getElementById('sw-test-list');
        if (!btn || !empty || !resultsDiv || !summary || !list) return;
        btn.innerHTML = cybersecAdmin.i18n.testing;
        btn.disabled = true;
        var fd = new FormData();
        fd.append('action', 'cybersec_run_security_test');
        fd.append('cybersec_ajax_nonce', cybersecAjax.nonce);
        fetch(cybersecAjax.url, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    var gradeColor = d.data.grade === 'A' ? 'var(--sw-success)' : (d.data.grade === 'B' ? 'var(--sw-warning)' : 'var(--sw-danger)');
                    summary.innerHTML = 'Result: <span style="color:' + gradeColor + '">' + d.data.grade + '</span> — passed <strong>' + d.data.passed + '/' + d.data.total + '</strong> tests';
                    var html = '';
                    d.data.results.forEach(function(r) {
                        var icon = r.passed ? '✅' : '❌';
                        var color = r.passed ? 'var(--sw-success)' : 'var(--sw-danger)';
                        html += '<div style="padding:8px 12px;margin:4px 0;background:var(--sw-card-bg-alt);border-left:3px solid ' + color + ';border-radius:var(--sw-radius);"><strong>' + icon + ' ' + r.test + '</strong> <span style="font-size:0.65rem;color:var(--sw-text-secondary);">— ' + r.detail + ' (HTTP ' + r.code + ')</span></div>';
                    });
                    list.innerHTML = html;
                    empty.style.display = 'none';
                    resultsDiv.style.display = 'block';
                }
                btn.innerHTML = cybersecAdmin.i18n.repeatTest;
                btn.disabled = false;
            })
            .catch(function() {
                btn.innerHTML = cybersecAdmin.i18n.repeat;
                btn.disabled = false;
            });
    };

    // ==================== DASHBOARD WIDGET ====================
    (function() {
        var w = document.getElementById('cybersec_dashboard_widget');
        if (!w) return;
        var n = document.getElementById('normal-sortables');
        if (!n) return;
        if (n.firstChild !== w) n.insertBefore(w, n.firstChild);
    })();

    // Auto-hide all notices after 3 seconds
    (function() {
        var notices = document.querySelectorAll('.notice-success, .notice-error');
        if (!notices.length) return;
        setTimeout(function() {
            notices.forEach(function(notice) {
                notice.style.transition = 'opacity 0.5s';
                notice.style.opacity = '0';
                setTimeout(function() {
                    if (notice.parentNode) notice.remove();
                }, 500);
            });
        }, 3000);
    })();

    // ==================== TEST MODE TIMER ====================
    (function() {
        var timerEl = document.getElementById('test-mode-timer');
        if (!timerEl) return;
        var timeLeft = 300; // 5 minutes
        function updateTimer() {
            if (timeLeft <= 0) {
                var statusEl = document.getElementById('test-mode-status');
                if (statusEl) statusEl.innerHTML = '<span style="color:var(--sw-success);">✅ ' + cybersecAdmin.i18n.done + '</span>';
                return;
            }
            var m = Math.floor(timeLeft / 60);
            var s = timeLeft % 60;
            timerEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            timeLeft--;
            setTimeout(updateTimer, 1000);
        }
        updateTimer();
    })();

})();