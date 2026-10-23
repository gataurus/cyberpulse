# 🛡️ CyberPulse Security — WordPress Security & Bot Protection

**Free WordPress security plugin. Blocks AI scrapers, brute force attacks, bots, XSS, and vulnerability scanners. 10 languages, real-time dashboard.**

[![Download](https://img.shields.io/badge/download-v5.9.1.1-blue.svg)](https://github.com/gataurus/cyberpulse/releases/latest)
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
- 🛡️ **Server-level `.htaccess` Protection** — optional (opt-in) blocking of sensitive files and AI bots directly at the Apache/LiteSpeed level
- 🕵️ **Multi-Layer Bot Detection** — 20+ independent checks, including Mac+Chrome antidetect browser detection
- 🔐 **Brute Force Protection** — automatic permanent /24 subnet ban
- 📊 **Real-Time Dashboard** — hourly attack histogram, security score
- 🌍 **10 Interface Languages** — auto-detection (RU, EN, DE, FR, IT, ES, PT, ZH, JA, KO)
- 🎨 **Dark & Light Theme** — CyberPulse Daylight for daytime work
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

### 5.9.1.1 — Security & Reliability Hotfix

* 🔒 **Security:** User-Agent validation rewritten — hardcoded Chrome version threshold replaced with behavioural verification (Sec-Fetch-*, Client Hints, Accept-Language). 
* 🤖 **Bot Detection:** Added WebView whitelist (Instagram, Facebook, TikTok, Telegram, Snapchat, Pinterest, LinkedIn, WeChat, LINE, KakaoTalk, Viber, Twitter) to prevent false positives on mobile in-app browsers.
* ⚡ **Reliability:** Temporary blocks now survive `alloptions` cache invalidation — fixes rare cases where a blocked IP was allowed again within seconds.

### 5.9.1.0 — Security Hardening & Performance

* 🔒 **Security:** AI scraper check now runs first, before the human-test window. Bots that pass the captcha once are still checked against AI signatures on every subsequent request. Admin IPs remain exempt.
* 🔒 **Security:** Request flood protection now issues temporary blocks for heuristic evidence (traffic spike, datacenter IP) and reserves permanent subnet bans for repeat offenders only — reduces false positives without weakening the protection.
* 🔒 **Reliability:** Server IP is now re-validated every 24 hours instead of being cached indefinitely. A hosting migration or proxy change is picked up automatically, and private/reserved addresses from reverse-proxy setups are rejected at every step.
* ⚡ **Performance:** Radically optimized admin panel loading by eliminating blocking GeoIP requests, implementing smart log reading (200KB) with a 1000-row display limit to prevent UI freezing (full files are still processed during download/copy).
* ⚡ **Performance:** Log writes are now **~50× faster** on busy sites, with **~5× lower memory** use during rotation. Leftover `.log.1` files from older versions are cleaned up automatically.

### 5.9.0.9 — Opt-in .htaccess, AI Bot Detection & UI Improvements

* 🔒 **Security & Feature:** `.htaccess` protection is now **opt-in** (disabled by default) and managed via a new "AI Scraping Protection" settings block. The ruleset was also extended to block `.git`, `.env`, `.sql` dumps, and other sensitive files before PHP loads.
* 🤖 **Bot Detection:** Unified and expanded detection logic — added 12 new AI bot signatures (Claude-User, xAI-Grok, etc.) and implemented Mac+Chrome antidetect checks to catch residential proxy bot farms. Also blocked fake GPTBot/ClaudeBot impersonation and scrapers masquerading as mobile apps (TikTokSpider, InstagramScraper).
* 🎨 **UI:** Brute Force panel now displays the full history of login attempts (active and inactive) with clear limit indicators.
* ⚙️ **Compatibility:** Removed SEO crawlers (AhrefsBot, SemrushBot, Moz, DataForSeoBot, etc.) from the `.htaccess` block. Paid SEO tools will no longer be blocked at the server level.
* 🌍 **Translations:** Fully synchronized and updated localization for all 10 supported languages.

<details>
<summary>📜 <b>Click to view older releases (5.9.0.8 and below)</b></summary>

### 5.9.0.8 — Dashboard Performance & UI Overhaul

* 🚀 **Major Performance Boost:** Complete dashboard overhaul — log files are now read only once, and CSS rendering optimizations allow you to smoothly view thousands of log rows without any lag.
* ⚡ **Backend Optimization:** Restored object caching for threat levels, top offenders, and UA statistics. Rate limiting is now working properly, and background tasks are safely throttled.
* 🎨 **Interactive Dashboard:** All cards (KPI and Pulse) are now clickable with smooth-scroll navigation to relevant sections. Added informative tooltips and replaced confusing percentages with clear numerical differences.

### 5.9.0.7 — Search Engine Protection & UI Redesign

**🔒 Security**
- Search engine protection: Google, Yandex, Bing, Baidu, Apple, Meta, Telegram, Twitter, and Cloudflare subnets bypass permaban
- Automated cleanup of search engine subnets from permanent block list
- Instant-pass whitelist for verified search bots
- Fake-UA guard: search bots with `Chrome/<digits>` in User-Agent fall back to regular checks
- Subnet size guard: permaban skips subnets wider than /16 (IPv4) or /32 (IPv6)

**⚡ Performance**
- Rate limiting and 404 detection skip verified search engine bots
- Merged whitelist checks into a single compact sanity check

**✅ Added**
- `cybersec_is_search_engine_subnet()` — centralized subnet check for Google, Bing, Yandex, Meta, Apple, Telegram, Twitter, Cloudflare
- `cybersec_purge_search_engine_subnets_from_permaban()` — automated cleanup of permaban

**🎨 UI**
- Complete UI redesign — cleaner, more professional interface across all admin tabs
- New light theme "CyberPulse Daylight" — warm green palette, brand grid pattern, saturated accent, chunky shadows

---

### 5.9.0.6 — Security Hardening & Bot Detection

**🔒 Security**
- Fixed brute force protection bypass via `_wpnonce` parameter
- Fixed privilege escalation via whitelist `note` field — admin IPs now use explicit `auto_type` marker
- Extended blockable file patterns — 30+ new patterns
- Added malformed Chrome UA detection
- Old Chrome (<70) and Firefox (<60) versions now blocked unless genuine legacy browsers

**🐛 Fixed**
- XSS check on AJAX human verification now reads raw request data
- Chrome parsers with fixed old builds now blocked
- 404 detection false positives — Googlebot/YandexBot no longer get permabanned for checking broken links
- Referer check order — cookie verification now happens before rate-limiting

**✅ Added**
- Extended bot UA blacklist (CVE-validators, unknown crawlers, NetAPI)
- Extended AI bots list — AionBot, BeansLLM, SEOJuice, IAS Crawler, KlavdiaBot, GrokBot, xAI-Grok, MistralAI
- Extended known bot subnets — Yandex.Cloud, Google/Bing IPv6, Facebook IPv6, Cloudflare IPv6, Apple IPv6
- RSS readers whitelisted — Feedly, Inoreader, NewsBlur, Feedbin, Feedspot, Uptime Kuma

**⚡ Performance**
- Cached `cybersec_check_ai_scraper()` result within single request
- Cached `cybersec_get_daily_stats()` — dashboard ~10x faster
- Cached `cybersec_get_permanent_blocked_subnets()` with proper invalidation
- Cached `cybersec_get_custom_whitelist()` — single query per request
- Centralized hostname lookup — DNS query runs once per 12h

**🧹 Cleanup**
- Activated `cybersec_sync_page_logs_stats()` — page counters now auto-refresh every 5 min
- Protected service logs — `audit.log`, `unblocks.log`, `integrity.log` no longer auto-deleted
- Extended transient cleanup — handles all plugin patterns

---

### 5.9.0.5 — Extended Bot Whitelist

**✅ Added**
- Extended whitelist for search engines and monitoring services
- Extended bot subnet list (Yandex, Bing, Apple, DuckDuckGo)
- Extended SQL injection patterns and audit logging

**🔒 Security**
- Improved bot detection integration
- Enhanced PRO security integration

---

### 5.9.0.4 — 403 Block Page Redesign & Plugin Check Pass

**🐛 Fixed**
- 403 block page styles not loading — CSS/JS are now inlined directly into the block page HTML.

**🎨 Added**
- Pulsing glow animation on the 403 page shield icon.
- Firmware shield-pulse icon in the 403 page header and footer.

---

### 5.9.0.3 — Permaban Hardening & Log Deduplication

**🔒 Security**
- Added permaban check to all blocking functions (`hide_wordpress`, `hide_plugins_themes`, `rate_limiting`, `404_detection`, `url_firewall`)
- Added permaban check to `cybersec_check_all` before all blocking checks
- Fixed server IP retrieval (`cybersec_get_server_ip`)

**⚡ Improvements**
- Static file exclusion in `hide_wordpress`
- Added deduplication window in `cybersec_log_blocked_bot`

---

### 5.9.0.2 — Performance & Security

**⚡ Performance**
- `cybersec_is_real_browser()` — reduced from 120+ `stripos()` calls to 3-4 `preg_match()` operations (95% faster)
- `cybersec_is_legitimate_bot()` — reduced from 60+ operations to 5-10 (85% faster)

**🚀 New Features**
- PTR (reverse DNS) verification for legitimate bots (Googlebot, Bingbot, Yandex)
- Tooltips for statistics blocks with translations in all 10 languages

**🔒 Security**
- Permanent block for system file access attempts (`wp-config.php`, `.env`, `.git`)

---

### 5.9.0.1 — Hotfix Release

**🔧 Fixed**
- Counter mismatch between statistics and log files
- Statistics cache causing stale data
- Tracked pages showing nested subpages
- Internal/private IPs shown in logs

**⚡ Improvements**
- Direct file counting for accurate real-time statistics
- Log deduplication with 60-second window

---

### 5.9.0.0 — Stats Accuracy & Dashboard

**🔧 Fixed**
- Counter mismatch between statistics and log files
- Stale statistics cache
- Internal/private IPs shown in logs (10.x.x.x, 172.16.x.x, 192.168.x.x, 100.64.x.x)

**⚡ Improvements**
- Dashboard order: Security Score → Allowed → Blocked → Page Tracking → Event Log
- Added `cybersec_is_internal_ip()` to detect and hide private IP ranges
- Added `cybersec_cleanup_duplicate_logs()` for log deduplication

---

### 5.8.9.9 — Security Hardening

**🔒 Critical Security Fixes**
- Fixed XSS vulnerabilities in 403 block page
- Fixed SQL injection vulnerability in stale transients cleanup query
- Added `current_user_can('manage_options')` check to `cybersec_handle_simple_check()`
- Added IP validation in `cybersec_ajax_human_verify()` AJAX handler

**⚡ Performance**
- Throttling for `cybersec_cleanup_expired_temp_blocks()`
- Fixed memory issue in `cybersec_fs_put_contents()` with large log files

---

### 5.8.9.8 — Security & Performance

**🔒 Security**
- XSS protection with input sanitization across all user inputs
- Nonce verification in AJAX human-verify handler
- Directory traversal protection in log functions
- Full data cleanup on IP unblock

**🚀 Performance**
- `wp_cache` support for settings, whitelist, and permanent blocks
- Reduced database queries with caching

---

### 5.8.9.7 — Performance & Security

**⚡ Performance**
- Object Cache support for 15+ core functions
- Added caching for geo-location lookups
- Improved log rotation with safe truncation

**🛡️ Security Enhancements**
- Removed browser header (Sec-Fetch-*) bypass
- XSS validation now applies to ALL users

**🤖 Bot Detection**
- Expanded real browser detection with 50+ User-Agent patterns

---

### 5.8.9 — Internationalization Update

- 🌍 Added translation files for 10 languages
- 🔗 Updated PRO website link
- 🛠️ Fixed SQL compatibility with MariaDB

---

### 5.8.6 — Initial Release

- 🚀 Initial release with AI scraping protection, bot detection, brute force defense, and more

</details>

---

## 🔓 Upgrade to PRO

| Feature | 🆓 Free | 🛡️ PRO |
|---------|---------|---------|
| Bot Detection | ✅ | ✅ |
| Brute Force Protection | ✅ | ✅ |
| AI Scraping Protection | ✅ | ✅ |
| `.htaccess` Server Protection | ✅ | ✅ |
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
