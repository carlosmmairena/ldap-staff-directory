# LDAP Staff Directory — Claude Code Guide

## Project Overview

WordPress plugin (GPL v2) that connects a site to an LDAP/LDAPS server and renders a public employee directory. Supports a native shortcode, an Elementor widget, and a Beaver Builder module.

- **Plugin slug:** `ldap-staff-directory`
- **Text domain:** `ldap-staff-directory`
- **Constant/class prefix:** `LDAP_ED_`
- **Option key:** `ldap_ed_settings` (constant `LDAP_ED_OPTION_KEY`)
- **Cache transient key:** `ldap_ed_users` (constant `LDAP_ED_CACHE_KEY`) — single global key, no per-shortcode variation
- **Stale cache key:** `ldap_ed_users_stale` (constant `LDAP_ED_STALE_KEY`) — permanent WP option, no TTL; fallback when LDAP is unreachable
- **Salt fingerprint key:** `ldap_ed_salt_fingerprint` — permanent WP option (autoload=false); stores SHA-256 of `AUTH_KEY` to detect WP salt rotation. Added in 1.0.4; deleted on uninstall.
- **Requires:** PHP 7.4+, WordPress 5.8+, PHP `ldap` extension (checked at runtime via `admin_notices`; activation does **not** block — the plugin activates regardless and shows a persistent admin error notice when the extension is absent)

## File Structure

```
ldap-staff-directory.php              # Main file: constants, autoloader, bootstrap hooks
includes/
  class-ldap-connector.php           # LDAP_ED_Connector  — connect/bind/search/test
  class-cache.php                    # LDAP_ED_Cache      — WP Transients wrapper
  class-admin.php                    # LDAP_ED_Admin      — Settings API, admin page
  class-ajax.php                     # LDAP_ED_Ajax       — test connection + clear cache
  class-shortcode.php                # LDAP_ED_Shortcode  — [ldap_directory] shortcode
elementor/
  class-elementor-widget.php         # LDAP_ED_Elementor_Widget
beaver-builder/
  class-bb-module.php                # LDAP_ED_BB_Module
  frontend.php                       # BB module front-end template
admin/
  views/settings-page.php            # Admin settings page HTML (two-column: form + sidebar)
  css/admin.css
  js/admin.js                        # AJAX: test connection, clear cache (jQuery-dependent)
public/
  views/directory.php                # Front-end directory card grid
  css/directory.css                  # Styles using CSS custom properties
  js/directory.js                    # Client-side search + pagination (vanilla JS, no dependencies)
uninstall.php                        # Cleanup on uninstall
readme.txt                           # WordPress.org readme
```

## Architecture

- **Bootstrap:** `plugins_loaded` → `ldap_ed_init()` instantiates Admin, Ajax, Shortcode. Page builder integrations are lazy-registered only when the builder is active.
- **Autoloader:** `ldap_ed_autoload()` maps class names to files via a manual `$map` array — no PSR-4.
- **Data flow:** Shortcode/widget → `LDAP_ED_Cache::get()` → on miss → `LDAP_ED_Connector::get_users()` → store result via `LDAP_ED_Cache::set()`.
- **Caching:** WP Transients (TTL) + permanent WP option (stale fallback). TTL default 60 min. On LDAP failure after cache expiry, the stale copy is served silently to visitors. `flush()` removes only the transient (preserves stale). `purge()` removes both — called on manual clear and on settings save (connection params may have changed).
- **Page builders:** Both Elementor widget and BB module delegate rendering to `do_shortcode('[ldap_directory ...]')`.
- **Pagination & search:** Entirely client-side. All users are fetched at once from LDAP; JS handles filtering and paging.
- **User sorting:** Results sorted alphabetically by name via `usort()` after retrieval.
- **Styling:** CSS custom properties on the wrapper element enable theme integration without selector overrides.

## Key Hooks

| Hook | Handler | Notes |
|---|---|---|
| `plugins_loaded` | `ldap_ed_init()` | Bootstrap |
| `admin_menu` | `LDAP_ED_Admin::add_menu()` | Settings sub-menu under "Settings" |
| `admin_init` | `LDAP_ED_Admin::register_settings()` | Settings API registration |
| `admin_enqueue_scripts` | `LDAP_ED_Admin::enqueue_assets()` | Conditional: only on `settings_page_ldap-staff-directory` |
| `wp_enqueue_scripts` | `LDAP_ED_Shortcode::register_assets()` | Registers public CSS/JS; enqueues early when shortcode is in `post_content` |
| `wp_ajax_ldap_ed_test_connection` | `LDAP_ED_Ajax::test_connection()` | Admin-only AJAX |
| `wp_ajax_ldap_ed_clear_cache` | `LDAP_ED_Ajax::clear_cache()` | Admin-only AJAX |
| `elementor/widgets/register` | `ldap_ed_register_elementor_widget()` | Only when Elementor active |
| `init` (priority 20) | `ldap_ed_register_bb_module()` | Only when FLBuilder class exists |

