## ADDED Requirements

### Requirement: Recuperación paginada de registros LDAP
`LDAP_ED_Connector::get_users()` SHALL utilizar el control RFC 2696 (`LDAP_CONTROL_PAGEDRESULTS`) para recuperar todos los registros en páginas de 500 entradas, iterando hasta que el servidor devuelva una cookie vacía. El resultado SHALL ser el conjunto completo de usuarios sin truncación por límite de servidor.

#### Scenario: Directorio con más de 1000 usuarios en AD
- **WHEN** el servidor LDAP es Active Directory con `MaxPageSize=1000` y existen 3000 usuarios habilitados
- **THEN** `get_users()` retorna un array con los 3000 usuarios (6 iteraciones de 500 cada una), sin `WP_Error`

#### Scenario: Directorio con exactamente el límite de página
- **WHEN** el servidor tiene exactamente 500 usuarios
- **THEN** `get_users()` retorna los 500 usuarios en una sola iteración y termina al recibir cookie vacía

#### Scenario: Directorio con menos de 500 usuarios
- **WHEN** hay 120 usuarios en el directorio
- **THEN** la primera iteración retorna los 120 y el loop termina; comportamiento idéntico al actual

### Requirement: Compatibilidad PHP 7.4–8.x sin funciones deprecadas
La implementación SHALL usar únicamente la constante `LDAP_CONTROL_PAGEDRESULTS` y el parámetro `$controls` de `ldap_search()`. No SHALL usar `ldap_control_paged_result()` ni `ldap_control_paged_result_response()`.

#### Scenario: Ejecución en PHP 8.0
- **WHEN** el plugin corre en PHP 8.0 o superior
- **THEN** no se producen fatal errors ni deprecation warnings relacionados con la extensión LDAP

#### Scenario: Ejecución en PHP 7.4
- **WHEN** el plugin corre en PHP 7.4
- **THEN** la búsqueda paginada funciona correctamente sin warnings

### Requirement: Degradación cuando el servidor no soporta paged results
Si el servidor LDAP ignora el control de paginación (porque no lo soporta), `get_users()` SHALL continuar retornando los registros disponibles (hasta el límite nativo del servidor) sin error fatal. El control SHALL enviarse con `iscritical = false`.

#### Scenario: Servidor LDAP sin soporte a RFC 2696
- **WHEN** el servidor ignora el control `LDAP_CONTROL_PAGEDRESULTS` y devuelve sus resultados directamente sin cookie
- **THEN** el loop termina tras la primera iteración (cookie vacía en respuesta), retornando lo que el servidor proporcionó

### Requirement: Liberación de resources por iteración
Después de procesar cada página de resultados, `get_users()` SHALL llamar `ldap_free_result()` sobre el resource de esa iteración antes de iniciar la siguiente.

#### Scenario: Liberación de resource en cada iteración del loop
- **WHEN** se procesan 6 páginas de 500 usuarios
- **THEN** solo hay 1 resource LDAP activo a la vez durante el loop; los anteriores quedan liberados

### Requirement: API pública sin cambios
La firma y el contrato de `get_users()` SHALL mantenerse igual: devuelve `array` con éxito o `\WP_Error` en caso de fallo en connect, bind o primera búsqueda. El caller (shortcode, caché) no requiere modificación.

#### Scenario: Error en bind, comportamiento heredado
- **WHEN** `ldap_bind()` falla por credenciales incorrectas
- **THEN** `get_users()` retorna un `\WP_Error` con código `ldap_bind_failed`, igual que antes de este cambio
