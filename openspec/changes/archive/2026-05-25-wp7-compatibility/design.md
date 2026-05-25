## Context

WordPress publica la versión actual del core en el perfil de cada plugin en wordpress.org. Si `Tested up to` es menor que la versión instalada del administrador, WP muestra: *"This plugin hasn't been tested with your version of WordPress."* El campo vive exclusivamente en `readme.txt` — no en el header del archivo PHP.

## Goals / Non-Goals

**Goals:**
- Eliminar la advertencia de compatibilidad no verificada para usuarios en WP 7.0
- Hacerlo dentro del release 1.1.1 ya existente (sin nuevo número de versión)

**Non-Goals:**
- Bump de versión del plugin
- Cambios en código PHP, JS ni CSS
- Declarar compatibilidad con versiones futuras de WP

## Decisions

### Campo `Tested up to` solo en `readme.txt`

El header del archivo principal del plugin (`ldap-staff-directory.php`) no tiene campo `Tested up to` — ese dato es exclusivo de `readme.txt`. La API de WP.org lee `readme.txt` para mostrar el badge de compatibilidad; el header PHP solo declara `Requires at least` y `Requires PHP`.

### Sin bump de versión

El cambio es puramente declarativo (metadata). WP.org no requiere un nuevo release para actualizar `Tested up to`; basta con que el trunk/tag publicado tenga el valor correcto. Se añade una nota en el changelog de 1.1.1 por trazabilidad.

## Risks / Trade-offs

| Riesgo | Mitigación |
|---|---|
| WP 7.0 introduce una incompatibilidad real no detectada en testing | El testing fue explícito; si emerge un bug, se crea un nuevo fix release |
| Declarar 7.0 antes de que WP.org procese el cambio muestra versión incorrecta temporalmente | El delay es de minutos; no hay impacto funcional |
