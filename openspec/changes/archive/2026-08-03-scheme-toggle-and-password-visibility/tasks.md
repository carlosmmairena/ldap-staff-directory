## 1. Helper compartido y connector

- [x] 1.1 Implementar `ldap_ed_split_server_scheme( string $raw_server ): array` en `ldap-staff-directory.php`, junto a los demás helpers globales. Despoja (case-insensitive, con `trim()`) un prefijo `ldap://`/`ldaps://`/`http://`/`https://` si existe. Devuelve `['scheme' => 'ldap'|'ldaps'|null, 'domain' => string]`.
- [x] 1.2 En `LDAP_ED_Connector::connect()`, reconstruir la URI de conexión como `scheme + '://' + domain`, usando `ldap_ed_split_server_scheme()` sobre el `server` guardado para obtener `domain` (despojando cualquier prefijo heredado) y el nuevo setting `scheme` (con fallback al `scheme` inferido por el helper, y de ahí a `'ldaps'`).
- [x] 1.3 Agregar `scheme` a los defaults del constructor de `LDAP_ED_Connector` (default `''`, resuelto en cascada dentro de `connect()` — no se hardcodea `'ldaps'` ahí para no romper la inferencia del esquema heredado).

## 2. Admin — settings, sanitización, render

- [x] 2.1 Registrar el nuevo campo `scheme` vía `add_settings_field()` en `register_settings()` (sección `connection`, `label_for`, posicionado antes del campo Server).
- [x] 2.2 Implementar `render_field_scheme()`: `<select>` con opciones "LDAP"/"LDAPS", preseleccionado según `get_option('scheme')` si existe, si no según `ldap_ed_split_server_scheme(get_option('server'))['scheme']`, si no `'ldaps'` (centralizado en el helper `get_effective_scheme()`, reutilizado también por `render_field_port()`).
- [x] 2.3 Actualizar `render_field_server()` para mostrar solo `ldap_ed_split_server_scheme($valor_guardado)['domain']`, nunca el prefijo.
- [x] 2.4 Cambiar la etiqueta de `server` en `$connection_text_fields` (dentro de `register_settings()`) de "LDAPS Server" a "Server".
- [x] 2.5 Actualizar `render_field_port()`: si el puerto guardado es `636`/`389` o no existe, renderizar el input vacío con `placeholder` igual al default del `scheme` actual (`636`/`389`); cualquier otro valor guardado se renderiza con `value` real.
- [x] 2.6 En `sanitize_settings()`: sanitizar `scheme` contra whitelist `['ldap','ldaps']` (default `'ldaps'`); sanitizar `server` con `ldap_ed_split_server_scheme()` para descartar cualquier prefijo que el admin haya pegado por costumbre, guardando solo el dominio. `sanitize_ldap_server()` se eliminó por completo (quedaba sin uso con el nuevo modelo). También se corrigió la comparación de `connection_changed` para comparar dominio-contra-dominio y no disparar una limpieza espuria del snapshot de departamentos en el primer guardado post-actualización.
- [x] 2.7 En `sanitize_settings()`: tratar `port` vacío (`''`) como "usar el default del `scheme` de este mismo submit" (636/389), no como `absint('') === 0`.
- [x] 2.8 Implementar el botón-ícono de mostrar/ocultar en `render_field_bind_pass()`, junto al `<input type="password">` existente (sin tocar el resto de la lógica del campo — sigue naciendo vacío).

## 3. Admin — JS

- [x] 3.1 En `admin/js/admin.js`, agregar el listener de `change` sobre el `<select>` de esquema: actualiza el `placeholder` del campo de puerto al default del nuevo esquema (636/389).
- [x] 3.2 **Revisado durante implementación:** el listener nunca toca `value`, solo `placeholder` — más simple y fiel al pedido original que la heurística "default-vs-personalizado" planteada en el diseño inicial. `design.md` y el spec de `ldap-connection-scheme` se actualizaron para reflejar esto.
- [x] 3.3 Agregar el listener de `click` sobre el botón de mostrar/ocultar contraseña: alterna `type="password"`/`type="text"` del input y el ícono (`eye`/`eye-off`).

## 4. Estilos

- [x] 4.1 En `admin/css/admin.css`, agregar los estilos del botón-ícono de mostrar/ocultar contraseña, reutilizando el patrón de ícono SVG inline vía `::before` que ya usa `.ldap-ed-test-result`.

## 5. Metadatos del plugin

- [x] 5.1 Bump de `LDAP_ED_VERSION` (constante + header del plugin) y `Stable tag` en `readme.txt` (1.1.4).
- [x] 5.2 Agregar entrada en `== Changelog ==` de `readme.txt` describiendo el selector de esquema, el placeholder dinámico de puerto, y el toggle de visibilidad de contraseña.

## 6. Verificación manual

- [x] 6.1 Con una instalación de prueba que tenga `server` guardado en el formato antiguo (`"ldaps://host"`), abrir Settings sin guardar nada y confirmar: el `<select>` de esquema muestra "LDAPS", el campo Server muestra solo el dominio, y "Test Connection" conecta correctamente (sin URI duplicada).
- [x] 6.2 Repetir 6.1 con un `server` guardado como `"ldap://host"` y confirmar que el `<select>` infiere "LDAP", no "LDAPS".
- [x] 6.3 Guardar el formulario sin cambios desde el estado de 6.1/6.2 y confirmar que `server`/`scheme` quedan en el formato nuevo (dominio solo, `scheme` explícito).
- [x] 6.4 Con el puerto vacío (placeholder visible), cambiar el selector de esquema varias veces y confirmar que el placeholder alterna 636/389 sin necesidad de guardar.
- [x] 6.5 Escribir un puerto personalizado (ej. `3269`), cambiar el selector de esquema, y confirmar que el valor del puerto no se sobreescribe.
- [x] 6.6 Guardar con el puerto vacío y confirmar que se guarda como `636`/`389` según el esquema, no como `0`.
- [x] 6.7 Escribir una contraseña de bind nueva, alternar el botón de mostrar/ocultar varias veces, y confirmar que el ícono y el tipo de input cambian correctamente.
- [x] 6.8 Con una contraseña ya guardada, recargar Settings y confirmar que el campo sigue naciendo vacío (el toggle no expone la contraseña guardada).
