# Entorno local de desarrollo

Requisitos: Docker (Desktop en macOS) corriendo, Node.js/`npx`, Composer.

## QA visual (wp-env)

```sh
wp-env start
```

Levanta un WordPress con el plugin activo:

- Sitio: http://localhost:8888 (usuario `admin` / contraseña `password`)
- Sitio de tests: http://localhost:8889

Sin LDAP configurado, el shortcode `[ldap_directory]` muestra el mensaje de error esperado ("LDAP server address is not configured.") en vez de un fatal — es el estado inicial normal antes de configurar **Settings → LDAP Staff Directory**.

Para detener: `wp-env stop`. Para reiniciar de cero: `wp-env destroy`.

## Tests automáticos

Un único comando corre todo, igual en local y en CI:

```sh
bin/test.sh
```

Pasos, en orden fijo, sin prompts — se detiene en el primer paso que falle:

1. Docker disponible
2. `wp-env start` (si no está arriba)
3. `openldap-test` levantado y sembrado desde `tests/fixtures/directory.ldif`
4. `vendor/bin/phpunit` (vía `wp-env run tests-cli`) — testsuites `unit`, `wp`, `ldap`

Al salir (pase lo que pase — éxito, fallo, o `Ctrl-C`), `bin/test.sh` **elimina** el trío `tests-wordpress`/`tests-cli`/`tests-mysql` de wp-env (existe solo para PHPUnit). Por eso cada corrida lo reconstruye desde cero (~25-30s). `openldap-test` **no se elimina** — se reusa entre corridas.

Si corrés `bin/test.sh` en una terminal interactiva, al final te pregunta si además querés eliminar `wordpress`/`cli`/`mysql` (el sitio de QA visual). Es la única pregunta de todo el script — en CI (sin terminal) se salta sola y esos tres contenedores quedan como estaban.

Para correr solo una capa durante desarrollo activo:

```sh
wp-env run tests-cli -- vendor/bin/phpunit --testsuite unit   # sin Docker de LDAP, rápido
wp-env run tests-cli -- vendor/bin/phpunit --testsuite wp      # Connector mockeado
wp-env run tests-cli -- vendor/bin/phpunit --testsuite ldap    # requiere bin/ldap-test-env.sh arriba
```

`exclude_disabled` (filtro `userAccountControl` de Active Directory) no se cubre en `ldap` — OpenLDAP estándar no soporta esa matching rule. Se verifica manualmente contra un LDAP/AD real; ver `openspec/changes/testing-infrastructure/design.md` (Decisión 6).
