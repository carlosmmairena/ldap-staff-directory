## Why

El directorio público hoy es una sola pantalla: todos los empleados en una grilla, con chips de departamento como atajo de filtro horizontal. Con 20 departamentos y 312 empleados esa grilla es abrumadora de entrada. El nuevo prototipo (`docs/design-public-ui.pen`) reemplaza esa pantalla única por un flujo de dos pasos — menú de departamentos → detalle del departamento con sus contactos — que reduce la carga inicial a una lista compacta y da contexto claro antes de mostrar personas.

## What Changes

- **BREAKING**: La pantalla raíz del shortcode (`ldap_dept` vacío) deja de mostrar la grilla completa de empleados. En su lugar renderiza un menú de departamentos (nombre, conteo, avatar con color y abreviatura determinísticos).
- **BREAKING**: Se elimina la barra de chips de departamento (`.ldap-dept-filters`, capability `department-filter`). La navegación por departamento pasa a hacerse por clic en una fila del menú, no por chip.
- Nueva vista de detalle de departamento: breadcrumb "← Departamentos", encabezado con nombre/conteo/badge del departamento activo, buscador de empleados con placeholder contextual ("Buscar en {Departamento}…"), grilla de `EmployeeCard` y paginación — reutilizando la lógica server-side existente (`ldap_dept`, `ldap_page`, `ldap_search`).
- Nuevo buscador client-side dentro del menú de departamentos (filtra las filas ya renderizadas, sin round-trip al servidor) — no reemplaza el buscador server-side de empleados dentro del detalle.
- Íconos (librería Lucide) en las filas de `EmployeeCard`: `briefcase` (cargo), `mail` (email), `phone` (teléfono), `building` (extensión) — mejora visual, sin cambios en los datos ni en el campo `extension` existente.
- Nuevo archivo de vista para el menú de departamentos; `class-shortcode.php::render()` decide entre menú y detalle según si `ldap_dept` está presente.
- Mejoras visuales de mobile para ambas pantallas (menú y detalle), replicando el layout de una columna del prototipo.

## Capabilities

### New Capabilities
- `department-menu`: pantalla raíz del shortcode — lista de departamentos con avatar (color + abreviatura determinísticos vía `crc32`), conteo de empleados, orden configurable (reutiliza `department_order`), y buscador client-side por nombre de departamento. Navega a la vista de detalle al hacer clic en una fila.
- `department-detail`: vista mostrada cuando `ldap_dept` está presente — breadcrumb de retorno al menú, encabezado del departamento (nombre, conteo, badge coloreado igual al del menú), grilla de `EmployeeCard` con íconos por campo, y paginación/búsqueda server-side existentes ahora contextualizadas a un único departamento.

### Modified Capabilities
- `department-filter`: se elimina la totalidad de sus requisitos (barra de chips, chip "All", navegación por `×`). La navegación por departamento la asumen `department-menu` y `department-detail`.
- `server-side-pagination`: los requisitos de paginación/grilla siguen vigentes tal cual, pero ahora aplican únicamente dentro de `department-detail` (`ldap_dept` presente). Ya no existe un estado "todos los empleados paginados sin filtro de departamento" — ese estado pasa a ser el menú de departamentos, sin paginación.

## Impact

- **Código afectado**: `includes/class-shortcode.php` (branching en `render()`, nuevo helper de color/abreviatura por departamento), `public/views/directory.php` (se convierte en la vista de detalle — pierde la barra de chips, gana breadcrumb/encabezado/íconos), nuevo `public/views/department-menu.php`, `public/css/directory.css` (estilos de menú, breadcrumb, íconos, badges por color), `public/js/directory.js` (nueva búsqueda client-side del menú; hoy casi vacío).
- **Ajenos al cambio**: `includes/class-ldap-connector.php` y el modelo de datos de empleados no cambian — el campo `extension`/`ipPhone` ya existe desde 1.1.2 y solo gana representación visual con ícono.
- **Configuración existente reutilizada sin cambios de esquema**: `department_order`, `enable_search`, `fields`, `per_page` conservan su significado actual.
- **Page builders**: Elementor y Beaver Builder delegan a `do_shortcode()`, heredan el nuevo flujo sin cambios en sus clases; su control `columns` pasa a aplicar solo dentro de la grilla de `department-detail`.
- **Compatibilidad**: sitios existentes con `[ldap_directory]` insertado verán el nuevo flujo de menú al recargar — no hay bandera de opt-out planteada en esta propuesta.
