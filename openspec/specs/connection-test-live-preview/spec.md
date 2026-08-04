## ADDED Requirements

### Requirement: Probar conexión valida el formulario actual, no lo guardado
El botón "Probar conexión" SHALL validar los valores actualmente escritos en el formulario de la pestaña Conexión, incluso si no fueron guardados todavía, en vez de leer siempre `get_option(LDAP_ED_OPTION_KEY)`.

#### Scenario: Prueba refleja un servidor recién escrito
- **WHEN** el admin escribe un Server distinto al guardado y hace click en "Probar conexión" sin guardar antes
- **THEN** la prueba de conexión usa el Server recién escrito, no el guardado previamente

#### Scenario: Prueba refleja un Bind DN recién escrito
- **WHEN** el admin escribe un Bind DN distinto al guardado y prueba la conexión sin guardar
- **THEN** la conexión se intenta con el Bind DN nuevo

---

### Requirement: Fallback de contraseña vacía a la ya guardada
Si el campo Bind Password del formulario está vacío al probar la conexión, el sistema SHALL usar la contraseña ya guardada (desencriptada), igual que hace el guardado real, en vez de intentar conectarse con una contraseña vacía.

#### Scenario: Prueba sin tocar el password
- **WHEN** el admin cambia solo el Server y prueba la conexión sin escribir nada en Bind Password
- **THEN** la prueba usa la contraseña ya guardada y desencriptada, no una cadena vacía

#### Scenario: Prueba con password nuevo
- **WHEN** el admin escribe una contraseña nueva en el campo Bind Password y prueba la conexión
- **THEN** la prueba usa esa contraseña nueva en texto plano, sin guardarla todavía en la base de datos

---

### Requirement: Validación compartida entre guardar y probar
La sanitización de los campos de conexión (scheme, dominio del server, puerto por defecto) SHALL usar la misma función tanto al guardar la pestaña Conexión como al probar la conexión sin guardar, de modo que ambos caminos apliquen exactamente las mismas reglas.

#### Scenario: Reglas de puerto consistentes
- **WHEN** el admin deja el campo Port vacío y prueba la conexión
- **THEN** se usa el mismo puerto por defecto (389 o 636 según el scheme) que se usaría si guardara el formulario
