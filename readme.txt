=== GhostAdmin ===
Contributors: cybercrew
Tags: security, login, admin, stealth, hardening
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hides your WordPress admin presence entirely.

== Description ==

GhostAdmin removes the footprint of your WordPress install from bots, scanners,
and attackers by doing three things:

**1. Custom Admin URL**
Replace /wp-login.php and /wp-admin/ with any slug you choose (e.g. /ghost-panel/).
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
Any IP or CIDR range in the whitelist bypasses all GhostAdmin blocks — useful for
your own office IP or a trusted CI/CD runner.

= Safety guarantees =

* admin-ajax.php is *never* blocked — front-end AJAX always works.
* admin-post.php for WP-Cron is always exempt from default-login blocking.
* Logged-in administrators are never blocked regardless of settings.

== Installation ==

1. Upload the `ghost-admin` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **GhostAdmin** in the left admin menu.
4. Set your custom login slug and toggle the blocks you want.
5. Click **Save Settings**.

**Important:** Note your custom login slug *before* enabling the block. If you
forget it, you can still log in by appending `?ga_bypass=1` to /wp-login.php while
temporarily disabling the plugin via FTP (rename the plugin folder).

== Frequently Asked Questions ==

= I forgot my custom login URL. How do I log in? =

Temporarily rename the plugin folder via FTP or your host's file manager. This
disables GhostAdmin and restores access to /wp-login.php. After logging in, rename
it back and note your slug from the settings page.

= Will this break my e-commerce checkout or contact forms? =

No. GhostAdmin only intercepts specific admin paths. Front-end pages, WooCommerce
checkout, and any plugin using admin-ajax.php are unaffected.

= Does this work with caching plugins? =

Yes, but ensure your caching plugin excludes /wp-admin/ and your custom login slug
from caching. Most do this automatically.

= Does this replace a web application firewall? =

No. GhostAdmin reduces your WordPress fingerprint and blocks common scanner
probes. It is a hardening layer, not a full WAF. Pair it with server-level rules
and a reputable firewall plugin for defense-in-depth.

= Is XMLRPC fully disabled? =

Only the URL is blocked. The `xmlrpc_enabled` filter is not touched, so plugins
that hook into XML-RPC internally are unaffected. If you use Jetpack, keep the
xmlrpc.php toggle off.

== Changelog ==

= 1.0.0 =
* Initial release.
* Custom admin URL with rewrite-rule routing.
* Stealth 404 blocking for /wp-login.php and /wp-admin/.
* Direct folder and sensitive file blocking.
* IP/CIDR whitelist.
* Dark stealth admin UI.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