## Enqueue Handles

| Handle | Type | Dependencies | Localization Object |
|---|---|---|---|
| `ldap-ed-admin` | CSS + JS | JS depends on `jquery` | `ldapEdAdmin` → `{ajaxUrl, nonce, i18n:{testing, clearing, cacheCleared}}` |
| `ldap-ed-public` | CSS + JS | None | — |


## Class API Reference

**`LDAP_ED_Connector`**
```php
public function __construct( array $settings = array() )
public function connect(): true|WP_Error
public function bind(): true|WP_Error
public function get_users(): array|WP_Error   // returns sorted array of user arrays
public function test_connection(): array       // { success: bool, message: string, count?: int }
private function get_entry_value( array $entry, string $attribute ): string|null
private function disconnect(): void
```

**`LDAP_ED_Cache`**
```php
public function __construct( string $key = LDAP_ED_CACHE_KEY, int $ttl = 3600 )
public function get(): mixed|false          // TTL-bound transient
public function get_stale(): mixed|false    // permanent WP option fallback (no TTL)
public function set( $data ): void          // writes transient + stale option
public function flush(): void               // removes transient only (preserves stale)
public function purge(): void               // removes transient + stale option
public function has(): bool
```

**`LDAP_ED_Admin`**
```php
public function add_menu(): void
public function register_settings(): void
public function sanitize_settings( $input ): array   // purges cache on save, preserves blank passwords
public function maybe_show_ldap_extension_notice(): void  // admin_notices — shown when ext/ldap absent
public function enqueue_assets( $hook ): void
public function render_settings_page(): void
public function render_field_*(): void               // one method per settings field
private function sanitize_ldap_server( $raw, $previous ): string  // validates ldap(s):// scheme
private function get_option( $key, $default = '' ): mixed
```

**Global crypto helpers** (defined in `ldap-staff-directory.php`, available after `plugins_loaded`):
```php
function ldap_ed_derive_sodium_key(): string      // BLAKE2b(wp_salt('auth').wp_salt('secure_auth'), 'ldap-ed-v1', 32)
function ldap_ed_encrypt_pass( string $plain ): string   // XSalsa20-Poly1305; returns 'sod::<base64>'; stores salt fingerprint
function ldap_ed_decrypt_pass( string $stored ): string  // Decrypts 'sod::' values; passes legacy plaintext through; '' on MAC fail
function ldap_ed_salts_have_changed(): bool        // True when stored fingerprint ≠ hash('sha256', wp_salt('auth'))
```

**`LDAP_ED_Ajax`**
```php
public function test_connection(): void   // wp_send_json_success/error
public function clear_cache(): void       // wp_send_json_success/error
```

**`LDAP_ED_Shortcode`**
```php
public function register_assets(): void             // registers CSS/JS; enqueues early if shortcode in post_content
public function render( $atts ): string             // enqueues assets as fallback for page builders
private function enqueue_assets(): void             // enqueues registered CSS/JS
private function get_users( array $settings ): array|WP_Error
```

## LDAP Connector Details

**Search filter (default):** `(&(objectClass=person)(mail=*))` — requires both person class and email attribute.

**Search filter (exclude_disabled='1'):** `(&(objectClass=person)(mail=*)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))` — additionally excludes disabled Active Directory accounts. Leave off for OpenLDAP/Samba.

**Attributes fetched:** `displayname`, `cn` (fallback for name), `mail`, `title`, `department`, `telephonenumber`.

**User array keys returned by `get_users()`:** `name`, `email`, `title`, `department`, `phone`.

**LDAP options set on every connection:**
- `LDAP_OPT_PROTOCOL_VERSION` = 3
- `LDAP_OPT_REFERRALS` = 0
- `LDAP_OPT_NETWORK_TIMEOUT` = 10 seconds

**SSL/TLS handling:**
- `verify_ssl='0'` → sets `LDAPTLS_REQCERT=never` env var + `LDAP_OPT_X_TLS_REQUIRE_CERT = LDAP_OPT_X_TLS_NEVER`
- `ca_cert` path provided and file exists → sets `LDAP_OPT_X_TLS_CACERTFILE`
- All `@ldap_*` functions are silenced; errors captured via `ldap_error()`

## Key Settings (stored in `ldap_ed_settings`)

Settings are split into three sections: `ldap_ed_section_connection`, `ldap_ed_section_display`, `ldap_ed_section_cache`.

