=== LDAP Staff Directory ===
Contributors:      carlosmmairena
Tags:              ldap, directory, wpbeaverbuilder, staff, elementor
Requires at least: 5.8
Tested up to:      7.0
Requires PHP:      7.4
Stable tag:        1.1.3
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Connects to an LDAPS server and displays an employee directory from a specific OU. Supports Elementor, Beaver Builder and a native shortcode.

== Description ==

**LDAP Staff Directory** lets you connect your WordPress site to an LDAP / LDAPS server and publish a public employee directory with zero manual data entry.

= Features =

* Connects to any LDAP or LDAPS server (including Active Directory, OpenLDAP, Samba)
* Configurable Base OU and Bind DN
* Extracts: full name, email, job title, department
* Shortcode `[ldap_directory]` usable in any post, page or widget
* Native Elementor widget with full style controls
* Native Beaver Builder module with General and Style tabs
* Real-time client-side search (no page reload)
* Client-side pagination with configurable items per page
* Transient-based cache with configurable TTL and one-click invalidation
* Admin panel under **Settings → LDAP Directory**
* SSL certificate verification toggle (supports self-signed certs)
* Optional CA certificate file path
* Multisite compatible (per-site settings)

= Requirements =

* PHP `ldap` extension enabled on the server
* WordPress 5.8 or higher
* PHP 7.4 or higher

== Installation ==

1. Upload the `ldap-staff-directory` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Go to **Settings → LDAP Directory** and fill in your LDAP connection details.
4. Click **Test Connection** to verify the settings.
5. Insert `[ldap_directory]` in any post or page, or use the Elementor / Beaver Builder widget.

== Frequently Asked Questions ==

= The plugin says "PHP LDAP extension not found". What should I do? =

Contact your hosting provider and ask them to enable the `php-ldap` (or `php7.x-ldap`) extension. On Linux servers you can typically install it with:
`sudo apt-get install php-ldap` or `sudo yum install php-ldap`.

= My server uses a self-signed SSL certificate. How do I connect? =

Go to **Settings → LDAP Directory → LDAP Connection** and uncheck **Verify SSL Certificate**. Save, then click **Test Connection**.

= How do I connect to Active Directory? =

Use `ldaps://your-dc.domain.com` as the server, port `636`, and a service account DN such as `CN=svc-wordpress,OU=ServiceAccounts,DC=domain,DC=com`.

= Can I show only certain fields? =

Yes. In the admin panel, check or uncheck the fields you want. You can also override per shortcode:
`[ldap_directory fields="name,title"]`

= How long is data cached? =

By default 60 minutes. Change the TTL under **Settings → LDAP Directory → Cache** or flush immediately with the **Clear Cache** button.

== Screenshots ==

1. Admin settings page — connection and display options
2. Employee directory rendered with the default style
3. Elementor widget controls
4. Beaver Builder module tabs

== Changelog ==

= 1.1.3 =
* Feature: Exclude specific departments from the public directory. The settings page can now discover every department value present in LDAP (with employee counts) via a "Refresh department list" button, and the admin can select which ones to hide. Excluded departments are removed at the LDAP query level — their employees are never fetched from the server.
* Feature: Optionally exclude employees with no department assigned, as a separate control from the department checklist.
* Feature: Configurable order for the public department filter chips — alphabetical (default) or by employee count, descending.

= 1.1.2 =
* Feature: New "Extension" field for telephone extensions — displays as plain text (no link) alongside the existing phone field. Supports organizations with PBX/telephone exchange systems where extensions are stored in a separate LDAP attribute (e.g. `ipPhone`). The LDAP attribute name is configurable in Display Options (default: `ipPhone`). Works with Active Directory, Samba, OpenLDAP, and custom schemas.

= 1.1.1 =
* Improvement: LDAP user retrieval now uses RFC 2696 paged results (LDAP_CONTROL_PAGEDRESULTS), allowing the plugin to retrieve all employees from directories with more than 1,000 users — previously, Active Directory's default MaxPageSize limit silently truncated results.
* Chore: Verified compatibility with WordPress 7.0.

