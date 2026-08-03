## Why

Hoy el esquema de conexión (`ldap://`/`ldaps://`) vive embebido como texto libre dentro del campo "LDAPS Server", y el puerto es un número fijo sin relación visible con el esquema elegido — el admin tiene que saber de memoria que LDAPS usa 636 y LDAP usa 389, y puede terminar con un puerto incorrecto sin ningún aviso. Además, la contraseña de bind nunca puede verse mientras se escribe, lo que dificulta detectar errores de tipeo antes de guardar.

## What Changes

- Nuevo control `<select>` para elegir el esquema de conexión (LDAP/LDAPS), separado del campo de servidor.
- **BREAKING (modelo de datos, con compatibilidad automática):** el campo "Server" pasa a aceptar solo el dominio/host (ej. `directory.example.com`), sin el prefijo de esquema. El esquema se guarda en un setting nuevo (`scheme`). Instalaciones existentes con un `server` guardado en el formato antiguo (`"ldaps://host"`) se migran de forma transparente, sin intervención del admin y sin romper la conexión.
- El campo de puerto usa un placeholder nativo (636/389 según el esquema elegido) en vez de un valor precargado, mientras el admin no haya escrito nada. Una vez que escribe un puerto, el selector de esquema deja de tocarlo — incluyendo puertos ya guardados que coincidan exactamente con 636 o 389 (se tratan como "sin personalizar"; cualquier otro valor se protege siempre).
- Botón de mostrar/ocultar (ícono de ojo) junto al campo de contraseña de bind, para verificar lo que se está escribiendo antes de guardar. Solo afecta la contraseña que el admin está tecleando — la contraseña ya guardada nunca se envía al navegador (comportamiento existente sin cambios).
- Renombrar la etiqueta del campo de "LDAPS Server" a "Server" (ya no es específico de un esquema).

## Capabilities

### New Capabilities
- `ldap-connection-scheme`: selector de esquema LDAP/LDAPS, campo de servidor solo-dominio, placeholder dinámico de puerto, y migración automática de instalaciones con el formato de servidor anterior.
- `bind-password-visibility`: alternar la visibilidad del campo de contraseña de bind mientras se edita.

### Modified Capabilities
*(ninguna — no hay specs existentes que cubran los campos de conexión LDAP)*

## Impact

- **Código afectado:** `includes/class-admin.php` (nuevo `render_field_scheme()`, cambios en `render_field_server()`, `render_field_port()`, `render_field_bind_pass()`, y `sanitize_settings()`), `includes/class-ldap-connector.php` (`connect()` debe reconstruir la URI a partir de `scheme` + `server`, despojando cualquier prefijo heredado), `ldap-staff-directory.php` (nuevo helper compartido para separar esquema/dominio y para el default inferido), `admin/js/admin.js` (listener del select de esquema, listener del botón de mostrar/ocultar contraseña), `admin/css/admin.css` (ícono del botón de contraseña).
- **Nueva opción de settings:** `scheme` (`'ldap'`\|`'ldaps'`, default `'ldaps'`).
- **Compatibilidad hacia atrás:** ninguna instalación existente pierde conectividad — el connector siempre despoja un prefijo heredado de `server` antes de reconstruir la URI, y el esquema por defecto se infiere de ese prefijo cuando `scheme` aún no existe como setting.
- **Referencia visual:** `docs/design-admin-ui.pen` (mockup explorado antes de esta propuesta).
