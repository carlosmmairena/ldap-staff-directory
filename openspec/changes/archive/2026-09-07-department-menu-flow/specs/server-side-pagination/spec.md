## MODIFIED Requirements

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
