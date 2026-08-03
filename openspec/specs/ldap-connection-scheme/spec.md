## ADDED Requirements

### Requirement: Selector de esquema de conexión
La página de settings SHALL renderizar un `<select>` con las opciones "LDAP" (`'ldap'`) y "LDAPS" (`'ldaps'`), preseleccionado según la opción `scheme` guardada. Cuando `scheme` no existe todavía como setting, el valor preseleccionado SHALL inferirse del prefijo heredado en `server` (si lo hay); si no hay prefijo heredado ni `scheme` guardado, SHALL preseleccionarse `'ldaps'`.

#### Scenario: Instalación nueva sin configuración previa
- **WHEN** se renderiza el selector y no existe `scheme` ni `server` con prefijo heredado
- **THEN** el `<select>` muestra "LDAPS" preseleccionado

#### Scenario: Instalación existente con esquema LDAP heredado
- **WHEN** `scheme` no existe todavía, pero `server` está guardado como `"ldap://directory.example.com"`
- **THEN** el `<select>` muestra "LDAP" preseleccionado, no el default `"LDAPS"`

#### Scenario: Instalación ya migrada
- **WHEN** `scheme` ya existe como setting guardado (`'ldap'` o `'ldaps'`)
- **THEN** el `<select>` respeta ese valor, sin volver a inspeccionar `server`

### Requirement: Campo Server acepta y muestra solo el dominio
El campo "Server" SHALL mostrar y aceptar únicamente el dominio/host de la conexión, sin ningún prefijo de esquema (`ldap://`, `ldaps://`, `http://`, `https://`). Cuando el valor guardado de `server` contiene un prefijo heredado, el campo SHALL mostrar solo la porción de dominio, despojando el prefijo, independientemente de si el formulario ya fue guardado con el formato nuevo.

#### Scenario: Instalación existente con prefijo heredado
- **WHEN** `server` está guardado como `"ldaps://directory.example.com"` y el admin nunca guardó el formulario desde la actualización
- **THEN** el campo Server muestra `"directory.example.com"`, sin el prefijo

#### Scenario: Admin pega una URL completa por costumbre
- **WHEN** el admin escribe `"ldaps://otro-host.com"` en el campo Server y guarda
- **THEN** el valor guardado de `server` es `"otro-host.com"` (prefijo despojado en la sanitización)

#### Scenario: Etiqueta del campo ya no menciona un esquema específico
- **WHEN** se renderiza la sección de conexión
- **THEN** la etiqueta del campo es "Server", no "LDAPS Server"

### Requirement: Reconstrucción segura de la URI de conexión
`LDAP_ED_Connector::connect()` SHALL construir la URI de conexión concatenando `scheme` y el dominio de `server` (con cualquier prefijo heredado ya despojado), de forma que la URI resultante nunca contenga un prefijo duplicado, sin importar si `server` está guardado en el formato antiguo o en el nuevo.

#### Scenario: Instalación existente nunca resaved, esquema LDAPS heredado
- **WHEN** `server` está guardado como `"ldaps://directory.example.com"` y `scheme` no existe todavía
- **THEN** la URI usada para `ldap_connect()` es `"ldaps://directory.example.com"`, no `"ldaps://ldaps://directory.example.com"`

#### Scenario: Instalación ya migrada al formato nuevo
- **WHEN** `scheme` es `'ldap'` y `server` es `"directory.example.com"`
- **THEN** la URI usada es `"ldap://directory.example.com"`

### Requirement: Puerto con placeholder dinámico según el esquema
El campo de puerto SHALL renderizarse vacío con un atributo `placeholder` igual al puerto default del esquema actual (`636` para LDAPS, `389` para LDAP) cuando no hay un puerto guardado, o cuando el puerto guardado coincide exactamente con `636` o `389`. Cualquier otro valor de puerto guardado SHALL renderizarse como un valor real, nunca como placeholder.

#### Scenario: Sin puerto guardado
- **WHEN** el plugin nunca guardó un puerto y el esquema actual es LDAPS
- **THEN** el campo de puerto está vacío con `placeholder="636"`

#### Scenario: Puerto guardado coincide con el default
- **WHEN** el puerto guardado es `636` y el esquema actual es LDAPS
- **THEN** el campo de puerto está vacío con `placeholder="636"`, no `value="636"`

#### Scenario: Puerto personalizado se preserva como valor real
- **WHEN** el puerto guardado es `3269`
- **THEN** el campo de puerto se renderiza con `value="3269"`, no como placeholder, independientemente del esquema actual

### Requirement: Actualización en vivo del placeholder de puerto al cambiar el esquema
Al cambiar el `<select>` de esquema, el `placeholder` del campo de puerto SHALL actualizarse al default del nuevo esquema (`389`/`636`). Esta actualización SHALL afectar únicamente el atributo `placeholder` — el `value` del campo NUNCA SHALL modificarse por este listener, sin importar si el campo está vacío o si ya tiene un valor escrito (incluyendo un valor que coincida numéricamente con un default).

#### Scenario: Cambiar de esquema con el campo vacío
- **WHEN** el campo de puerto está vacío (mostrando el placeholder `636`) y el admin cambia el selector a "LDAP"
- **THEN** el placeholder pasa a `389`; el campo permanece vacío (sin `value`)

#### Scenario: Cambiar de esquema con un valor ya escrito
- **WHEN** el admin escribió `636` (o cualquier otro número) como valor real del campo de puerto y cambia el selector de esquema
- **THEN** el `value` del campo permanece `636`, sin cambios; solo el `placeholder` (invisible mientras haya un `value`) se actualiza

### Requirement: Sanitización correcta del puerto vacío
`sanitize_settings()` SHALL tratar un campo de puerto enviado vacío (`''`) como "usar el default del esquema elegido en el mismo submit", no como el valor `0`.

#### Scenario: Envío con puerto vacío y esquema LDAPS
- **WHEN** el formulario se envía con `port=''` y `scheme='ldaps'`
- **THEN** el valor guardado de `port` es `636`, no `0`

#### Scenario: Envío con puerto vacío y esquema LDAP
- **WHEN** el formulario se envía con `port=''` y `scheme='ldap'`
- **THEN** el valor guardado de `port` es `389`, no `0`

### Requirement: Sanitización del esquema contra lista blanca
`sanitize_settings()` SHALL validar `scheme` contra la lista `['ldap', 'ldaps']`, usando `'ldaps'` como valor de reserva ante cualquier valor fuera de esa lista.

#### Scenario: Valor de esquema inesperado
- **WHEN** el formulario se envía con un valor de `scheme` distinto a `'ldap'`/`'ldaps'`
- **THEN** el valor guardado de `scheme` es `'ldaps'`
