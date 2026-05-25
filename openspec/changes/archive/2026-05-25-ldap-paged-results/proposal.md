## Why

`ldap_search()` sin control de paginación está limitado por la política `MaxPageSize` del servidor (Active Directory: 1000 por defecto, OpenLDAP: configurable, a veces 500). Organizaciones con más de 1000 empleados reciben un directorio silenciosamente truncado sin ningún aviso — el plugin muestra 1000 de 3000 registros y tanto el admin como el visitante lo creen completo.

## What Changes

- **Paged results en `get_users()`**: reemplaza la llamada única a `ldap_search()` por un loop do-while que usa el control RFC 2696 (`LDAP_CONTROL_PAGEDRESULTS`) hasta agotar la cookie de paginación del servidor.
- **Admin notice en truncación**: si al terminar el loop el servidor reportó `LDAP_SIZELIMIT_EXCEEDED` (lo que indicaría que el servidor no respetó el control), se incluye ese contexto en el `WP_Error` para que el admin reciba visibilidad.
- **Liberación de memoria por página**: `ldap_free_result()` al final de cada iteración para no acumular estructuras LDAP internas junto con el array PHP creciente.
- **Sin cambios de API pública**: `get_users()` sigue devolviendo `array|WP_Error`. Nada más cambia.

## Capabilities

### New Capabilities

- `ldap-paged-search`: Recuperación de todos los registros LDAP mediante paginación RFC 2696, sin límite de tamaño de servidor, compatible con AD, OpenLDAP y Samba.

### Modified Capabilities

*(ninguna — el contrato externo de `get_users()` no cambia)*

## Impact

- **`includes/class-ldap-connector.php`**: único archivo modificado. Método `get_users()` convertido a loop paginado. El resto de la clase (connect, bind, disconnect, test_connection) no cambia.
- **Sin cambios en caché, template, CSS ni JS**: el resultado sigue siendo el mismo array de usuarios que se almacena en el transient de WP.
- **Compatibilidad PHP**: usa `LDAP_CONTROL_PAGEDRESULTS` (constante desde PHP 7.3) y el parámetro `$controls` de `ldap_search()` (disponible desde PHP 7.3) — sin funciones deprecadas ni removidas.
- **Sin nuevas dependencias**: todo en la extensión `ldap` de PHP que ya se requiere.
