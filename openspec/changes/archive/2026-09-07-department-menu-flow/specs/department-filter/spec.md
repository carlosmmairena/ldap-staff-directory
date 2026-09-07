## REMOVED Requirements

### Requirement: Barra de filtros por departamento
**Reason**: La navegación por departamento pasa a hacerse por clic en una fila del nuevo menú de departamentos, no por una barra de chips horizontal sobre la grilla plana de empleados.
**Migration**: Ver `department-menu` (listado de departamentos) y `department-detail` (breadcrumb de retorno).

### Requirement: Chip "All" siempre primero
**Reason**: Ya no existe un estado "todos los empleados sin filtro" con grilla visible — ese estado es ahora el menú de departamentos, que no lista empleados.
**Migration**: El breadcrumb "← Departamentos" en `department-detail` cumple el rol de "volver a ver todo" que antes cumplía el chip "All".

### Requirement: Chip de departamento muestra nombre y conteo
**Reason**: Cada fila del menú de departamentos ya muestra nombre y conteo; el chip queda redundante.
**Migration**: Ver requisito "Fila de departamento con nombre y conteo" en `department-menu`.

### Requirement: Activación y limpieza del filtro
**Reason**: El clic en una fila del menú reemplaza el clic en un chip; el breadcrumb reemplaza la `×` de limpieza.
**Migration**: Ver "Clic en una fila navega al detalle del departamento" en `department-menu` y "Breadcrumb de retorno al menú" en `department-detail`.

### Requirement: Scroll horizontal implícito
**Reason**: Sin barra de chips, no hay contenedor horizontal que desbordar — el menú de departamentos es una lista vertical.
**Migration**: N/A — no aplica un equivalente en el nuevo flujo.

### Requirement: Sanitización del parámetro ldap_dept
**Reason**: El requisito en sí no desaparece — `ldap_dept` se sigue leyendo de `$_GET` y sanitizando — pero deja de pertenecer conceptualmente a "filtro por chips"; ahora es la vía de navegación menú→detalle.
**Migration**: Ver "Sanitización del parámetro ldap_dept" en `department-detail`, con el mismo tratamiento (`sanitize_text_field()` + comparación case-insensitive).
