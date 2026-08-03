## ADDED Requirements

### Requirement: Barra de filtros por departamento
El shortcode SHALL renderizar una barra de chips de departamento cuando `enable_search` es `'1'` y existen 2 o más departamentos únicos en el array completo de empleados en caché. El orden de los chips de departamento (excluyendo el chip "All", que siempre es primero) SHALL respetar la configuración `department_order`: `'alpha'` (orden alfabético ascendente, comportamiento por defecto) o `'count_desc'` (orden descendente por cantidad de empleados en ese departamento).

#### Scenario: Renderizado con múltiples departamentos y orden alfabético
- **WHEN** el shortcode se renderiza, hay 3 departamentos distintos en caché y `department_order` es `'alpha'`
- **THEN** se muestra `.ldap-dept-filters` con un chip "All (N)" seguido de un chip por cada departamento ordenado alfabéticamente

#### Scenario: Renderizado con orden por cantidad de contactos
- **WHEN** el shortcode se renderiza, hay departamentos con conteos `Ventas: 3`, `TI: 8`, `RRHH: 5` en caché y `department_order` es `'count_desc'`
- **THEN** los chips se muestran en el orden "TI (8)", "RRHH (5)", "Ventas (3)", después del chip "All"

#### Scenario: Sin barra cuando hay un solo departamento
- **WHEN** todos los empleados pertenecen al mismo departamento
- **THEN** `.ldap-dept-filters` no se renderiza

#### Scenario: Sin barra cuando search está desactivado
- **WHEN** `enable_search` es `'0'` en la configuración
- **THEN** `.ldap-dept-filters` no se renderiza

#### Scenario: Valor por defecto preserva comportamiento actual
- **WHEN** `department_order` no está guardado aún (plugin recién actualizado)
- **THEN** se asume `'alpha'` y el orden es idéntico al comportamiento previo a este cambio

### Requirement: Chip "All" siempre primero
El chip "All" SHALL ser el primer chip de la barra y SHALL mostrar el total de empleados del directorio completo (sin ningún filtro aplicado).

#### Scenario: Chip All muestra total global
- **WHEN** hay 312 empleados en caché y se está filtrando por "Engineering"
- **THEN** el chip "All" muestra "All (312)", no "All (47)"

#### Scenario: Chip All activo en estado por defecto
- **WHEN** no hay `ldap_dept` en la URL
- **THEN** el chip "All" tiene la clase `is-active`

### Requirement: Chip de departamento muestra nombre y conteo
Cada chip de departamento SHALL mostrar el nombre del departamento y entre paréntesis el número de empleados de ese departamento en el dataset completo (sin filtros).

#### Scenario: Conteo correcto por departamento
- **WHEN** Engineering tiene 47 empleados en el total
- **THEN** el chip muestra "Engineering (47)" independientemente de si hay un filtro de búsqueda activo

### Requirement: Activación y limpieza del filtro
Al hacer clic en un chip de departamento, el directorio SHALL filtrar los empleados por ese departamento navegando a la misma URL con `?ldap_dept=<nombre>&ldap_page=1`. El chip activo SHALL mostrar una × que al clicar elimina `ldap_dept` de la URL.

#### Scenario: Clic en chip inactivo activa el filtro
- **WHEN** el usuario hace clic en el chip "RRHH (23)"
- **THEN** la página navega a `?ldap_dept=RRHH&ldap_page=1` y solo se muestran empleados de RRHH

#### Scenario: Clic en × limpia el filtro
- **WHEN** "Engineering" está activo y el usuario hace clic en la ×
- **THEN** la página navega sin `ldap_dept` en la URL y se muestran todos los empleados

#### Scenario: Clic en chip ya activo no cambia estado
- **WHEN** el usuario hace clic en el chip que ya está activo (sin ×)
- **THEN** la URL navega al mismo destino (recargar sin cambio de filtro); la × es el control explícito de limpieza

### Requirement: Scroll horizontal implícito
La barra de chips SHALL soportar scroll horizontal mediante CSS (`overflow-x: auto; white-space: nowrap`) sin JS adicional. Los chips que no caben en el ancho visible SHALL quedar parcialmente visibles para indicar contenido adicional.

#### Scenario: Muchos departamentos en pantalla estrecha
- **WHEN** hay 15 departamentos y el viewport es de 390px
- **THEN** los chips se desbordan horizontalmente y el contenedor permite scroll sin scroll vertical

### Requirement: Sanitización del parámetro ldap_dept
El valor de `$_GET['ldap_dept']` SHALL ser sanitizado con `sanitize_text_field()` antes de cualquier uso. La comparación contra los valores de departamento SHALL ser case-insensitive (`strcasecmp` o `strtolower`).

#### Scenario: Parámetro con caracteres especiales
- **WHEN** `ldap_dept` contiene HTML (`<script>alert(1)</script>`)
- **THEN** `sanitize_text_field()` elimina las etiquetas y el valor procesado es texto plano; no se produce output sin escapar
