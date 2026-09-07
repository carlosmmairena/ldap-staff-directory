## ADDED Requirements

### Requirement: Vista raíz muestra el menú de departamentos
El shortcode SHALL renderizar el menú de departamentos (`department-menu.php`) cuando `$_GET['ldap_dept']` está ausente o vacío, en lugar de una grilla de empleados. No SHALL ejecutarse `filter_users()` ni `paginate_users()` para este estado. El menú SHALL incluir un encabezado agregado con el total de departamentos y el total de empleados del directorio completo (`$ldap_ed_all_count`), en el formato "$N departamentos · $M empleados".

#### Scenario: Carga inicial sin parámetros
- **WHEN** el shortcode se renderiza sin `ldap_dept` en la URL
- **THEN** se muestra el menú de departamentos y no se renderiza ninguna `.ldap-staff-card`

#### Scenario: ldap_dept vacío tras limpiar un filtro
- **WHEN** `ldap_dept=` (string vacío) está presente en la URL
- **THEN** se trata igual que ausencia del parámetro y se muestra el menú de departamentos

#### Scenario: Encabezado agregado con totales
- **WHEN** hay 20 departamentos y 312 empleados en el dataset cacheado
- **THEN** el encabezado del menú muestra "20 departamentos · 312 empleados"

### Requirement: Fila de departamento con nombre y conteo
Cada fila del menú SHALL mostrar el nombre del departamento y, entre paréntesis o como texto secundario, el número de empleados de ese departamento — usando el mismo array `[ 'Departamento' => count ]` que produce `extract_departments()`.

#### Scenario: Conteo coincide con el total real
- **WHEN** Engineering tiene 47 empleados en el dataset cacheado
- **THEN** la fila de Engineering muestra "47 empleados"

### Requirement: Orden de filas reutiliza department_order
El orden de las filas del menú SHALL respetar la configuración existente `department_order`: `'alpha'` (alfabético ascendente, default) o `'count_desc'` (descendente por cantidad de empleados) — el mismo criterio que hoy ordena la barra de chips eliminada.

#### Scenario: Orden alfabético por defecto
- **WHEN** `department_order` es `'alpha'` o no está guardado
- **THEN** las filas se muestran ordenadas alfabéticamente por nombre de departamento

#### Scenario: Orden por cantidad de empleados
- **WHEN** `department_order` es `'count_desc'`
- **THEN** las filas se muestran de mayor a menor cantidad de empleados

### Requirement: Avatar de departamento con color y abreviatura determinísticos
Cada fila SHALL mostrar un avatar circular con una abreviatura de hasta 2 letras derivada del nombre del departamento y un color de fondo determinístico calculado con `crc32(nombre_departamento) % count(paleta)` sobre la misma paleta de 8 colores usada para avatares de empleado.

#### Scenario: Mismo departamento siempre mismo color
- **WHEN** el menú se renderiza en dos peticiones distintas sin cambios en los datos
- **THEN** el departamento "Engineering" muestra el mismo color de avatar en ambas

#### Scenario: Abreviatura de dos palabras
- **WHEN** el departamento se llama "Talento Humano"
- **THEN** la abreviatura mostrada es "TH"

#### Scenario: Abreviatura de una sola palabra
- **WHEN** el departamento se llama "Marketing"
- **THEN** la abreviatura mostrada son las 2 primeras letras: "MA"

### Requirement: Clic en una fila navega al detalle del departamento
Cada fila SHALL ser un enlace (`<a>`) cuyo `href` apunta a la URL actual con `ldap_dept=<nombre>&ldap_page=1`, construido con `add_query_arg()` y escapado con `esc_url()`.

#### Scenario: Clic navega con el nombre exacto del departamento
- **WHEN** el usuario hace clic en la fila "Engineering (47)"
- **THEN** la página navega a `?ldap_dept=Engineering&ldap_page=1`

### Requirement: Búsqueda de departamentos client-side sin round-trip
Cuando `enable_search` es `'1'`, el menú SHALL incluir un campo de búsqueda que filtra las filas ya renderizadas por coincidencia de substring (case-insensitive) contra el nombre del departamento, ejecutado enteramente en el navegador sin petición al servidor. Este filtro SHALL operar únicamente sobre las filas de departamento presentes en el DOM (máximo la cantidad total de departamentos, sin paginación) y es independiente del buscador de empleados server-side de la vista de detalle.

#### Scenario: Filtrado en vivo sin recarga
- **WHEN** el usuario escribe "eng" en el buscador del menú
- **THEN** solo permanecen visibles las filas cuyo nombre contiene "eng" (p. ej. "Engineering"), sin que la URL cambie ni se dispare una petición de red

#### Scenario: Sin resultados
- **WHEN** el texto ingresado no coincide con ningún departamento
- **THEN** se muestra un mensaje de "sin resultados" y ninguna fila queda visible

#### Scenario: Buscador oculto cuando search está desactivado
- **WHEN** `enable_search` es `'0'`
- **THEN** el menú no incluye el campo de búsqueda de departamentos

### Requirement: Layout de dos columnas en desktop, una columna en mobile
En viewports de escritorio, las filas de departamento SHALL distribuirse en dos columnas lado a lado (wrap por CSS, sin JS), balanceando el total de filas entre ambas. En viewports mobile (`≤540px`, consistente con el breakpoint ya usado por la grilla de `EmployeeCard`), el menú SHALL colapsar a una sola columna vertical.

#### Scenario: Desktop con 20 departamentos
- **WHEN** el viewport es de escritorio y existen 20 departamentos
- **THEN** las filas se distribuyen en dos columnas visualmente balanceadas, sin depender de JS para el wrap

#### Scenario: Mobile colapsa a una columna
- **WHEN** el viewport es `≤540px`
- **THEN** todas las filas de departamento se muestran en una única columna vertical, en el mismo orden que aplicaría en desktop

### Requirement: El menú nunca pagina
El menú de departamentos SHALL mostrar siempre la lista completa de departamentos en una sola carga, sin controles de paginación, independientemente de la cantidad de departamentos existentes.

#### Scenario: Organización con muchos departamentos
- **WHEN** existen 20 departamentos distintos
- **THEN** las 20 filas se renderizan en la misma carga, sin botones "Anterior"/"Siguiente"
