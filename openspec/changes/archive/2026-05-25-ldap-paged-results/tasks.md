## 1. Refactorizar `get_users()` en `class-ldap-connector.php`

- [x] 1.1 Reemplazar la llamada única `@ldap_search()` y el bloque `ldap_get_entries()` + loop `for` por un `do-while` que incluya en cada iteración: construir `$controls` con `LDAP_CONTROL_PAGEDRESULTS` (oid, iscritical=false, value={size:500, cookie:$cookie}), llamar `@ldap_search()` con el 9.° parámetro `$controls`, y verificar que el resultado no sea falso antes de continuar
- [x] 1.2 Dentro del loop, después de `ldap_get_entries()`, agregar guard de salida: `if (0 === $entries['count']) { ldap_free_result($result); break; }` para evitar loop infinito en servidores que no retornan cookie de terminación
- [x] 1.3 Después del guard, llamar `ldap_parse_result($this->connection, $result, $errcode, $matcheddn, $errmsg, $referrals, $response_controls)` para leer los controles de respuesta; extraer la cookie con `$cookie = $response_controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? ''`
- [x] 1.4 Llamar `ldap_free_result($result)` al final de cada iteración (después de extraer la cookie) para liberar el resource LDAP antes de la siguiente página
- [x] 1.5 Verificar que la condición del `do-while` sea `'' !== $cookie` para que el loop termine cuando el servidor retorne cookie vacía
- [x] 1.6 Confirmar que el silenciador `@` se mantiene en `@ldap_search()` (WPCS) y que el path de error (`if (!$result)`) retorna `WP_Error` con `ldap_error()` al igual que antes, solo en la primera iteración fallida

## 2. Bump de versión y changelog

- [x] 2.1 Incrementar `LDAP_ED_VERSION` en `ldap-staff-directory.php` (header del plugin + constante)
- [x] 2.2 Actualizar `Stable tag` en `readme.txt`
- [x] 2.3 Añadir entrada en `== Changelog ==` de `readme.txt` describiendo la búsqueda paginada LDAP y el soporte para directorios con más de 1000 usuarios
