## Context

Hoy `admin/views/settings-page.php` renderiza un único `<form>` que envuelve las tres secciones de `do_settings_sections('ldap-staff-directory')` (Connection, Display, Cache) y un solo botón "Save Changes" que hace POST a `options.php`. `LDAP_ED_Admin::sanitize_settings()` asume que `$input` siempre trae **todos** los campos del plugin — la única excepción ya construida es `bind_pass` (`class-admin.php:264-268`), donde un valor vacío significa "conservar el existente" en vez de "borrar".

`LDAP_ED_Ajax::test_connection()` (`class-ajax.php:20-37`) ignora por completo el body del POST y siempre construye el `LDAP_ED_Connector` a partir de `get_option(LDAP_ED_OPTION_KEY)` — es decir, prueba lo último guardado, nunca lo que el admin tiene tecleado en pantalla. `admin.js` (`#ldap-ed-test-btn`) tampoco manda ningún campo, solo `action` + `nonce`.

Referencia de diseño visual: `.interface-design/system.md` (paleta, spacing base 4px, depth strategy border-only, patrón de banners `.is-success`/`.is-error`). Los mockups de esta propuesta ya están dibujados en `docs/design-admin-ui.pen` (frames `tZjqK` Conexión, `Pz23y` Empleados, `ThRaT` Campos).

## Goals / Non-Goals

**Goals:**
- Reestructurar la UI de settings en 3 pestañas persistentes con guardado independiente, sin cambiar el shape de la opción `ldap_ed_settings` guardada en la base de datos.
- Agregar ayuda contextual (popovers, acordeón avanzado, callout de "pedile esto a tu admin de LDAP") reutilizando los patrones visuales/JS ya existentes (toggle de password, banners de resultado).
- Que "Probar conexión" valide el estado actual del formulario de Conexión, no lo ya guardado.

**Non-Goals:**
- No se cambia la lógica de conexión/búsqueda LDAP (`LDAP_ED_Connector`), ni el shape de los datos de usuario devueltos.
- No se migra `ldap_ed_settings` a múltiples opciones separadas por pestaña — sigue siendo una sola opción serializada.
- No se rediseña el frontend público (`public/views/directory.php`) ni Elementor/Beaver Builder — son consumidores del shortcode, no de esta pantalla.
- No se implementa un wizard de onboarding forward-only — ya se descartó a favor de pestañas persistentes.

## Decisions

### 1. Pestañas persistentes, no wizard forward-only
Las 3 pestañas (Conexión / Empleados / Campos) son navegación libre en todo momento, no un flujo secuencial bloqueante. Justificación: el plugin asume "Escenario B" — el mismo admin no-técnico vuelve a tocar Connection periódicamente (ej. rotación de salts de WP fuerza a reingresar el bind password), no solo en un setup inicial. Un wizard forward-only optimizaría el primer uso a costa de estorbar en cada uso posterior.

### 2. Un `<form>` independiente por pestaña (Opción B), no un form único con tabs visuales
**Alternativa descartada:** un solo `<form>` con las 3 secciones ocultas/mostradas por JS y un único botón Save global — más simple de implementar, pero el admin no puede guardar "solo Empleados" sin también re-enviar (y potencialmente pisar) los campos de Conexión que no está mirando en ese momento.

**Elegido:** cada pestaña es su propio `<form method="post" action="options.php">` con su propio `settings_fields('ldap_ed_settings_group')` y su propio botón "Guardar cambios". Los tres apuntan al mismo `LDAP_ED_OPTION_KEY`. Cada `<form>` incluye un campo oculto `ldap_ed_settings[_tab]` (`connection` | `employees` | `display`) que `sanitize_settings()` usa para saber qué subconjunto de claves viene en `$input` y cuáles debe preservar de `$existing = get_option(LDAP_ED_OPTION_KEY)` sin tocar. Esto generaliza el patrón que ya existe para `bind_pass` (valor ausente = conservar) a nivel de grupo de campos en vez de campo individual.

