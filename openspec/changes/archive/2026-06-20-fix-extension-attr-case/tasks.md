## 1. Bug Fix — LDAP Connector

- [x] 1.1 En `includes/class-ldap-connector.php`, en `get_users()`, extraer la variable `$ext_attr_key = strtolower( $ext_attr )` y usarla (en lugar de `$ext_attr`) al llamar a `get_entry_value()` para mapear el campo `extension`

## 2. Admin — Texto de Ayuda

- [x] 2.1 En `includes/class-admin.php`, actualizar el texto de ayuda de `render_field_extension_attr()` para indicar que el case del nombre del atributo es indiferente (ej. `ipPhone`, `IPPHONE` e `ipphone` son equivalentes)
