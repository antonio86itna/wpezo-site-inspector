/**
 * WPezo Site Inspector — Admin Dashboard JS
 * Vanilla JS, no jQuery dependency.
 */
(function () {
    'use strict';

    var DATA     = window.wpsiData || {};
    var REST_URL = DATA.restUrl || '';
    var NONCE    = DATA.nonce || '';
    var I18N     = DATA.i18n || {};

    var CATEGORY_META = {
        security:      { icon: '🛡️', label: 'Security',      cls: 'wpsi-cat-security' },
        performance:   { icon: '⚡', label: 'Performance',   cls: 'wpsi-cat-performance' },
        seo:           { icon: '🔍', label: 'SEO',            cls: 'wpsi-cat-seo' },
        accessibility: { icon: '♿', label: 'Accessibility', cls: 'wpsi-cat-accessibility' },
        database:      { icon: '🗄️', label: 'Database',       cls: 'wpsi-cat-database' },
    };

    var STATUS_ICONS = { pass: '✓', warn: '!', fail: '✗', locked: '🔒' };

    var GRADE_LABELS = {
        A: 'Excellent — Your site is in great shape!',
        B: 'Good — Just a few things to improve.',
        C: 'Fair — Several issues need attention.',
        D: 'Poor — Your site needs significant improvements.',
        F: 'Critical — Immediate action required!',
    };

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'className') node.className = attrs[k];
                else if (k === 'innerHTML') node.innerHTML = attrs[k];
                else if (k === 'textContent') node.textContent = attrs[k];
                else if (k.startsWith('on')) node.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
                else node.setAttribute(k, attrs[k]);
            });
        }
        if (children) {
            (Array.isArray(children) ? children : [children]).forEach(function (c) {
                if (typeof c === 'string') node.appendChild(document.createTextNode(c));
                else if (c) node.appendChild(c);
            });
        }
        return node;
    }

    function gradeColor(g) {
        return { A: '#16a34a', B: '#65a30d', C: '#ca8a04', D: '#ea580c', F: '#dc2626' }[g] || '#6b7280';
    }

    function countStatus(checks, s) {
        if (!Array.isArray(checks)) return 0;
        return checks.filter(function (c) { return c.status === s; }).length;
    }

    function animateCounter(id, start, end, dur) {
        var t = document.getElementById(id);
        if (!t) return;
        var range = end - start, st = null;
        function step(ts) {
            if (!st) st = ts;
            var p = Math.min((ts - st) / dur, 1);
            t.textContent = Math.floor(start + range * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    /* ── Progress ────────────────────────────────────────────────── */

    function showProgress() {
        var prog = document.getElementById('wpsi-progress');
        var fill = document.getElementById('wpsi-progress-fill');
        var text = document.getElementById('wpsi-progress-text');
        if (!prog) return { complete: function(){}, error: function(){} };
        prog.style.display = '';
        fill.style.width = '0%';
        fill.style.background = '';
        var steps = [
            { pct: 12, msg: '🛡️ Checking security...' },
            { pct: 28, msg: '⚡ Analyzing performance...' },
            { pct: 44, msg: '🔍 Auditing SEO...' },
            { pct: 58, msg: '♿ Testing accessibility...' },
            { pct: 72, msg: '🗄️ Inspecting database...' },
            { pct: 85, msg: '🔐 Running advanced checks...' },
            { pct: 95, msg: '📊 Calculating score...' },
        ];
        var i = 0;
        var iv = setInterval(function () {
            if (i < steps.length) { fill.style.width = steps[i].pct + '%'; text.textContent = steps[i].msg; i++; }
        }, 650);
        return {
            complete: function () { clearInterval(iv); fill.style.width = '100%'; text.textContent = I18N.scanDone || 'Scan complete!'; setTimeout(function () { prog.style.display = 'none'; }, 600); },
            error: function () { clearInterval(iv); fill.style.width = '100%'; fill.style.background = '#dc2626'; text.textContent = I18N.scanError || 'Scan failed.'; },
        };
    }

    /* ── Empty State ─────────────────────────────────────────────── */

    function renderEmpty(c) {
        c.innerHTML = '';
        c.appendChild(el('div', { className: 'wpsi-empty' }, [
            el('div', { className: 'wpsi-empty-icon', textContent: '🔎' }),
            el('h2', null, [I18N.noResults || 'No scan results yet.']),
            el('p', null, ['Click "Run Full Scan" to analyze your WordPress site across 43 checks covering security, performance, SEO, accessibility, database, and advanced diagnostics.']),
        ]));
    }

    /* ── Score Card ──────────────────────────────────────────────── */

    function renderScoreCard(container, results) {
        var score = results.score || 0, grade = results.grade || 'F';
        var summary = results.summary || { pass: 0, warn: 0, fail: 0 };
        var meta = results.meta || {};
        var circ = 2 * Math.PI * 52;
        var off = circ - (score / 100) * circ;

        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', '130'); svg.setAttribute('height', '130'); svg.setAttribute('viewBox', '0 0 130 130');
        svg.innerHTML = '<circle cx="65" cy="65" r="52" class="wpsi-score-circle-track"/><circle cx="65" cy="65" r="52" class="wpsi-score-circle-fill" stroke="' + gradeColor(grade) + '" stroke-dasharray="' + circ + '" stroke-dashoffset="' + circ + '"/>';
        setTimeout(function () { var f = svg.querySelector('.wpsi-score-circle-fill'); if (f) f.style.strokeDashoffset = off; }, 100);

        container.appendChild(el('div', { className: 'wpsi-score-card' }, [
            el('div', { className: 'wpsi-score-circle' }, [svg,
                el('div', { className: 'wpsi-score-label' }, [
                    el('span', { className: 'wpsi-score-number', textContent: '0', id: 'wpsi-score-num' }),
                    el('span', { className: 'wpsi-score-max', textContent: '/ 100' }),
                ]),
            ]),
            el('div', { className: 'wpsi-score-details' }, [
                el('div', { className: 'wpsi-score-grade' }, [
                    el('span', { className: 'wpsi-score-grade-badge', style: 'background:' + gradeColor(grade), textContent: 'Grade ' + grade }),
                    el('span', { textContent: ' ' + (GRADE_LABELS[grade] || '') }),
                ]),
                el('div', { className: 'wpsi-score-summary' }, [
                    el('div', { className: 'wpsi-score-stat wpsi-stat-pass' }, [el('span', { className: 'wpsi-score-stat-icon', textContent: '✓' }), el('span', { textContent: summary.pass + ' ' + (I18N.pass || 'Passed') })]),
                    el('div', { className: 'wpsi-score-stat wpsi-stat-warn' }, [el('span', { className: 'wpsi-score-stat-icon', textContent: '!' }), el('span', { textContent: summary.warn + ' ' + (I18N.warn || 'Warning') })]),
                    el('div', { className: 'wpsi-score-stat wpsi-stat-fail' }, [el('span', { className: 'wpsi-score-stat-icon', textContent: '✗' }), el('span', { textContent: summary.fail + ' ' + (I18N.fail || 'Failed') })]),
                ]),
                el('div', { className: 'wpsi-meta-row' }, [
                    el('span', { className: 'wpsi-meta-item', innerHTML: '<strong>WP</strong> ' + (meta.wp_version || '—') }),
                    el('span', { className: 'wpsi-meta-item', innerHTML: '<strong>PHP</strong> ' + (meta.php_version || '—') }),
                    el('span', { className: 'wpsi-meta-item', innerHTML: '<strong>Server</strong> ' + (meta.server || '—') }),
                    el('span', { className: 'wpsi-meta-item', innerHTML: '<strong>Scanned</strong> ' + (meta.scan_time || '—') }),
                ]),
            ]),
        ]));
        animateCounter('wpsi-score-num', 0, score, 800);
    }

    /* ── History Chart ───────────────────────────────────────────── */

    function renderHistory(container, history) {
        if (!Array.isArray(history) || history.length < 2) return;
        var wrap = el('div', { className: 'wpsi-history-card' });
        wrap.appendChild(el('div', { className: 'wpsi-history-header' }, [
            el('h3', { textContent: '📈 Score History' }),
            el('span', { className: 'wpsi-history-count', textContent: history.length + ' scans recorded' }),
        ]));
        var w = 600, h = 160, pad = 40;
        var maxPts = Math.min(history.length, 20);
        var pts = history.slice(-maxPts);
        var stepX = (w - pad * 2) / (maxPts - 1);
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
        svg.setAttribute('class', 'wpsi-history-svg');
        svg.style.width = '100%'; svg.style.height = 'auto';
        [0, 25, 50, 75, 100].forEach(function (v) {
            var y = pad + (h - pad * 2) * (1 - v / 100);
            var ln = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            ln.setAttribute('x1', pad); ln.setAttribute('x2', w - pad); ln.setAttribute('y1', y); ln.setAttribute('y2', y);
            ln.setAttribute('stroke', '#e2e8f0'); ln.setAttribute('stroke-width', '1'); svg.appendChild(ln);
            if (v % 50 === 0) {
                var tx = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                tx.setAttribute('x', pad - 8); tx.setAttribute('y', y + 4); tx.setAttribute('text-anchor', 'end');
                tx.setAttribute('fill', '#a0aec0'); tx.setAttribute('font-size', '10'); tx.textContent = v; svg.appendChild(tx);
            }
        });
        var pathD = '', dots = [];
        pts.forEach(function (p, i) {
            var x = pad + i * stepX, y = pad + (h - pad * 2) * (1 - p.score / 100);
            dots.push({ x: x, y: y, score: p.score, grade: p.grade, date: p.date });
            pathD += (i === 0 ? 'M' : 'L') + x + ',' + y;
        });
        var areaD = pathD + 'L' + dots[dots.length - 1].x + ',' + (h - pad) + 'L' + pad + ',' + (h - pad) + 'Z';
        var defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        defs.innerHTML = '<linearGradient id="wpsi-grad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3182ce" stop-opacity="0.2"/><stop offset="100%" stop-color="#3182ce" stop-opacity="0.02"/></linearGradient>';
        svg.appendChild(defs);
        var area = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        area.setAttribute('d', areaD); area.setAttribute('fill', 'url(#wpsi-grad)'); svg.appendChild(area);
        var line2 = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        line2.setAttribute('d', pathD); line2.setAttribute('fill', 'none');
        line2.setAttribute('stroke', '#3182ce'); line2.setAttribute('stroke-width', '2.5');
        line2.setAttribute('stroke-linecap', 'round'); line2.setAttribute('stroke-linejoin', 'round'); svg.appendChild(line2);
        dots.forEach(function (d) {
            var c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            c.setAttribute('cx', d.x); c.setAttribute('cy', d.y); c.setAttribute('r', '4');
            c.setAttribute('fill', gradeColor(d.grade)); c.setAttribute('stroke', '#fff'); c.setAttribute('stroke-width', '2');
            var t = document.createElementNS('http://www.w3.org/2000/svg', 'title');
            t.textContent = d.date + ' — Score: ' + d.score + ' (Grade ' + d.grade + ')'; c.appendChild(t); svg.appendChild(c);
        });
        wrap.appendChild(svg);
        container.appendChild(wrap);
    }

    /* ── Category Accordion ──────────────────────────────────────── */

    function renderCheckItem(check) {
        var isFixable = (check.status !== 'pass' && check.status !== 'locked' && DATA.fixableChecks && DATA.fixableChecks.indexOf(check.id) >= 0);
        var isPremium = DATA.isPremium;

        var contentChildren = [
            el('p', { className: 'wpsi-check-title', textContent: check.title }),
            el('p', { className: 'wpsi-check-detail', textContent: check.detail }),
        ];

        // Fix suggestion text.
        if (check.fix) {
            contentChildren.push(el('div', { className: 'wpsi-check-fix' }, [
                el('span', { className: 'wpsi-check-fix-icon', textContent: '💡' }),
                el('span', { textContent: check.fix }),
            ]));
        }

        // Auto-Fix button (only for fixable checks with issues).
        if (isFixable) {
            
            {
                // Free user → locked fix button → opens upgrade.
                var lockBtn = el('button', {
                    className: 'wpsi-btn wpsi-btn-fix-locked',
                    textContent: '🔒 Auto-Fix (Pro)',
                    onClick: function () {
                        if (DATA.checkoutUrl) { window.location.href = DATA.checkoutUrl; }
                        else if (DATA.upgradeUrl) { window.location.href = DATA.upgradeUrl; }
                    },
                });
                contentChildren.push(lockBtn);
            }
        }

        return el('div', { className: 'wpsi-check wpsi-check-' + check.status }, [
            el('div', { className: 'wpsi-check-status', textContent: STATUS_ICONS[check.status] || '?' }),
            el('div', { className: 'wpsi-check-content' }, contentChildren),
        ]);
    }

    

    function renderCategories(container, categories) {
        var wrap = el('div', { className: 'wpsi-categories' });
        Object.keys(categories).forEach(function (catKey) {
            var checks = categories[catKey];
            var cm = CATEGORY_META[catKey] || { icon: '📋', label: catKey, cls: '' };
            var cs = { pass: 0, warn: 0, fail: 0 };
            checks.forEach(function (c) { if (cs.hasOwnProperty(c.status)) cs[c.status]++; });
            var card = el('div', { className: 'wpsi-category ' + cm.cls });
            var header = el('div', { className: 'wpsi-category-header' }, [
                el('div', { className: 'wpsi-category-header-left' }, [
                    el('div', { className: 'wpsi-category-icon', textContent: cm.icon }),
                    el('div', null, [
                        el('div', { className: 'wpsi-category-name', textContent: cm.label }),
                        el('div', { className: 'wpsi-category-count', textContent: checks.length + ' checks' }),
                    ]),
                ]),
                el('div', { className: 'wpsi-category-header-right' }, [
                    el('div', { className: 'wpsi-category-badges' }, [
                        cs.pass > 0 ? el('span', { className: 'wpsi-badge wpsi-badge-pass', textContent: cs.pass + ' ✓' }) : null,
                        cs.warn > 0 ? el('span', { className: 'wpsi-badge wpsi-badge-warn', textContent: cs.warn + ' !' }) : null,
                        cs.fail > 0 ? el('span', { className: 'wpsi-badge wpsi-badge-fail', textContent: cs.fail + ' ✗' }) : null,
                    ]),
                    el('span', { className: 'wpsi-chevron', textContent: '▾' }),
                ]),
            ]);
            header.addEventListener('click', function () { card.classList.toggle('is-open'); });
            var body = el('div', { className: 'wpsi-category-body' });
            checks.forEach(function (c) { body.appendChild(renderCheckItem(c)); });
            card.appendChild(header);
            card.appendChild(body);
            if (cs.fail > 0) card.classList.add('is-open');
            wrap.appendChild(card);
        });
        container.appendChild(wrap);
    }

    /* ── PRO CHECKS (locked / unlocked) ──────────────────────────── */

    function renderProChecks(container, proChecks, isUnlocked) {
        var section = el('div', { className: 'wpsi-pro-section' });
        section.appendChild(el('div', { className: 'wpsi-pro-header' }, [
            el('div', { className: 'wpsi-pro-header-left' }, [
                el('span', { textContent: '🔐', style: 'font-size:24px;' }),
                el('div', null, [
                    el('h3', { className: 'wpsi-pro-title', textContent: 'Advanced Diagnostics' }),
                    el('p', { className: 'wpsi-pro-subtitle', textContent: isUnlocked ? '8 advanced checks unlocked' : '8 advanced checks — unlock free with your email' }),
                ]),
            ]),
            isUnlocked ? el('span', { className: 'wpsi-badge wpsi-badge-pass', textContent: '✓ Unlocked', style: 'font-size:13px;padding:6px 14px;' }) : null,
        ]));

        if (isUnlocked) {
            var body = el('div', { className: 'wpsi-pro-body wpsi-pro-unlocked' });
            proChecks.forEach(function (c) { body.appendChild(renderCheckItem(c)); });
            section.appendChild(body);
        } else {
            var blurWrap = el('div', { className: 'wpsi-pro-blur-container' });
            var blurBody = el('div', { className: 'wpsi-pro-body wpsi-pro-blurred' });
            proChecks.forEach(function (c) {
                blurBody.appendChild(el('div', { className: 'wpsi-check wpsi-check-locked' }, [
                    el('div', { className: 'wpsi-check-status wpsi-check-status-locked', textContent: '🔒' }),
                    el('div', { className: 'wpsi-check-content' }, [
                        el('p', { className: 'wpsi-check-title', textContent: c.title }),
                        el('p', { className: 'wpsi-check-detail', textContent: c.detail }),
                    ]),
                ]));
            });
            blurWrap.appendChild(blurBody);
            var overlay = el('div', { className: 'wpsi-pro-overlay' });
            overlay.appendChild(el('div', { className: 'wpsi-pro-gate-card' }, [
                el('div', { className: 'wpsi-pro-gate-icon', textContent: '🔓' }),
                el('h3', { textContent: 'Unlock 8 Advanced Checks — Free' }),
                el('p', { textContent: 'Get deeper diagnostics: mixed content, image optimization, login security, backup status, and more. Enter your email to unlock instantly.' }),
                el('div', { className: 'wpsi-pro-gate-form', id: 'wpsi-gate-form' }, [
                    el('input', { type: 'text', id: 'wpsi-gate-name', placeholder: 'Your name (optional)', className: 'wpsi-gate-input' }),
                    el('input', { type: 'email', id: 'wpsi-gate-email', placeholder: 'Your email address', className: 'wpsi-gate-input', required: 'required' }),
                    el('label', { className: 'wpsi-gate-consent' }, [
                        el('input', { type: 'checkbox', id: 'wpsi-gate-consent-cb' }),
                        el('span', { textContent: ' I agree to receive WordPress tips and product updates from WPezo. Unsubscribe anytime.' }),
                    ]),
                    el('button', { className: 'wpsi-btn wpsi-btn-primary wpsi-gate-btn', id: 'wpsi-gate-submit', textContent: '🔓 Unlock Advanced Checks' }),
                    el('p', { className: 'wpsi-gate-privacy', textContent: '🔒 We respect your privacy. No spam, ever.' }),
                ]),
            ]));
            blurWrap.appendChild(overlay);
            section.appendChild(blurWrap);
            setTimeout(bindGateForm, 0);
        }
        container.appendChild(section);
    }

    function bindGateForm() {
        var btn = document.getElementById('wpsi-gate-submit');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var email = (document.getElementById('wpsi-gate-email') || {}).value || '';
            var name = (document.getElementById('wpsi-gate-name') || {}).value || '';
            var consent = (document.getElementById('wpsi-gate-consent-cb') || {}).checked;

            // Client-side email validation.
            email = email.trim().toLowerCase();
            var emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
            if (!email || !emailRegex.test(email)) { alert('Please enter a valid email address.'); return; }
            if (!consent) { alert('Please check the consent box to continue.'); return; }

            btn.disabled = true; btn.textContent = '📧 Sending confirmation...';

            fetch(REST_URL + 'unlock', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                credentials: 'same-origin',
                body: JSON.stringify({ email: email, name: name, consent: true }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.confirmed && data.pro_results) {
                    // Already confirmed — show results immediately.
                    DATA.proUnlocked = true;
                    var ps = document.querySelector('.wpsi-pro-section');
                    if (ps && ps.parentNode) {
                        var parent = ps.parentNode;
                        var next = ps.nextSibling;
                        parent.removeChild(ps);
                        var newSection = el('div');
                        if (next) parent.insertBefore(newSection, next);
                        else parent.appendChild(newSection);
                        renderProChecks(newSection.parentNode, data.pro_results, true);
                        if (newSection.parentNode) newSection.parentNode.removeChild(newSection);
                    }
                } else if (data.success && data.pending) {
                    // Confirmation email sent — show "check inbox" message.
                    var gateCard = document.querySelector('.wpsi-pro-gate-card');
                    if (gateCard) {
                        gateCard.innerHTML = '';
                        gateCard.appendChild(el('div', { className: 'wpsi-pro-gate-icon', textContent: '📬' }));
                        gateCard.appendChild(el('h3', { textContent: 'Check Your Inbox!' }));
                        gateCard.appendChild(el('p', { textContent: 'We sent a confirmation email to ' + (data.email_sent_to || email) + '. Click the link in the email to unlock your Advanced Checks.' }));
                        gateCard.appendChild(el('div', { style: 'background:#eff6ff;border-radius:8px;padding:14px 18px;margin-top:16px;text-align:left;' }, [
                            el('p', { style: 'font-size:13px;color:#1e40af;margin:0 0 6px;font-weight:600;', textContent: '💡 Tips:' }),
                            el('p', { style: 'font-size:12px;color:#4a5568;margin:0 0 4px;', textContent: '• Check your spam/junk folder if you don\'t see it' }),
                            el('p', { style: 'font-size:12px;color:#4a5568;margin:0 0 4px;', textContent: '• The email comes from no-reply@wpezo.com' }),
                            el('p', { style: 'font-size:12px;color:#4a5568;margin:0;', textContent: '• The link expires in 24 hours' }),
                        ]));
                        gateCard.appendChild(el('button', {
                            className: 'wpsi-btn wpsi-btn-secondary',
                            style: 'margin-top:16px;width:100%;justify-content:center;',
                            textContent: '🔄 Resend Confirmation Email',
                            onClick: function () { window.location.reload(); },
                        }));
                    }
                } else {
                    // Handle API errors including "already_used" (409).
                    var errMsg = data.message || (data.data && data.data.message) || 'Something went wrong. Please try again.';
                    var isAlreadyUsed = (data.code === 'already_used');

                    if (isAlreadyUsed) {
                        // Replace form with specific error UI.
                        var gateCard = document.querySelector('.wpsi-pro-gate-card');
                        if (gateCard) {
                            gateCard.innerHTML = '';
                            gateCard.appendChild(el('div', { className: 'wpsi-pro-gate-icon', textContent: '⚠️' }));
                            gateCard.appendChild(el('h3', { textContent: 'Email Already Used' }));
                            gateCard.appendChild(el('p', { textContent: 'This email has already been used to unlock Advanced Checks on another WordPress site. Each email can only be used on one site.' }));
                            gateCard.appendChild(el('p', { style: 'font-size:13px;color:#4a5568;margin-top:12px;', textContent: 'Please use a different email address to unlock on this site.' }));
                            gateCard.appendChild(el('button', {
                                className: 'wpsi-btn wpsi-btn-primary wpsi-gate-btn',
                                style: 'margin-top:16px;',
                                textContent: '🔄 Try Another Email',
                                onClick: function () { window.location.reload(); },
                            }));
                        }
                    } else {
                        alert(errMsg);
                        btn.disabled = false; btn.textContent = '🔓 Unlock Advanced Checks';
                    }
                }
            })
            .catch(function () { alert('Network error. Please try again.'); btn.disabled = false; btn.textContent = '🔓 Unlock Advanced Checks'; });
        });
    }

    /* ── Recommendations ─────────────────────────────────────────── */

    function renderRecommendations(container, results) {
        var cats = results.categories || {};
        var recs = [];
        if (countStatus(cats.performance, 'fail') + countStatus(cats.performance, 'warn') > 0) recs.push({ title: 'SpeedKit Pro', desc: 'Optimize CSS/JS delivery, lazy loading, database cleanup & Core Web Vitals in one plugin.', tag: 'Coming Soon', url: '' });
        if (countStatus(cats.security, 'fail') + countStatus(cats.security, 'warn') > 0) recs.push({ title: 'CleanKit Pro', desc: 'WordPress hardening, malware scanning & security headers — fix security issues automatically.', tag: 'Coming Soon', url: '' });
        if (countStatus(cats.accessibility, 'fail') + countStatus(cats.accessibility, 'warn') > 0) recs.push({ title: 'A11yKit Pro', desc: 'Full WCAG 2.2 accessibility toolkit — widget, audit tools, statement generator & compliance badges.', tag: 'Coming Soon', url: '' });
        if (countStatus(cats.database, 'fail') + countStatus(cats.database, 'warn') > 0) recs.push({ title: 'CleanKit Pro', desc: 'Database optimizer, revision cleaner, transient manager & autoload reducer in one package.', tag: 'Coming Soon', url: '' });
        recs.push({ title: 'WPezo YouTube', desc: 'Free video tutorials on WordPress optimization, security, development & best practices.', tag: 'Free Resource', url: 'https://www.youtube.com/@WPezo' });
        var seen = {};
        recs = recs.filter(function (r) { if (seen[r.title]) return false; seen[r.title] = true; return true; });
        var grid = el('div', { className: 'wpsi-recommendations-grid' });
        recs.forEach(function (r) {
            var t = r.url ? 'a' : 'div', a = { className: 'wpsi-rec-card' };
            if (r.url) { a.href = r.url; a.target = '_blank'; a.rel = 'noopener'; } else { a.style = 'cursor:default;'; }
            grid.appendChild(el(t, a, [
                el('div', { className: 'wpsi-rec-card-title', textContent: r.title }),
                el('div', { className: 'wpsi-rec-card-desc', textContent: r.desc }),
                el('span', { className: 'wpsi-rec-card-tag', textContent: r.tag }),
            ]));
        });
        container.appendChild(el('div', { className: 'wpsi-recommendations' }, [el('h3', { textContent: '🚀 Recommended Solutions by WPezo' }), grid]));
    }

    /* ── Export Report ────────────────────────────────────────────── */

    function exportReport(results) {
        var s = results.score || 0, g = results.grade || 'F', m = results.meta || {}, cats = results.categories || {};
        var L = [];
        L.push('═══════════════════════════════════════════════════════');
        L.push('   WPEZO SITE INSPECTOR — FULL REPORT');
        L.push('═══════════════════════════════════════════════════════');
        L.push(''); L.push('SCORE: ' + s + '/100  |  GRADE: ' + g);
        L.push('WordPress: ' + (m.wp_version || '-') + '  |  PHP: ' + (m.php_version || '-'));
        L.push('Server: ' + (m.server || '-')); L.push('Scan Date: ' + (m.scan_time || '-')); L.push('');
        Object.keys(cats).forEach(function (ck) {
            var lb = (CATEGORY_META[ck] || {}).label || ck;
            L.push('───────────────────────────────────────────');
            L.push('  ' + lb.toUpperCase());
            L.push('───────────────────────────────────────────');
            cats[ck].forEach(function (c) {
                var i = c.status === 'pass' ? '✓' : c.status === 'warn' ? '⚠' : '✗';
                L.push('[' + i + '] ' + c.title); L.push('    ' + c.detail);
                if (c.fix) L.push('    💡 ' + c.fix); L.push('');
            });
        });
        if (results.pro_checks && results.pro_unlocked) {
            L.push('───────────────────────────────────────────');
            L.push('  ADVANCED DIAGNOSTICS');
            L.push('───────────────────────────────────────────');
            results.pro_checks.forEach(function (c) {
                var i = c.status === 'pass' ? '✓' : c.status === 'warn' ? '⚠' : '✗';
                L.push('[' + i + '] ' + c.title); L.push('    ' + c.detail);
                if (c.fix) L.push('    💡 ' + c.fix); L.push('');
            });
        }
        L.push('═══════════════════════════════════════════════════════');
        L.push('Generated by WPezo Site Inspector v' + (m.plugin_ver || WPSI_VERSION));
        L.push('https://wpezo.com');
        var blob = new Blob([L.join('\n')], { type: 'text/plain;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = 'site-inspector-report-' + (new Date()).toISOString().slice(0, 10) + '.txt';
        document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
    }

    /* ── Main Render ─────────────────────────────────────────────── */

    function renderResults(container, results) {
        container.innerHTML = '';
        renderScoreCard(container, results);
        renderHistory(container, DATA.history || []);
        renderCategories(container, results.categories || {});
        renderAutoFixSection(container, results);
        var pc = results.pro_checks || [];
        if (pc.length > 0) renderProChecks(container, pc, results.pro_unlocked || DATA.proUnlocked || false);
        renderRecommendations(container, results);
        var pb = document.getElementById('wpsi-print-btn');
        var eb = document.getElementById('wpsi-export-btn');
        if (pb) pb.style.display = '';
        if (eb) eb.style.display = '';
    }

    /**
     * Render the Auto-Fix premium section.
     */
    function renderAutoFixSection(container, results) {
        // Count how many issues are auto-fixable.
        var cats = results.categories || {};
        var fixable = DATA.fixableChecks || [];
        var fixCount = 0;

        Object.keys(cats).forEach(function (ck) {
            cats[ck].forEach(function (c) {
                if (c.status !== 'pass' && fixable.indexOf(c.id) >= 0) fixCount++;
            });
        });

        if (fixCount === 0) return; // Nothing to fix.

        var isPremium = DATA.isPremium;
        var section = el('div', { className: 'wpsi-autofix-section' + (isPremium ? '' : ' wpsi-autofix-locked') });

        
        {
            // Free user — show premium upsell with blur/lock.
            var inner = el('div', { className: 'wpsi-autofix-blur-wrap' });

            // Blurred preview.
            var preview = el('div', { className: 'wpsi-autofix-preview' }, [
                el('div', { className: 'wpsi-autofix-preview-item' }, [el('span', { textContent: '🔧' }), el('span', { textContent: 'Fix File Editor' })]),
                el('div', { className: 'wpsi-autofix-preview-item' }, [el('span', { textContent: '🔧' }), el('span', { textContent: 'Clean Expired Transients' })]),
                el('div', { className: 'wpsi-autofix-preview-item' }, [el('span', { textContent: '🔧' }), el('span', { textContent: 'Optimize Database' })]),
                el('div', { className: 'wpsi-autofix-preview-item' }, [el('span', { textContent: '🔧' }), el('span', { textContent: 'Add Security Headers' })]),
                el('div', { className: 'wpsi-autofix-preview-item' }, [el('span', { textContent: '🔧' }), el('span', { textContent: 'Delete Spam Comments' })]),
                el('div', { className: 'wpsi-autofix-preview-item' }, [el('span', { textContent: '🔧' }), el('span', { textContent: 'Clean Orphaned Meta' })]),
            ]);
            inner.appendChild(preview);

            // Overlay CTA.
            var overlay = el('div', { className: 'wpsi-autofix-overlay' }, [
                el('div', { className: 'wpsi-autofix-cta' }, [
                    el('div', { textContent: '⚡', style: 'font-size:36px;margin-bottom:8px;' }),
                    el('h3', { textContent: 'One-Click Auto-Fix' }),
                    el('p', { textContent: fixCount + ' issue' + (fixCount > 1 ? 's' : '') + ' found that can be fixed automatically. Upgrade to Pro to unlock one-click fixes for security, performance, database cleanup, and more.' }),
                    el('a', {
                        className: 'wpsi-btn wpsi-btn-upgrade',
                        href: DATA.checkoutUrl || DATA.upgradeUrl || '#',
                        textContent: '⚡ Upgrade to Pro — $3.99/mo',
                    }),
                    el('p', { style: 'font-size:11px;color:#a0aec0;margin-top:10px;', textContent: 'Cancel anytime. Saves hours of manual work.' }),
                ]),
            ]);
            inner.appendChild(overlay);
            section.appendChild(inner);
        }

        container.appendChild(section);
    }

    /* ── Scan ────────────────────────────────────────────────────── */

    function runScan() {
        var btn = document.getElementById('wpsi-scan-btn');
        var container = document.getElementById('wpsi-results');
        if (!btn || !container) return;
        btn.disabled = true;
        btn.querySelector('.wpsi-btn-label').textContent = I18N.scanning || 'Scanning...';
        container.innerHTML = '';
        var progress = showProgress();
        fetch(REST_URL + 'scan', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function (data) {
            if (data.score !== undefined) {
                if (!Array.isArray(DATA.history)) DATA.history = [];
                DATA.history.push({ date: (data.meta || {}).scan_time || '', score: data.score, grade: data.grade });
            }
            DATA.results = data;
            progress.complete();
            setTimeout(function () { renderResults(container, data); }, 700);
        })
        .catch(function (e) { progress.error(); console.error('WPSI:', e); })
        .finally(function () { btn.disabled = false; btn.querySelector('.wpsi-btn-label').textContent = I18N.reScan || 'Re-Scan'; });
    }

    /* ── Init ────────────────────────────────────────────────────── */

    function init() {
        var container = document.getElementById('wpsi-results');
        var scanBtn = document.getElementById('wpsi-scan-btn');
        var printBtn = document.getElementById('wpsi-print-btn');
        var exportBtn = document.getElementById('wpsi-export-btn');
        if (!container) return;
        if (DATA.results && DATA.results.score !== undefined) {
            renderResults(container, DATA.results);
            if (scanBtn) scanBtn.querySelector('.wpsi-btn-label').textContent = I18N.reScan || 'Re-Scan';
        } else { renderEmpty(container); }
        if (scanBtn) scanBtn.addEventListener('click', runScan);
        if (printBtn) printBtn.addEventListener('click', function () {
            document.querySelectorAll('.wpsi-category').forEach(function (c) { c.classList.add('is-open'); });
            window.print();
        });
        if (exportBtn) exportBtn.addEventListener('click', function () { if (DATA.results) exportReport(DATA.results); });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
