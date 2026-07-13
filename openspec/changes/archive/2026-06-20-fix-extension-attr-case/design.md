## Context

La función PHP `ldap_get_entries()` normaliza todos los nombres de atributo a minúscula en el array devuelto, independientemente del case usado en la solicitud `ldap_search()` o del case real en el esquema LDAP. Este es el comportamiento estándar y documentado de la extensión PHP LDAP.

El método `get_entry_value()` en `LDAP_ED_Connector` ya documenta esto en su docblock (`@param string $attribute Attribute name (lowercase)`). Todos los atributos hardcodeados del plugin (`telephonenumber`, `displayname`, `mail`, `title`, `department`, `cn`) ya están en minúscula por este motivo.

El atributo configurable `$ext_attr` (leído del setting `extension_attr`) es el único que puede llegar en cualquier case según lo que el admin escriba, causando que la clave de lookup no coincida con las claves del array de entradas LDAP.

## Goals / Non-Goals

**Goals:**
- El campo `extension` funciona correctamente sin importar el case que el admin escriba en `extension_attr`.
- El texto de ayuda del campo en el admin refleja que el case es indiferente.

**Non-Goals:**
- No cambiar el comportamiento del array `$attributes` pasado a `ldap_search()` (el servidor acepta cualquier case).
- No aplicar normalización de case a otros campos configurables (no existen otros casos).

## Decisions

### D1 — Normalizar a minúscula en el momento de lectura, no en el de guardado

Aplicar `strtolower()` al usar `$ext_attr` como clave de lookup en el array de entradas (`get_entry_value( $entry, strtolower( $ext_attr ) )`), no al guardar el setting en `sanitize_settings()`.

**Razón:** Mantener el valor guardado fiel a lo que el admin escribió (mejor UX — el campo muestra `ipPhone` tal cual, no `ipphone`). La normalización ocurre solo en el punto donde PHP impone la restricción.

**Alternativa descartada:** Normalizar en `sanitize_settings()` con `strtolower()`. Funcional, pero el campo del admin mostraría `ipphone` en lugar de `ipPhone`, lo que puede confundir al admin al comparar con la documentación del esquema LDAP de su proveedor.

## Risks / Trade-offs

- **Sin riesgos conocidos.** `strtolower()` es una operación determinista y sin efectos secundarios. No afecta la petición LDAP (solo la lectura del resultado), no cambia datos guardados, y es consistente con el resto del connector.

## Migration Plan

Sin migración requerida. El fix es retrocompatible: instalaciones existentes con `extension_attr = 'ipPhone'` empezarán a funcionar automáticamente al actualizar el plugin.

## Open Questions

(ninguna)
