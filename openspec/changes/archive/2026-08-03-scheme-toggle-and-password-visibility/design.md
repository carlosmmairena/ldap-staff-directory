## Context

`includes/class-admin.php::render_field_server()` renderiza un único campo de texto para `server`, guardado con el esquema embebido (`"ldaps://directory.example.com"`), validado por `sanitize_ldap_server()` (exige prefijo `ldap://`/`ldaps://`, cae al valor previo si es inválido). `render_field_port()` siempre imprime un `value` numérico real (nunca ha usado `placeholder`). `LDAP_ED_Connector::connect()` pasa `server` (con esquema incluido) y `port` por separado a `ldap_connect()`. `render_field_bind_pass()` siempre renderiza el campo vacío (la contraseña guardada nunca se re-envía al navegador — comportamiento de seguridad que este cambio no toca).

Referencia visual explorada antes de esta propuesta: `docs/design-admin-ui.pen` (3 paneles comparando estado LDAPS/LDAP/puerto-personalizado, y 2 paneles comparando contraseña oculta/visible).

Restricción central: partir `server` en `scheme` + dominio no puede romper instalaciones existentes que ya tienen `server` guardado en el formato antiguo con el esquema embebido.

## Goals / Non-Goals

**Goals:**
- El admin elige el esquema (LDAP/LDAPS) desde un `<select>` separado del campo de servidor.
- El campo "Server" acepta y guarda solo el dominio/host, nunca un esquema embebido, hacia adelante.
- El campo de puerto muestra un placeholder nativo (636/389 según el esquema) mientras no tenga un valor explícito; una vez que el admin escribe algo, el selector de esquema deja de tocarlo.
- Instalaciones existentes (con `server` en el formato antiguo) migran de forma transparente: sin romper la conexión LDAP, sin requerir que el admin vuelva a guardar el formulario para que la UI se vea correcta.
- El admin puede alternar la visibilidad de la contraseña de bind mientras la edita.

**Non-Goals:**
- No se valida el formato del dominio en `server` más allá de lo que ya hace `sanitize_text_field()` (sin regex de hostname) — se deja para una iteración futura si se necesita.
- No se cambia el comportamiento de seguridad de `bind_pass` (sigue sin re-enviarse al navegador; el toggle de visibilidad solo afecta lo que el admin está tecleando en ese momento).
- No se acopla `scheme` con `verify_ssl` — son conceptualmente independientes (STARTTLS puede aplicar sobre `ldap://` también); cambiar el esquema no modifica `verify_ssl` automáticamente.

## Decisions

### 1. Helper compartido para separar esquema y dominio

Nueva función global `ldap_ed_split_server_scheme( string $raw_server ): array` (junto a los demás helpers en `ldap-staff-directory.php`, mismo patrón que `ldap_ed_encrypt_pass()` etc.). Devuelve `[ 'scheme' => 'ldap'|'ldaps'|null, 'domain' => string ]`, despojando cualquier prefijo `ldap://`/`ldaps://` (o `http://`/`https://` por si el admin pega una URL completa por costumbre) del valor crudo. `scheme` es `null` cuando no había ningún prefijo reconocible (valor ya en formato nuevo).

Se usa en tres lugares:
- `render_field_server()` — muestra siempre `domain`, nunca el prefijo, incluso en instalaciones que no han vuelto a guardar el formulario desde la actualización.
- `render_field_scheme()` (nuevo) — determina el valor a preseleccionar: `get_option('scheme')` si ya existe; si no, el `scheme` inferido por el helper a partir del `server` heredado; si tampoco hay prefijo heredado, default `'ldaps'`.
- `LDAP_ED_Connector::connect()` — reconstruye la URI como `scheme + '://' + domain`, usando siempre el `domain` ya despojado del helper — así nunca se duplica el prefijo aunque `server` todavía tenga el formato antiguo en la base de datos.

**Alternativa descartada:** una rutina de migración única en `admin_init` que reescriba la opción en la base de datos la primera vez que se detecta el formato antiguo. Se descarta porque el plugin no tiene infraestructura de versionado de opciones hoy, y el helper de lectura defensiva ya resuelve el problema — tanto la visualización como la conexión quedan correctas sin necesidad de una migración activa. El guardado sí escribe siempre el formato nuevo (limpio) desde el primer submit del nuevo formulario.

### 2. `scheme` como setting independiente, no como parte de `server`

Confirmado en exploración: en vez de que el `<select>` reescriba el prefijo dentro del texto de `server` (opción descartada), se introduce el setting `scheme` (`'ldap'`\|`'ldaps'`) y `server` pasa a ser solo dominio. Esto es lo que obliga a la migración de la Decisión 1, pero dado que ya está resuelta ahí, el modelo de datos queda más limpio: el dominio nunca cambia entre los dos esquemas, solo el `scheme` y el `port` lo hacen.

### 3. Puerto como placeholder nativo, con protección de valores personalizados

`render_field_port()` cambia de siempre imprimir `value` a decidir entre `value` (real) y `placeholder` (fantasma):
- Si el puerto guardado es exactamente `636` o `389` → se trata como "default sin personalizar": el campo se renderiza **vacío** con `placeholder` igual al default del `scheme` actual.
- Cualquier otro número guardado (ej. `3269`) → se trata como "personalizado": se renderiza con `value` real, nunca como placeholder.
- Campo nuevo/nunca guardado → vacío con placeholder del default correspondiente al `scheme` actual.

