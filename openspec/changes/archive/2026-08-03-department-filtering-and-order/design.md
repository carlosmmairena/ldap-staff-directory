## Context

`LDAP_ED_Connector::get_users()` construye un filtro LDAP fijo (`objectClass=person`, `mail=*`, `+exclude_disabled` opcional) y trae **todos** los empleados que lo cumplen. `LDAP_ED_Shortcode::extract_departments()` deriva la lista de departamentos y sus conteos a partir de ese mismo array ya traído, y siempre los ordena alfabéticamente (`ksort()`). No existe ningún punto de control para que el admin decida qué departamentos no deben llegar al público, ni para cambiar el orden de los chips.

Restricción central del diseño: la exclusión debe aplicarse **en el filtro de búsqueda LDAP**, no post-fetch en PHP (decidido en exploración — motivo: privacidad, los datos de departamentos sensibles no deben ni salir del servidor LDAP).

Esa restricción crea una dependencia circular si no se resuelve con cuidado: si `get_users()` ya excluye "RRHH" en su filtro, no puede volver a usarse para *descubrir* que "RRHH" existe y ofrecer des-excluirlo. El admin necesita una función de descubrimiento completamente separada que ignore las exclusiones vigentes.

## Goals / Non-Goals

**Goals:**
- El admin puede ver, en la página de settings, la lista real de valores del atributo `department` presentes en LDAP (con conteo), sin necesidad de conocerlos de antemano.
- El admin puede marcar cualquier subconjunto de esos departamentos, más el caso "sin departamento asignado", para excluirlos del directorio público.
- Los empleados excluidos nunca llegan al plugin — la exclusión ocurre en el filtro de la búsqueda LDAP de `get_users()`.
- El admin puede elegir el orden de los chips de departamento en la UI pública: alfabético (default, comportamiento actual) o por cantidad de contactos (descendente).
- El descubrimiento de departamentos es una acción explícita (botón), no una llamada LDAP automática en cada carga de la página de settings.

**Non-Goals:**
- No se modela la estructura real de OUs de LDAP (contenedores `organizationalUnit`); se sigue trabajando exclusivamente sobre el atributo `department` de las entradas `person`, igual que hoy.
- No se agrupa visualmente el grid público por departamento (secciones con encabezado); el grid sigue siendo una lista plana ordenada por nombre. Solo el orden de los *chips* de filtro es configurable.
- No se valida en el sanitizador que los valores de `excluded_departments` existan en el snapshot conocido — un valor huérfano (departamento renombrado/eliminado en LDAP) simplemente deja de tener efecto y de aparecer marcado, sin necesidad de limpieza activa.

## Decisions

### 1. Descubrimiento vía método nuevo y separado: `get_departments()`

Filtro: mismo filtro base que `get_users()` (`objectClass=person`, `mail=*`, `+exclude_disabled` si aplica) pero **sin** las cláusulas de `excluded_departments` ni `exclude_no_department`. Solo pide el atributo `department` (proyección liviana) y reutiliza el mismo bucle de paginación (`LDAP_CONTROL_PAGEDRESULTS`) que ya existe en `get_users()`.

**Alternativa descartada:** derivar el listado de departamentos a partir de la caché existente (`LDAP_ED_CACHE_KEY`) para evitar una consulta LDAP adicional. Se descarta porque la caché, una vez implementada la exclusión, **ya no contendría** los departamentos excluidos — el admin jamás podría volver a verlos para des-excluirlos. El descubrimiento debe ser una fuente de verdad independiente del pipeline de exhibición pública.

**Refactor menor:** el bucle de paginación (`do { ldap_search + paged control } while ($cookie)`) se extrae a un helper privado compartido entre `get_users()` y `get_departments()` para no duplicar ~30 líneas de manejo de cookies RFC 2696.

### 2. Snapshot persistente en vez de consulta automática

Nueva opción `ldap_ed_known_departments` (permanente, sin TTL — mismo patrón que `LDAP_ED_STALE_KEY`), actualizada únicamente cuando el admin dispara la acción AJAX `ldap_ed_get_departments` (botón "Actualizar lista de departamentos"). La página de settings renderiza el checklist desde este snapshot en server-side render; si no existe snapshot (primera vez), se muestra un estado vacío con el mismo botón.

**Alternativa descartada:** disparar el descubrimiento automáticamente en cada carga de `render_settings_page()`. Se descarta por costo — una consulta LDAP paginada completa cada vez que el admin abre la pestaña de settings es innecesario y puede ser lento/costoso en directorios grandes o con rate limiting.

### 3. Exclusión de "sin departamento" como checkbox separado, no como valor dentro de `excluded_departments`

`exclude_no_department` es un setting booleano independiente (mismo patrón que `exclude_disabled`), no un valor sentinel mezclado en el array `excluded_departments`.

**Alternativa descartada:** agregar un valor mágico (ej. `__no_department__`) dentro de `excluded_departments`. Se descarta porque requiere sanitización especial, lógica condicional al construir el filtro (una cláusula de *presencia* `(department=*)` en vez de una cláusula de *igualdad negada* `(!(department=X))`), y crea una colisión teórica si algún día existiera un departamento con ese nombre literal. Un booleano separado es más simple, más explícito en la UI (fila visualmente distinta, no mezclada con nombres reales) y sigue el patrón ya validado de `exclude_disabled`.

