## Context

El plugin no tiene entorno local ni tests automáticos (ver proposal.md). Su lógica más frágil — `LDAP_ED_Connector` (`includes/class-ldap-connector.php`) — depende de un directorio LDAP real: construcción de filtros (`build_person_filter()`), paginación RFC 2696 con cookie (`search_paged()`), y el invariante de que `get_departments()` nunca aplica exclusiones (documentado en el propio código, líneas 292-296). Esa dependencia de un LDAP real es la que determina toda la arquitectura de este cambio — no es solo "levantar WordPress".

Restricción de mantenimiento: proyecto de un solo mantenedor (no hay equipo de contribuidores externos que justifique optimizar para onboarding de terceros).

## Goals / Non-Goals

**Goals:**
- QA visual local sin mantener un `Dockerfile`/`docker-compose.yml` propio.
- Tests automatizados que detecten regresiones en el parser de filtros LDAP, paginación y cache al agregar features futuras.
- Un directorio OpenLDAP de prueba desechable y determinístico (fixtures versionadas), independiente del LDAP real al que ya tiene acceso el mantenedor.
- Un único comando que corra todo, con el mismo comportamiento en local y en CI.

**Non-Goals:**
- No se optimiza para contribuidores externos (p. ej. no se generan dos configuraciones de PHPUnit para permitir tests "sin Docker" — ver Decisión 4).
- No se cubre performance/carga del LDAP real ni HA.
- No se empaqueta nada de esto en el `.zip` de distribución del plugin.
- No se automatiza la verificación de `exclude_disabled` (matching rule específica de Active Directory, no soportada por OpenLDAP estándar — ver Decisión 6). Queda como verificación manual contra el LDAP/AD real.

## Decisions

### 1. `wp-env` en vez de `docker-compose.yml`/`Dockerfile` propio
`wp-env` es la herramienta oficial de WordPress, ya envuelve Docker Compose internamente, y su mantenimiento (versiones de PHP/WP, extensión `ldap`) corre por cuenta del core team en vez de nuestro. Confirmado contra la documentación oficial (`@wordpress/env` README): trae Composer, PHPUnit y wp-cli preinstalados en sus contenedores `cli`/`tests-cli`, y soporta `phpVersion` para pinchar la versión mínima declarada por el plugin (`Requires PHP: 7.4`).
**Alternativa descartada**: Docker Compose a mano — más control, pero mantenimiento propio de imagen base, sin ganancia real dado que `wp-env` ya es Compose por debajo.

### 2. OpenLDAP de prueba: `docker run` adjunto a la red de `wp-env`, sin `lifecycleScripts`
`.wp-env.json` **no soporta agregar servicios Docker propios** (confirmado contra la doc oficial — no existe campo `services` ni override de compose). La red de `wp-env` sí es descubrible en runtime (Compose crea una red por proyecto compartida por todos sus servicios). En vez de arrancar `openldap-test` en una red separada y luego conectar los cuatro contenedores de `wp-env` (`wordpress`, `cli`, `tests-wordpress`, `tests-cli`) a esa red, se invierte el orden: `bin/ldap-test-env.sh` corre **después** de `wp-env start`, descubre la red que `wp-env` ya creó, y arranca `openldap-test` directamente adjunto a ella (`docker run --network <red-de-wp-env>`). Una sola operación de red en vez de cuatro, y **elimina la necesidad de `lifecycleScripts.afterStart`** en `.wp-env.json`.
**Alternativa descartada**: publicar el puerto de OpenLDAP en el host y conectar vía `host.docker.internal` — requiere `--add-host=host.docker.internal:host-gateway` en los contenedores de `wp-env`, que no controlamos; además es frágil en GitHub Actions (los "service containers" de GH exponen a `localhost` del runner, no directamente a contenedores anidados).
**Alternativa descartada**: `docker-compose.yml` propio solo para OpenLDAP — el mantenedor prefirió explícitamente `docker run` por portabilidad (una sola herramienta menos que instalar/versionar).

