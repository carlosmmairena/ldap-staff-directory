## ADDED Requirements

### Requirement: Extension attribute setting
El sistema SHALL permitir al administrador configurar el nombre del atributo LDAP del que se leerá la extensión telefónica. El setting `extension_attr` SHALL residir en la sección Display del admin y tener como valor por defecto `ipPhone`. El valor SHALL sanitizarse con `sanitize_text_field()`. El campo de ayuda SHALL indicar que el case del nombre del atributo es indiferente.

#### Scenario: Admin guarda atributo por defecto
- **WHEN** el admin accede por primera vez a la sección Display sin haber configurado `extension_attr`
- **THEN** el campo muestra el valor `ipPhone` como placeholder o valor por defecto

#### Scenario: Admin guarda atributo custom
- **WHEN** el admin escribe `extensionAttribute1` en el campo Extension attribute y guarda
- **THEN** el plugin usa `extensionAttribute1` como atributo LDAP en las búsquedas subsiguientes

#### Scenario: Admin guarda atributo vacío
- **WHEN** el admin borra el valor del campo Extension attribute y guarda
- **THEN** el sistema usa el fallback `ipPhone` como valor efectivo del atributo

#### Scenario: Purge de caché al cambiar atributo
- **WHEN** el admin guarda un nuevo valor de `extension_attr`
- **THEN** la caché LDAP se purga (transient + stale) para que los datos reflejen el nuevo atributo

---

### Requirement: Lectura del campo extension desde LDAP
El conector LDAP SHALL incluir el atributo configurado en `extension_attr` en cada búsqueda `ldap_search`. El nombre del atributo SHALL normalizarse a minúscula antes de usarse como clave de lectura en el array de entradas LDAP devuelto por `ldap_get_entries()`, dado que PHP normaliza todos los nombres de atributo a minúscula. El resultado SHALL mapearse al key `extension` en el array de usuario devuelto por `get_users()`. Si el atributo no existe en la entrada LDAP o está vacío, el valor SHALL ser una cadena vacía `''`.

#### Scenario: Usuario con extensión presente
- **WHEN** una entrada LDAP tiene el atributo configurado (ej. `ipPhone: 1234`)
- **THEN** el array de usuario incluye `'extension' => '1234'`

#### Scenario: Usuario sin extensión
- **WHEN** una entrada LDAP no tiene el atributo configurado o está vacío
- **THEN** el array de usuario incluye `'extension' => ''`

#### Scenario: Atributo LDAP inexistente en el esquema
- **WHEN** el atributo configurado no existe en el esquema LDAP del servidor
- **THEN** todos los usuarios tendrán `'extension' => ''` y el directorio se renderiza sin errores

#### Scenario: Atributo configurado en camelCase
- **WHEN** el admin configura `extension_attr = 'ipPhone'` (camelCase)
- **THEN** la extensión se visualiza correctamente en el directorio (equivalente a haber escrito `ipphone`)

#### Scenario: Atributo configurado en mayúsculas
- **WHEN** el admin configura `extension_attr = 'IPPHONE'`
- **THEN** la extensión se visualiza correctamente en el directorio

---

### Requirement: Extensión como campo visualizable
El sistema SHALL incluir `extension` en la lista de campos visualizables del directorio. El campo SHALL aparecer en la sección Display del admin junto a `name`, `email`, `title`, `department`, `phone`. El campo SHALL estar desactivado por defecto en instalaciones nuevas y existentes.

#### Scenario: Admin activa el campo extension
- **WHEN** el admin marca el checkbox `extension` en los campos visibles y guarda
- **THEN** las tarjetas del directorio muestran la extensión de los empleados que la tienen

#### Scenario: Admin desactiva el campo extension
- **WHEN** el admin desmarca el checkbox `extension` en los campos visibles y guarda
- **THEN** las tarjetas del directorio no muestran ningún dato de extensión

#### Scenario: Campo extension ausente en datos del usuario
- **WHEN** el campo `extension` está activado pero un usuario tiene extensión vacía
- **THEN** la tarjeta de ese usuario no muestra ningún elemento de extensión (sin espacio en blanco)

---

### Requirement: Renderizado de extensión como texto plano
El sistema SHALL renderizar el valor del campo `extension` como texto plano (elemento `<span class="ldap-extension">`), sin link `tel:` ni ningún otro enlace interactivo. El valor SHALL escaparse con `esc_html()`. El elemento SHALL omitirse completamente si el valor está vacío.

#### Scenario: Renderizado de extensión con valor
- **WHEN** un usuario tiene `extension = '1234'` y el campo está activado
- **THEN** la tarjeta renderiza `<span class="ldap-extension">1234</span>`

#### Scenario: Valor con caracteres especiales
- **WHEN** el valor LDAP contiene `Ext. 1234 <interno>`
- **THEN** el valor es escapado con `esc_html()` y se muestra de forma segura como texto

#### Scenario: Coexistencia con phone
- **WHEN** un usuario tiene `phone = '+506 2234-5678'` y `extension = '1234'` y ambos campos están activos
- **THEN** la tarjeta muestra el link de teléfono y el texto de extensión como elementos separados e independientes

---

### Requirement: Integración con shortcode
El shortcode `[ldap_directory]` SHALL aceptar `extension` como valor válido en el atributo `fields`. El campo SHALL incluirse en `$allowed_fields` del shortcode.

#### Scenario: Shortcode con extension incluida
- **WHEN** se usa `[ldap_directory fields="name,email,phone,extension"]`
- **THEN** el directorio muestra el campo extensión en las tarjetas

#### Scenario: Shortcode sin extension
- **WHEN** se usa `[ldap_directory fields="name,email,phone"]`
- **THEN** el directorio no muestra extensión aunque esté activada en el admin

---

### Requirement: Integración con Elementor
El widget de Elementor SHALL incluir `extension` como opción en el control multi-select de campos visibles.

#### Scenario: Widget Elementor con extension activada
- **WHEN** el usuario activa `extension` en los controles del widget de Elementor
- **THEN** el directorio renderizado por el widget muestra extensiones en las tarjetas

---

### Requirement: Integración con Beaver Builder
El módulo de Beaver Builder SHALL incluir `extension` como opción en el campo `fields_to_show`.

#### Scenario: Módulo BB con extension activada
- **WHEN** el usuario activa `extension` en los controles del módulo Beaver Builder
- **THEN** el directorio renderizado por el módulo muestra extensiones en las tarjetas

---

### Requirement: Búsqueda por extensión
Cuando la búsqueda client-side está activa, el sistema SHALL incluir el valor del campo `extension` en la evaluación de `matchesQuery()` en `directory.js`, de modo que un usuario pueda filtrar tarjetas por número de extensión.

#### Scenario: Búsqueda por número de extensión
- **WHEN** el usuario escribe `1234` en el campo de búsqueda y hay un empleado con `extension = '1234'`
- **THEN** la tarjeta de ese empleado permanece visible y el resto se oculta

#### Scenario: Extensión vacía no afecta la búsqueda
- **WHEN** un usuario tiene extensión vacía y se realiza una búsqueda
- **THEN** el usuario no aparece en resultados de búsquedas por extensión pero sí aparece en búsquedas que coinciden con sus otros campos
