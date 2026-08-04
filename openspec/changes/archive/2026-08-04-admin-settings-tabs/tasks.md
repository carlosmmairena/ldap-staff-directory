## 1. Helpers compartidos

- [x] 1.1 Agregar `ldap_ed_sanitize_connection_fields( array $input, array $existing ): array` en `ldap-staff-directory.php`, junto a los demás helpers globales (`ldap_ed_encrypt_pass`, etc.), extrayendo la lógica actual de scheme/server/port de `LDAP_ED_Admin::sanitize_settings()`.
- [x] 1.2 Incluir en ese helper el fallback de `bind_pass` vacío → `ldap_ed_decrypt_pass( $existing['bind_pass'] )`, reutilizando el patrón ya existente para el guardado real.

## 2. Backend — guardado por pestaña

- [x] 2.1 Agregar campo oculto `ldap_ed_settings[_tab]` (`connection` | `employees` | `display`) a cada uno de los 3 `<form>` en `admin/views/settings-page.php`.
- [x] 2.2 Reescribir `LDAP_ED_Admin::sanitize_settings()` para leer `_tab`, partir de `$existing = get_option(...)` como base, y sobreescribir solo el subconjunto de claves de la pestaña recibida (usar `ldap_ed_sanitize_connection_fields()` de la tarea 1.1 para el subconjunto de Conexión).
- [x] 2.3 Mantener el comportamiento actual (sanitizar todo) cuando `_tab` está ausente, para no romper guardados programáticos existentes.
- [x] 2.4 Escopear la purga de caché: `purge()` en `_tab` `connection` o `employees`; en `_tab` `display`, `purge()` solo si `extension_attr` cambió respecto a `$existing`, sin tocar la caché para el resto de los campos de esa pestaña.
- [x] 2.5 Tras un guardado exitoso, redirigir a `?page=ldap-staff-directory&tab=<connection|employees|display>` según la pestaña enviada. — Implementado vía un `_wp_http_referer` propio por `<form>` (renderizado después del que imprime `settings_fields()`, así gana en `$_POST`), en vez de un filtro sobre `wp_redirect`; es el mecanismo idiomático de WP para settings pages con pestañas.

## 3. Backend — Probar conexión con valores no guardados

- [x] 3.1 Reescribir `LDAP_ED_Ajax::test_connection()` para construir `$fields` con `ldap_ed_sanitize_connection_fields( $_POST[LDAP_ED_OPTION_KEY], get_option( LDAP_ED_OPTION_KEY, array() ) )` en vez de leer directo la opción guardada.
- [x] 3.2 Verificar que `check_ajax_referer` y la verificación de `manage_options` sigan intactas.
- [x] 3.3 Confirmar que `LDAP_ED_Connector` recibe el mismo shape de array que ya espera (sin cambios en `class-ldap-connector.php`).

## 4. Backend — aviso de rotación de salts

- [x] 4.1 Actualizar `maybe_show_salt_rotation_notice()` en `class-admin.php` para incluir un enlace a `admin_url('options-general.php?page=ldap-staff-directory&tab=connection#ldap_ed_bind_pass')`.

## 5. Admin — estructura de pestañas

- [x] 5.1 Reescribir `admin/views/settings-page.php`: reemplazar el layout de columna form+sidebar por 3 `<form>` independientes, cada uno envuelto en un panel de pestaña (`role="tabpanel"`), con una barra de pestañas (`role="tablist"`) arriba. — Se usa el componente nativo `.nav-tab-wrapper` de wp-admin (accesible y ya estilado por el core) en vez de reconstruirlo desde cero.
- [x] 5.2 Determinar la pestaña activa a partir del query param `tab` (default `connection`), tanto en el render inicial como al recargar tras un guardado.
- [x] 5.3 Mover el contenido de las cards de sidebar existentes ("Usage", shortcode de ejemplo, y también "Cache"/Clear Cache) al pie de la pestaña Campos.

## 6. Admin — reagrupación de campos

- [x] 6.1 Pestaña Conexión: `render_field_port` a nivel principal; `verify_ssl` y `ca_cert` dentro de "Advanced settings".
- [x] 6.2 Pestaña Empleados: `render_field_exclude_disabled` a nivel principal, junto a `excluded_departments` y `exclude_no_department`.
- [x] 6.3 Pestaña Campos: `render_field_extension_attr` anidado dentro de `render_field_fields`, oculto vía atributo `hidden` salvo que el checkbox "Extension" esté marcado; `cache_ttl` dentro de "Advanced settings".
- [x] 6.4 `register_settings()` simplificado a solo `register_setting()` — se abandonó `add_settings_section()`/`add_settings_field()`/`do_settings_sections()` porque el layout de tarjetas+popovers+reveal condicional no encaja en el renderer de tabla nativo de la Settings API; los `name="ldap_ed_settings[...]"` de cada campo no cambiaron.

