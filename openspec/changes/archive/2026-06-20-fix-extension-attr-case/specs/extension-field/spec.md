## MODIFIED Requirements

### Requirement: Lectura del campo extension desde LDAP
El conector LDAP SHALL incluir el atributo configurado en `extension_attr` en cada búsqueda `ldap_search`. El nombre del atributo SHALL normalizarse a minúscula antes de usarse como clave de lectura en el array de entradas LDAP devuelto por `ldap_get_entries()`, dado que PHP normaliza todos los nombres de atributo a minúscula. El resultado SHALL mapearse al key `extension` en el array de usuario devuelto por `get_users()`. Si el atributo no existe en la entrada LDAP o está vacío, el valor SHALL ser una cadena vacía `''`.

#### Scenario: Usuario con extensión presente
- **WHEN** una entrada LDAP tiene el atributo configurado (ej. `ipPhone: 1234`)
- **THEN** el array de usuario incluye `'extension' => '1234'`

#### Scenario: Usuario sin extensión
- **WHEN** una entrada LDAP no tiene el atributo configurado o está vacío
- **THEN** el array de usuario incluye `'extension' => ''`

#### Scenario: Atributo LDAP inexistente en el esquema
- **WHEN** el atributo configurado no existe en el esquema LDAP del servidor
- **THEN** todos los usuarios tendrán `'extension' => ''` y el directorio se renderiza sin errores

#### Scenario: Atributo configurado en camelCase
- **WHEN** el admin configura `extension_attr = 'ipPhone'` (camelCase)
- **THEN** la extensión se visualiza correctamente en el directorio (equivalente a haber escrito `ipphone`)

#### Scenario: Atributo configurado en mayúsculas
- **WHEN** el admin configura `extension_attr = 'IPPHONE'`
- **THEN** la extensión se visualiza correctamente en el directorio

## MODIFIED Requirements

### Requirement: Extension attribute setting
El sistema SHALL permitir al administrador configurar el nombre del atributo LDAP del que se leerá la extensión telefónica. El setting `extension_attr` SHALL residir en la sección Display del admin y tener como valor por defecto `ipPhone`. El valor SHALL sanitizarse con `sanitize_text_field()`. El campo de ayuda SHALL indicar que el case del nombre del atributo es indiferente.

#### Scenario: Admin guarda atributo por defecto
- **WHEN** el admin accede por primera vez a la sección Display sin haber configurado `extension_attr`
- **THEN** el campo muestra el valor `ipPhone` como placeholder o valor por defecto

#### Scenario: Admin guarda atributo custom
- **WHEN** el admin escribe `extensionAttribute1` en el campo Extension attribute y guarda
- **THEN** el plugin usa `extensionAttribute1` como atributo LDAP en las búsquedas subsiguientes

#### Scenario: Admin guarda atributo vacío
- **WHEN** el admin borra el valor del campo Extension attribute y guarda
- **THEN** el sistema usa el fallback `ipPhone` como valor efectivo del atributo

#### Scenario: Purge de caché al cambiar atributo
- **WHEN** el admin guarda un nuevo valor de `extension_attr`
- **THEN** la caché LDAP se purga (transient + stale) para que los datos reflejen el nuevo atributo