En el cliente, `admin.js` escucha el `change` del `<select>` de esquema y actualiza únicamente el atributo `placeholder` del campo de puerto (636/389) — **nunca** su `value`. Es la lectura más literal del pedido original ("cambie el puerto por defecto como placeholder, pero que permita ser editable") y evita el caso ambiguo de "¿un valor que el admin escribió a mano y que coincide numéricamente con un default cuenta como default o como personalizado?": con este diseño la pregunta no se plantea — cualquier `value` ya escrito, sea cual sea, nunca se toca en vivo. (La heurística "636/389 guardado = default" sigue existiendo, pero solo aplica al render inicial en PHP a partir de lo guardado en la base de datos, no a la interacción en vivo con el `<select>`.)

**Consecuencia server-side obligatoria:** `sanitize_settings()` ya no puede usar `absint( $input['port'] ?? 636 )` tal cual — un campo vacío llega como `''` (la clave sí existe), y `absint('')` da `0`, no `636`. La sanitización debe tratar `'' === $input['port']` como "usar el default del esquema elegido en este mismo submit", no como puerto `0`.

### 4. Botón de mostrar/ocultar contraseña — sin cambios de backend

Puramente front-end: `render_field_bind_pass()` agrega un botón-ícono junto al `<input type="password">` existente; `admin.js` alterna `type="password"`↔`type="text"` del input al hacer clic, y el ícono entre `eye`/`eye-off` (mismo patrón de inyección de icono SVG que ya usa `.ldap-ed-test-result::before` en `admin.css`, documentado en `.interface-design/system.md`). No toca `sanitize_settings()`, `ldap_ed_encrypt_pass()`, ni el comportamiento de "nunca re-enviar la contraseña guardada" — el campo sigue naciendo vacío en cada carga de página.

### 5. Renombrar la etiqueta del campo

`'server' => __( 'LDAPS Server', ... )` pasa a `__( 'Server', ... )` en el arreglo `$connection_text_fields` de `register_settings()` — ya no es específico de un esquema ahora que el `<select>` existe por separado.

## Flujo de migración (secuencia)

```
Admin abre Settings (instalación existente, nunca actualizó el form nuevo)
        │
        ▼
render_field_scheme()          render_field_server()           render_field_port()
  lee scheme (no existe)         lee server = "ldaps://host"      lee port = 636
  → ldap_ed_split_server_scheme("ldaps://host")
       = { scheme: "ldaps", domain: "host" }
        │                              │                                │
        ▼                              ▼                                ▼
  <select> preseleccionado      muestra solo "host"              vacío, placeholder "636"
  en "LDAPS" (inferido)         (sin prefijo visible)             (636 == default LDAPS)

Admin guarda el formulario sin tocar nada
        │
        ▼
sanitize_settings(): scheme="ldaps" (whitelist), server="host" (limpio, ya sin prefijo
  porque el campo del formulario nunca lo tuvo), port=636 (default aplicado porque
  llegó vacío)
        │
        ▼
Opción guardada en formato nuevo — próximas cargas ya no necesitan inferencia
```

## Risks / Trade-offs

- **[Riesgo] El helper de despojo de prefijo no reconoce un formato inesperado** (ej. `server` guardado con espacios extra o mayúsculas `LDAPS://`) → **Mitigación:** el regex de detección es case-insensitive y hace `trim()` antes de comparar, igual que ya hacía `sanitize_ldap_server()`.
- **[Riesgo] Puerto guardado como `636`/`389` que el admin SÍ eligió deliberadamente (no es "el default sin tocar", coincide por casualidad)** → al cambiar el esquema, ese valor se trataría como personalizable y podría actualizarse a 389/636 sin que el admin lo pidiera explícitamente. **Mitigación:** aceptado como trade-off consciente (decisión tomada en exploración) — es indistinguible de "default sin personalizar" sin guardar un flag adicional, y el caso de coincidencia accidental es infrecuente comparado con el beneficio de que instalaciones ya existentes con el puerto default se comporten de forma consistente con instalaciones nuevas.
- **[Riesgo] `sanitize_settings()` requiere lógica nueva para el puerto vacío** → si se omite, un puerto vacío se guardaría como `0`, rompiendo la conexión. **Mitigación:** cubierto explícitamente en Decisión 3 y en `tasks.md`.
- **[Riesgo] Cambiar la etiqueta de "LDAPS Server" a "Server" es un cambio de texto visible** → bajo impacto, no afecta datos ni comportamiento, solo legibilidad de la UI.

## Migration Plan

- Sin acción requerida del admin: la primera carga de la página de settings después de actualizar ya muestra los campos separados y correctos (Decisión 1).
- El primer guardado del formulario (con o sin cambios) reescribe `server`/`scheme`/`port` en el formato nuevo de forma transparente — mismo espíritu que la migración ya existente de `bind_pass` de texto plano a cifrado "en el próximo guardado".
- Sin rollback especial: desactivar el plugin no borra opciones; si se hace downgrade del código a una versión anterior sin este cambio, `LDAP_ED_Connector::connect()` de la versión vieja leería `server` (ahora solo dominio, sin esquema) y probablemente fallaría al conectar — riesgo aceptado de cualquier downgrade de plugin, no específico de este cambio.

## Open Questions

- Ninguna pendiente — todas las decisiones de alcance (setting `scheme` separado, migración por lectura defensiva en vez de rutina activa, heurística de puerto default-vs-personalizado, alcance del toggle de contraseña) fueron confirmadas durante la exploración previa a esta propuesta.