```
sanitize_settings($input):
  existing = get_option(...)
  tab = input['_tab']
  clean = existing                         // arranca como copia de lo guardado
  if tab in (connection, unknown): clean[scheme|server|port|bind_dn|bind_pass|base_ou|verify_ssl|ca_cert] = sanitize(...)
  if tab in (employees, unknown):  clean[exclude_disabled|excluded_departments|exclude_no_department] = sanitize(...)
  if tab in (display, unknown):    clean[fields|per_page|enable_search|extension_attr|department_order|cache_ttl] = sanitize(...)
  return clean
```
"unknown" (sin `_tab`, ej. un guardado programático viejo) cae en comportamiento actual: sanitiza todo, igual que hoy.

### 3. Purga de caché escopeada por pestaña, no incondicional
Hoy `sanitize_settings()` llama `purge()` en cada guardado, sin importar qué cambió. Con guardado independiente, guardar solo "Campos" (fields to show, per_page, orden, extensión) no cambia qué se pide a LDAP — solo cómo se renderiza lo ya cacheado. Decisión: `purge()` solo se dispara cuando el tab guardado es `connection` (cambia el server/credenciales) o `employees` (cambia el filtro de exclusión, que si afecta qué se sirve al público); un guardado de `display` hace `purge()` únicamente si `extension_attr` cambió (afecta qué atributo se lee de LDAP), y no toca la caché en absoluto para el resto de los campos de esa pestaña.

### 4. Test Connection valida el formulario actual, con sanitización compartida
Se extrae la validación de campos de conexión (scheme, split de dominio del server, default de puerto) a una función global reutilizable, siguiendo el mismo precedente que los helpers de cifrado ya definidos en `ldap-staff-directory.php` (`ldap_ed_encrypt_pass`, etc.):

```php
function ldap_ed_sanitize_connection_fields( array $input, array $existing ): array
```

Tanto `LDAP_ED_Admin::sanitize_settings()` (al guardar de verdad) como `LDAP_ED_Ajax::test_connection()` (al probar sin guardar) llaman a esta misma función, evitando que las reglas de validación diverjan entre "guardar" y "probar".

`admin.js` cambia de `$.post({action, nonce})` a serializar el `<form>` de Conexión completo (`$('#ldap-ed-connection-form').serializeArray()`) y mandarlo junto con la request. El acordeón "Configuración avanzada" se implementa ocultando con CSS (`display:none` vía clase, nunca removiendo del DOM ni marcando `disabled`), para que esos campos (`verify_ssl`, `ca_cert`) sigan viajando en el `serialize()` estén o no expandidos visualmente.

Fallback de contraseña: si `bind_pass` llega vacío en el POST (el admin no lo tocó), `ldap_ed_sanitize_connection_fields()` no lo sobreescribe con cadena vacía — el handler de test desencripta y usa `ldap_ed_decrypt_pass($existing['bind_pass'])`, igual que ya hace `sanitize_settings()` hoy para el guardado real.

```
Admin escribe Server nuevo, no toca Bind Password
        │
        ▼
Click "Probar conexión" ──▶ JS: serializeArray(#ldap-ed-connection-form)
        │
        ▼
POST { action: ldap_ed_test_connection, nonce, scheme, server, port,
       bind_dn, bind_pass: "", base_ou, verify_ssl, ca_cert }
        │
        ▼
PHP: $existing = get_option(...)
     $fields = ldap_ed_sanitize_connection_fields($_POST, $existing)
     // bind_pass vacío → ldap_ed_decrypt_pass($existing['bind_pass'])
        │
        ▼
new LDAP_ED_Connector($fields)->test_connection()
        │
        ▼
wp_send_json_success/error   // refleja el Server nuevo + password guardada
```

