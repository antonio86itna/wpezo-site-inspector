=== WPezo Site Inspector ===
Contributors: wpezo, freemius
Donate link: https://wpezo.com
Tags: site audit, security, performance, seo, accessibility
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free comprehensive site audit — 35+ checks for security, performance, SEO, accessibility & database health. Instant grade and actionable fixes.

== Description ==

**WPezo Site Inspector** gives your WordPress site a complete health check in one click. Get a clear letter grade (A–F) and specific fix instructions for every issue found. No technical knowledge required.

= 43 Checks Across 5 Categories =

**🛡️ Security (10 checks)** — SSL/HTTPS, file editor, debug mode, database prefix, PHP version, WordPress version, admin username, XML-RPC, security headers, file permissions.

**⚡ Performance (8 checks)** — Active plugin count, autoloaded data size, post revisions, expired transients, PHP memory limit, GZIP/Brotli compression, WP-Cron health, page caching.

**🔍 SEO (7 checks)** — Site title, tagline, search engine visibility, permalink structure, XML sitemap, robots.txt, SEO plugin detection.

**♿ Accessibility (5 checks)** — Accessibility-ready theme, image alt text coverage, HTML language attribute, skip-to-content link, heading hierarchy.

**🗄️ Database (5 checks)** — Spam comments, trashed posts, auto-drafts, orphaned post meta, database overhead.

**🔐 Advanced Diagnostics (8 bonus checks)** — Mixed content detection, image optimization analysis, login security audit, backup status, plugin/theme update status, REST API exposure, email deliverability, broken media files. *Unlock free by entering your email.*

= Key Features =

* **Instant Score & Grade** — Visual circular score (0–100) with A–F letter grade
* **Actionable Fix Guide** — Every failed check includes step-by-step fix instructions
* **Score History** — Track your progress with an SVG chart over time
* **Dashboard Widget** — See your site health score at a glance
* **Admin Bar Indicator** — Always know your site health status
* **Print & Export** — Generate printable or downloadable reports
* **Weekly Auto-Scan** — Background scans keep results fresh
* **Zero Config** — Install → Activate → Scan. That's it.
* **Lightweight** — Vanilla JS, no jQuery, no external dependencies

= Pro Feature: One-Click Auto-Fix ($3.99/mo) =

Tired of fixing issues manually? Upgrade to Pro and fix problems with a single click:

* Disable file editor in wp-config.php
* Disable debug display
* Limit and clean post revisions
* Clean expired transients
* Delete spam comments
* Empty trash
* Clean auto-drafts and orphaned metadata
* Optimize database tables
* Add security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy)
* Disable XML-RPC
* Clear default tagline

All fixes are non-destructive and safe. [Upgrade to Pro →](https://wpezo.com/site-inspector)

= More by WPezo =

We build premium WordPress plugins focused on performance, security, and accessibility. Visit [wpezo.com](https://wpezo.com) or subscribe to our [YouTube channel](https://www.youtube.com/@WPezo) for free WordPress tutorials.

== Installation ==

1. Upload the `wpezo-site-inspector` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Site Inspector** in the admin sidebar menu
4. Click **Run Full Scan**
5. Review your results and start improving your site!

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

No. Scans only run when you click "Run Full Scan" or during the weekly background scan. The plugin adds zero frontend overhead — no CSS, no JS, no database queries on the public side.

= Is my data sent anywhere? =

All scan checks run locally on your server. No data leaves your site. When you unlock Advanced Diagnostics with your email, the email is securely processed to send you a confirmation — but your scan results never leave your server.

= What PHP and WordPress versions are supported? =

PHP 7.4+ and WordPress 6.0+. We recommend PHP 8.1+ for best performance and security.

= Does it work with page builders? =

Yes. Fully compatible with Elementor, Divi, Beaver Builder, Gutenberg, and all major page builders and themes.

= Does it work with multisite? =

It works on individual sites within a multisite network. Network-wide scanning is not currently supported.

= What does the Pro version add? =

The Pro version ($3.99/month) adds **One-Click Auto-Fix** — buttons next to every fixable issue that resolve the problem automatically. All 12 fixes are non-destructive and can be undone.

= Is the Pro version required? =

No. The free version includes all 43 checks with detailed fix instructions. The Pro version simply automates the fixes to save you time.

== Screenshots ==

1. Dashboard with animated score circle and letter grade
2. Security checks with actionable fix instructions
3. Performance analysis with auto-fix buttons (Pro)
4. Advanced Diagnostics unlock gate
5. One-Click Auto-Fix premium section
6. Dashboard widget showing site score
7. Score history chart tracking improvements over time

== Changelog ==

= 1.0.0 =
* Initial release
* 35 core checks + 8 advanced diagnostic checks
* Score circle with A–F grading
* Score history with SVG chart
* Dashboard widget and admin bar indicator
* Print and export report
* Weekly auto-scan
* Advanced Diagnostics with email verification
* 12 one-click auto-fixes (Pro)
* Freemius integration for Pro plans

== Upgrade Notice ==

= 1.0.0 =
First release — scan your WordPress site for free today!
