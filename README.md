# 🛡️ CyberPulse Security — WordPress Security & Bot Protection

**Free WordPress security plugin. Blocks AI scrapers, brute force attacks, bots, XSS, and vulnerability scanners. 10 languages, real-time dashboard.**

[![Download](https://img.shields.io/badge/download-v5.9.1.6-blue.svg)](https://github.com/gataurus/cyberpulse/releases/latest)
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
- 🕵️ **Multi-Layer Bot Detection** — 20+ independent checks, including outdated browsers (Chrome < 90, Firefox < 90) and Mac+Chrome antidetect detection
- 🔐 **Brute Force Protection** — automatic permanent /24 subnet ban
- 🔒 **Force HTTPS** — automatic 301 redirect with proxy-loop protection
- ⏱️ **Smart Rate Limiting** — 100 req / 10 min absolute flood threshold; datacenter addresses limited to 30 req / 10 min
- 🚀 **Lightweight 403 Blocks** — HEAD requests, empty User-Agents and CLI scanners get a plain 403 under 1 KB instead of the full branded block page
- ⚡ **Faster Edge Sync** — new or lifted temporary blocks schedule a sync within 30–60 seconds instead of waiting for the 5-minute maintenance run
- 🛡️ **Custom Static 403 Page** — beautifully styled block page for server-level blocks
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

### 5.9.1.6 — Faster Sync, Lightweight Blocks & Stricter Rate Limits

* ⚡ **Faster sync:** New or lifted temporary blocks mark the edge list as stale and schedule a sync within 30–60 seconds instead of waiting for the five-minute maintenance run.
* 🚀 **Lightweight blocks:** HEAD requests, empty User-Agents and command-line scanners (`curl`, `wget`, `python-requests`, `sqlmap`, `nmap`, etc.) receive a plain `403 Forbidden` under 1 KB instead of the full branded block page.
* ⚡ **Rate limits:** The absolute flood threshold is now **100 requests / 10 minutes**, and datacenter addresses are limited to **30 / 10 minutes**. Verified search engines, whitelisted addresses, administrators and the server itself stay exempt.

### 5.9.1.5 — Security Hardening & Performance

* 🔒 **Security:** Fake bots can no longer slip through. A crawler name in the User-Agent is no longer a free pass — known crawlers must come from their own domains and network (ASN), and spoofed search engines are rejected.
* 🔒 **Security:** Login page, XML-RPC, admin pages, and the REST API are now checked early. Previously the main protection layer never saw these requests.
* 🛡️ **Data safety:** Background code can no longer wipe permanent bans. Any automatic cleanup that would remove more than 25 records is refused and written to the audit log. Only an administrator can remove bans in bulk.
* ⚡ **Speed:** Obvious bots no longer pay for a full WordPress load — cheap checks run first.
* ⚡ **Speed:** Rate limits, flood counters, and log deduplication moved to a fast cache layer, so a busy site stops writing to the database on every page view.
* ⚡ **Speed:** Heavy admin files load only where they are needed, and dashboard counters are cached.
* 🎨 **Interface:** Unban and delete actions work without reloading the page — the row disappears, scroll position is kept, and results are announced politely instead of blocking alerts. Everything still works without JavaScript.
* 🎨 **Interface:** The Permaban tile updates instantly after an unban and shows a real breakdown of single addresses and ranges.
* ⚡ **Rate limiting:** The effective default is **20 requests per minute**. Builds that stored the old 40 value are migrated to 20, because the duplicate check that used to halve it has been removed.

### 5.9.1.4 — Major UI Overhaul & Security Fixes

* 🎨 **Major UI Overhaul:** Completely redesigned plugin interface to match the premium "CyberPulse" branding. Enjoy a refreshed dark theme and a fully reworked light theme ("CyberPulse Daylight") with a refined color palette, polished grid backgrounds, smoother transitions, and improved overall visual stability.
* 🔒 **Security:** Prevented spoofed search engine User-Agents (e.g., fake Googlebot) from accessing sensitive files like `.env`, `config.yml`, or `wp-config.php.bak`. Hidden file pattern checks now run before the search bot whitelist.
* 🎨 **Visual Log Analysis:** Tracked page logs are now highly informative. IPs are highlighted in yellow, URLs in green, and Referers in blue for instant visual parsing.
* 🤖 **Bot Detection:** Added 9 Joomla/Drupal scanner signatures (`/administrator/components/com_*`, `/plugins/editors/*`, `/sites/default/*`, `/templates/system/*`, `/modules/mod_*`, etc.) to the hidden-file scanner. Joomla-targeting bots that hit 404s on WordPress sites are now blocked at first request instead of being counted toward the 404 threshold.
* ⚡ **Reliability:** Geo-location lookups now use a 5-second timeout (was 2s), so the ip-api.com service can respond under load without being incorrectly marked as down.
* 🐛 **Fixed:** Legit bot cache is now keyed by IP + User-Agent instead of just IP. This prevents a malicious scanner from inheriting whitelist status if it uses the same IP as a legitimate crawler.
* 🐛 **Fixed:** Flood attack auto-permaban now reliably triggers even if the repeat offender counter fails to increment due to DB race conditions. IPs exceeding the rate limit by 5+ requests are now instantly escalated to a permanent subnet ban.

### 5.9.1.3 — Rate Limiting, Static 403 Page & Force HTTPS

* ⚡ **Performance:** Lowered the default Rate Limiting threshold from 60 to 40 requests per minute. Added a smart collapsible guide in the UI to help users choose the right value (with CGNAT and mobile carrier warnings).
* 🛡️ **Custom Static 403 Page:** When `.htaccess` protection blocks a bot, it now displays a beautifully styled `cyberpulse-403.html` page instead of the hosting provider's default error page.
* 🔧 **Better UX:** Replaced the "Back to Home" button with a "Refresh Page" button on the static block page, which is more useful for temporary blocks.
* 🔒 **New Free Feature:** Force HTTPS 301 redirect with proxy-loop protection, full-width UI, and translations for all 10 supported languages.
* 🤖 **Bot Detection:** Added outdated browser protection. Chrome < 90, Firefox < 90, and Safari < 14 are now flagged as bot signatures in the anomaly check (with exemptions for mobile devices and legitimate forks).
* 🛡️ **SEO Crawler Toggle:** Added a new opt-in option in `.htaccess` settings to optionally block SEO crawlers (Ahrefs, Semrush, Moz) at the server level.
* 🔒 **Security:** Added an early bypass for critical SEO and monetization files (`ads.txt`, `app-ads.txt`, `robots.txt`, `favicon.ico`, `sellers.json`, `security.txt`, and `/.well-known/`). Prevents AdSense/Let's Encrypt false positives. Fixed a bypass vulnerability in the URI check.

### 5.9.1.2 — Configurable AI Bot Protection & Hardened `.htaccess`

* 🤖 **AI Bot Protection is Now Configurable.** Added two independent toggles so you control exactly what gets blocked.
  * 🧠 **AI Training Bots (default ON)** — Blocks bots that only download your content to train AI models (GPTBot, CCBot, Google-Extended, ClaudeBot, Bytespider, Amazonbot, etc.).
  * 🔍 **AI Search Bots (default OFF)** — Blocks bots that index your site for AI search engines (OAI-SearchBot, ChatGPT-User, PerplexityBot, Amzn-SearchBot, etc.). Enable this if you want the old strict behavior.
* 🆕 **Expanded Signature Database** — Added newly discovered AI bots to the detection map: `DuckAssistBot` (DuckDuckGo), `Amzn-User` (Amazon), `Grok-DeepSearch` (xAI), `QwenBot` (Alibaba), `DeepSeekBot`, `xAI-Crawler`, and `cohere-training-data-crawler`.
* 🛡️ **Hardened `.htaccess` Protection:**
  * **Regex Fix:** Fixed a critical bug where a space in the `IAS Crawler` signature could crash Apache (500 Error). Spaces are now properly converted to `\s+`.
  * **Config Protection:** Added rules to block direct access to `.htaccess`, `.htpasswd`, and `.user.ini` files, including their backups.
  * **wp-config Backups:** Improved the regex to reliably block `wp-config.php.bak`, `wp-config.php.zip`, etc.
  * **Server-Aware UI:** The `.htaccess` settings panel now detects nginx or read-only setups and disables the checkbox if the rules cannot be applied.

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
| Force HTTPS | ✅ | ✅ |
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
| CSP Headers | ❌ | ✅ |
| Block SEO Crawlers (.htaccess) | ❌ | ✅ |
| Block CLI & HTTP Clients (.htaccess) | ❌ | ✅ |

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
