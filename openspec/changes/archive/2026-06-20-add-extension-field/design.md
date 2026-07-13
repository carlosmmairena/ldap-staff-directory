## Context

El plugin actualmente soporta cinco campos visualizables: `name`, `email`, `title`, `department`, `phone`. El campo `phone` lee el atributo LDAP `telephonenumber` y lo renderiza como link `tel:`. Las organizaciones con centrales telefónicas (PBX) necesitan un campo separado para extensiones internas, cuyo atributo LDAP varía según el proveedor del directorio.

Estado actual del flujo de datos:
```
LDAP telephonenumber → Connector::get_users() → $user['phone'] → template <a href="tel:...">
```

El nuevo flujo agrega una rama paralela:
```
LDAP <atributo configurable> → Connector::get_users() → $user['extension'] → template <span class="ldap-extension">
```

## Goals / Non-Goals

**Goals:**
- Agregar `extension` como sexto campo visualizable, independiente de `phone`.
- El atributo LDAP de origen es configurable por el admin (default: `ipPhone`).
- La extensión se renderiza como texto plano, sin link `tel:`.
- Funciona en shortcode, Elementor y Beaver Builder con el mismo patrón que los campos existentes.
- Compatible con AD (`ipPhone`), Samba, OpenLDAP y atributos custom.

**Non-Goals:**
- No se combina la extensión con el número de teléfono en un único link RFC 3966.
- No se valida el formato de la extensión (el plugin muestra el valor tal como viene del LDAP).
- No se soporta múltiples extensiones por usuario (se toma el primer valor del atributo).

## Decisions

### D1 — Setting `extension_attr` en sección Display (no Connection)

La audiencia objetivo son admins de WordPress sin conocimiento profundo de LDAP. Colocar el setting de atributo LDAP junto a los campos visibles (`fields`, `per_page`) en la sección Display es más intuitivo: el admin activa el campo y en el mismo panel configura de dónde viene el dato.

**Alternativa descartada:** Sección Connection (semánticamente correcto para un mapeo de esquema LDAP, pero más confuso para el perfil de usuario objetivo).

### D2 — Atributo LDAP configurable con default `ipPhone`

En lugar de hard-codear `ipPhone`, se permite que el admin especifique el nombre del atributo. Razón: los entornos LDAP son variados (Samba usa atributos distintos, algunos esquemas custom usan `extensionAttribute1`, etc.). El default `ipPhone` cubre el caso más común (AD con IP-PBX) sin requerir configuración adicional para esa mayoría.

**Alternativa descartada:** Hard-code de `ipPhone`. Más simple, pero rompe para Samba/OpenLDAP y esquemas custom.

### D3 — Texto plano, sin link `tel:`

Las extensiones internas no son marcables desde redes externas. Un link `tel:1234` en un móvil iniciaría una llamada a un número de un dígito inútil. La extensión es un dato de referencia, no un punto de contacto directo.

### D4 — Valor mostrado tal cual, sin prefijo automático "Ext."

El plugin no añade prefijo "Ext." ni formatea el valor. Razón: el formato de la extensión varía por organización (algunos ya tienen "Ext. 1234" en el LDAP, otros solo "1234"). Transformar el valor podría generar duplicados ("Ext. Ext. 1234"). El admin controla el formato a nivel LDAP.

### D5 — Extensión incluida en búsqueda client-side

El campo `extension` se agrega a la función `matchesQuery()` en `directory.js`, consistente con el resto de campos. Un usuario puede buscar por número de extensión.

### D6 — Purge de caché al guardar `extension_attr`

El setting `extension_attr` se sanitiza y guarda junto al resto de settings en `sanitize_settings()`. El mecanismo existente de purge de caché al guardar settings cubre este caso sin cambio adicional.

## Risks / Trade-offs

- **[Risk] Atributo LDAP inexistente:** Si el admin configura un atributo que no existe en el esquema LDAP, todos los usuarios tendrán `extension` vacío. El campo simplemente no se renderiza (comportamiento idéntico al de otros campos vacíos). No hay error visible, lo cual puede confundir al admin. → **Mitigation:** El campo de ayuda en el admin menciona que debe coincidir exactamente con el nombre del atributo LDAP.

- **[Risk] Rendimiento:** Se agrega un atributo más al `ldap_search`. Para directorios muy grandes el impacto es mínimo (atributo de cadena simple). La caché existente absorbe la diferencia.

- **[Trade-off]** Al mostrar el valor crudo del LDAP sin transformación, la presentación queda en manos de quién administra el directorio LDAP, no del plugin. Esto es intencional (D4) pero puede percibirse como falta de pulido si el LDAP no es consistente.

## Migration Plan

No requiere migración de datos. El nuevo campo es aditivo:
- Instalaciones existentes: `extension` desactivado por defecto en campos visibles; `extension_attr` = `ipPhone` como default.
- El admin activa el campo en Display settings si lo necesita.
- No hay cambio de esquema de base de datos; todo se almacena en `ldap_ed_settings` (WP option existente).

## Open Questions

(ninguna — el diseño está completamente definido)
