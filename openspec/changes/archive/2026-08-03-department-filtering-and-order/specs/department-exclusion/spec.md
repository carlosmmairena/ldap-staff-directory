## ADDED Requirements

### Requirement: Descubrimiento de departamentos independiente de las exclusiones
`LDAP_ED_Connector::get_departments()` SHALL ejecutar una búsqueda LDAP paginada que use el mismo filtro base que `get_users()` (`objectClass=person`, `mail=*`, `+exclude_disabled` si está activo) pero SHALL ignorar por completo `excluded_departments` y `exclude_no_department` al construir su filtro, de modo que siempre devuelva el universo completo de departamentos existentes, incluso los ya excluidos de la UI pública.

#### Scenario: Departamento previamente excluido sigue siendo descubierto
- **WHEN** "RRHH" está en `excluded_departments` y el admin dispara el descubrimiento
- **THEN** "RRHH" aparece en el resultado con su conteo real, permitiendo des-excluirlo

#### Scenario: Conteo de empleados sin departamento
- **WHEN** existen empleados con el atributo `department` vacío o ausente que cumplen el resto del filtro (`mail=*`, `+exclude_disabled`)
- **THEN** el resultado incluye un conteo separado de esos empleados, distinto del listado de departamentos con nombre

#### Scenario: Directorio grande pagina correctamente
- **WHEN** el directorio tiene más entradas que el límite de página del servidor LDAP (ej. 1000 en Active Directory)
- **THEN** `get_departments()` recorre todas las páginas usando `LDAP_CONTROL_PAGEDRESULTS` (RFC 2696), igual que `get_users()`, y el conteo final refleja el total real

### Requirement: Acción AJAX de descubrimiento restringida a administradores
La acción AJAX `ldap_ed_get_departments` SHALL verificar el nonce `ldap_ed_admin_nonce` y la capacidad `manage_options` antes de ejecutar `get_departments()`. Al completarse con éxito, SHALL persistir el resultado en la opción `ldap_ed_known_departments` y responder `wp_send_json_success` con el listado y el conteo sin departamento. En caso de error de conexión LDAP, SHALL responder `wp_send_json_error` con el mensaje de error.

#### Scenario: Solicitud sin nonce válido
- **WHEN** se invoca `ldap_ed_get_departments` sin un nonce válido
- **THEN** la solicitud se rechaza antes de contactar LDAP

#### Scenario: Descubrimiento exitoso persiste snapshot
- **WHEN** un administrador autenticado dispara el descubrimiento y la conexión LDAP responde correctamente
- **THEN** `ldap_ed_known_departments` se sobrescribe con el resultado más reciente y la respuesta JSON incluye el listado actualizado

#### Scenario: LDAP inaccesible durante el descubrimiento
- **WHEN** la conexión LDAP falla al ejecutar el descubrimiento
- **THEN** la opción `ldap_ed_known_departments` NO se modifica y la respuesta es un error con el mensaje de LDAP

### Requirement: Checklist de exclusión en el admin
La página de settings SHALL renderizar, a partir del snapshot en `ldap_ed_known_departments`, un checklist con un checkbox por departamento descubierto (nombre + conteo), pre-marcado según los valores guardados en `excluded_departments`. Cuando no exista snapshot, SHALL mostrarse un estado vacío junto con el botón de actualización. El botón "Actualizar lista de departamentos" SHALL estar visible siempre, dispare o no la carga inicial.

#### Scenario: Primera visita sin snapshot previo
- **WHEN** el admin abre la página de settings y `ldap_ed_known_departments` no existe
- **THEN** se muestra un mensaje de estado vacío y el botón de actualización, sin checkboxes

#### Scenario: Checklist refleja exclusiones guardadas
- **WHEN** existe un snapshot y `excluded_departments` contiene `["RRHH"]`
- **THEN** el checkbox correspondiente a "RRHH" aparece marcado y el resto sin marcar

#### Scenario: Actualización manual reemplaza el checklist
- **WHEN** el admin hace clic en "Actualizar lista de departamentos" y la llamada AJAX responde con éxito
- **THEN** el checklist se re-renderiza con los departamentos recién descubiertos, preservando las marcas de exclusión ya guardadas para los departamentos que siguen existiendo