### 3. Fixtures LDIF versionadas en el repo
`tests/fixtures/directory.ldif` se versiona (decisión explícita del mantenedor) en vez de generarse dinámicamente. Da reproducibilidad total: cada `docker run` reseedea desde cero, sin drift entre corridas. El archivo debe cubrir explícitamente los casos que un OpenLDAP estándar sí puede validar: entries sin atributo `department` (`exclude_no_department`), departamentos con distintos valores (`excluded_departments`), y >500 entries para forzar más de una página en `search_paged()` (el límite de página está hardcodeado en 500, línea 190 de `class-ldap-connector.php`). `exclude_disabled` queda fuera — ver Decisión 6.

### 4. Un único `phpunit.xml.dist` con tres testsuites (`unit`/`wp`/`ldap`), no dos configs separadas
Se consideró inicialmente separar un `phpunit-unit.xml.dist` (sin bootstrap de WP, corre sin Docker) de un `phpunit.xml.dist` para las capas dependientes de WP/LDAP. Se descartó: esa separación optimiza para un escenario de muchos contribuidores que no aplica a este proyecto (mantenedor único). Cargar el bootstrap de WP para un test de `ldap_ed_split_server_scheme()` no tiene costo real. Un solo archivo con tres testsuites permite igual correr subconjuntos (`--testsuite unit`) sin la complejidad de mantener dos configuraciones.

### 5. `bin/test.sh`: wizard secuencial, no interactivo
Decisión explícita del mantenedor: los 4 pasos (Docker disponible → `wp-env start` → `openldap-test` levantado+sembrado → PHPUnit) corren en orden fijo, reportan ✓/✗ por paso, y **paran en el primer fallo sin preguntar nada**. Mismo comando, mismo comportamiento, en local y en CI — el job `phpunit` de GitHub Actions invoca literalmente `bin/test.sh`, no una versión distinta de la lógica.
**Alternativa descartada**: wizard interactivo en los 4 pasos (preguntar "¿levanto `openldap-test`?" si no está corriendo) — más "amigable" pero una pieza más de UX para mantener sin beneficio real en un flujo ya automático.

**Teardown al salir** (pedido explícito del mantenedor, tras ver 7 contenedores corriendo a la vez; revisado una vez más tras probarlo): `bin/test.sh` remueve, vía `trap cleanup EXIT` (corre pase lo que pase — éxito, fallo, o interrupción), el trío `tests-*` de wp-env (`tests-wordpress`/`tests-cli`/`tests-mysql`) — existe solo para PHPUnit. **`openldap-test` NO se remueve** — se reusa entre corridas (pedido explícito del mantenedor: la primera versión lo borraba también, se revirtió). `wordpress`/`cli`/`mysql` (QA visual, Goal 1) tampoco se remueven automáticamente — en su lugar, si la terminal es interactiva (`[ -t 0 ] && [ -t 1 ]`, siempre falso en CI), se pregunta una vez al final si eliminarlos. Esta es la **única** interactividad en todo el script — una excepción explícita a "sin prompts", acotada a esta pregunta de limpieza, no a los 4 pasos de validación.
**Alternativa descartada**: `wp-env destroy` completo — más simple de escribir, pero borra también `wordpress`/`cli`/`mysql` sin preguntar, tirando abajo cualquier QA visual en curso en paralelo.
**Alternativa descartada**: remover siempre `openldap-test` también (primera versión implementada) — se revirtió porque el mantenedor lo quiere persistente entre corridas, igual que `wordpress`/`cli`/`mysql`.

