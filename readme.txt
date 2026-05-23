=== CyberCrew Admin Hide ===
Contributors: cybercrew
Tags: security, login, admin, stealth, hardening
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hides your WordPress admin presence entirely.

== Description ==

CyberCrew Admin Hide removes the footprint of your WordPress install from bots, scanners,
and attackers by doing three things:

**1. Custom Admin URL**
Replace /wp-login.php and /wp-admin/ with any slug you choose (e.g. /my-panel/).
Logged-in administrators are forwarded transparently. Unauthenticated access to the
default paths returns a stealth 404.

**2. Stealth 404 Blocking**
Blocked requests return a real WordPress 404 page — not a 403, not a redirect.
This means automated scanners that fingerprint /wp-login.php receive the same
response as any missing page, giving nothing away about the stack underneath.

**3. Direct File & Folder Blocking**
Block direct URL access to /wp-content/, /wp-includes/, xmlrpc.php, readme.html,
license.txt, wp-config.php, and wp-config-sample.php. Optional toggle to also
block direct wp-cron.php access (for hosts that use system cron instead).

**IP Whitelist**
Any IP or CIDR range in the whitelist bypasses all Admin Hide blocks — useful for
your own office IP or a trusted CI/CD runner.

= Safety guarantees =

* admin-ajax.php is *never* blocked — front-end AJAX always works.
* admin-post.php for WP-Cron is always exempt from default-login blocking.
* Logged-in administrators are never blocked regardless of settings.

= Source Code =

Source code is publicly available at: https://github.com/cybercrewinc/ghost-admin/

Official plugin page: https://cybercrew-admin-hide.cybercrew.co.jp/?lang=en
Developed by [CyberCrew](https://cybercrew.co.jp)

== Installation ==

1. Upload the `cybercrew-admin-hide` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **CyberCrew Admin Hide** in the left admin menu.
4. Set your custom login slug and toggle the blocks you want.
5. Click **Save Settings**.

**Important:** Note your custom login slug *before* enabling the block. If you
forget it, temporarily rename the plugin folder via FTP to disable Admin Hide —
this restores access to /wp-login.php. Log in, rename the folder back, and
retrieve your slug from the settings page.

== Frequently Asked Questions ==

= I forgot my custom login URL. How do I log in? =

Temporarily rename the plugin folder via FTP or your host's file manager. This
disables Admin Hide and restores access to /wp-login.php. After logging in, rename
it back and note your slug from the settings page.

= Will this break my e-commerce checkout or contact forms? =

No. CyberCrew Admin Hide only intercepts specific admin paths. Front-end pages, WooCommerce
checkout, and any plugin using admin-ajax.php are unaffected.

= Does this work with caching plugins? =

Yes, but ensure your caching plugin excludes /wp-admin/ and your custom login slug
from caching. Most do this automatically.

= Does this replace a web application firewall? =

No. CyberCrew Admin Hide reduces your WordPress fingerprint and blocks common scanner
probes. It is a hardening layer, not a full WAF. Pair it with server-level rules
and a reputable firewall plugin for defense-in-depth.

= Is XMLRPC fully disabled? =

Only the URL is blocked. The `xmlrpc_enabled` filter is not touched, so plugins
that hook into XML-RPC internally are unaffected. If you use Jetpack, keep the
xmlrpc.php toggle off.

= Does this plugin collect any data? =

No. CyberCrew Admin Hide makes no external HTTP requests and collects no user data. All
settings are stored locally in your WordPress database only.

== Changelog ==

= 1.0.2 =
* Rename: Plugin display name changed to CyberCrew Admin Hide and slug updated to cybercrew-admin-hide to comply with WordPress.org naming guidelines.
* Update: Plugin URI updated to cybercrew-admin-hide.cybercrew.co.jp.

= 1.0.1 =
* Fix: Plugin URI updated to official plugin page (was GitHub URL returning 404).
* Fix: Author URI corrected to cybercrew.co.jp (previous domain was broken/stale).
* Fix: All i18n calls now use the correct text domain `ghostadmin` (was `ghostadmin-2`).
* Fix: Suppressed PHP 8.x undefined-variable notices in `serve_custom_login_slug()`.
* Readme: Added official plugin page and CyberCrew attribution to source section.

= 1.0.0 =
* Initial release.
* Custom admin URL — replace /wp-login.php with any slug.
* Stealth 404 blocking for /wp-login.php and /wp-admin/.
* Direct folder and sensitive file blocking.
* IP/CIDR whitelist bypass.
* Clean flat light-mode admin UI.

== Upgrade Notice ==

= 1.0.2 =
Plugin renamed to CyberCrew Admin Hide for WordPress.org compliance. No settings or database changes.

= 1.0.1 =
Metadata and text-domain corrections for WordPress.org compliance. No settings or database changes.

= 1.0.0 =
Initial release — no upgrade steps required.
