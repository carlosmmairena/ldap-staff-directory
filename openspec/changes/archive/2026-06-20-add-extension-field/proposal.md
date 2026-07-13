## Why

Las organizaciones con centrales telefónicas (PBX) necesitan mostrar extensiones internas junto al número de teléfono principal. Actualmente ambos datos coexisten de forma desorganizada en el campo `phone` (atributo LDAP `telephonenumber`), ya que no existe un campo dedicado para extensiones.

## What Changes

- Se agrega `extension` como nuevo campo visualizable en el directorio, independiente de `phone`.
- Se agrega el setting **"Extension attribute"** en la sección Display del admin: campo de texto donde el admin especifica el atributo LDAP que contiene la extensión (default: `ipPhone`).
- La extensión se renderiza como texto plano (sin link `tel:`), a diferencia de `phone`.
- Los contactos pueden tener: solo `phone`, solo `extension`, o ambos campos de forma independiente.
- El campo `extension` se integra en shortcode, Elementor y Beaver Builder con el mismo patrón que los campos existentes.

## Capabilities

### New Capabilities

- `extension-field`: Campo de extensión telefónica configurable — atributo LDAP configurable en el admin (sección Display), renderizado como texto plano, compatible con AD (`ipPhone`), Samba, OpenLDAP y esquemas custom.

### Modified Capabilities

(ninguna — el campo `phone` existente no cambia de comportamiento ni requisitos)

## Impact

- **`includes/class-ldap-connector.php`**: añadir atributo configurable a `$attributes` en `get_users()`; mapear al key `extension` en el array de usuario.
- **`includes/class-admin.php`**: nuevo setting `extension_attr` en sección Display; añadir `extension` a la lista de campos visibles.
- **`includes/class-shortcode.php`**: añadir `extension` a `$allowed_fields`.
- **`public/views/directory.php`**: renderizar `extension` como texto plano con clase `ldap-extension`.
- **`public/css/directory.css`**: estilos para `.ldap-extension`.
- **`public/js/directory.js`**: incluir `extension` en `matchesQuery()` para búsqueda client-side (cuando aplica).
- **`elementor/class-elementor-widget.php`**: añadir `extension` al control multi-select de campos.
- **`beaver-builder/class-bb-module.php`**: añadir `extension` a `fields_to_show`.
- **`ldap-staff-directory.php`**: bump de versión (1.1.2).
- **`readme.txt`**: entrada en changelog.
