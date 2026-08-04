## MODIFIED Requirements

### Requirement: Extension attribute setting
El sistema SHALL permitir al administrador configurar el nombre del atributo LDAP del que se leerá la extensión telefónica. El setting `extension_attr` SHALL residir en la pestaña "Campos" del admin y tener como valor por defecto `ipPhone`. El valor SHALL sanitizarse con `sanitize_text_field()`. El campo de ayuda SHALL indicar que el case del nombre del atributo es indiferente. A diferencia del comportamiento anterior (campo siempre visible dentro de "Configuración avanzada"), el campo SHALL revelarse únicamente cuando el checkbox "Extensión" está marcado dentro de "Campos a mostrar", y SHALL permanecer oculto (sin renderizarse como input editable) cuando ese checkbox no está marcado.

#### Scenario: Admin guarda atributo por defecto
- **WHEN** el admin accede por primera vez a la pestaña Campos sin haber configurado `extension_attr`
- **THEN** el campo, una vez revelado, muestra el valor `ipPhone` como placeholder o valor por defecto

#### Scenario: Admin guarda atributo custom
- **WHEN** el admin escribe `extensionAttribute1` en el campo Extension attribute y guarda
- **THEN** el plugin usa `extensionAttribute1` como atributo LDAP en las búsquedas subsiguientes

#### Scenario: Admin guarda atributo vacío
- **WHEN** el admin borra el valor del campo Extension attribute y guarda
- **THEN** el sistema usa el fallback `ipPhone` como valor efectivo del atributo

#### Scenario: Purge de caché al cambiar atributo
- **WHEN** el admin guarda un nuevo valor de `extension_attr`
- **THEN** la caché LDAP se purga (transient + stale) para que los datos reflejen el nuevo atributo

#### Scenario: Campo oculto cuando Extensión no está marcada
- **WHEN** el checkbox "Extensión" dentro de "Campos a mostrar" no está marcado
- **THEN** el input de Extension attribute no se muestra en la pestaña Campos

#### Scenario: Campo se revela al marcar Extensión
- **WHEN** el admin marca el checkbox "Extensión" dentro de "Campos a mostrar"
- **THEN** el input de Extension attribute aparece inmediatamente debajo, dentro del mismo bloque, sin recargar la página

#### Scenario: Valor guardado se conserva aunque el campo esté oculto
- **WHEN** el admin desmarca "Extensión" (ocultando el input) pero ya tenía un `extension_attr` custom guardado, y guarda la pestaña sin volver a marcarlo
- **THEN** el valor de `extension_attr` guardado previamente no se pierde ni se resetea a `ipPhone`
