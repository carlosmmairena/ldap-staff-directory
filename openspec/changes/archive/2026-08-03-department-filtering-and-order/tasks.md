## 1. Connector — descubrimiento y filtro de exclusión

- [x] 1.1 Extraer el bucle de paginación RFC 2696 (`do { ldap_search + LDAP_CONTROL_PAGEDRESULTS + ldap_parse_result } while ($cookie)`) de `get_users()` a un helper privado reutilizable en `class-ldap-connector.php`, parametrizado por filtro y lista de atributos.
- [x] 1.2 Implementar `LDAP_ED_Connector::get_departments(): array|WP_Error`, usando el helper de 1.1 con el filtro base (`objectClass=person`, `mail=*`, `+exclude_disabled` si aplica) **sin** cláusulas de `excluded_departments`/`exclude_no_department`. Devuelve `{ departments: [{name, count}], no_department_count: int }`.
- [x] 1.3 Extender la construcción del filtro en `get_users()` para agregar `(!(department=<valor escapado con ldap_escape($v, '', LDAP_ESCAPE_FILTER)>))` por cada valor en `excluded_departments`, y `(department=*)` cuando `exclude_no_department === '1'`.
- [x] 1.4 Agregar `excluded_departments` y `exclude_no_department` a los defaults del constructor de `LDAP_ED_Connector`.

## 2. AJAX — acción de descubrimiento

- [x] 2.1 En `class-ajax.php`, agregar el método `get_departments()` registrado en `wp_ajax_ldap_ed_get_departments`: `check_ajax_referer('ldap_ed_admin_nonce')` + `current_user_can('manage_options')`.
- [x] 2.2 Llamar `LDAP_ED_Connector::get_departments()`; en éxito, `update_option('ldap_ed_known_departments', $resultado)` (autoload false) y `wp_send_json_success($resultado)`; en `WP_Error`, `wp_send_json_error(['message' => ...])` sin tocar la opción existente.
- [x] 2.3 Registrar la constante para la clave de opción (`LDAP_ED_KNOWN_DEPARTMENTS_KEY` o similar) junto a las demás constantes en `ldap-staff-directory.php`.

## 3. Admin — settings, sanitización, UI

- [x] 3.1 En `sanitize_settings()`: sanitizar `excluded_departments` (array de `sanitize_text_field()`, descartar vacíos/duplicados) y `exclude_no_department` (`'0'`/`'1'`) y `department_order` (whitelist `'alpha'`/`'count_desc'`, default `'alpha'`).
- [x] 3.2 En `sanitize_settings()`: si `server`, `bind_dn` o `base_ou` cambian respecto al valor previo en `$existing`, `delete_option('ldap_ed_known_departments')`.
- [x] 3.3 Registrar los nuevos campos vía `add_settings_field()` en `register_settings()`: `excluded_departments` (sin `label_for`, checklist), `exclude_no_department` (sin `label_for`, checkbox), `department_order` (con `label_for`, select) — sección `connection` para los dos primeros, `display` para el orden.
- [x] 3.4 Implementar `render_field_excluded_departments()`: lee `ldap_ed_known_departments`; si no existe, muestra estado vacío + botón; si existe, renderiza un checkbox por `{name, count}` (marcado según `excluded_departments` guardado) más el botón "Actualizar lista de departamentos".
- [x] 3.5 Implementar `render_field_exclude_no_department()`: checkbox independiente, mostrando el `no_department_count` del snapshot (si existe) en el label/description.
- [x] 3.6 Implementar `render_field_department_order()`: `<select>` con las opciones "Alfabético (A-Z)" (`alpha`) y "Por cantidad de contactos (mayor a menor)" (`count_desc`).
- [x] 3.7 Localizar en `enqueue_assets()` (vía `wp_localize_script`) los datos que `admin.js` necesita para la llamada AJAX de refresco (ya existe `ldapEdAdmin.ajaxUrl`/`nonce`; agregar strings i18n: `loadingDepartments`, `refreshDepartments`, error genérico).

## 4. Admin — JS del checklist

- [x] 4.1 En `admin/js/admin.js`, agregar el handler del botón "Actualizar lista de departamentos": deshabilita el botón (capturando y restaurando el label original, patrón existente), llama `ldap_ed_get_departments` vía AJAX.
- [x] 4.2 En éxito, re-renderizar el checklist de departamentos y el conteo de "sin departamento" en el DOM, preservando qué checkboxes ya estaban marcados por nombre de departamento (los que sigan existiendo en la respuesta nueva).
- [x] 4.3 En error, mostrar el mensaje de error (mismo patrón visual que el resultado de "Test Connection").

## 5. Shortcode — orden de chips

- [x] 5.1 En `class-shortcode.php::extract_departments()`, leer `department_order` de settings y aplicar `arsort($counts)` cuando sea `'count_desc'`, `ksort($counts)` en cualquier otro caso (default `'alpha'`).

## 6. Limpieza y metadatos del plugin

- [x] 6.1 En `uninstall.php`, agregar `delete_option('ldap_ed_known_departments')` para sitio único y para cada sitio en multisite.
- [x] 6.2 Bump de `LDAP_ED_VERSION` (constante + header del plugin) y `Stable tag` en `readme.txt`.
- [x] 6.3 Agregar entrada en `== Changelog ==` de `readme.txt` describiendo exclusión de departamentos, exclusión de "sin departamento", y orden configurable de chips.

## 7. Verificación manual

- [x] 7.1 Con un directorio de prueba: descubrir departamentos, excluir uno, guardar, confirmar que `get_users()` ya no lo trae y que el chip correspondiente desaparece de la UI pública.
- [x] 7.2 Confirmar que el departamento excluido sigue apareciendo (con su conteo real) la próxima vez que se dispara el descubrimiento, permitiendo des-excluirlo.
- [x] 7.3 Activar `exclude_no_department`, confirmar que empleados sin `department` desaparecen del grid público.
- [x] 7.4 Cambiar `department_order` a `count_desc` y confirmar el orden de los chips en la UI pública (excluyendo el chip "All", que sigue siempre primero).
- [x] 7.5 Cambiar `server` en settings y confirmar que el checklist de departamentos vuelve al estado vacío hasta el próximo refresh manual.
- [x] 7.6 Probar un nombre de departamento con paréntesis o `*` en LDAP (si el servidor de prueba lo permite) y confirmar que la exclusión funciona sin romper el filtro.
