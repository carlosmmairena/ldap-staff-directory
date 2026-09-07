## Context

Hoy `LDAP_ED_Shortcode::render()` siempre resuelve una sola vista: lee `ldap_dept`/`ldap_search`/`ldap_page` de `$_GET`, filtra y pagina en PHP sobre el array cacheado, e incluye `public/views/directory.php` (grid de `EmployeeCard` + barra de chips + paginación). El prototipo (`docs/design-public-ui.pen`) reemplaza esa vista única por dos pantallas — menú de departamentos y detalle — sin tocar el modelo de datos ni la caché.

```
render()
  │
  ├─ ldap_dept vacío  ──▶  department-menu.php   (sin fetch adicional; solo counts)
  │
  └─ ldap_dept presente ─▶  directory.php (detalle)  (filter_users + paginate_users, como hoy)
```

## Goals / Non-Goals

**Goals:**
- Reutilizar al máximo la mecánica server-side existente (`ldap_dept`, `ldap_page`, `ldap_search`, `add_query_arg`) — cero JS de routing, cero estado nuevo en el cliente.
- El menú de departamentos y la vista de detalle comparten el mismo shortcode, la misma caché y el mismo array de settings; no se agregan opciones nuevas al schema de `ldap_ed_settings`.
- Color y abreviatura por departamento se derivan determinísticamente (sin configuración manual), igual que ya ocurre con los avatares de empleado.

**Non-Goals:**
- No se cambia el modelo de datos LDAP ni se agregan atributos nuevos al connector.
- No se introduce enrutamiento por hash/History API — la navegación sigue siendo recarga completa vía `<a href>`.
- No se rediseña el panel de administración ni sus settings.

## Decisions

**1. Branching server-side por presencia de `ldap_dept`, sin estado en el cliente.**
`render()` decide entre las dos vistas antes de correr `filter_users()`/`paginate_users()`: si `ldap_dept === ''`, se salta esa lógica por completo y se incluye `department-menu.php` (solo necesita `$ldap_ed_departments`, ya calculado por `extract_departments()`). Si hay departamento, el flujo actual (filtrado + paginación + detalle) corre sin cambios de firma.
*Alternativa descartada*: alternar las vistas con JS (SPA-like, mostrar/ocultar con `display:none`). Se descarta porque el proyecto no tiene build step, porque rompería el botón "atrás"/bookmarks de forma más frágil que una URL real, y porque duplicaría lo que `add_query_arg()` ya resuelve hoy en `build_nav_url()`.

**2. `directory.php` se conserva como vista de detalle; se agrega `department-menu.php` como archivo nuevo.**
Mínimo diff: el archivo de detalle mantiene su nombre e incorpora el breadcrumb, el encabezado de departamento y los íconos por campo; pierde la barra de chips (`.ldap-dept-filters`) y su bloque de render.

**3. Color + abreviatura por departamento reutilizan el mismo patrón que el avatar de empleado (`crc32(name) % count($paleta)`), extraído a un helper compartido.**
En vez de duplicar la lógica de iniciales/color entre `directory.php` (empleados) y `department-menu.php` (departamentos), se extrae un helper privado reusable (p. ej. `LDAP_ED_Shortcode::get_initials_and_color( string $name ): array`) que ambas vistas invocan con distinto argumento.
*Alternativa descartada*: copiar el bloque de cálculo de iniciales/color en el nuevo archivo — se descarta por duplicación directa de una lógica ya no trivial (split de nombre, `crc32`, módulo de paleta).

**4. Búsqueda del menú de departamentos: client-side, acotada a las filas ya renderizadas.**
`directory.js` gana una función pequeña que filtra `.ldap-dept-row[data-name]` por coincidencia de substring, sin red. Esto es un dominio distinto del que `server-side-pagination` eliminó (esa capability retiró `render()`/`matchesQuery()`/`initDirectory()` que ocultaban *cards de empleados* con `display:none` sobre listas de cientos de registros); acá se filtran como máximo ~20-30 filas de departamento ya presentes en el DOM, sin paginación ni fetch. El spec de esta capability debe dejar explícito ese límite para no leerse como una reversión de `server-side-pagination`.
*Alternativa descartada*: nuevo query param `dept_search` con recarga de página — round-trip innecesario para filtrar una lista que ya está completa en memoria y nunca pagina.

