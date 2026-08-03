## Why

El admin no tiene control sobre qué departamentos son visibles en el directorio público ni sobre el orden en que se muestran los chips de filtro. Cualquier valor no vacío del atributo `department` en LDAP termina expuesto públicamente (útil o no — ej. departamentos de RRHH/Legal/Ejecutivos), y los chips siempre se ordenan alfabéticamente sin alternativa. Además, los empleados sin el atributo `department` poblado son invisibles en los chips pero sí aparecen en el grid, sin que el admin lo sepa ni pueda decidir al respecto.

## What Changes

- Nuevo método `LDAP_ED_Connector::get_departments()` que descubre, vía una búsqueda LDAP independiente y paginada, todos los valores distintos del atributo `department` presentes en el directorio (con su conteo), más el conteo de empleados sin `department` asignado. Esta búsqueda **nunca** aplica el filtro de exclusión — es la única forma de poder des-excluir un departamento más adelante.
- Nueva acción AJAX `ldap_ed_get_departments` (admin-only, nonce + `manage_options`) que ejecuta el descubrimiento y persiste un snapshot en la opción `ldap_ed_known_departments`.
- Nuevo control en el admin: checklist de departamentos descubiertos (poblado desde el snapshot, refrescable con un botón manual "Actualizar lista de departamentos") donde el admin marca cuáles excluir de la UI pública.
- Nuevo setting `excluded_departments` (array) — los departamentos marcados se excluyen agregando cláusulas `(!(department=X))` al filtro de búsqueda LDAP en `get_users()`, **antes** de traer los empleados (no post-filtrado en PHP).
- Nuevo setting `exclude_no_department` (`'0'`/`'1'`) — checkbox separado (mismo patrón que `exclude_disabled`) que, si está activo, agrega `(department=*)` al filtro para excluir empleados sin el atributo poblado.
- Nuevo setting `department_order` (`'alpha'` | `'count_desc'`) — controla el orden de los chips de departamento en la UI pública (hoy hardcodeado a alfabético vía `ksort()`).
- `uninstall.php` debe limpiar la nueva opción `ldap_ed_known_departments`.

## Capabilities

### New Capabilities
- `department-exclusion`: descubrimiento de departamentos vía LDAP (independiente del filtro de exclusión), UI de admin para seleccionar exclusiones (incluyendo el caso "sin departamento"), y aplicación de esas exclusiones directamente en el filtro de búsqueda LDAP antes de traer empleados.

### Modified Capabilities
- `department-filter`: el orden de los chips de departamento pasa de estar hardcodeado (alfabético) a ser configurable (`alpha` | `count_desc`) vía el nuevo setting `department_order`.

## Impact

- **Código afectado:** `includes/class-ldap-connector.php` (nuevo método + filtro extendido), `includes/class-ajax.php` (nueva acción), `includes/class-admin.php` (nuevos campos + sanitización), `includes/class-shortcode.php` (orden de `extract_departments()`), `admin/js/admin.js` (refresh del checklist), `uninstall.php`.
- **Nueva opción de WP:** `ldap_ed_known_departments` (permanente, sin TTL).
- **Sin cambios de breaking:** los settings nuevos tienen defaults que preservan el comportamiento actual (`excluded_departments = []`, `exclude_no_department = '0'`, `department_order = 'alpha'`).
- **LDAP:** una búsqueda adicional (bajo demanda, no automática) para el descubrimiento de departamentos; el filtro de `get_users()` crece con cláusulas condicionales de exclusión.
