## ADDED Requirements

### Requirement: Vista de detalle se muestra cuando ldap_dept está presente
El shortcode SHALL renderizar la vista de detalle de departamento (`directory.php`) cuando `$_GET['ldap_dept']` sanitizado es un string no vacío. Esta vista SHALL ejecutar el flujo existente de filtrado y paginación server-side (`filter_users()`, `paginate_users()`) sin cambios de comportamiento.

#### Scenario: Detalle con departamento válido
- **WHEN** `ldap_dept=Engineering` y existen empleados en ese departamento
- **THEN** se renderiza la grilla de `EmployeeCard` filtrada por Engineering, paginada según `per_page`

### Requirement: Sanitización del parámetro ldap_dept
El valor de `$_GET['ldap_dept']` SHALL sanitizarse con `sanitize_text_field()` antes de cualquier uso, tanto para decidir qué vista renderizar como para filtrar empleados. La comparación contra los nombres de departamento SHALL ser case-insensitive (`strcasecmp` o `strtolower`).

#### Scenario: Parámetro con caracteres especiales
- **WHEN** `ldap_dept` contiene HTML (`<script>alert(1)</script>`)
- **THEN** `sanitize_text_field()` elimina las etiquetas; no se produce output sin escapar y no se interpreta como departamento válido

### Requirement: Breadcrumb de retorno al menú
La vista de detalle SHALL mostrar un enlace de retorno ("← Departamentos") cuyo `href` apunta a la URL actual sin `ldap_dept` ni `ldap_page` (equivalente a `remove_query_arg(['ldap_dept','ldap_page'])`).

#### Scenario: Clic en breadcrumb limpia el filtro
- **WHEN** el usuario está en `?ldap_dept=RRHH&ldap_page=2` y hace clic en el breadcrumb
- **THEN** navega a la URL sin `ldap_dept` ni `ldap_page`, mostrando el menú de departamentos

### Requirement: Encabezado de departamento con badge coloreado
La vista de detalle SHALL mostrar el nombre del departamento activo, la cantidad total de empleados en ese departamento, y un badge circular con la misma abreviatura y color determinístico que su fila correspondiente en el menú de departamentos.

#### Scenario: Color consistente entre menú y detalle
- **WHEN** el departamento "Engineering" se muestra en el menú con avatar azul y abreviatura "EN"
- **THEN** al entrar al detalle de Engineering, el badge del encabezado usa el mismo color y la misma abreviatura "EN"

### Requirement: Buscador de empleados con placeholder contextual
Cuando `enable_search` es `'1'`, el campo de búsqueda de la vista de detalle SHALL mostrar un placeholder que incluye el nombre del departamento activo (p. ej. "Buscar en Engineering…") y SHALL seguir operando server-side vía `ldap_search`, exactamente como hoy — sin cambios en `filter_users()`.

#### Scenario: Placeholder incluye el departamento activo
- **WHEN** se visualiza el detalle de "Engineering"
- **THEN** el campo de búsqueda muestra el placeholder "Buscar en Engineering…"

### Requirement: Cards de empleado con íconos por campo
Cada `EmployeeCard` en la vista de detalle SHALL mostrar un ícono junto a cada campo habilitado que lo tenga definido: `briefcase` para `title`, `mail` para `email`, `phone` para `phone`, `building` para `extension`. Los íconos SHALL insertarse como SVG inline en el markup, sin dependencia de una librería JS ni de un icon-font externo. La visibilidad de cada fila SHALL seguir respetando el array `$ldap_ed_fields` habilitado, igual que hoy.

#### Scenario: Campo deshabilitado no muestra ícono ni fila
- **WHEN** `'phone'` no está en `$ldap_ed_fields`
- **THEN** no se renderiza la fila de teléfono ni su ícono

#### Scenario: Campo de extensión con ícono
- **WHEN** `'extension'` está habilitado y el empleado tiene un valor de extensión
- **THEN** la fila de extensión se muestra con el ícono `building`, igual que ya ocurre hoy sin ícono

### Requirement: Departamento sin coincidencias en el dataset
Si `ldap_dept` no coincide (case-insensitive) con ningún departamento existente en el dataset cacheado, la vista de detalle SHALL renderizarse igualmente con el nombre solicitado en el encabezado, cero empleados, y el mensaje existente de "sin resultados" en la grilla.

#### Scenario: Departamento inexistente o mal escrito
- **WHEN** `ldap_dept=DepartamentoQueNoExiste`
- **THEN** se muestra el breadcrumb y el encabezado con ese nombre, la grilla muestra `.ldap-no-results`, y no se renderiza paginación
