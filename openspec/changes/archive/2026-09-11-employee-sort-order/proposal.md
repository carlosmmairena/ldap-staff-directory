## Why

Hoy el orden de las tarjetas de empleado dentro de un departamento está fijo en código (`usort` por `name` ascendente en `LDAP_ED_Connector::get_users()`). Los administradores no técnicos necesitan poder elegir si la página pública muestra a los empleados ordenados por nombre o por puesto, en cualquiera de los dos sentidos, sin depender de un desarrollador.

## What Changes

- Nuevo setting `employee_order` (string) en la pestaña **Fields** del admin, con un único `<select>` de 4 valores: `name_asc` (default), `name_desc`, `title_asc`, `title_desc`.
- El orden ya no se decide en `LDAP_ED_Connector::get_users()` — se elimina el `usort` hardcodeado ahí. El orden se aplica en `LDAP_ED_Shortcode::render()`, sobre `$filtered_users`, justo antes de `paginate_users()`, para que quede aplicado de forma consistente entre páginas y ediciones de búsqueda/departamento.
- Al ordenar por puesto (`title_asc` / `title_desc`), los empates de puesto (incluyendo puesto vacío) se desempatan siempre por nombre ascendente (A–Z), sin importar la dirección elegida para puesto.
- La comparación sigue usando `strcmp()` tal como hoy — sin collation por locale; acentos/ñ mantienen el comportamiento por defecto de PHP.
- Alcance exclusivamente admin-global: no hay override por shortcode ni selector de orden para el visitante en la página pública.
- Guardar este campo en la pestaña Fields no dispara purga de caché (mismo tratamiento que `per_page`, `fields` y `department_order` — no afecta qué se lee de LDAP, solo cómo se muestra).

## Capabilities

### New Capabilities
_Ninguna._

### Modified Capabilities
- `department-detail`: nuevo requirement de orden configurable de las `EmployeeCard` (por nombre o puesto, ascendente o descendente, con desempate por nombre), reemplazando el orden fijo alfabético por nombre que la vista hereda hoy del conector.

## Impact

- `includes/class-ldap-connector.php`: se elimina el `usort` fijo en `get_users()`; el array de usuarios cacheado queda sin ordenar por contrato (el orden es responsabilidad exclusiva del shortcode).
- `includes/class-shortcode.php`: nuevo método privado de ordenamiento, invocado en `render()` entre `filter_users()` y `paginate_users()`.
- `includes/class-admin.php`: whitelist de los 4 valores permitidos en `sanitize_settings()` (bloque `display`) + `render_field_employee_order()`.
- `admin/views/settings-page.php`: nueva fila en la pestaña Fields.
- Compatibilidad: instalaciones existentes sin el setting guardado usan `name_asc`, que reproduce exactamente el orden actual — no hay cambio de comportamiento visible hasta que el admin cambie el valor.
