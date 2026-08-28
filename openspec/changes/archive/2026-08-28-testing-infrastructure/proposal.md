## Why

El plugin no tiene forma de levantar un WordPress local para QA visual ni tests automáticos que prevengan regresiones al seguir agregando features. El único CI existente (`plugin-check-action`) es lint estático — nunca ejecuta `LDAP_ED_Connector`, `LDAP_ED_Cache` ni `LDAP_ED_Ajax`. Lógica sensible como el parser de filtros LDAP, la paginación RFC 2696 (cookie) y las exclusiones de departamento solo se valida hoy a mano contra un LDAP real.

## What Changes

- Agrega `wp-env` (`.wp-env.json`) como entorno local de WordPress para QA visual — sin `docker-compose.yml`/`Dockerfile` propios.
- Agrega un contenedor OpenLDAP de prueba (`osixia/openldap` vía `docker run`, no compose) sembrado desde `tests/fixtures/directory.ldif` versionado, adjunto a la red que `wp-env` crea.
- Agrega suite de PHPUnit (`composer.json` con `yoast/phpunit-polyfills`, un único `phpunit.xml.dist` con tres testsuites: `unit`, `wp`, `ldap`) cubriendo:
  - Capa `unit`: `ldap_ed_split_server_scheme()`, `ldap_ed_encrypt_pass()`/`decrypt_pass()` — sin WordPress, sin LDAP.
  - Capa `wp`: `LDAP_ED_Cache` completo, y solo las guardas de `LDAP_ED_Ajax` (nonce/`manage_options`) que nunca llegan a tocar el Connector — `LDAP_ED_Ajax` no tiene seam de inyección, así que no se mockea (ver design.md, Decisión 8).
  - Capa `ldap`: `LDAP_ED_Connector` contra el OpenLDAP de prueba real — filtros compilados (`exclude_no_department`, `excluded_departments`), paginación con >500 entries, invariante de `get_departments()` (nunca aplica exclusiones), y los caminos AJAX (`test_connection`, `get_departments`) que sí instancian el Connector. `exclude_disabled` queda fuera — es una matching rule específica de Active Directory que OpenLDAP estándar no soporta (ver design.md, Decisión 6); se verifica solo a mano contra el LDAP/AD real.
- Agrega `bin/test.sh`: wizard secuencial de 4 pasos (Docker → wp-env → openldap-test → PHPUnit), sin prompts, falla y para en el primer paso que falle. Mismo comando en local y en CI. Al salir, limpia el trío `tests-wordpress`/`tests-cli`/`tests-mysql` (solo existe para PHPUnit); `openldap-test` y el entorno de QA visual (`wordpress`/`cli`/`mysql`) se reusan entre corridas — para este último, en terminal interactiva, pregunta una vez si también eliminarlo (única excepción a "sin prompts", ver design.md Decisión 5).
- Agrega job `phpunit` en `.github/workflows/build-test.yml`, junto al `lint` (`plugin-check-action`) existente, ejecutando `bin/test.sh`.
- Actualiza `.gitignore` para `/vendor` y el cache de PHPUnit.

## Capabilities

### New Capabilities
- `local-testing-environment`: entorno local (wp-env) para QA visual + suite de tests automatizados en capas (unit/wp/ldap) contra un OpenLDAP de prueba desechable, ejecutable de forma idéntica en local y CI vía un único script secuencial.

### Modified Capabilities
(ninguna — no cambia comportamiento del plugin en producción)

## Impact

- **Archivos nuevos**: `.wp-env.json`, `composer.json`, `composer.lock`, `phpunit.xml.dist`, `bin/test.sh`, `bin/ldap-test-env.sh`, `bin/install-php-ldap-ext.sh`, `tests/bootstrap.php`, `tests/unit/*`, `tests/wp/*`, `tests/ldap/*` (incluye `ConnectorTestCase.php`, base compartida), `tests/fixtures/directory.ldif`, `tests/fixtures/generate-bulk-entries.sh`, `tests/fixtures/schema/ldap-ed-test.schema`, `docs/local-development.md`.
- **Archivos modificados**: `.gitignore` (`/vendor`, cache PHPUnit), `.github/workflows/build-test.yml` (nuevo job `phpunit`).
- **Sin impacto en runtime del plugin**: nada de esto se empaqueta ni se distribuye en el `.zip` de WordPress.org — es tooling de desarrollo puro.
- **Dependencias nuevas (dev-only)**: Composer, PHPUnit, `yoast/phpunit-polyfills`, `wp-env` (Node/npx), Docker.