### 4. Construcción del filtro LDAP en `get_users()`

```
$parts = [ '(objectClass=person)', '(mail=*)' ];
if ( exclude_disabled )     $parts[] = '(!(userAccountControl:...:=2))';
if ( exclude_no_department ) $parts[] = '(department=*)';
foreach ( excluded_departments as $dept ) {
    $parts[] = '(!(department=' . ldap_escape( $dept, '', LDAP_ESCAPE_FILTER ) . '))';
}
$filter = '(&' . implode( '', $parts ) . ')';
```

`ldap_escape()` (nativo de PHP ≥5.6, `LDAP_ESCAPE_FILTER`) protege contra valores de departamento con paréntesis, `*` o `\` que romperían la sintaxis del filtro o permitirían LDAP injection.

### 5. Orden de chips configurable

`extract_departments()` en `class-shortcode.php` cambia de `ksort( $counts )` incondicional a:
```php
if ( 'count_desc' === $department_order ) {
    arsort( $counts );
} else {
    ksort( $counts );
}
```
Sin impacto en caché (la caché almacena empleados, no el orden de presentación) ni en el grid público (sigue ordenado por nombre).

## Flujo de descubrimiento (secuencia)

```
Admin                Admin JS            AJAX Handler         Connector          LDAP Server
  │  clic "Actualizar"    │                     │                    │                  │
  ├──────────────────────▶│                     │                    │                  │
  │                       │  POST ldap_ed_get_departments             │                  │
  │                       ├────────────────────▶│                    │                  │
  │                       │                     │ check nonce +      │                  │
  │                       │                     │ manage_options     │                  │
  │                       │                     ├───────────────────▶│                  │
  │                       │                     │  get_departments()  │  search paginado │
  │                       │                     │                    │ (sin exclusiones) │
  │                       │                     │                    ├─────────────────▶│
  │                       │                     │                    │◀─────────────────┤
  │                       │                     │◀───────────────────┤  [{name,count}], │
  │                       │                     │ update_option(      │  no_dept_count   │
  │                       │                     │  known_departments) │                  │
  │                       │◀────────────────────┤ wp_send_json_success │                  │
  │◀──────────────────────┤ re-render checklist │                    │                  │
  │  (marca lo ya guardado│                     │                    │                  │
  │   en excluded_deps)   │                     │                    │                  │
```

## Risks / Trade-offs

- **[Riesgo] Snapshot desactualizado tras cambiar `server`/`bind_dn`/`base_ou`** — el checklist podría mostrar departamentos de una conexión LDAP anterior hasta el próximo refresh manual. → **Mitigación:** limpiar `ldap_ed_known_departments` (vía `delete_option`) dentro de `sanitize_settings()` cuando cualquiera de esos tres campos cambia respecto al valor previo, igual que ya se hace con `purge()` del caché.
- **[Riesgo] Departamento renombrado en LDAP después de excluirlo** — el valor guardado en `excluded_departments` queda huérfano (no coincide con ningún depto real, no tiene efecto, no aparece marcado tras refrescar). → **Mitigación:** ninguna activa requerida (comportamiento inofensivo); se documenta como Non-Goal.
- **[Riesgo] Admin excluye todos los departamentos existentes** — el directorio público podría quedar vacío o mostrar solo empleados sin departamento (si `exclude_no_department` está desactivado). → **Mitigación:** ninguna a nivel de código; es una decisión válida del admin, igual que hoy es posible dejar `fields` vacío.
- **[Riesgo] Consulta de descubrimiento costosa en directorios muy grandes** — al no tener `department=*` como filtro obligatorio (ahora se cuenta también "sin departamento"), la consulta recorre el mismo universo que `get_users()` sin filtro de exclusión — mismo volumen que la carga pública actual. → **Mitigación:** ya paginada (RFC 2696, igual que `get_users()`); es una acción manual bajo demanda, no automática.

## Migration Plan

- Defaults preservan el comportamiento actual: `excluded_departments = []`, `exclude_no_department = '0'`, `department_order = 'alpha'` → sin exclusiones activas y orden alfabético hasta que el admin configure algo explícitamente.
- `purge()` del caché ya se ejecuta en cada guardado de settings (`sanitize_settings()`), por lo que cualquier cambio en las nuevas exclusiones invalida el caché público automáticamente — no se requiere lógica de invalidación adicional.
- `uninstall.php`: agregar `delete_option( 'ldap_ed_known_departments' )` (single-site y multisite) junto a la limpieza existente.
- Sin rollback especial: desactivar el plugin no borra opciones (comportamiento estándar ya existente); desinstalar sí las limpia todas.

## Open Questions

- Ninguna pendiente — las decisiones de alcance (OU = atributo `department`, trigger manual, checkbox separado para "sin departamento", solo orden de chips no agrupación del grid) fueron confirmadas durante la exploración previa a esta propuesta.