### 6. `exclude_disabled` queda fuera del `ldap` testsuite automatizado
`build_person_filter()` (`class-ldap-connector.php:149`) usa el filtro `(!(userAccountControl:1.2.840.113556.1.4.803:=2))` — la regla de coincidencia `1.2.840.113556.1.4.803` (`LDAP_MATCHING_RULE_BIT_AND`) es específica de Active Directory. Un OpenLDAP estándar (`osixia/openldap`) no la reconoce: la búsqueda falla con matching rule no soportada. No es declarable vía schema propio de OpenLDAP (no es una limitación de configuración, es una regla de coincidencia compilada en el servidor). Confirmado contra discusión técnica de OpenLDAP.
Decisión (explícita del mantenedor): **`exclude_disabled` no se cubre en el `ldap` testsuite automatizado**. El resto de `build_person_filter()` (departamentos, `mail=*`, `exclude_no_department`) sí usa sintaxis de filtro LDAP estándar y se prueba sin problema contra `openldap-test`.
**Alternativa descartada**: reemplazar `openldap-test` por un contenedor Samba4 actuando como Active Directory DC (sí soporta esa matching rule) — más fiel al código, pero mucho más pesado/lento de levantar; contradice el objetivo de mantener pocas capas para un proyecto de un solo mantenedor.
**Alternativa descartada**: correr ambos contenedores (OpenLDAP + Samba AD) — máxima cobertura, pero duplica LDIFs, redes a descubrir, y puntos de fallo, sin justificación para el volumen de este plugin.
**Gap aceptado**: `exclude_disabled` se verifica solo manualmente contra el LDAP/AD real al que ya tiene acceso el mantenedor (no automatizado, no bloqueante para este cambio).

### 7. Extensión `ldap` de PHP instalada vía `lifecycleScripts.afterStart`
Descubierto en implementación: **ninguna** de las imágenes que usa `wp-env` trae la extensión `ldap` de PHP (`wordpress`/`tests-wordpress`, Debian bullseye vía la imagen oficial `wordpress:*-apache`; `cli`/`tests-cli`, Alpine, imagen propia de wp-env). `LDAP_ED_Connector` llama `ldap_connect()`/`ldap_bind()`/etc. directamente — sin la extensión, tanto QA visual real (Test Connection) como el `ldap` testsuite fallan con fatal error, no con un error de conexión.
Decisión (explícita del mantenedor, entre 3 opciones): `bin/install-php-ldap-ext.sh`, invocado por `lifecycleScripts.afterStart` en `.wp-env.json`, instala la extensión en las 4 containers cada `wp-env start` (Alpine: `apk add openldap-dev`; Debian: `apt-get install libldap2-dev`; luego `docker-php-ext-install ldap` en ambos). Idempotente (verifica `php -m` antes de reinstalar). `wordpress`/`tests-wordpress` requieren `docker restart` después — Apache (mod_php) solo lee extensiones al arrancar el proceso; `cli`/`tests-cli` no, porque cada `wp-env run` es un `docker exec` con proceso PHP nuevo. Costo: ~15-20s agregados a un `wp-env start` con contenedores nuevos (validado localmente); prácticamente nulo en corridas donde ya está instalado.
**Alternativas descartadas**: instalarlo solo dentro de `bin/test.sh` (un `wp-env start` suelto para QA visual quedaría sin LDAP funcional hasta correr el wizard); o como paso manual documentado (se pierde en cada `wp-env destroy`, hay que recordarlo).

### 8. `wp` no mockea `LDAP_ED_Connector` — no tiene punto de inyección
Descubierto en implementación: `LDAP_ED_Ajax` instancia `new LDAP_ED_Connector(...)` directamente (sin seam de inyección). Mockearlo requeriría tocar código de producción solo para hacerlo testeable, contradiciendo el impacto declarado en proposal.md ("sin impacto en runtime del plugin"). Se prefiere: `wp` cubre `LDAP_ED_Cache` completo y solo las guardas de `LDAP_ED_Ajax` que no llegan a tocar el Connector (nonce, `manage_options` — fallan antes); los caminos AJAX que sí instancian el Connector (`test_connection`, `get_departments`) se prueban en `ldap` contra `openldap-test` real. Cobertura equivalente, sin tocar código de producción, y sin el riesgo de que un mock quede desincronizado del comportamiento real del Connector.