### 5. Popovers reutilizan el patrón DOM/JS del toggle de password existente
`.ldap-ed-password-toggle` ya establece el patrón "botón pequeño junto al campo que togglea un estado" (`admin.css`, `admin.js`). Los popovers `[?]` siguen la misma convención: un botón-ícono junto al label que togglea `aria-expanded` y muestra/oculta un panel flotante (`role="tooltip"`, posicionado con JS o CSS `position:absolute` relativo al label). Se cierra con click-fuera o `Escape`, igual que cualquier disclosure widget accesible.

### 6. Callout "pedile esto a tu admin de LDAP" con plantilla copiable
El texto de la plantilla vive como string localizado en `ldapEdAdmin.i18n` (igual que el resto de mensajes de JS). El botón "Copiar solicitud para TI" usa `navigator.clipboard.writeText()` con fallback a `document.execCommand('copy')` para navegadores viejos soportados por WP, y muestra una confirmación breve (reutilizando la clase `.ldap-ed-test-result.is-success`) tras copiar.

### 7. Redirect de la pestaña activa vía query param, no sesión/transient
Cada pestaña, al guardar, redirige de vuelta a `?page=ldap-staff-directory&tab=<connection|employees|display>` en vez de depender de JS para recordar la última pestaña abierta. El aviso de rotación de salts (`maybe_show_salt_rotation_notice`) enlaza a `?page=ldap-staff-directory&tab=connection#ldap_ed_bind_pass` y el JS, al cargar, si el hash apunta a `bind_pass`, hace `focus()` en ese input después de activar la pestaña Conexión.

## Risks / Trade-offs

- **[Riesgo] Tres `<form>` distintos apuntando al mismo option key vía Settings API es un patrón menos común que el de "una página, un form".** → Mitigado con el campo oculto `_tab` y tests manuales de que guardar una pestaña no pisa las otras dos (verificar `sanitize_settings()` con los 3 casos de `_tab`).
- **[Riesgo] Duplicar la extracción de campos de conexión entre `sanitize_settings()` y `test_connection()` si no se comparte la función.** → Mitigado por la Decisión 4: una sola función `ldap_ed_sanitize_connection_fields()`.
- **[Riesgo] El acordeón "Configuración avanzada" implementado removiendo nodos del DOM (en vez de solo ocultarlos) rompería silenciosamente el `serialize()` del test de conexión** (los campos `verify_ssl`/`ca_cert` desaparecerían del payload). → Documentado explícitamente en la Decisión 4 y en `tasks.md`: el acordeón SIEMPRE debe implementarse con visibilidad CSS, nunca con inserción/remoción condicional de los inputs.
- **[Riesgo] Mensaje de confirmación de WP tras guardar ("Settings saved.") no distingue qué pestaña se guardó**, lo que puede confundir si el admin esperaba ver reflejado un cambio de otra pestaña. → Aceptado como limitación menor; se puede refinar en una iteración futura con `add_settings_error()` por pestaña si se vuelve un problema real.

## Migration Plan

No hay migración de datos — `ldap_ed_settings` conserva exactamente el mismo shape de claves (CLAUDE.md sin cambios en la tabla de "Key Settings"). Es un cambio de UI + lógica de guardado/test. Rollout normal: bump de `LDAP_ED_VERSION` + `Stable tag` en `readme.txt`, entrada de changelog, y revisar si `Features`/`FAQ` de `readme.txt` necesitan actualizarse (checklist de CLAUDE.md). Rollback: revertir los archivos de `admin/`, `includes/class-admin.php`, `includes/class-ajax.php` — no hay riesgo de pérdida de datos porque el option key no cambia de forma.

## Open Questions

- ¿El mensaje de éxito tras guardar debería diferenciarse por pestaña ("Conexión guardada" / "Filtros de empleados guardados" / "Preferencias de visualización guardadas") vía `add_settings_error()`, o alcanza con el "Settings saved." genérico de WP por ahora?
- ¿La plantilla de "Copiar solicitud para TI" debería incluir los valores ya completados en el formulario (ej. si el admin ya escribió el Server pero no el Bind DN, prellenar esa parte), o debe ser siempre un texto genérico fijo? Se asume texto fijo para la primera versión, pero vale confirmarlo antes de implementar.
