## ADDED Requirements

### Requirement: Navegación por pestañas persistentes
La página de settings SHALL organizar sus campos en 3 pestañas persistentes — "Conexión", "Empleados", "Campos" — navegables libremente en cualquier orden, no como un asistente secuencial. Cada pestaña SHALL permanecer accesible en cualquier momento, incluso después de que las demás hayan sido guardadas.

#### Scenario: Cambiar de pestaña sin guardar
- **WHEN** el admin cambia a otra pestaña sin haber guardado la que estaba editando
- **THEN** la navegación no se bloquea ni exige confirmación obligatoria

#### Scenario: Acceso directo a cualquier pestaña
- **WHEN** el admin ya completó el setup inicial y vuelve a visitar la página de settings
- **THEN** puede abrir directamente cualquiera de las 3 pestañas, sin pasar antes por las demás

---

### Requirement: Guardado independiente por pestaña
Cada pestaña SHALL tener su propio formulario y botón de guardado. Al enviarse, SHALL actualizar únicamente las claves de `ldap_ed_settings` correspondientes a esa pestaña, preservando sin modificar las claves de las otras dos pestañas.

#### Scenario: Guardar Empleados no afecta Conexión
- **WHEN** el admin cambia `excluded_departments` en la pestaña Empleados y guarda
- **THEN** `server`, `bind_dn`, `bind_pass` y el resto de los campos de Conexión permanecen exactamente como estaban antes del guardado

#### Scenario: Guardar Conexión no afecta Campos
- **WHEN** el admin cambia `server` en la pestaña Conexión y guarda
- **THEN** `fields`, `per_page`, `extension_attr` y el resto de los campos de Campos no cambian

---

### Requirement: Reagrupación de campos por pregunta que responden
El campo `port` SHALL ser un campo principal (no colapsado) de la pestaña Conexión. El campo `exclude_disabled` SHALL ser un campo principal de la pestaña Empleados. El acordeón "Configuración avanzada" de Conexión SHALL contener únicamente `verify_ssl` y `ca_cert`.

#### Scenario: Port visible sin expandir avanzado
- **WHEN** el admin abre la pestaña Conexión
- **THEN** el campo Port es visible sin necesidad de expandir "Configuración avanzada"

#### Scenario: Exclude Disabled vive en Empleados
- **WHEN** el admin abre la pestaña Empleados
- **THEN** el checkbox "Excluir cuentas deshabilitadas" aparece junto a los demás filtros de empleados, no en la pestaña Conexión

---

### Requirement: Redirección a la pestaña guardada
Tras un guardado exitoso, el sistema SHALL redirigir al admin de vuelta a la misma pestaña que acaba de guardar (vía parámetro `tab` en la URL), no a la primera pestaña por defecto.

#### Scenario: Redirección tras guardar Campos
- **WHEN** el admin guarda cambios en la pestaña Campos
- **THEN** la página recargada muestra la pestaña Campos activa, no Conexión

---

### Requirement: Aviso de rotación de salts enlaza a Conexión
Cuando `maybe_show_salt_rotation_notice()` detecta que las claves de seguridad de WordPress rotaron, el aviso SHALL incluir un enlace que abre la página de settings directamente en la pestaña Conexión, con el campo Bind Password enfocado.

#### Scenario: Click en el aviso de rotación
- **WHEN** el admin hace click en el enlace del aviso de rotación de salts
- **THEN** aterriza en la pestaña Conexión con el foco ya puesto en el campo Bind Password

---

### Requirement: Purga de caché escopeada por pestaña
Guardar la pestaña Conexión o la pestaña Empleados SHALL purgar la caché (transient + stale), igual que el comportamiento actual. Guardar la pestaña Campos SHALL purgar la caché únicamente cuando `extension_attr` cambió; el resto de los campos de Campos (`fields`, `per_page`, `enable_search`, `department_order`, `cache_ttl`) NO SHALL disparar una purga de caché.

#### Scenario: Guardar Conexión purga caché
- **WHEN** el admin guarda la pestaña Conexión
- **THEN** la caché LDAP se purga igual que en el comportamiento actual

#### Scenario: Guardar Campos sin cambiar extension_attr no purga
- **WHEN** el admin guarda la pestaña Campos habiendo cambiado solo `per_page`
- **THEN** la caché LDAP no se purga ni se vacía

#### Scenario: Guardar Campos cambiando extension_attr sí purga
- **WHEN** el admin guarda la pestaña Campos habiendo cambiado `extension_attr`
- **THEN** la caché LDAP se purga para reflejar el nuevo atributo