## Risks / Trade-offs

- **[Riesgo]** El nombre de la red Docker que crea `wp-env` no está documentado como API estable — depende de convenciones internas de Compose (nombre de proyecto por hash de directorio). → **Mitigación**: `bin/ldap-test-env.sh` la descubre dinámicamente vía `docker inspect` sobre un contenedor de `wp-env` ya corriendo, en vez de hardcodearla. Este descubrimiento es el único punto marcado como spike en tasks.md — debe prototiparse y validarse localmente antes de darse por cerrado.
- **[Riesgo]** `docker run --name openldap-test` falla en corridas repetidas si el contenedor previo sigue existiendo (parado o corriendo). → **Mitigación**: el script es idempotente — verifica con `docker ps -a --filter name=openldap-test` y reutiliza/reinicia en vez de fallar; esto no es "interactividad", sigue siendo determinístico y sin prompts.
- **[Trade-off]** Sin fixtures dinámicas, ampliar casos de prueba (p. ej. un nuevo atributo LDAP) requiere editar el LDIF a mano. Aceptado: da trazabilidad y legibilidad del dataset de prueba, prioridad más alta que la conveniencia de generarlo por código.
- **[Riesgo]** `wp-env` requiere Docker corriendo — en macOS eso es Docker Desktop. Si no está disponible, `bin/test.sh` debe fallar en el paso 1 con un mensaje claro, no con un error críptico de `wp-env`.
- **[Riesgo, aceptado]** `wp-env` marca como deprecado el comportamiento por defecto de levantar dev+tests desde un solo `.wp-env.json` (recomienda dos archivos separados vía `--config`). Se mantiene el archivo único por ahora — menos piezas, comportamiento ya validado (`tests-cli` trae PHPUnit 9.6.36 preinstalado) — y se revisita cuando `wp-env` remueva el comportamiento legado, no antes.
- **[Riesgo, resuelto en PR real]** `wordpress/plugin-check-action` (job `test`) escaneaba `bin/`, `tests/`, `.wp-env.json`, `composer.json`/`.lock`, `phpunit.xml.dist` como si fueran parte del plugin distribuido — errores falsos de "hidden/application files not permitted", ABSPATH faltante, y nombres de clase de test sin prefijo `LDAP_ED_`. → **Mitigación**: agregados al `exclude-directories`/`exclude-files` del job `test`, consistente con el Impact declarado en proposal.md ("sin impacto en runtime del plugin").
- **[Nota, fuera de alcance]** El mismo run de CI mostró `Tested up to: 7.0 < 7.1` en `readme.txt` — pre-existente, no tocado por este cambio (ver `git log` de `readme.txt`), y no es responsabilidad de `testing-infrastructure` corregirlo.

## Migration Plan

No aplica migración de datos ni compatibilidad hacia atrás — es tooling de desarrollo nuevo, sin impacto en instalaciones existentes del plugin. Rollback trivial: eliminar los archivos nuevos y el job de CI agregado.

## Open Questions

- ~~Confirmar en el spike si `docker inspect` sobre el contenedor `cli` de `wp-env` es la forma más estable de descubrir la red~~ — Resuelto: se ancla al contenedor `wordpress` (nombre `wp-env-*-wordpress-1`), siempre presente mientras `wp-env start` está arriba. La red resultante es `<compose-project>_default`. Ver tasks.md 1.1.
- ~~Definir si el job `phpunit` de CI corre en cada PR (como `lint`) o solo en push a `main`/`releases/*`~~ — Resuelto: mismo `on:` que `lint` (sin condición de job aparte) — corre en cada PR y push a `main`/`releases/*`, sin lógica condicional extra que mantener.