| Key | Section | Type | Default | Sanitization |
|---|---|---|---|---|
| `server` | connection | string | `ldaps://` | `sanitize_ldap_server()` — allows only `ldap://`/`ldaps://`; falls back to previous value on invalid scheme |
| `port` | connection | int | `636` | `absint()` |
| `bind_dn` | connection | string | `''` | `sanitize_text_field()` |
| `bind_pass` | connection | string | `''` | Encrypted at rest via `ldap_ed_encrypt_pass()` (XSalsa20-Poly1305, key derived from WP salts); blank = keep existing. Decrypted in `LDAP_ED_Connector::bind()` via `ldap_ed_decrypt_pass()`. Legacy plaintext migrates transparently on next save. |
| `base_ou` | connection | string | `''` | `sanitize_text_field()` |
| `verify_ssl` | connection | `'0'`/`'1'` | `'1'` | Binary |
| `ca_cert` | connection | string | `''` | `sanitize_text_field()` |
| `exclude_disabled` | connection | `'0'`/`'1'` | `'0'` | Binary |
| `fields` | display | array | `['name','email','title','department']` | Intersect with allowed list |
| `per_page` | display | int | `20` | `absint()` |
| `enable_search` | display | `'0'`/`'1'` | `'1'` | Binary |
| `cache_ttl` | cache | int | `60` | `absint()` (minutes) |

**Allowed field values:** `name`, `email`, `title`, `department`, `phone`. Any other value is silently discarded.

## Shortcode

```
[ldap_directory]
[ldap_directory fields="name,title" per_page="10" search="false"]
```

Attributes override admin defaults. Shortcode attributes: `fields` (comma-separated), `per_page` (int), `search` (`"true"`/`"false"`).

## AJAX Actions (nonce: `ldap_ed_admin_nonce`, capability: `manage_options`)

| Action | Handler | Response |
|---|---|---|
| `ldap_ed_test_connection` | `LDAP_ED_Ajax::test_connection()` | JSON `{success, data:{message, count?}}` |
| `ldap_ed_clear_cache` | `LDAP_ED_Ajax::clear_cache()` | JSON `{success, data:{message}}` |

## Frontend Template

**File:** `public/views/directory.php` — variables available: `$users`, `$fields`, `$per_page`, `$enable_search`.

**HTML structure:**
```
.ldap-directory-wrap[data-per-page][data-total]
  .ldap-search-wrap > #ldap-search-input                     (conditional; icon via CSS ::before)
  .ldap-directory-grid[aria-live="polite"]
    article.ldap-staff-card[data-name][data-email][data-title][data-department][data-phone]
      div.ldap-card-avatar[aria-hidden][style="--ldap-avatar-bg:#hex"]   (initials circle)
      h3.ldap-name | p.ldap-title | p.ldap-department > span.ldap-dept-badge | a.ldap-email | a.ldap-phone
  p.ldap-no-results.ldap-no-results--search       (shown by JS when search yields nothing)
  nav.ldap-pagination
    button.ldap-btn.ldap-prev | span.ldap-page-info | button.ldap-btn.ldap-next
```

**CSS custom properties** (set on the wrapper or via page builder controls):

| Property | Default | Set by |
|---|---|---|
| `--ldap-primary-color` | `#0073aa` | All contexts |
| `--ldap-card-bg` | `#ffffff` | All contexts |
| `--ldap-text-color` | `#3c434a` | All contexts |
| `--ldap-columns` | `3` | Shortcode attr / Elementor / BB |
| `--ldap-gap` | `20px` | Elementor / BB |
| `--ldap-card-radius` | `8px` | Elementor / BB |
| `--ldap-font-size` | — | BB module only |
| `--ldap-avatar-size` | `44px` | All contexts |
| `--ldap-avatar-bg` | per-card inline style | Template (computed from name hash) |
| `--ldap-dept-badge-bg` | `rgba(0,115,170,.08)` | All contexts |
| `--ldap-dept-badge-color` | `#005a87` | All contexts |

## Page Builder Integrations

**Elementor widget** (`LDAP_ED_Elementor_Widget extends \Elementor\Widget_Base`):
- Name: `ldap_staff_directory`, icon: `eicon-person`, category: `general`
- Content controls: `fields` (multi-select2: name, email, title, department, phone), `per_page` (number), `enable_search` (switcher), `columns` (select 1–4)
- Style controls: `primary_color`, `card_bg`, `text_color`, `card_typography`, `card_padding`, `card_border_radius`, `grid_gap`
- Columns injected as inline CSS variable `--ldap-columns` on the widget wrapper class
- `content_template()` returns a "preview on frontend" notice (server-side render only)
- BB module has `partial_refresh: true` for live preview in the BB editor

**Beaver Builder module** (`LDAP_ED_BB_Module extends FLBuilderModule`):
- Fields tab: `fields_to_show` (name, email, title, department, phone), `per_page`, `enable_search`, `columns`
- Style tab: colors, `font_size`, `gap`, `border_radius`
- CSS variables scoped per node via `wp_add_inline_style()`: `.fl-node-{uid}` selector

