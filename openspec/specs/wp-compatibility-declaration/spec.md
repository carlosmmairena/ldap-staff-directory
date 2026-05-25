## Requirements

### Requirement: Declaración de compatibilidad con WordPress 7.0
El campo `Tested up to` en `readme.txt` SHALL reflejar la versión más alta de WordPress con la que el plugin ha sido verificado. Tras confirmar compatibilidad con WP 7.0, el valor SHALL ser `7.0`.

#### Scenario: Campo actualizado en readme.txt
- **WHEN** se lee el encabezado de `readme.txt`
- **THEN** `Tested up to` es `7.0`

#### Scenario: Nota de compatibilidad en changelog de 1.1.1
- **WHEN** se lee la sección `== Changelog ==` entrada `1.1.1`
- **THEN** existe una línea que menciona compatibilidad verificada con WordPress 7.0
