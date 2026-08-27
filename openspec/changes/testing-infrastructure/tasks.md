## 1. Spike: descubrir la red Docker de wp-env

- [x] 1.1 Correr `wp-env start` en local y confirmar, vía `docker inspect` sobre el contenedor `wordpress`, cómo se llama la red compartida por los servicios de wp-env — resultado: `<compose-project>_default` (p. ej. `wp-env-ldap-staff-directory-<hash>_default`), descubierta con:
  ```
  docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{end}}' \
    $(docker ps --format '{{.Names}}' | grep -E -- '-wordpress-1$' | grep -v -- '-tests-wordpress-1$' | head -1)
  ```
  (note: a loose `*-wordpress-1$` match also matches `*-tests-wordpress-1` since it's a substring — must be excluded explicitly, see `bin/ldap-test-env.sh`)
- [x] 1.2 Confirmar que un contenedor nuevo adjunto a esa red resuelve `wordpress` por hostname (validado con `alpine` + `getent hosts wordpress`)
- [x] 1.3 Documentar el comando exacto de descubrimiento (arriba, a usar en `bin/ldap-test-env.sh`)

## 2. wp-env (QA visual)

- [x] 2.1 Crear `.wp-env.json` (core WP, plugin activo, `phpVersion` alineado a `Requires PHP: 7.4`)
- [x] 2.2 Verificar que `wp-env start` deja el plugin activo y la vista `directory.php` renderizando sin LDAP configurado (mensaje de error esperado, no fatal) — confirmado: `LDAP server address is not configured.`
- [x] 2.3 Documentar en `docs/` (o README de desarrollo) el flujo de QA visual: `wp-env start`, credenciales, URL — `docs/local-development.md`
- [x] 2.4 (descubierto en implementación) Escribir `bin/install-php-ldap-ext.sh` + `lifecycleScripts.afterStart` en `.wp-env.json`: ninguna imagen de wp-env trae la extensión `ldap` de PHP — ver design.md Decisión 7. Validado en las 4 containers (`wordpress`, `tests-wordpress`, `cli`, `tests-cli`), idempotente

## 3. OpenLDAP de prueba

- [x] 3.1 Escribir `tests/fixtures/directory.ldif` (+ `schema/ldap-ed-test.schema` para `department`/`ipPhone`, no estándar en OpenLDAP): `ou=people`, 6 entries en 5 departamentos, 2 sin `department`, 510 entries generadas (`generate-bulk-entries.sh`) — 518 person entries en total. `exclude_disabled` queda fuera (ver design.md, Decisión 6)
- [x] 3.2 Escribir `bin/ldap-test-env.sh`: descubre la red de wp-env (tarea 1.3), `docker run` de `osixia/openldap` adjunto a esa red con schema+LDIF montados, idempotente (reutiliza/reinicia si ya existe), espera a que `slapd` acepte binds antes de salir
- [x] 3.3 Verificar manualmente un bind + búsqueda contra `openldap-test` desde el contenedor `cli` de wp-env — confirmado: bind OK, primera página de búsqueda paginada = 500 (tope de página), reachable por hostname `openldap-test`

## 4. Suite PHPUnit

- [x] 4.1 Crear `composer.json` (`yoast/phpunit-polyfills`, PHPUnit `^9.6` pinneado, `config.platform.php: 7.4`) y `.gitignore` para `/vendor` + cache de PHPUnit
- [x] 4.2 Crear `phpunit.xml.dist` con testsuites `unit`, `wp`, `ldap` y `tests/bootstrap.php`
- [x] 4.3 Tests `unit`: `ldap_ed_split_server_scheme()` (7 casos), `ldap_ed_encrypt_pass()`/`decrypt_pass()` (4 casos) — 11 tests, 22 asserts
- [x] 4.4 Tests `wp`: `LDAP_ED_Cache` completo (5 tests); `LDAP_ED_Ajax` — nonce + `manage_options` guards (sin mock, ver design.md Decisión 8: `LDAP_ED_Ajax` no tiene seam de inyección, los caminos que sí tocan el Connector se movieron a `ldap`) — 12 tests, 19 asserts. Verificado con `openldap-test` parado (`docker stop`): sigue pasando 12/12 — la capa `wp` de verdad no depende de LDAP vivo
- [x] 4.5 Tests `ldap`: filtros (`exclude_no_department`, `excluded_departments`, combinados — sin `exclude_disabled`, ver Decisión 6), paginación >500 (518 entries, 2 páginas), invariante de `get_departments()` ignorando exclusiones — 10 tests, 1052 asserts
- [x] 4.6 Confirmado: cada testsuite aislado y los 33 tests juntos (1093 asserts) — sin conflictos

## 5. Wizard secuencial

- [x] 5.1 Escribir `bin/test.sh`: paso 1 Docker disponible, paso 2 `wp-env start`, paso 3 `bin/ldap-test-env.sh`, paso 4 `composer install` (si falta `vendor/`) + `vendor/bin/phpunit` vía `wp-env run tests-cli`
- [x] 5.2 Cada paso imprime ✓/✗; el script para en el primer fallo (`set -euo pipefail` + `fail()`) sin prompts y sale con código de salida no-cero. Forzado en vivo (renombrando `bin/ldap-test-env.sh` temporalmente): falla en paso 3/4, PHPUnit nunca corre, exit code 1, sin prompts, y el `trap cleanup EXIT` igual remueve el trío `tests-*` en una salida por fallo
- [x] 5.3 Re-ejecución consecutiva confirmada: sin conflictos de nombre de contenedor, 33/33 tests OK en ambas corridas; `tests-*` se reconstruye cada vez (~27-29s, por el teardown de 5.4), `openldap-test`/`wordpress`/`cli`/`mysql` se reusan sin recrearse
- [x] 5.4 (pedido explícito del mantenedor, tras ver 7 containers corriendo) `trap cleanup EXIT` en `bin/test.sh`: remueve `tests-wordpress`/`tests-cli`/`tests-mysql` al salir (éxito o fallo). `openldap-test` NO se remueve (pedido explícito, revierte una versión anterior que sí lo hacía) — se reusa entre corridas, validado (2da corrida: "already running, reusing")
- [x] 5.5 (pedido explícito del mantenedor) Al final, si la terminal es interactiva, preguntar si eliminar también `wordpress`/`cli`/`mysql` (QA visual) — única excepción a "sin prompts", ver design.md Decisión 5. Matching de respuesta (`y`/`yes`/`s`/`si`/`sí`, case-insensitive) verificado en aislado tras un bug real (el primer patrón no matcheaba "si"/"sí"); prompt confirmado que se salta sin colgarse cuando `stdin`/`stdout` no son TTY (corrida real con `< /dev/null`, sin hang)

## 6. CI

- [x] 6.1 Agregar job `phpunit` en `.github/workflows/build-test.yml`, junto al job existente (`test`, plugin-check), invocando `bin/test.sh`
- [x] 6.2 Decidido: mismo `on:` que el job existente — cada PR y push a `main`/`releases/*`, sin condición de job aparte
- [x] 6.4 (descubierto en el PR real) `plugin-check-action` escaneaba `bin/`, `tests/`, `.wp-env.json`, `composer.json`/`.lock`, `phpunit.xml.dist` como si se distribuyeran con el plugin — errores falsos (hidden/application files, falta ABSPATH, clases de test sin prefijo). Agregados a `exclude-directories`/`exclude-files` del job `test`
- [ ] 6.3 Confirmar que el job `phpunit` pasa en un runner limpio de GitHub Actions (sin estado previo de Docker) — PR abierto (`feat/testing-infrastructure`), esperando resultado tras el fix de 6.4