= 1.1.0 =
* Feature: Department filter bar — dynamically generated chips above the directory grid. Clicking a chip filters employees by department with horizontal scroll for 15+ departments.
* Feature: Server-side pagination and search — filtering and pagination now processed in PHP over the WordPress cache; the DOM contains only the current page of results (no more 300+ hidden elements).
* Improvement: Pagination now shows "Showing X–Y of Z" (and "Showing X–Y of Z in Department" when filtered) instead of the previous "1 / N" format.
* Fix: Added missing `.ldap-phone` CSS rules — phone links now display consistently with email links.

= 1.0.6 =
* Fix: Removed custom CSS input feature (admin panel textarea and Beaver Builder Advanced tab) per WordPress.org guideline prohibiting arbitrary CSS/JS/PHP injection.
* Fix: Added `phpcs:ignore` annotation with justification to `echo do_shortcode()` output in Beaver Builder frontend template; changed `per_page` shortcode argument from `esc_attr()` to `absint()` for correct integer escaping.

= 1.0.5 =
* Fix: Replace inline `<style>` tags in Elementor widget and Beaver Builder module with `wp_add_inline_style()` and Elementor's native `add_render_attribute()` API to comply with WordPress.org plugin guidelines (Guideline 11 / wp_enqueue best practices).

= 1.0.4 =
* Security: LDAP bind password is now encrypted at rest using libsodium (XSalsa20-Poly1305). The encryption key is derived from WordPress's built-in security keys — no configuration required.
* Security: Existing plaintext passwords continue to work and are automatically re-encrypted on the next settings save (transparent migration).
* Security: An admin notice is shown when WordPress security keys (wp-config.php) have been regenerated, prompting the administrator to re-enter the bind password.
* Note: Regenerating WordPress security keys requires re-entering the bind password once in Settings → LDAP Staff Directory.

= 1.0.3 =
* Fix: Plugin now activates without the PHP LDAP extension; a persistent admin notice informs the administrator when the extension is missing instead of blocking activation with a fatal error.
* Fix: `/* translators: */` comment repositioned inside `sprintf()`, immediately above `__()`, to satisfy the WordPress Plugin Checker i18n rule.
* Fix: All local variables in included template files (`directory.php`, `beaver-builder/frontend.php`) renamed with `ldap_ed_` prefix to comply with WPCS global-variable naming requirements.
* Fix: `absint()` applied to `$columns` in Elementor widget `printf()` output to satisfy the WPCS escaping rule for integer values.
* Fix: `load_plugin_textdomain()` removed — not required for WordPress.org-hosted plugins since WordPress 4.6.
* Fix: `Domain Path` header removed from plugin file — no local translation files are bundled.
* Chore: "Tested up to" updated to WordPress 6.9.
* Chore: Tag list reduced to five entries per WordPress.org limit.

= 1.0.2 =
* Feat: Added `telephoneNumber` field — read from LDAP, displayed on cards as a clickable `tel:` link, included in client-side search, and available in admin panel, Elementor and Beaver Builder controls.
* Feat: New "Exclude Disabled Accounts" setting (connection section) — filters out disabled Active Directory accounts using the `userAccountControl` bit flag. Leave unchecked for OpenLDAP/other servers.
* Feat: Resilient cache — when the LDAP server is unreachable after cache expiry, the last successfully fetched data (stale copy) is served silently to visitors. Only a manual "Clear Cache" action removes the stale copy entirely.

= 1.0.1 =
* Fix: LDAP server URL no longer lost on save — replaced `esc_url_raw()` (which strips `ldap://`/`ldaps://` schemes) with a dedicated sanitizer that validates the scheme and shows an admin error on invalid input.
* Fix: Added runtime admin notice when the PHP LDAP extension is missing, covering cases where the extension is disabled after activation or the plugin is activated via WP-CLI/DB without going through the activation hook.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.6 =
The custom CSS textarea in the admin panel and the Beaver Builder Advanced tab have been removed. To style the directory, use CSS custom properties (`--ldap-primary-color`, `--ldap-card-bg`, `--ldap-columns`, etc.) in your theme's stylesheet instead.

= 1.0.0 =
Initial release — no upgrade steps required.
