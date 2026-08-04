## Why

La pantalla de settings es un único formulario largo (Settings API de WP) donde jerga LDAP cruda (Bind DN, Base OU, userAccountControl) convive sin jerarquía con configuración avanzada que casi nadie toca (CA Cert, Cache TTL). El plugin está pensado para un site administrator de WordPress sin conocimiento de LDAP, pero hoy asume ese conocimiento. Esto no es un problema de "una sola vez": el propio plugin ya obliga al admin a volver a tocar Connection cuando rota las claves de seguridad de WordPress (`maybe_show_salt_rotation_notice()`), así que la experiencia de configuración se revive periódicamente, no solo en el setup inicial.

## What Changes

- Reemplazar el formulario plano único por **3 pestañas persistentes**: Conexión, Empleados, Campos — cada una es un `<form>` independiente con su propio guardado (**BREAKING**: deja de existir un único submit combinado hacia `options.php`; `sanitize_settings()` debe procesar solo los campos presentes en el POST de cada pestaña y preservar el resto de la opción guardada).
- Reagrupar campos existentes según a qué pregunta responden, no según a qué tabla de LDAP pertenecen:
  - `port` pasa a ser un campo principal de Conexión (antes vivía junto a los demás campos de conexión, sin cambio de comportamiento).
  - `exclude_disabled` se mueve de "Configuración avanzada" de Conexión a un campo principal de Empleados, junto a los demás filtros de "quién aparece".
  - `extension_attr` deja de ser un campo siempre visible en Advanced de Campos y pasa a revelarse solo dentro de "Campos a mostrar", condicionado a que el checkbox "Extensión" esté marcado.
- Añadir popovers `[?]` junto a Bind DN, Bind Password y Base OU con explicaciones en lenguaje simple (sin jerga LDAP).
- Añadir un acordeón colapsable "Configuración avanzada" por pestaña para agrupar los campos que casi nunca se tocan (Verify SSL + CA Cert en Conexión; Cache TTL en Campos).
- Añadir un bloque "¿No tenés estos datos a mano?" en Conexión con un botón "Copiar solicitud para TI" que copia al portapapeles una plantilla de texto pre-armada para pedirle los datos de conexión a quien administra el LDAP.
- Corregir el botón "Probar conexión" para que valide los valores actualmente escritos en el formulario de Conexión (aunque no se hayan guardado todavía) en vez de leer siempre la opción ya persistida en la base de datos, incluyendo el mismo fallback de "contraseña vacía = conservar la guardada" que ya usa el guardado real.
- El aviso de rotación de salts de WordPress (`maybe_show_salt_rotation_notice`) enlaza directamente a la pestaña Conexión con el campo Bind Password enfocado, en vez de solo mostrar un admin notice genérico.

## Capabilities

### New Capabilities
- `admin-settings-tabs`: navegación por pestañas persistentes (Conexión / Empleados / Campos), guardado independiente por pestaña, reagrupación de campos, y el enlace directo a Conexión desde el aviso de rotación de salts.
- `admin-contextual-guidance`: popovers de ayuda inline, acordeón de "Configuración avanzada" por pestaña, y el bloque "pedile esto a tu administrador de LDAP" con plantilla copiable.
- `connection-test-live-preview`: el botón "Probar conexión" valida el estado actual (no guardado) del formulario de Conexión, con el fallback correcto de contraseña.

### Modified Capabilities
- `extension-field`: el requirement "El setting `extension_attr` SHALL residir en la sección Display del admin" cambia — el campo ya no es un input siempre visible en esa sección, sino que se revela condicionalmente dentro de "Campos a mostrar" (pestaña Campos) solo cuando el checkbox "Extensión" está marcado.

## Impact

- **PHP**: `includes/class-admin.php` (registro de settings, sanitización dividida por pestaña, render de campos reagrupados, redirect del aviso de salts), `includes/class-ajax.php` (`test_connection()` deja de leer solo `get_option()`), `admin/views/settings-page.php` (estructura de pestañas en vez de columnas fijas).
- **JS**: `admin/js/admin.js` (manejo de pestañas, acordeón, popovers, copiar-al-portapapeles, serialización del form de Conexión para el test).
- **CSS**: `admin/css/admin.css` (estilos de pestañas, acordeón, popover, callout).
- **i18n**: nuevas cadenas en `wp_localize_script` (`ldapEdAdmin.i18n`) para textos de ayuda y la plantilla de solicitud a IT.
- Sin cambios en el shape de `ldap_ed_settings` ni en `LDAP_ED_Connector` — es una reestructuración de UI/UX y de la lógica de guardado/test, no de la lógica LDAP en sí.
