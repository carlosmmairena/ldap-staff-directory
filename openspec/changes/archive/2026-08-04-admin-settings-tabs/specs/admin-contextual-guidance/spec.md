## ADDED Requirements

### Requirement: Popovers de ayuda contextual
Los campos Bind DN, Bind Password y Base OU SHALL mostrar un botón de ayuda `[?]` junto a su etiqueta que, al activarse, revela un panel con una explicación en lenguaje simple, sin jerga LDAP. El panel SHALL cerrarse al hacer click fuera de él o al presionar Escape.

#### Scenario: Abrir popover de Bind DN
- **WHEN** el admin hace click en el botón de ayuda junto a "Bind DN"
- **THEN** se muestra un panel explicando que es el usuario de servicio que WordPress usa para conectarse, no su usuario personal

#### Scenario: Cerrar con click fuera
- **WHEN** el popover está abierto y el admin hace click en cualquier otro lugar de la página
- **THEN** el popover se cierra

#### Scenario: Cerrar con Escape
- **WHEN** el popover está abierto y el admin presiona Escape
- **THEN** el popover se cierra y el foco vuelve al botón de ayuda

---

### Requirement: Acordeón de Configuración avanzada sin remover campos del DOM
Cada pestaña con campos secundarios (Conexión: `verify_ssl`, `ca_cert`; Campos: `cache_ttl`) SHALL agruparlos bajo un acordeón colapsable "Configuración avanzada", colapsado por defecto. El acordeón SHALL implementarse ocultando los campos con una clase CSS, nunca removiéndolos del DOM ni marcándolos como `disabled`, de modo que sigan enviándose al guardar o al probar la conexión, estén expandidos o no.

#### Scenario: Colapsado por defecto
- **WHEN** el admin abre cualquier pestaña con campos avanzados
- **THEN** el acordeón aparece colapsado y esos campos no son visibles

#### Scenario: Guardar con acordeón colapsado preserva valores
- **WHEN** el admin guarda la pestaña Conexión sin haber expandido "Configuración avanzada"
- **THEN** los valores previamente guardados de `verify_ssl` y `ca_cert` se envían igual en el POST y no se pierden

#### Scenario: Expandir revela los campos
- **WHEN** el admin hace click en el header del acordeón
- **THEN** los campos avanzados se vuelven visibles y editables

---

### Requirement: Callout de solicitud a IT con plantilla copiable
La pestaña Conexión SHALL mostrar un bloque "¿No tenés estos datos a mano?" con un botón "Copiar solicitud para TI" que copia al portapapeles una plantilla de texto pre-armada listando qué pedirle a quien administra el servidor LDAP (dirección del servidor, usuario de servicio con contraseña, y la OU de empleados).

#### Scenario: Copiar plantilla
- **WHEN** el admin hace click en "Copiar solicitud para TI"
- **THEN** el texto de la plantilla se copia al portapapeles y se muestra una confirmación visual breve

#### Scenario: Plantilla en el idioma del admin
- **WHEN** el sitio WordPress está configurado en un idioma con traducción disponible para el text domain `ldap-staff-directory`
- **THEN** la plantilla copiada se muestra en ese idioma en vez de en el idioma por defecto
