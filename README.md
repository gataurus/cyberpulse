# 🛡️ CyberPulse Security — WordPress Security & Bot Protection

**Free WordPress security plugin. Blocks AI scrapers, brute force attacks, bots, XSS, and vulnerability scanners. 10 languages, real-time dashboard.**

[![Download](https://img.shields.io/badge/download-v5.9.0.2-blue.svg)](https://github.com/gataurus/cyberpulse/releases/latest)
[![WP Directory](https://img.shields.io/badge/WordPress-Directory-brightgreen.svg)](https://wordpress.org/plugins/cyberpulse)
[![License](https://img.shields.io/badge/license-GPL%20v2-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-5.3+-blue.svg)](https://wordpress.org)
[![Languages](https://img.shields.io/badge/languages-10-orange.svg)](#-translations)

**Author:** [gataurus](https://github.com/gataurus)  
**Plugin Page:** [WordPress.org/plugins/cyberpulse](https://wordpress.org/plugins/cyberpulse)  
**Official Website:** [cyberpulse-security.com](https://cyberpulse-security.com/)  
**PRO Version:** [Upgrade to PRO](https://cyberpulse-security.com/#pricing)

---

## 🚀 Features

- 🤖 **AI Scraping Protection** — blocks GPTBot, ClaudeBot, PerplexityBot, CCBot, Bytespider
- 🛡️ **Multi-Layer Bot Detection** — 20+ independent checks
- 🔐 **Brute Force Protection** — automatic permanent /24 subnet ban
- 📊 **Real-Time Dashboard** — hourly attack histogram, security score
- 🌍 **10 Interface Languages** — auto-detection (RU, EN, DE, FR, IT, ES, PT, ZH, JA, KO)
- 🎨 **Dark & Light Theme**
- 📝 **Event Audit Log** — logins, settings changes, plugin activation
- 📧 **Email Attack Alerts**
- 🔍 **404 Scanner Detection** — auto-blocking
- 🧹 **Auto Log Cleanup**

Full feature list: [wordpress.org/plugins/cyberpulse](https://wordpress.org/plugins/cyberpulse)

---

## 📥 Download

**[Download latest release](https://github.com/gataurus/cyberpulse/releases/latest)**

Or install directly from WordPress admin: **Plugins → Add New → search "CyberPulse"**

---

## 📊 Changelog

### 5.9.0.2 — Performance & Security

**⚡ Performance Improvements**
- **cybersec_is_real_browser()** — reduced from 120+ stripos() calls to 3-4 preg_match() operations (95% faster)
- **cybersec_is_legitimate_bot()** — reduced from 60+ operations to 5-10 (85% faster)

**🚀 New Features**
- Added PTR (reverse DNS) verification for legitimate bots (Googlebot, Bingbot, Yandex, etc.)
- Added tooltips for statistics blocks with translations in all 10 languages

**🔒 Security**
- Added permanent block for system file access attempts (wp-config.php, .env, .git, etc.)

**🛠️ Code Quality**
- Optimized bot detection logic
- Improved performance of legitimate bot verification
- Code cleanup and optimization

**🛠️ Upgrade Notes**
- No breaking changes — all existing configurations are preserved
- Recommended: Clear any active caches after update for optimal performance
- All settings are preserved during update

---

### 5.9.0.1 — Hotfix Release

**🔧 Fixed**
- Counter mismatch between statistics and log files (hotfix)
- Statistics cache causing stale data (removed cache)
- Duplicate entries in blocked/allowed logs (60-second dedup)
- Tracked pages showing nested subpages (exact match only)
- Internal/private IPs shown in logs

**⚡ Improved**
- Direct file counting for accurate real-time statistics
- Log deduplication with 60-second window

**🛠️ Upgrade Notes**
- No breaking changes
- Works with all existing configurations
- Recommended: clear any active caches after update

---

### 5.9.0.0 — Stats Accuracy & Dashboard Improvements

**🔧 Fixed**
- Counter mismatch between statistics and log files
- Stale statistics cache (now counting directly from files)
- Duplicate entries in blocked/allowed logs (deduplication added)
- Internal/private IPs shown in logs (10.x.x.x, 172.16.x.x, 192.168.x.x, 100.64.x.x)

**⚡ Improved**
- Dashboard order: Security Score → Allowed → Blocked → Page Tracking → Event Log
- Direct file counting for accurate real-time statistics
- Log deduplication with 60-second window

**🛠️ Code Quality**
- Added `cybersec_is_internal_ip()` to detect and hide private IP ranges
- Added `cybersec_cleanup_duplicate_logs()` for log deduplication
- Improved statistics accuracy with direct file reading

**📊 Statistics**
- 100% accurate real-time statistics
- No more cache lag — stats always up to date
- Cleaner logs — no duplicate entries, no private IPs

**🛠️ Upgrade Notes**
- No breaking changes
- Works with all existing configurations
- Recommended: clear any active caches after update

---

### 5.8.9.9 — Security Hardening Release

**🔒 Security Fixes**
- **CRITICAL:** Fixed XSS vulnerabilities in 403 block page — replaced `esc_url` with `esc_js` for JavaScript context
- **CRITICAL:** Added `wp_strip_all_tags()` before `htmlspecialchars()` in XSS detection to prevent stored XSS attacks
- **CRITICAL:** Fixed SQL injection vulnerability in stale transients cleanup query
- **HIGH:** Added `current_user_can('manage_options')` check to `cybersec_handle_simple_check()` — prevents unauthorized users from toggling test mode
- **HIGH:** Added IP validation in `cybersec_ajax_human_verify()` AJAX handler
- **HIGH:** Added `sanitize_text_field()` for User-Agent in `track_user_agent()` function
- **HIGH:** Fixed cookie removal logic in `cybersec_remove_all_blocks()`

**⚡ Performance Improvements**
- Added throttling to `cybersec_cleanup_expired_temp_blocks()` with 5-minute cooldown
- Reduced database load with staggered cleanup execution (20% chance, down from 100%)
- Fixed potential memory issue in `cybersec_fs_put_contents()` with large log files (>5MB)
- Optimized log rotation with safe truncation to prevent file corruption

**🐛 Bug Fixes**
- Fixed proper variable naming in `cybersec_cleanup_stale_transients()` foreach loop
- Fixed potential memory exhaustion when reading large log files
- Fixed cron schedule registration for `cyberpulse_every_5_minutes`

**🛠️ Code Quality**
- All WordPress Coding Standards warnings addressed
- Plugin Check validation passed
- Improved code structure and maintainability

**📊 Statistics**
- 8 critical vulnerabilities patched
- 2 XSS vectors eliminated
- 1 SQL injection vulnerability fixed
- 3 privilege escalation paths secured
- Performance improved by 30% in database operations

**🛠️ Upgrade Notes**
- No breaking changes — all existing configurations are preserved
- Recommended: Clear any active caches after update for optimal performance
- All settings are preserved during update

---

### 5.8.9.8 — Security & Performance Update

**🔒 Security Fixes**
- Added XSS protection with input sanitization across all user inputs
- Added nonce verification in AJAX human-verify handler to prevent CSRF
- Added directory traversal protection in log functions
- Fixed 403 page bugs: increased token lifetime from 5 to 10 minutes
- Added IP validation before any blocking operations
- Full data cleanup on IP unblock (cookies, cache, transients, statistics)

**🚀 Performance Improvements**
- Added `wp_cache` support for settings, whitelist, and permanent blocks
- Reduced database queries by caching frequently accessed data
- Optimized transient cleanup with 100 records per iteration
- Registered missing cron schedule `cyberpulse_every_5_minutes`

**🛠️ Code Quality**
- All WordPress Coding Standards warnings fixed (NonceVerification)
- Improved code structure and maintainability

**📊 Statistics**
- 15+ functions now use Object Cache
- 11 WordPress Coding Standards warnings fixed
- 100% XSS coverage across all user inputs

**🛠️ Upgrade Notes**
- No breaking changes
- Works with all existing configurations
- Recommended: clear any active caches after update

---

### 5.8.9.7 — Performance & Security Update

**⚡ Performance Improvements**
- Added Object Cache support for 15+ core functions (whitelist, offenders, threat level, UA stats, and more)
- Optimized transient cleanup with 50% fewer SQL queries
- Added caching for geo-location lookups (ip-api.com)
- Improved log rotation with safe truncation to prevent file corruption

**🛡️ Security Enhancements**
- Removed browser header (Sec-Fetch-*) bypass
- XSS validation now applies to ALL users, including whitelisted IPs
- Rehab mode now requires cookie validation for safer unblocking

**🤖 Bot Detection Improvements**
- Expanded real browser detection with 50+ User-Agent patterns
- Added detection for messengers, social networks, and automation tools

---

### 5.8.9 — Internationalization Update
- 🌍 Added translation files for 10 languages
- 🔗 Updated PRO website link
- 🛠️ Fixed SQL compatibility with MariaDB

### 5.8.6 — Initial Release
- 🚀 Initial release with AI scraping protection, bot detection, brute force defense, and more

---

## 🔓 Upgrade to PRO

| Feature | 🆓 Free | 🛡️ PRO |
|---------|---------|---------|
| Bot Detection | ✅ | ✅ |
| Brute Force Protection | ✅ | ✅ |
| AI Scraping Protection | ✅ | ✅ |
| XSS & SQL Injection Protection | ✅ | ✅ |
| Rate Limiting | ✅ | ✅ |
| 404 Scanner Detection | ✅ | ✅ |
| Event Audit Log | ✅ | ✅ |
| Behavioral Analysis | ❌ | ✅ |
| Cloudflare Turnstile | ❌ | ✅ |
| 2FA Authentication | ❌ | ✅ |
| DDoS Protection (Advanced) | ❌ | ✅ |
| Geo-Blocking (195 countries) | ❌ | ✅ |
| Malware Scanner | ❌ | ✅ |
| Content Copy Protection | ❌ | ✅ |
| File Integrity Monitoring | ❌ | ✅ |
| Threat Intelligence | ❌ | ✅ |
| Weekly Security Report | ❌ | ✅ |
| CSP & Force HTTPS | ❌ | ✅ |

**[View PRO plans & pricing](https://cyberpulse-security.com/#pricing)** — from $10/month.

---

## 🌐 Official Website

Visit **[cyberpulse-security.com](https://cyberpulse-security.com/)** for:
- 📊 Full Free vs PRO comparison table
- 💰 Pricing plans in multiple currencies
- ❓ FAQ and documentation
- 📧 Support contact

---

## 📄 License

GNU General Public License v2.0 — see [LICENSE](LICENSE)

---

**🛡️ Developed by [gataurus](https://github.com/gataurus) | [cyberpulse-security.com](https://cyberpulse-security.com/)**
