## Context

`LDAP_ED_Connector::get_users()` hace una única llamada `ldap_search()` sin control de paginación. Active Directory responde con máximo 1000 entradas (política `MaxPageSize`) y retorna `LDAP_SIZELIMIT_EXCEEDED`. El código no verifica ese código de error — simplemente procesa lo recibido. Con 3000 usuarios en el directorio, el plugin muestra 1000 y nadie lo sabe.

PHP removió `ldap_control_paged_result()` en 8.0. La API correcta para PHP 7.4–8.x usa el parámetro `$controls` de `ldap_search()` (disponible desde PHP 7.3) junto con `ldap_parse_result()` para leer la cookie de respuesta.

## Goals / Non-Goals

**Goals:**
- Recuperar la totalidad de registros LDAP sin importar el límite de tamaño del servidor
- Mantener la firma pública de `get_users()`: `array|\WP_Error`
- Compatible con PHP 7.4 y PHP 8.0+
- Liberar cada result resource después de procesar la página (evitar pico de memoria innecesario)
- No introducir nuevas opciones de configuración ni cambios en UI

**Non-Goals:**
- Paginación lazy o streaming al shortcode (el array completo sigue cacheándose en WP Transient)
- Detección automática de `MaxPageSize` por servidor
- Soporte para LDAP v2 (el plugin ya requiere LDAPv3)

## Decisions

### 1. API: `$controls` en `ldap_search()` en lugar de las funciones deprecadas

**Decisión:** Usar el 9.° parámetro `$controls` de `ldap_search()` (array de controls) y `ldap_parse_result()` con su parámetro de salida `$controls` para leer la cookie de respuesta.

**Alternativa descartada:** `ldap_control_paged_result()` + `ldap_control_paged_result_response()` — removidas en PHP 8.0. El plugin soporta PHP 7.4+ y debe funcionar en PHP 8.x sin warnings ni errores.

**Flujo del loop:**
```
$cookie = '';
do {
    $controls = [[
        'oid'        => LDAP_CONTROL_PAGEDRESULTS,   // '1.2.840.113556.1.4.319'
        'iscritical' => false,
        'value'      => ['size' => 500, 'cookie' => $cookie],
    ]];

    $result = @ldap_search($conn, $base, $filter, $attrs, 0, 0, 0, LDAP_DEREF_NEVER, $controls);
    if (!$result) break;

    $entries = ldap_get_entries($conn, $result);
    // … append to $users[]

    $response_controls = [];
    ldap_parse_result($conn, $result, $errcode, $matcheddn, $errmsg, $referrals, $response_controls);
    $cookie = $response_controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';

    ldap_free_result($result);
} while ('' !== $cookie);
```

### 2. `iscritical => false` para máxima compatibilidad

**Decisión:** No marcar el control como crítico. Si el servidor no implementa RFC 2696, ignora el control y devuelve lo que pueda (degradación al comportamiento actual). Si está marcado como crítico y el servidor no lo soporta, falla la búsqueda completa.

**Trade-off:** Un servidor que ignora el control podría seguir truncando resultados. Dado que AD y OpenLDAP modernos soportan paged results, la degradación silenciosa solo afectaría servidores LDAP muy antiguos — menos mala que un error fatal en producción.

### 3. Tamaño de página: 500

**Decisión:** 500 entradas por página. AD soporta hasta 1000; usar 500 es conservador y compatible con configuraciones de OpenLDAP más restrictivas.

Para 3000 usuarios: 6 iteraciones en la misma conexión TCP, sin reconexión entre páginas.

### 4. `ldap_free_result()` por iteración

**Decisión:** Llamar `ldap_free_result($result)` al final de cada iteración del loop antes de continuar.

**Por qué:** Sin esto, PHP acumula el resource interno de la búsqueda LDAP mientras crece el array `$users`. Para 3000 entradas divididas en 6 páginas de 500, el peak de RAM sería ~3 recursos LDAP simultáneos (ventana de GC PHP) + el array completo. Liberando explícitamente se garantiza que solo hay 1 resource activo a la vez.

### 5. Sin nueva opción de configuración

**Decisión:** El tamaño de página (500) es una constante de implementación, no un setting del admin. La complejidad de configurar `MaxPageSize` por servidor no tiene valor para el usuario final — 500 funciona en todos los casos de uso.

## Risks / Trade-offs

| Riesgo | Mitigación |
|---|---|
| Servidor LDAP no soporta paged results (muy raro en AD/OpenLDAP modernos) | `iscritical => false` hace que el servidor ignore el control y siga funcionando con su límite existente — sin regresión |
| 6 request LDAP en vez de 1 en cache miss | El tiempo extra es ~500ms–1s más; solo ocurre cuando el TTL expira. El stale fallback sigue activo si el servidor cae durante el loop |
| `ldap_parse_result()` devuelve controls vacíos si el servidor no los soporta | El fallback `?? ''` termina el loop normalmente después de la primera página |
| AD devuelve cookie no vacía incluso en la última página (edge case) | El loop termina cuando `$entries['count'] === 0` o cuando la cookie llega vacía — el primero actúa como guard adicional |
| `LDAP_CONTROL_PAGEDRESULTS` no está definida en instalaciones PHP sin extensión LDAP | La extensión LDAP ya es un prerequisito del plugin (hay admin notice si falta) — no hay regresión nueva |
