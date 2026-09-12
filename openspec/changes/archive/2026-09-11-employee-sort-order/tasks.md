## 1. Conector: quitar el orden fijo

- [x] 1.1 Eliminar el `usort()` por `strcmp($a['name'], $b['name'])` en `LDAP_ED_Connector::get_users()` (`includes/class-ldap-connector.php`)
- [x] 1.2 Actualizar el docblock de `get_users()` para dejar explícito que el array devuelto/cacheado no garantiza orden

## 2. Setting `employee_order`

- [x] 2.1 En `LDAP_ED_Admin::sanitize_settings()` (bloque `display`), agregar whitelist de 4 valores (`name_asc`, `name_desc`, `title_asc`, `title_desc`) con default `'name_asc'`, siguiendo el mismo patrón que `department_order`
- [x] 2.2 Implementar `render_field_employee_order()` en `class-admin.php`: `<select>` con las 4 opciones y su texto (p. ej. "Nombre (A–Z)", "Nombre (Z–A)", "Puesto (A–Z)", "Puesto (Z–A)")
- [x] 2.3 Agregar la fila en la pestaña Fields de `admin/views/settings-page.php` (junto a `department_order`), con texto de ayuda aclarando que aplica a la grilla de empleados dentro de un departamento
- [x] 2.4 Confirmar que guardar esta fila NO dispara `LDAP_ED_Cache::purge()` (no debe sumarse a la condición de purga en `sanitize_settings()`)

## 3. Ordenamiento en el shortcode

- [x] 3.1 Implementar método privado `sort_users( array $users, string $order ): array` en `LDAP_ED_Shortcode`, análogo a `extract_departments()`
- [x] 3.2 Lógica de comparación: `name_asc`/`name_desc` → `strcmp` por `name` (signo invertido en `desc`); `title_asc`/`title_desc` → `strcmp` por `title`, con desempate SIEMPRE por `strcmp($a['name'], $b['name'])` ascendente (sin invertir, incluso en `title_desc`)
- [x] 3.3 Fallback a `'name_asc'` cuando el valor guardado no está en la whitelist (defensivo, por si el option se editó fuera del admin)
- [x] 3.4 Invocar `sort_users()` en `render()`, sobre `$filtered_users`, entre `filter_users()` y `paginate_users()`
- [x] 3.5 Leer el setting con `$settings['employee_order'] ?? 'name_asc'`, mismo patrón que `department_order` en `render()`

## 4. Tests

- [x] 4.1 Test unitario para `sort_users()` cubriendo los 4 valores (`name_asc`, `name_desc`, `title_asc`, `title_desc`)
- [x] 4.2 Test de empate de puesto: mismo `title` en dos usuarios → desempate por `name` ascendente, verificado tanto en `title_asc` como en `title_desc`
- [x] 4.3 Test de `title` vacío en varios usuarios → se agrupan y ordenan entre sí por `name` ascendente
- [x] 4.4 Test de valor inválido/corrupto en `employee_order` → cae a `'name_asc'`
- [x] 4.5 Test de consistencia entre páginas: mismo criterio de orden produce una secuencia sin huecos ni duplicados al paginar (reutilizar fixtures de `tests/ldap/ConnectorPaginationTest.php` si aplica)
- [x] 4.6 Revisar `tests/ldap/ConnectorFilterTest.php` y `ConnectorPaginationTest.php` por si algún assert depende implícitamente del orden por nombre que hoy aplica el conector; ajustar si es necesario tras 1.1

## 5. Documentación

- [x] 5.1 Actualizar `readme.txt` (sección de features / changelog) mencionando el nuevo control de orden de empleados — bump a 1.4.0 (readme.txt + header del plugin + `LDAP_ED_VERSION`)
- [x] 5.2 Screenshot o nota en docs/ si el proyecto mantiene capturas de la pestaña Fields — no aplica: `docs/` no mantiene capturas de pantalla (solo `.pen` de diseño y docs en Markdown)
