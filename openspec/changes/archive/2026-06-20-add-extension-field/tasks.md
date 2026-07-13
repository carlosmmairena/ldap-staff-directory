## 1. LDAP Connector

- [x] 1.1 En `includes/class-ldap-connector.php`, añadir el atributo configurado (`$this->settings['extension_attr'] ?? 'ipPhone'`) al array `$attributes` en `get_users()`
- [x] 1.2 En el loop de construcción de usuarios, añadir `'extension' => $this->get_entry_value( $entry, $ext_attr ) ?? ''` al array de cada usuario

## 2. Admin Settings

- [x] 2.1 En `includes/class-admin.php`, añadir `extension_attr` al grupo de sanitización en `sanitize_settings()` usando `sanitize_text_field()`; usar `ipPhone` como fallback si está vacío
- [x] 2.2 Registrar el campo `extension_attr` con `add_settings_field()` en la sección `ldap_ed_section_display`, con `label_for`
- [x] 2.3 Crear el método `render_field_extension_attr()` que renderiza un `<input type="text">` con placeholder `ipPhone` y texto de ayuda indicando que debe coincidir con el nombre exacto del atributo LDAP
- [x] 2.4 Añadir `'extension'` a la lista de campos permitidos `$allowed_fields` en `sanitize_settings()` y añadirlo al array del control de checkboxes de campos visibles con su label i18n `__( 'Extension', 'ldap-staff-directory' )`

## 3. Shortcode

- [x] 3.1 En `includes/class-shortcode.php`, añadir `'extension'` a `$allowed_fields` en `render()`
- [x] 3.2 En el mismo método, añadir `'extension'` al loop que construye los `data-*` en las tarjetas (línea ~164 donde itera `array( 'name', 'email', 'title', 'department', 'phone' )`)

## 4. Frontend Template

- [x] 4.1 En `public/views/directory.php`, añadir bloque condicional para `extension` después del bloque de `phone`: renderizar `<span class="ldap-extension">` con `esc_html()` del valor, omitir si está vacío

## 5. Frontend CSS

- [x] 5.1 En `public/css/directory.css`, añadir estilos para `.ldap-extension` (fuente, color, alineación — coherente con `.ldap-phone` pero sin `text-decoration` de link)

## 6. Frontend JS (búsqueda client-side)

- [x] 6.1 Búsqueda es server-side en `filter_users()` — cubierto por tarea 3.2 (extensión añadida al foreach de campos)

## 7. Elementor Widget

- [x] 7.1 En `elementor/class-elementor-widget.php`, añadir `'extension' => __( 'Extension', 'ldap-staff-directory' )` al control multi-select de campos (`fields`)

## 8. Beaver Builder Module

- [x] 8.1 En `beaver-builder/class-bb-module.php`, añadir `'extension' => __( 'Extension', 'ldap-staff-directory' )` al campo `fields_to_show`

## 9. Versión y Changelog

- [x] 9.1 En `ldap-staff-directory.php`, actualizar `LDAP_ED_VERSION` a `1.1.2` en el header del plugin y en la constante
- [x] 9.2 En `readme.txt`, actualizar `Stable tag` a `1.1.2` y añadir entrada en `== Changelog ==` describiendo el nuevo campo de extensión
