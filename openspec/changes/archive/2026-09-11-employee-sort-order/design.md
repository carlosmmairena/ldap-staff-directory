## Context

Hoy el orden de los empleados es un efecto colateral de `LDAP_ED_Connector::get_users()`, que aplica un `usort()` fijo por `name` (via `strcmp`) sobre el array completo antes de cachearlo. Ese array cacheado fluye sin reordenar a través de `extract_departments()` (que sí es configurable, vía `department_order`), `filter_users()` (filtra, no reordena) y `paginate_users()` (solo recorta). El resultado es que el orden visible en `directory.php` es siempre "nombre A–Z", sin forma de cambiarlo salvo editando código.

Se necesita un segundo criterio de orden — configurable desde el admin — que aplique específicamente a la grilla de empleados de la vista de detalle (`directory.php`), independiente del `department_order` que ya gobierna el menú de departamentos.

## Goals / Non-Goals

**Goals:**
- El admin elige, con un único `<select>`, uno de 4 órdenes: nombre A–Z, nombre Z–A, puesto A–Z, puesto Z–A.
- El orden aplica de forma consistente en todas las páginas de la paginación server-side (no solo dentro de cada página).
- Compatibilidad hacia atrás total: instalaciones sin el setting guardado se comportan exactamente igual que hoy.

**Non-Goals:**
- No se ordena por email, teléfono ni extensión (explícitamente descartado por el usuario para esta iteración).
- No hay override por shortcode (`ldap_sort` att) ni control para el visitante en la página pública.
- No se corrige la falta de collation por locale en `strcmp()` (acentos/ñ quedan con el comportamiento por defecto de PHP, sin `Collator`/`intl`).
- No se toca `department_order` ni el menú de departamentos — es un eje de orden distinto y ya resuelto.

## Decisions

**1. El orden se mueve del conector al shortcode.**
`LDAP_ED_Connector::get_users()` deja de ordenar (se elimina el `usort` de `strcmp` por nombre). El array que devuelve/cachea queda en el orden que entrega LDAP (no garantizado). El único punto que decide el orden final es un nuevo método privado en `LDAP_ED_Shortcode`, ejecutado en `render()` entre `filter_users()` y `paginate_users()`.
*Alternativa descartada*: mantener el sort en el conector y parametrizarlo ahí. Se descarta porque el conector cachea el resultado una sola vez (TTL de `cache_ttl`); si el admin cambia `employee_order` sin cambiar ningún otro setting que dispare purga, el orden nuevo no se vería hasta el siguiente refresh de caché — un desfase confuso para un cambio que se siente instantáneo en la UI de settings.

**2. Un solo `<select>` con 4 presets, no campo+dirección separados.**
Igual patrón que `department_order` (`'alpha'` / `'count_desc'`): un valor de string con semántica completa (`name_asc`, `name_desc`, `title_asc`, `title_desc`), sanitizado contra whitelist. Consistente con el estilo ya establecido en `sanitize_settings()` y más simple de validar/testear que dos campos independientes.

**3. Desempate de puesto siempre por nombre A–Z, incluso en `title_desc`.**
Cuando dos empleados comparten puesto (o ambos tienen puesto vacío), el desempate usa `strcmp($a['name'], $b['name'])` sin invertir signo, sin importar si el orden primario de puesto es ascendente o descendente. Decisión explícita del usuario: el desempate no hereda la dirección del criterio primario.

**4. Guardar `employee_order` no dispara purga de caché.**
Sigue el mismo tratamiento que `per_page`, `fields` y `department_order`: son ajustes de presentación sobre datos ya cacheados, no cambian qué se lee de LDAP. Solo `extension_attr` (dentro de la pestaña Display) fuerza purga, porque cambia el atributo LDAP consultado.

**5. Comparación de strings: `strcmp()` sin cambios.**
Se mantiene el comportamiento actual (byte-wise, no locale-aware) por decisión explícita del usuario — evita introducir una dependencia opcional (`intl`/`Collator`) y su lógica de fallback en esta iteración.

## Risks / Trade-offs

- **[Riesgo] Quitar el sort del conector expone el array cacheado "desordenado" a cualquier otro consumidor futuro que asuma orden por nombre** (p. ej. un endpoint AJAX que lea `get_users()` directamente). → *Mitigación*: documentar en el docblock de `get_users()` que el array ya no garantiza orden; el orden es responsabilidad del consumidor.
- **[Riesgo] Con `title_asc`/`title_desc`, empleados sin puesto (`title === ''`) quedan agrupados al inicio del orden ascendente** (string vacío ordena primero con `strcmp`), lo cual puede sorprender a un admin que espera verlos al final. → *Mitigación*: ninguna en esta iteración — comportamiento por defecto de `strcmp`, documentado en el spec como conocido; no se pidió tratamiento especial para vacíos.
- **[Riesgo] Bajo `title_desc`, el desempate ascendente por nombre puede leerse como inconsistente** (p. ej. dos "Gerente" ordenados A–Z mientras el resto de la lista va Z–A). → *Mitigación*: es un requisito explícito del usuario, no un descuido; se documenta en el spec para que sea un comportamiento intencional y testeado, no un bug reportado después.

## Migration Plan

No requiere migración de datos. `get_option( LDAP_ED_OPTION_KEY )` sin la clave `employee_order` cae al default `'name_asc'` vía `get_option( ..., 'name_asc' )`, reproduciendo el `usort` por nombre que hoy vive en el conector. Rollout es un simple deploy de código; no hay flag ni toggle adicional. Rollback: revertir el commit restaura el `usort` fijo en el conector sin efectos secundarios en datos guardados (el option `employee_order`, si quedó guardado, simplemente deja de leerse).

## Open Questions

Ninguna pendiente — alcance, desempate y collation fueron decididos explícitamente por el usuario durante la exploración previa a esta propuesta.