## Coding Standards

Follow **WordPress Coding Standards (WPCS)**:

- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_*`, `esc_textarea()`.
- Sanitize all input: `sanitize_text_field()`, `absint()`, `esc_url_raw()`, `wp_strip_all_tags()`.
- Use `WP_Error` for all error returns from `LDAP_ED_Connector` — never throw exceptions.
- Nonces on every AJAX action (`check_ajax_referer`) and capability checks (`current_user_can('manage_options')`).
- Prefix all functions, classes, constants, option names with `ldap_ed_` / `LDAP_ED_`.
- Silence LDAP PHP functions (`@ldap_*`) — they trigger warnings on failure; capture errors with `ldap_error()`.
- Add `/* translators: ... */` comments for every `sprintf`+`__()` call. The comment must be placed **inside** `sprintf()`, on the line **immediately above** `__()` — NOT above the `sprintf()` call itself. The WordPress Plugin Checker looks for the comment on the line directly preceding `__()`:
  ```php
  sprintf(
      /* translators: %s: description of placeholder */
      __( 'Message with %s placeholder.', 'ldap-staff-directory' ),
      $value
  )
  ```
- All variables declared in template/included files (`public/views/directory.php`, `beaver-builder/frontend.php`) must use the `ldap_ed_` prefix. These files are included in global scope by WordPress/page builders, so unprefixed local variables trigger the WPCS global-variable naming rule. Exception: variables injected by the page builder itself (`$settings`, `$module` in BB; `$settings` in Elementor) must keep their original names.
- For integer values in `printf()`/`sprintf()` output, wrap with `absint()` explicitly — `%d` format alone does not satisfy WPCS escaping checks.
- For `echo do_shortcode(...)` output, use `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped` with an inline explanation confirming that shortcode attribute values are individually escaped before building the shortcode string.
- Do not call `load_plugin_textdomain()` — WordPress.org-hosted plugins load translations automatically since WP 4.6. The `Text Domain` header in the plugin file is sufficient.
- Do not add a `Domain Path` header unless the plugin ships local `.po`/`.mo` translation files in the repository. A header pointing to a non-existent folder triggers a plugin-check error.
- Never echo the bind password back to the admin form. Blank submission = keep the existing saved value.
- Binary settings (`verify_ssl`, `enable_search`) use string `'1'`/`'0'`, not PHP booleans, for WP options consistency.
- Use `printf()`/`sprintf()` for all HTML output in `render_field_*` methods — no `echo` with string concatenation.
- Pass `'label_for' => 'ldap_ed_{id}'` in the `$args` array of `add_settings_field()` for all text, number, and textarea fields so the Settings API links the `<th>` label to the input. Skip `label_for` for checkboxes (inline label) and multi-checkbox groups.
- In `admin.js`, capture the original button label before disabling it and restore it in `.always()` — do not use hardcoded label strings.

## Adding a New Feature Checklist

1. Add the class to `includes/` and register it in `ldap_ed_autoload()`.
2. Instantiate it inside `ldap_ed_init()` if needed.
3. Add new settings fields via `LDAP_ED_Admin::register_settings()` + a `render_field_*` method + sanitization in `sanitize_settings()`. Assign to the appropriate section (`connection`, `display`, or `cache`).
4. Escape output, sanitize input, add nonce/capability checks on any new AJAX handler.
5. Update `readme.txt` changelog (add entry under `== Changelog ==`) and bump `LDAP_ED_VERSION` in the plugin header and the constant **and** `Stable tag` in `readme.txt` consistently. If the feature is user-facing, also check whether it belongs in the `= Features =` bullet list and the `== Frequently Asked Questions ==` section — the changelog is chronological and easy to keep current, but those two sections are a static snapshot that silently goes stale otherwise.
6. If adding a new LDAP attribute, add it to the `$attributes` array in `LDAP_ED_Connector::get_users()`, map it in `get_entry_value()`, and add the key to the user array built in the same method.
7. If adding a new displayable field, also add it to the allowed-fields list in `sanitize_settings()`, to `$allowed_fields` in `LDAP_ED_Shortcode::render()`, to the Elementor/BB controls, to the `data-*` attributes on `article.ldap-staff-card` in the template, and to the `matchesQuery()` function in `directory.js`.
8. When clearing cache on a settings change use `purge()` (removes transient + stale). Use `flush()` only when the stale data should be preserved (e.g., a future scheduled refresh that hasn't been confirmed yet).
9. `uninstall.php` must clean up both `delete_transient(LDAP_ED_CACHE_KEY)` and `delete_option(LDAP_ED_STALE_KEY)`, for both single-site and each site in a multisite network.
