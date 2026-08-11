=== CyberPulse — Advanced Security & Bot Protection ===
Contributors: gataurus
Tags: security, firewall, bot-protection, brute-force, anti-spam
Requires at least: 5.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 5.8.9.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Real-time security firewall. Blocks bots, brute force attacks, XSS, and AI scrapers. 10 languages, lightweight, no ads.

== Description ==

CyberPulse is a powerful security firewall that protects your website 24/7.

Every request is analyzed in real time. Bots, scanners, and attackers are blocked instantly — before they reach your site. The plugin detects suspicious behavior, blocks AI data collectors like GPTBot and ClaudeBot, and stops brute force attacks with automatic IP bans.

**Why Choose CyberPulse:**

* **Real-Time Protection** — monitors every request and blocks threats instantly
* **Lightweight & Fast** — no database load, logs stored in files with automatic cleanup
* **10 Languages** — Russian, English, German, French, Italian, Spanish, Portuguese, Chinese, Japanese, Korean
* **Easy to Use** — install and protect your site in under a minute
* **No Ads** — clean interface, no upsells in your admin panel
* **Works with Caching** — compatible with WP Rocket, W3 Total Cache, LiteSpeed Cache, and CDN services

**Protection Features:**

* AI Scraping Protection — blocks GPTBot, ClaudeBot, PerplexityBot, CCBot, and other AI data collectors
* Multi-layer bot detection (11 independent checks: User-Agent, HTTP headers, Referer, Accept-Language, and more)
* Brute force protection — automatic permanent /24 subnet ban after exceeding attempt limit
* WordPress system files hiding — blocks access to wp-config.php, .env, .git, and other sensitive files
* Rate Limiting — configurable request limits to prevent DDoS and aggressive scraping
* IP Whitelist — manual and automatic (server IP, search engines, administrator IPs)
* XSS attack protection — blocks cross-site scripting in requests
* 404 scanner detection — blocks vulnerability scanners by excessive 404 errors
* URL Firewall — manual path blocking for specific endpoints
* User-Agent blacklist with statistics — block specific bots by User-Agent
* Referer verification — blocks suspicious requests without proper Referer header
* Security score dashboard — hourly attack histogram, fix recommendations
* Email alerts on attack spikes
* Event audit log — track logins, settings changes, and plugin activity
* Dark & Light theme for admin panel

== Installation ==

1. Go to **Plugins → Add New** in your WordPress admin
2. Search for "CyberPulse"
3. Click **Install Now** and then **Activate**
4. Navigate to **CyberPulse** in the admin menu
5. Your site is protected — default settings provide optimal security

== Frequently Asked Questions ==

= How does bot detection work? =

CyberPulse analyzes 11 parameters of every request: User-Agent, HTTP headers, Accept-Language, Referer, browser headers (Sec-Fetch-*), and more. Suspicious requests are blocked automatically with temporary or permanent IP bans.

= Does it slow down my site? =

No. CyberPulse is designed to be lightweight. All checks use efficient caching, and logs are stored in files — not in the database. It works smoothly with high-traffic sites.

= Is it compatible with caching and CDN services? =

Yes. CyberPulse works with WP Rocket, W3 Total Cache, LiteSpeed Cache, Cloudflare, and other CDN and caching solutions. Real visitor IPs are correctly detected via proxy headers.

= Will it block search engines? =

No. Google, Bing, Yandex, Baidu, DuckDuckGo, and other legitimate search bots are automatically detected and whitelisted by IP subnet and User-Agent signature.

= Can I whitelist my IP or specific services? =

Yes. You can whitelist IPs, subnets (CIDR), hostnames, User-Agent patterns, and specific URL paths. Administrators are automatically whitelisted on login.

= How does brute force protection work? =

When failed login attempts exceed the limit (default: 5 attempts in 15 minutes), the entire /24 subnet is permanently blocked. No temporary bans — attackers go straight to permanent block.

= Does it protect against AI scraping? =

Yes. CyberPulse blocks known AI training bots: GPTBot (OpenAI), ClaudeBot (Anthropic), PerplexityBot, CCBot (Common Crawl), and others. AI scrapers are detected by User-Agent and IP signatures.

= How is this different from other security plugins? =

CyberPulse offers AI scraping protection, 10 languages out of the box, a real-time dashboard with hourly attack histogram, and a clean interface with no ads or upsells. It's lightweight and stores logs in files — not in your database.

= Is there a PRO version? =

Yes. PRO adds 2FA authentication, geo-blocking (195 countries), DDoS protection, malware scanner, content copy protection, file integrity monitoring, and more. [Learn more](https://cyberpulse-security.com/)

== Screenshots ==

1. Main dashboard with real-time statistics and hourly histogram
2. Security score assessment with fix recommendations
3. Blocked bots log with detailed analysis
4. Page tracking and traffic analytics
5. Whitelist management panel
6. Security settings with 11 bot detection options

== Changelog ==

= 5.8.9 =
* Added translation files for 10 languages
* Updated PRO website link
* Fixed SQL compatibility with MariaDB

= 5.8.6 =
* Initial release with AI scraping protection, bot detection, brute force defense, and more

== External Services ==

This plugin connects to external services to provide specific functionality.

**IP Geolocation**
* Service: ip-api.com
* Purpose: Determines visitor country for statistics
* Data sent: Visitor IP address
* When: On each page visit from non-whitelisted IP
* Terms of Service: https://ip-api.com/docs/legal
* Privacy Policy: https://ip-api.com/docs/legal

**Server IP Detection**
* Service: https://www.ipify.org/
* Purpose: Detects the server's public IP address
* Data sent: None (only receives IP)
* When: Once on plugin activation, then cached for 24 hours

**Threat Intelligence**
* Service: blocklist.de, abuse.ch (FeodoTracker), Tor Project
* Purpose: Checks visitor IPs against known malicious IP databases
* Data sent: None (downloads threat lists locally)
* When: Periodically (cached for 24 hours)
* blocklist.de: https://www.blocklist.de/en/impressum.html
* abuse.ch: https://abuse.ch/
* Tor Project: https://www.torproject.org/about/trademark/

**WordPress.org API**
* Service: api.wordpress.org
* Purpose: Checks for WordPress core updates (security score feature)
* Data sent: None (standard WordPress update check)
* When: On security score calculation
* Privacy Policy: https://wordpress.org/about/privacy/

== Source Code ==

The original unminified developer source code is available at:
https://github.com/gataurus/cyberpulse
