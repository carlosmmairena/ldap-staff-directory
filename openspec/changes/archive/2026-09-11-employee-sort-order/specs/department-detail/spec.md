## ADDED Requirements

### Requirement: Orden configurable de las tarjetas de empleado
La vista de detalle SHALL ordenar las `EmployeeCard` de un departamento según el setting `employee_order`, con 4 valores posibles: `name_asc` (nombre A–Z, default), `name_desc` (nombre Z–A), `title_asc` (puesto A–Z) y `title_desc` (puesto Z–A). El orden SHALL aplicarse sobre el conjunto ya filtrado por departamento y búsqueda (`filter_users()`), antes de `paginate_users()`, de modo que el orden sea consistente a través de todas las páginas de un mismo resultado filtrado. La comparación SHALL usar `strcmp()` (case-sensitive, sin collation por locale — comportamiento por defecto de PHP para acentos/ñ).

#### Scenario: Orden por nombre ascendente (default)
- **WHEN** `employee_order` es `'name_asc'` o no está guardado
- **THEN** las `EmployeeCard` del departamento se muestran ordenadas por `name` de A a Z, igual que el comportamiento actual

#### Scenario: Orden por nombre descendente
- **WHEN** `employee_order` es `'name_desc'`
- **THEN** las `EmployeeCard` se muestran ordenadas por `name` de Z a A

#### Scenario: Orden por puesto ascendente
- **WHEN** `employee_order` es `'title_asc'`
- **THEN** las `EmployeeCard` se muestran ordenadas por `title` de A a Z

#### Scenario: Orden por puesto descendente
- **WHEN** `employee_order` es `'title_desc'`
- **THEN** las `EmployeeCard` se muestran ordenadas por `title` de Z a A

#### Scenario: Orden consistente entre páginas
- **WHEN** un departamento tiene 45 empleados, `per_page` es 20 y `employee_order` es `'title_asc'`
- **THEN** el empleado #21 en orden de puesto ascendente aparece como primer resultado de la página 2, sin duplicados ni saltos respecto a la página 1

#### Scenario: Valor guardado inválido cae al default
- **WHEN** `employee_order` contiene un valor fuera de la whitelist (p. ej. modificado directamente en la base de datos)
- **THEN** el sistema aplica `'name_asc'` como si el setting no estuviera guardado

### Requirement: Desempate de puesto siempre por nombre ascendente
Cuando el orden activo es `title_asc` o `title_desc` y dos o más empleados comparten el mismo valor de `title` (incluyendo `title` vacío), el desempate SHALL aplicarse por `name` ascendente (A–Z), independientemente de si el orden primario de puesto es ascendente o descendente.

#### Scenario: Empate de puesto bajo orden ascendente
- **WHEN** `employee_order` es `'title_asc'` y "Beatriz Soto" y "Andrés Vega" comparten el puesto "Analista"
- **THEN** "Andrés Vega" se muestra antes que "Beatriz Soto" dentro del bloque "Analista"

#### Scenario: Empate de puesto bajo orden descendente
- **WHEN** `employee_order` es `'title_desc'` y "Beatriz Soto" y "Andrés Vega" comparten el puesto "Analista"
- **THEN** dentro del bloque "Analista", "Andrés Vega" sigue apareciendo antes que "Beatriz Soto" (el desempate no se invierte con la dirección del puesto)

#### Scenario: Empate por puesto vacío
- **WHEN** `employee_order` es `'title_asc'` y varios empleados no tienen `title` guardado en LDAP
- **THEN** ese grupo de empleados sin puesto se ordena entre sí por `name` ascendente
