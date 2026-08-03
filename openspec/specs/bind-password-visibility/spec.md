## ADDED Requirements

### Requirement: Botón de mostrar/ocultar la contraseña de bind
El campo de contraseña de bind SHALL mostrar un botón-ícono junto al input que alterna su visibilidad mientras el admin escribe un valor nuevo. El botón SHALL cambiar de ícono (`eye`/`eye-off`) según el estado actual.

#### Scenario: Estado inicial oculto
- **WHEN** se renderiza el campo de contraseña de bind
- **THEN** el input es `type="password"` y el botón muestra el ícono `eye`

#### Scenario: Clic revela la contraseña
- **WHEN** el admin hace clic en el botón estando el campo oculto
- **THEN** el input cambia a `type="text"` y el botón muestra el ícono `eye-off`

#### Scenario: Clic vuelve a ocultar
- **WHEN** el admin hace clic en el botón estando el campo visible
- **THEN** el input vuelve a `type="password"` y el botón vuelve al ícono `eye`

### Requirement: El toggle no expone la contraseña ya guardada
El campo de contraseña de bind SHALL seguir renderizándose vacío en cada carga de página, sin importar si ya existe una contraseña guardada. El botón de mostrar/ocultar SHALL afectar únicamente el texto que el admin esté escribiendo en ese momento, nunca un valor pre-cargado desde el servidor.

#### Scenario: Carga de página con contraseña ya guardada
- **WHEN** existe una contraseña de bind guardada y el admin abre la página de settings
- **THEN** el campo de contraseña se renderiza vacío, igual que antes de este cambio

#### Scenario: Alternar visibilidad sin haber escrito nada
- **WHEN** el admin hace clic en el botón de mostrar/ocultar sin haber escrito ningún carácter
- **THEN** el campo permanece vacío; no aparece la contraseña guardada previamente
