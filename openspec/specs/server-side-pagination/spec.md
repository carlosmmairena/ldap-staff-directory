## ADDED Requirements

### Requirement: Carga y filtrado en PHP sobre caché
El shortcode SHALL cargar el array completo de empleados desde `LDAP_ED_Cache::get()` (o el stale fallback) y aplicar los filtros de departamento y búsqueda en PHP antes de renderizar, únicamente cuando `ldap_dept` está presente y no vacío (vista de detalle de departamento). No SHALL emitirse ninguna nueva consulta al servidor LDAP por cada petición paginada. Cuando `ldap_dept` está ausente o vacío, `filter_users()` y `paginate_users()` no SHALL ejecutarse — se renderiza el menú de departamentos en su lugar.

#### Scenario: Filtrado sin consulta LDAP
- **WHEN** el caché contiene 400 empleados, `ldap_dept` está presente y el usuario navega a la página 3
- **THEN** PHP hace slice del array en memoria; `ldap_connect()` no se llama

#### Scenario: Filtro de departamento aplicado antes de paginar
- **WHEN** `ldap_dept=Engineering` y hay 47 empleados en Engineering
- **THEN** el slice de paginación se aplica sobre los 47, no sobre el total de 400

#### Scenario: Sin ldap_dept no hay filtrado ni paginación de empleados
- **WHEN** `ldap_dept` está ausente o vacío
- **THEN** `render()` no invoca `filter_users()` ni `paginate_users()`; se incluye `department-menu.php` en lugar de la grilla de empleados

### Requirement: Paginación por query param ldap_page
El shortcode SHALL leer `$_GET['ldap_page']` (entero positivo, default 1) para determinar qué segmento del array mostrar. El valor SHALL sanitizarse con `absint()` y validarse: si `$page > $total_pages`, se usa `$total_pages`.

#### Scenario: Primera página por defecto
- **WHEN** no hay `ldap_page` en la URL
- **THEN** se muestran los primeros `$per_page` empleados

#### Scenario: Página fuera de rango se corrige
- **WHEN** `ldap_page=99` y solo hay 5 páginas
- **THEN** se muestra la última página (página 5) sin error

#### Scenario: Valor no numérico se trata como página 1
- **WHEN** `ldap_page=abc`
- **THEN** `absint('abc')` devuelve 0, que se normaliza a 1; se muestra la primera página

### Requirement: Búsqueda por query param ldap_search
El shortcode SHALL leer `$_GET['ldap_search']` (string, default `''`) y filtrar empleados donde el valor aparezca (case-insensitive) en alguno de los campos: `name`, `email`, `title`, `department`, `phone`. El valor SHALL sanitizarse con `sanitize_text_field()`.

#### Scenario: Búsqueda por nombre parcial
- **WHEN** `ldap_search=ana`
- **THEN** se muestran empleados cuyo name, email, title, department o phone contiene "ana" (case-insensitive)

#### Scenario: Sin resultados muestra mensaje vacío
- **WHEN** `ldap_search=zzznomatch`
- **THEN** la cuadrícula muestra `.ldap-no-results` con el mensaje de no empleados encontrados y la paginación no se renderiza

#### Scenario: Búsqueda combinada con filtro de departamento
- **WHEN** `ldap_dept=Engineering` y `ldap_search=dev`
- **THEN** solo se muestran empleados de Engineering cuyo name/email/title/dept/phone contiene "dev"

### Requirement: Texto de paginación con rango y contexto
El elemento `.ldap-page-info` SHALL mostrar el texto "Mostrando X–Y de Z" donde X es el primer registro de la página, Y es el último, y Z es el total filtrado. Cuando hay filtro de departamento activo, el texto SHALL incluir "en [Departamento]".

#### Scenario: Paginación sin filtro
- **WHEN** página 2 de 312 empleados con `per_page=20`
- **THEN** el texto muestra "Mostrando 21–40 de 312"

#### Scenario: Paginación con filtro de departamento
- **WHEN** página 1 de 47 empleados en Engineering con `per_page=20`
- **THEN** el texto muestra "Mostrando 1–20 de 47 en Engineering"

#### Scenario: Última página con registros parciales
- **WHEN** página 3 y quedan 7 registros (de 47 con per_page=20)
- **THEN** el texto muestra "Mostrando 41–47 de 47 en Engineering"

### Requirement: Links de navegación construidos con add_query_arg
Los botones "Anterior" y "Siguiente" SHALL ser elementos `<a>` con `href` construido con `add_query_arg()`, preservando `ldap_search` y `ldap_dept` en la URL. Los links SHALL usar `esc_url()` en el output.

#### Scenario: Siguiente preserva filtros activos
- **WHEN** se está en `?ldap_dept=RRHH&ldap_search=mar&ldap_page=1`
- **THEN** el link "Siguiente" apunta a `?ldap_dept=RRHH&ldap_search=mar&ldap_page=2`

#### Scenario: Botón Anterior deshabilitado en página 1
- **WHEN** `ldap_page=1`
- **THEN** el botón "Anterior" se renderiza con `aria-disabled="true"` y sin `href`

#### Scenario: Botón Siguiente deshabilitado en última página
- **WHEN** `ldap_page=$total_pages`
- **THEN** el botón "Siguiente" se renderiza con `aria-disabled="true"` y sin `href`

### Requirement: Eliminación de lógica client-side en directory.js
Las funciones `render()`, `matchesQuery()` e `initDirectory()` SHALL eliminarse de `directory.js`. El archivo puede quedar vacío o reducirse a un comentario. El comportamiento de ocultar/mostrar cards con `display:none` SHALL desaparecer.

#### Scenario: No hay loop allCards en el DOM
- **WHEN** la página carga con 20 empleados visibles (de 312 en servidor)
- **THEN** el DOM contiene exactamente 20 elementos `.ldap-staff-card`, no 312

### Requirement: Fix de estilos para .ldap-phone
El selector `.ldap-phone` SHALL existir en `directory.css` con el mismo tratamiento visual que `.ldap-email`: color primario, `font-size: .88em`, `text-decoration: none`, `word-break: break-all`, y `transition: opacity` en hover.

#### Scenario: Teléfono visible con estilo consistente
- **WHEN** el campo `phone` está habilitado y el empleado tiene teléfono
- **THEN** el link de teléfono se muestra en color primario sin subrayado, igual que el email
