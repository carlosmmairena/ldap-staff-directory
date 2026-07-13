## Why

La extensión telefónica (`extension_attr`) no se visualiza cuando el admin escribe el nombre del atributo LDAP en camelCase (ej. `ipPhone`), porque `ldap_get_entries()` en PHP normaliza todos los nombres de atributo a minúscula en el array devuelto. El texto de ayuda en el admin indica "must match exactly, e.g. ipPhone" — instrucción incorrecta que contradice el comportamiento real.

## What Changes

- En `includes/class-ldap-connector.php`, aplicar `strtolower()` al nombre del atributo de extensión **antes** de usarlo como clave de lectura en el array de entrada LDAP (dentro del loop de `get_users()`).
- La variable que se pasa a `ldap_search()` en `$attributes` puede mantener el case original (los servidores LDAP aceptan cualquier case en la solicitud).
- El texto de ayuda del campo `extension_attr` en `includes/class-admin.php` se actualiza para indicar que el case no importa.

## Capabilities

### New Capabilities

(ninguna — es una corrección de bug, no una nueva capacidad)

### Modified Capabilities

- `extension-field`: El requisito de lectura del campo extension cambia: el nombre del atributo LDAP SHALL normalizarse a minúscula antes de leer la entrada, haciéndolo case-insensitive para el admin.

## Impact

- **`includes/class-ldap-connector.php`**: Una línea — `strtolower()` sobre `$ext_attr` antes del loop de lectura de entradas.
- **`includes/class-admin.php`**: Actualizar el texto de ayuda del campo `extension_attr` para indicar que el case es indiferente.