### Requirement: Control separado para empleados sin departamento
La configuración `exclude_no_department` (`'0'`/`'1'`) SHALL controlar, como un checkbox independiente del checklist de departamentos con nombre, si los empleados sin el atributo `department` poblado se excluyen del directorio público. SHALL mostrarse junto al conteo de empleados sin departamento obtenido del último snapshot.

#### Scenario: Checkbox de "sin departamento" visualmente separado
- **WHEN** se renderiza la página de settings con un snapshot disponible
- **THEN** el checkbox de "sin departamento asignado" se muestra en una fila distinta del checklist de departamentos con nombre, junto a su conteo

#### Scenario: Valor por defecto preserva comportamiento actual
- **WHEN** el plugin se actualiza y `exclude_no_department` no está guardado aún
- **THEN** se asume `'0'` y los empleados sin departamento se siguen mostrando, igual que antes de este cambio

### Requirement: Exclusión aplicada en el filtro de búsqueda LDAP
`LDAP_ED_Connector::get_users()` SHALL incorporar al filtro de búsqueda una cláusula `(!(department=<valor escapado>))` por cada valor en `excluded_departments`, y una cláusula `(department=*)` cuando `exclude_no_department` es `'1'`, de modo que los empleados excluidos nunca sean devueltos por el servidor LDAP ni lleguen al plugin.

#### Scenario: Departamento excluido no aparece en resultados
- **WHEN** `excluded_departments` contiene `["RRHH"]` y se ejecuta `get_users()`
- **THEN** ningún empleado con `department=RRHH` está presente en el array devuelto, ni en el conteo de `extract_departments()`, ni en el caché resultante

#### Scenario: Exclusión de empleados sin departamento
- **WHEN** `exclude_no_department` es `'1'`
- **THEN** el filtro de búsqueda exige `(department=*)` y ningún empleado sin ese atributo aparece en los resultados

#### Scenario: Valor de departamento con caracteres especiales de filtro LDAP
- **WHEN** un valor en `excluded_departments` contiene paréntesis, `*` o `\`
- **THEN** el valor se escapa con `ldap_escape( $valor, '', LDAP_ESCAPE_FILTER )` antes de insertarse en el filtro, evitando sintaxis LDAP inválida o inyección

#### Scenario: Sin exclusiones configuradas
- **WHEN** `excluded_departments` está vacío y `exclude_no_department` es `'0'`
- **THEN** el filtro de búsqueda es idéntico al comportamiento previo a este cambio

### Requirement: Sanitización de las exclusiones al guardar settings
`sanitize_settings()` SHALL sanitizar cada valor de `excluded_departments` con `sanitize_text_field()`, descartar valores vacíos y duplicados, y normalizar `exclude_no_department` a `'0'`/`'1'`. SHALL además limpiar la opción `ldap_ed_known_departments` cuando `server`, `bind_dn` o `base_ou` cambian respecto al valor previamente guardado.

#### Scenario: Cambio de servidor invalida el snapshot
- **WHEN** el admin guarda settings con un `server` distinto al previamente guardado
- **THEN** `ldap_ed_known_departments` se elimina, forzando un nuevo descubrimiento manual antes de poder marcar exclusiones

#### Scenario: Departamento ya no existente queda huérfano sin error
- **WHEN** `excluded_departments` contiene un valor que ya no aparece en un snapshot recién actualizado
- **THEN** el valor permanece guardado sin efecto visible (no bloquea el guardado ni genera error), y el checklist simplemente no lo muestra marcado

### Requirement: Limpieza en desinstalación
`uninstall.php` SHALL eliminar la opción `ldap_ed_known_departments` para el sitio único y para cada sitio en una red multisite, junto con las demás opciones del plugin.

#### Scenario: Desinstalación en sitio único
- **WHEN** el plugin se desinstala en un sitio único
- **THEN** `ldap_ed_known_departments` ya no existe como opción de WordPress

#### Scenario: Desinstalación en multisite
- **WHEN** el plugin se desinstala en una red multisite con varios sitios activados
- **THEN** `ldap_ed_known_departments` se elimina en cada sitio de la red