**5. Íconos vía SVG inline embebido en el template PHP, no librería JS ni icon-font.**
El stack no tiene build tooling (vanilla JS/CSS). Los íconos Lucide del prototipo (`briefcase`, `mail`, `phone`, `building`, `chevron-right`, `arrow-left`, `search`) se insertan como `<svg>` inline directamente en `directory.php`/`department-menu.php`, siguiendo el mismo patrón que ya usa el ícono de búsqueda actual (SVG vía `::before`), solo que aquí como markup en vez de `background-image` porque los íconos van intercalados con texto en cada fila.

**6. El menú de departamentos usa un layout de 2 columnas en desktop, resuelto por CSS (sin JS).**
El prototipo (`KdCvc/deptGrid`) reparte las filas en dos columnas lado a lado en desktop; el mobile (`Mobile Screen 1`) las apila en una sola columna. Se implementa con CSS puro (p. ej. `columns: 2` o dos contenedores `fill_container` alimentados por PHP alternando filas), sin JS de balanceo — igual que el resto del proyecto, que no depende de JS para layout.
*Alternativa descartada*: mantener una sola columna también en desktop — se descarta porque el prototipo explícitamente separa las filas en dos columnas para reducir el alto total de la pantalla con 20 departamentos.

**7. La barra de chips se elimina por completo, no se oculta condicionalmente.**
`department-filter` pasa a `REMOVED Requirements` en su delta spec — no queda una bandera de settings para reactivarla.

## Risks / Trade-offs

- **[Riesgo]** La paleta existente de 8 colores, aplicada a 20 departamentos vía `crc32`, produce colisiones de color entre departamentos distintos (el prototipo usa ~15 tonos distintos, más que la paleta actual). → *Mitigación*: la abreviatura + el nombre completo siguen diferenciando cada fila visualmente incluso con color repetido; no se bloquea el flujo por esto. Ver Open Questions.
- **[Riesgo]** Cambio de comportamiento sin bandera de opt-out rompe la experiencia de sitios que ya insertaron `[ldap_directory]` esperando el grid plano. → *Mitigación*: documentar como cambio breaking en el changelog de `readme.txt`; ver Open Questions sobre un posible atributo de opt-out.
- **[Riesgo]** Reintroducir JS de filtrado (punto 4) después de que `server-side-pagination` documentó explícitamente "eliminación de lógica client-side" podría interpretarse como una reversión de esa capability. → *Mitigación*: alcance acotado y documentado explícitamente en el spec de `department-menu` (filtra filas de departamento, no cards de empleado; sin paginación involucrada).

## Migration Plan

- Sin cambios de esquema de datos ni de caché — no se requiere purge ni migración de settings guardados.
- Bump de versión (constante `LDAP_ED_VERSION` + header del plugin + `Stable tag` en `readme.txt`) y entrada de changelog marcando el cambio de flujo como breaking para el front-end público.
- Rollback: downgrade del plugin a la versión anterior: no hay datos persistidos nuevos que limpiar.

## Open Questions

- ~~¿Se agrega un atributo de shortcode (p. ej. `view="flat"`) como opt-out para preservar el comportamiento anterior en sitios existentes, o se acepta como cambio breaking sin escape hatch en esta primera versión?~~ — Resuelto (decisión explícita del mantenedor): se acepta como cambio breaking sin escape hatch en esta primera versión. Documentado en `readme.txt`: "There is no setting to restore the old flat grid."
- ~~¿Se amplía la paleta de colores específicamente para departamentos (más cercana a los ~15 tonos del prototipo), o se acepta la colisión de colores reutilizando la paleta de 8 de empleados?~~ — Resuelto (decisión explícita del mantenedor): se acepta la colisión de colores reutilizando la paleta de 8 de empleados. No se amplía.