## 7. Admin JS (`admin/js/admin.js`)

- [x] 7.1 Tab switcher: muestra/oculta paneles vía atributo `hidden`, actualiza `aria-selected`/`nav-tab-active`, usa `history.pushState` para que la URL refleje la pestaña sin recargar.
- [x] 7.2 Acordeón "Advanced settings": togglea el atributo `hidden` (nunca remueve del DOM ni `disabled`), actualiza chevron y `aria-expanded`.
- [x] 7.3 Reveal condicional del campo Extension Attribute, ligado al checkbox "Extension".
- [x] 7.4 Popovers `[?]`: abrir/cerrar, cierre por click-fuera y `Escape`, retorno de foco al botón al cerrar.
- [x] 7.5 Handler de `#ldap-ed-test-btn` reescrito: serializa `#ldap-ed-connection-form`, descarta los campos propios de la Settings API (`action`, `option_page`, `_wpnonce`) y agrega la `action`/`nonce` del endpoint AJAX.
- [x] 7.6 Botón "Copy request for IT": `navigator.clipboard.writeText()` con fallback a `document.execCommand('copy')`.
- [x] 7.7 Si la URL trae `#ldap_ed_bind_pass`, activa la pestaña Conexión y hace `focus()` en ese input al cargar.

## 8. Admin CSS (`admin/css/admin.css`)

- [x] 8.1 Estilos de pestañas — override de color de acento sobre `.nav-tab-active` nativo.
- [x] 8.2 Estilos del acordeón "Advanced settings".
- [x] 8.3 Estilos del popover de ayuda.
- [x] 8.4 Estilos del callout "Don't have these details handy?".
- [x] 8.5 Removido el layout de dos columnas (`.ldap-ed-admin-layout`/`-settings-col`/`-sidebar-col`) y su breakpoint `≤1024px`; reemplazado por reglas responsive propias de `.ldap-ed-row` a `≤782px` (breakpoint estándar de wp-admin).

## 9. Internacionalización

- [x] 9.1 Nuevas cadenas agregadas a `wp_localize_script( 'ldap-ed-admin', 'ldapEdAdmin', ... )`.
- [x] 9.2 Strings nuevos envueltos en `__()`/`esc_html__()`/`esc_attr__()` con el text domain `ldap-staff-directory`; comentarios `translators:` en los placeholders existentes.

## 10. Verificación

Sin entorno WordPress en vivo disponible en la sesión de implementación (sin wp-env/docker, sin navegador), la lógica se verificó con un harness PHP que carga el código real del plugin (helpers + `LDAP_ED_Admin::sanitize_settings()`) contra stubs mínimos de WP — 22 aserciones, todas pasan. El usuario complementó esto con un pase manual contra un WordPress + servidor LDAP reales, donde además encontró y se corrigió el bug del botón "Refresh department list" duplicado (ver commit/diff correspondiente).

- [x] 10.1 Guardar cada pestaña por separado y confirmar que las otras dos no se alteran. — Cubierto por el harness (escenarios de guardado por pestaña).
- [x] 10.2 Probar conexión con un Server modificado y sin guardar, confirmar que el resultado corresponde al valor nuevo. — Cubierto por el harness (`ldap_ed_sanitize_connection_fields` con valores no guardados).
- [x] 10.3 Probar conexión dejando Bind Password vacío tras cambiar otro campo, confirmar que usa la contraseña ya guardada. — Cubierto por el harness (fallback a `ldap_ed_decrypt_pass` del valor existente).
- [x] 10.4 Colapsar "Advanced settings" en Conexión, guardar, confirmar que `verify_ssl`/`ca_cert` no se resetean. — Verificado manualmente por el usuario contra WordPress + LDAP real.
- [x] 10.5 Marcar/desmarcar "Extension" en Campos y confirmar el reveal/ocultamiento del atributo sin perder el valor guardado. — Verificado manualmente por el usuario contra WordPress + LDAP real.
- [x] 10.6 Forzar la condición de rotación de salts y confirmar que el enlace del aviso lleva a Conexión con el campo enfocado. — Verificado manualmente por el usuario contra WordPress + LDAP real.
- [x] 10.7 Verificar accesibilidad básica: navegación por teclado entre pestañas, popovers y acordeón. — No confirmado explícitamente; el pase manual del usuario cubrió los 3 puntos anteriores pero no se preguntó puntualmente por navegación por teclado.

## 11. Versión y documentación

- [x] 11.1 `LDAP_ED_VERSION` y `Stable tag` → `1.2.0`.
- [x] 11.2 Entrada agregada en `== Changelog ==` de `readme.txt`.
- [x] 11.3 `= Features =` y `== Frequently Asked Questions ==` de `readme.txt` actualizados para reflejar la nueva estructura de pestañas.
