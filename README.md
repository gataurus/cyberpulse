# 🛡️ CyberPulse Security — WordPress Security & Bot Protection

**Free WordPress security plugin. Blocks AI scrapers, brute force attacks, bots, XSS, and vulnerability scanners. 10 languages, real-time dashboard.**

[![Download](https://img.shields.io/badge/download-v5.8.9.8-blue.svg)](https://github.com/gataurus/cyberpulse/releases/latest)
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

### 5.8.9.8 — Security & Performance Update (2026-08-18)

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

### 5.8.9.7 — Performance & Security Update (2026-08-09)

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

### 5.8.9 — Internationalization Update (2026-08-01)
- 🌍 Added translation files for 10 languages
- 🔗 Updated PRO website link
- 🛠️ Fixed SQL compatibility with MariaDB

### 5.8.6 — Initial Release (2026-07-15)
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

**[View PRO plans & pricing](https://cyberpulse-security.com/#pricing)** — from 990₽/month.

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
