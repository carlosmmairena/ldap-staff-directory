## MODIFIED Requirements

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
