## 1. Helper compartido de iniciales/color

- [x] 1.1 Extraer la lógica de iniciales + color determinístico (`crc32(name) % count($paleta)`) de `directory.php` a un método privado reusable en `LDAP_ED_Shortcode` (p. ej. `get_initials_and_color( string $name ): array`).
- [x] 1.2 Actualizar `directory.php` para usar el helper en vez del bloque inline actual (sin cambiar el resultado visual de los avatares de empleado existentes).

## 2. Branching en class-shortcode.php::render()

- [x] 2.1 En `render()`, verificar `ldap_ed_current_dept` (post-sanitización) antes de llamar a `filter_users()`/`paginate_users()`: si está vacío, incluir `department-menu.php`; si no, seguir el flujo actual hacia `directory.php`.
- [x] 2.2 Cuando se renderiza el menú, pasar a la vista `$ldap_ed_departments` (ya calculado por `extract_departments()`) y `$ldap_ed_enable_search`; no ejecutar `filter_users()` ni `paginate_users()` en esta rama.
- [x] 2.3 Confirmar que `$ldap_ed_all_count` y el resto de variables que ya no se usan en la rama de menú no generan warnings de variables indefinidas en `directory.php`.

## 3. Vista nueva: department-menu.php

- [x] 3.1 Crear `public/views/department-menu.php` con encabezado (título, subtítulo "N departamentos · M empleados" usando `count( $ldap_ed_departments )` y `$ldap_ed_all_count`), etiqueta "DEPARTAMENTOS" sobre la lista, buscador de departamentos (condicional a `enable_search`) y lista de filas de departamento.
- [x] 3.2 Cada fila: avatar circular (helper de la tarea 1.1 aplicado al nombre del departamento), nombre, conteo de empleados, ícono `chevron-right`, envuelta en `<a href>` con `esc_url( add_query_arg( ['ldap_dept' => $name, 'ldap_page' => 1] ) )`.
- [x] 3.3 Ordenar las filas según `department_order` (mismo criterio `alpha`/`count_desc` que hoy usa `extract_departments()` para las chips).
- [x] 3.4 Marcar cada fila con `data-name` (nombre del departamento, para el filtro client-side de la tarea 6) y agrupar el markup para que CSS pueda repartirlo en 2 columnas sin JS.
- [x] 3.5 Todas las variables locales del archivo usan el prefijo `ldap_ed_`.

## 4. Vista de detalle: directory.php

- [x] 4.1 Eliminar el bloque completo de `.ldap-dept-filters` (chip "All", chips por departamento, lógica de `×`).
- [x] 4.2 Agregar breadcrumb "← Departamentos" con `href` = `esc_url( remove_query_arg( ['ldap_dept', 'ldap_page'] ) )` e ícono `arrow-left`.
- [x] 4.3 Agregar encabezado de departamento: nombre, "$N empleados en este departamento", badge circular reutilizando el helper de color/abreviatura (mismo resultado que la fila del menú para ese departamento).
- [x] 4.4 Actualizar el placeholder del buscador de empleados a `sprintf( __( 'Buscar en %s…', 'ldap-staff-directory' ), $ldap_ed_current_dept )` con el comentario `/* translators: %s: department name */` en la línea inmediatamente anterior.
- [x] 4.5 Agregar íconos SVG inline por fila del `EmployeeCard`: `briefcase` (title), `mail` (email), `phone` (phone), `building` (extension) — respetando el condicional existente por campo en `$ldap_ed_fields`.
- [x] 4.6 Manejar el caso de `ldap_dept` sin coincidencias: encabezado con el nombre solicitado, cero empleados, `.ldap-no-results` visible, sin paginación.

## 5. Estilos: directory.css

- [x] 5.1 Estilos para el menú de departamentos: filas (`.ldap-dept-row`), avatar de departamento, buscador del menú — reutilizando tokens existentes (`--ldap-primary-color`, `--ldap-card-radius`, paleta de 8 colores de `.interface-design/system.md`).
- [x] 5.2 Layout de 2 columnas para las filas del menú en desktop (p. ej. `columns: 2` sobre el contenedor de filas), colapsando a 1 columna en el breakpoint `≤540px` ya usado por la grilla de `EmployeeCard`.
- [x] 5.3 Estilos para breadcrumb y encabezado de departamento en la vista de detalle.
- [x] 5.4 Estilos para los íconos inline por fila del `EmployeeCard` (tamaño, color `#8c8f94`, alineación con el texto).
- [x] 5.5 Eliminar las reglas CSS de `.ldap-dept-filters` / `.ldap-dept-chip` que ya no se usan.
- [x] 5.6 Responsive: detalle a 1 columna en mobile (`≤540px`), avatares 36px en mobile vs 44px desktop, siguiendo el layout mobile del prototipo (el colapso a 1 columna del menú ya lo cubre 5.2).

## 6. Búsqueda client-side del menú (directory.js)

- [x] 6.1 Agregar listener sobre el input de búsqueda del menú que filtra `.ldap-dept-row[data-name]` por substring case-insensitive, sin red ni cambio de URL.
- [x] 6.2 Mostrar un mensaje de "sin resultados" cuando el filtro no deja ninguna fila visible.
- [x] 6.3 Confirmar que este JS no interfiere con el formulario de búsqueda server-side existente de la vista de detalle (que sigue enviando `GET` con `ldap_search`).

## 7. Housekeeping

- [x] 7.1 Bump de `LDAP_ED_VERSION` en el header del plugin, la constante, y `Stable tag` en `readme.txt`.
- [x] 7.2 Agregar entrada en `== Changelog ==` de `readme.txt` describiendo el cambio de flujo como breaking para el front-end público.
- [x] 7.3 Revisar `= Features =` y `== Frequently Asked Questions ==` de `readme.txt` por si el nuevo flujo de menú→detalle amerita mención.

## 8. Verificación

- [x] 8.1 Probar el shortcode standalone: carga inicial muestra menú, clic en departamento navega al detalle, breadcrumb vuelve al menú.
- [x] 8.2 Probar con Elementor y Beaver Builder (delegan a `do_shortcode()`) — confirmar que el flujo nuevo se renderiza igual dentro de ambos builders.
- [x] 8.3 Probar con `enable_search='0'`: ni el buscador del menú ni el de detalle se muestran.
- [x] 8.4 Probar con un solo departamento en el dataset — confirmar que el menú igual se muestra (a diferencia de las chips, que se ocultaban con 1 solo departamento).
- [x] 8.5 Probar navegación directa a una URL con `ldap_dept` de un departamento inexistente.
- [x] 8.6 Verificar en mobile (`≤540px`) ambas pantallas contra el prototipo `docs/design-public-ui.pen`.

