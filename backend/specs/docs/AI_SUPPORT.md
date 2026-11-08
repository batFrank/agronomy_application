# Soporte de IA en el Base Project: guía de integración para forks

> **Estado**: implementado (épica 7). Lo descrito aquí existe en el código y está cubierto por tests;
> donde algo no está resuelto, el documento lo dice y apunta a la issue que lo lleva.
> **Épica**: [epic 7](https://gitlab.com/groups/ManagementDrives/developers/base-projects/-/epics/7)
> **Verificado contra**: `symfony/ai-agent`, `symfony/ai-platform` y `symfony/ai-bundle` **0.13.0**
> (pre-1.0, pinneadas a versión exacta).

Este documento es para quien construye un **fork** sobre la base y quiere montar un agente de IA.
Dice qué trae la base y qué tiene que escribir el fork (sección 1), cómo montar un agente paso a paso
(sección 2), dónde va cada validación y por qué (sección 3), cómo son las piezas de la base
(sección 4), las dos formas de uso que vienen resueltas con ejemplo (sección 5) y, sobre todo, las
**condiciones que el fork tiene que cumplir antes de ponerlo delante de una persona** (sección 6).
Las secciones 7 a 9 recogen lo que queda fuera, lo que la épica destapó en el resto del código y el
recorrido que llevó hasta aquí.

Todo lo que se cita como «medido» se comprobó leyendo el código del paquete o ejecutándolo, no a
partir de su documentación.

---

## 1. Reparto de responsabilidades

### 1.1 Lo que pone la base

| Pieza | Dónde | Qué resuelve |
|---|---|---|
| **Puerto del agente** | `Application/AI/AgentGateway`, query `AskAgent` | Un único embudo para llamar al agente desde un controlador, un handler de Messenger o un comando. Ningún tipo del SDK cruza el puerto. |
| **Adaptador del puerto** | `Infrastructure/AI/Agent/SymfonyAiAgentGateway` | Entra y sale del `AgentContext`, convierte el fallo del proveedor en `AgentUnavailableException` (502 genérico, causa en `previous`). |
| **Conversación** | `Application/AI/AgentConversation`, `AgentTurn` | Ventana de turnos, longitudes, quién habla último. Las invariantes viven aquí y no en el controlador. |
| **Contexto del actor** | `Infrastructure/AI/Context/AgentContext` | Quién origina la llamada, explícito, sin `RequestStack` (sección 4.1). |
| **Esqueleto de tools** | `Infrastructure/AI/Toolbox/AgentTool` | Base de toda tool: bus + actor + traducción de errores del bus (sección 4.2). |
| **Traducción de errores** | `ToolCallException`, `ToolExceptionMapper`, `Translator/*` | Del fallo de dominio al mensaje que ve el modelo, con un genérico opaco por defecto (secciones 3.5 y 4.4). |
| **Saneado de identificadores** | `IdentifierStrippingToolbox`, `ToolPayloadSanitizer` | Quita los identificadores de lo que una tool devuelve al modelo (sección 5.1). |
| **Registro de fallos de tools** | `LogFailedToolCallSubscriber` | Lleva al log lo que falla dentro del bus (sección 4.5). |
| **Forma agéntica con ejemplo** | agente `user_directory`, `ListUsersTool` | Plantilla de agente con una tool de solo lectura. |
| **Decodificador de salida estructurada** | `Infrastructure/AI/StructuredOutput/StructuredOutputDecoder` | Pieza genérica: comprueba la respuesta contra el schema a cualquier profundidad, la denormaliza y la valida. El fork la usa tal cual (sección 5.2). |
| **Forma de generación con ejemplo** | `Application/AI/ReportGateway`, agente `example_report` | Plantilla de agente sin tools con salida estructurada. |
| **Endpoint de chat de ejemplo** | `POST /ai/chat`, `PostAiChatController` | Superficie HTTP del agente `user_directory`, con su validador y su OpenAPI. |
| **Dobles de test** | `tests/Infrastructure/AI/` (`ScriptedPlatform`, `ToolboxHelperTrait`) | Ningún test llama al proveedor (sección 2.7). |

### 1.2 Lo que pone cada fork

La base no sabe qué hace tu producto. Lo que es **tuyo**, y que la base deliberadamente no trae:

- **El prompt** de cada agente (un `prompt.md` junto al agente, versionado).
- **El contexto de negocio**: qué datos conoce el agente y de dónde salen.
- **Las tools**: qué puede leer o hacer el agente, y la política de acceso de cada una (sección 3.6).
- **El schema de salida**, si el agente genera algo con forma fija: el DTO y sus constraints.
- **El proveedor y el modelo** (`ai.yaml`, variables de entorno), y la revisión legal que lo acompaña
  (sección 6.6).
- **Los requisitos que dependen de quien consume**: revisión humana, aviso al usuario de que habla con
  una IA, y la clasificación de tus tools en lectura y efectos (sección 6).

Regla práctica: **lo genérico y sin conocimiento del caso de uso es de la base; todo lo que mira
al negocio es del fork**. Si te encuentras copiando una pieza de la base para cambiarle una línea,
probablemente falta un punto de extensión (como los traductores por tag) y es una issue, no una copia.

---

## 2. Montar un agente: paso a paso

Se sigue la forma agéntica (con tools); la de generación es más corta y está en la sección 5.2.
Todo lo siguiente tiene su ejemplo vivo en el código: copia `user_directory` y cambia lo que toca.

### 2.1 Variables de entorno

`OPENROUTER_API_KEY` y `OPENROUTER_MODEL` (en `.env`, vacías por defecto). El proveedor inicial es
OpenRouter vía `symfony/ai-open-router-platform`; cambiar de proveedor o de modelo es configuración,
no código. El modelo se referencia **por parámetro** (`app.ai.openrouter_model`) y no por la variable
directamente: al doblar la plataforma en el entorno de test, una variable que dejara de usarse haría
que Symfony abortase la compilación del contenedor.

### 2.2 Declarar el agente

```yaml
# config/packages/ai.yaml
ai:
    agent:
        user_directory:
            model: '%app.ai.openrouter_model%'
            prompt:
                file: '%kernel.project_dir%/src/Infrastructure/AI/Agent/UserDirectory/prompt.md'
            # Lista explícita a propósito: `tools: true` expondría cada servicio con el tag
            # `ai.tool`, que en un fork es impredecible. Declarar las tools es además lo que crea
            # `ai.toolbox.user_directory`, que es lo que decora el saneado de identificadores.
            tools:
                - App\Infrastructure\AI\Agent\UserDirectory\ListUsersTool
            max_tool_calls: 5
```

`max_tool_calls` acota las rondas de tool calls por llamada. El valor por defecto del bundle (50)
deja que un modelo que entra en bucle gaste mucho antes de parar; al superar el límite se lanza
`MaxIterationsExceededException`, que el adaptador ya convierte en `AgentUnavailableException`.
Ajústalo a las tools de tu agente.

### 2.3 Escribir el prompt

Un `prompt.md` junto al agente: texto plano, iterable sin tocar PHP. Mira el de `user_directory`: dice
qué hace el agente, que solo responde con lo que devuelve la tool, que no reintenta lo que se le
deniega y que no puede escribir. **El prompt es una defensa blanda**: depende del modelo y no
sustituye a ninguna comprobación en código (sección 6.4).

### 2.4 Escribir la tool

Copia `ListUsersTool`, que es la plantilla y tiene marcados con `ADAPT` los cuatro puntos que
cambian con la query: los argumentos que ve el modelo (con su `#[Schema]`), el tipo que devuelve la
query, el mapeo de ese resultado y la política de acceso. Una tool es un adaptador fino: construye una
`Query` o un `Command` y lo pasa por el **mismo bus** que usan los controladores (sección 3.1). Para
ello extiende `AgentTool`.

El parámetro de un rol se tipa como **`string` con `#[Schema(enum: [...])]` escrito a mano** y se
traduce al enum del dominio con el mapa de la frontera de entrada (`RoleInputTransformer`). No se
respalda el enum de dominio con strings externos (sección 8.2).

### 2.5 Quién llama al agente

Nadie toca el `AgentContext` desde fuera: se pregunta al agente con la query `AskAgent` (actor +
conversación) y el puerto `AgentGateway` se encarga de poblar y limpiar el contexto. Un controlador,
un handler de Messenger o un comando solo construyen el actor y la conversación. Si el consumidor
poblara el contexto él también, fallaría: entrar dos veces se rechaza a propósito.

`POST /ai/chat` es el ejemplo HTTP completo: valida el JSON (`AiChatValidator`), lo traduce a turnos
tipados en la frontera, construye la `AgentConversation` y hace `AskAgent` con el usuario
autenticado. No requiere añadir nada a la política HTTP (nota en 3.6): la regla por defecto ya exige
autenticación.

### 2.6 Declarar la política de acceso de la tool

Cada tool decide quién puede usarla (sección 3.6). Con el diseño actual se comprueba en la propia
tool leyendo el actor de `AgentContext`, como hace `ListUsersTool`.

### 2.7 Tests

- **Ningún test llama al proveedor.** La plataforma real del contenedor se sustituye por
  `ScriptedPlatform` (`tests/Infrastructure/AI/Agent/Double/`), un doble que responde lo que el test
  le escribe. Decora la plataforma (no la redefine), para que el contenedor siga necesitando las
  variables `OPENROUTER_*` y compile. `NoProviderOutsideTheContainerTest` vigila que nadie construya
  una plataforma o un cliente HTTP fuera del contenedor.
- **Los tests de una tool pasan por un `Toolbox` real**, no invocando `__invoke()` directamente: eso se
  saltaría el `ValidateToolCallArgumentsListener` y con él toda la validación de `#[Schema]`. El
  `ToolboxHelperTrait` monta el toolbox completo (incluido el decorador de saneado) y ejecuta un
  `ToolCall` contra él; devuelve lo que vería el modelo.
- Los servicios privados se inlinean o se eliminan al compilar, así que el contenedor de test no puede
  pedirlos. Por eso `config/services_test.yaml` declara **alias públicos** (`test.agent_context`,
  `test.ai.toolbox.user_directory`...). Si un fork añade un agente y lo prueba, añade el alias del
  suyo.

---

## 3. Dónde va la validación

### 3.1 El principio

> **Una tool es un adaptador de la capa Application, exactamente igual que un controlador, con una
> única diferencia: donde el controlador deja escapar la excepción hacia el `ExceptionListener`, la
> tool la convierte en un mensaje para el modelo.**

Esto se aplica a las **reglas de negocio** —qué se puede hacer, con qué datos, en qué estado—: viven
en el handler, y **si una regla no está en el handler, la corrección es meterla en el handler, nunca
copiarla en la tool**. Una regla duplicada en la tool produce dos rutas de entrada con
comportamientos distintos para el mismo caso de uso. Es el motivo de #121.

Pero **no se aplica a la política de entrada** —quién puede usar *esta* vía—. Esa la declara cada
adaptador: el endpoint HTTP, la tool, un comando de consola o un consumidor de Messenger son vías de
entrada distintas y pueden exigir condiciones distintas. Que hoy la política de la tool y la del
endpoint equivalente coincidan es circunstancial, no una señal de duplicación (sección 3.6). Confundir
las dos cosas lleva a «unificar» lo que no debe unificarse.

### 3.2 Equivalencia entre las dos rutas

| Responsabilidad | Ruta HTTP | Ruta agente |
|---|---|---|
| Forma de la entrada | `XxxValidator` (`Collection` de constraints) | `#[Schema]` en los parámetros + `ValidateToolCallArgumentsListener` |
| Coerción a VO / enum | `Transformer` + value object | **el mismo** `Transformer` + value object |
| **Política de entrada** (quién puede usar esta vía) | `access_control` de `config/app/security.yaml` (política del transporte HTTP, nota en 3.6) | **propia de la tool**, comprobada con el actor de `AgentContext` (sección 3.6) |
| Reglas de negocio | command handler / Domain | **el mismo** command handler |
| Fallo → salida | excepción → `ExceptionListener` → status HTTP | excepción → `getToolCallResult()` → texto para el modelo |

### 3.3 Las piezas del paquete que se usan

- **`ValidateToolCallArgumentsListener`** (`ai-agent`, `src/Toolbox/EventListener/`). El bundle lo
  registra siempre que esté instalado `symfony/validator`. Traduce los keywords de `#[Schema]` a
  constraints de Symfony Validator y los aplica antes de invocar la tool: `enum` → `Assert\Choice`,
  `minLength`/`maxLength` → `Assert\Length`, `minimum`/`maximum`, `pattern` → `Assert\Regex`,
  `multipleOf`, `minItems`/`maxItems`, `uniqueItems`, `const`. Es el equivalente de un `XxxValidator`,
  con la ventaja de que **la misma declaración describe el JSON Schema que ve el modelo y se aplica en
  ejecución**.
- **`fault_tolerant_toolbox`**, que el bundle deja **activo por defecto**. Si una tool lanza una
  excepción que implementa `ToolExecutionExceptionInterface` (o no encuentra la tool), el
  `FaultTolerantToolbox` devuelve el error al modelo como resultado de la tool y el bucle agéntico
  continúa: **el patrón «error como dato» ya lo da el framework**.
- **`#[IsGrantedTool('ROLE_ADMIN')]`** (`ai-bundle`). Autorización declarativa por tool, evaluada antes
  de ejecutar. Tiene una condición que hoy la base no cumple: ver 3.7.
- **Evento `ToolCallRequested`**, con `deny($razón)` y `setResult()`. Punto de intercepción para
  política transversal —confirmación de tools con efectos, cuotas, auditoría de tool calls— sin tocar
  ninguna tool.
- **Evento `ToolCallFailed`**, que el `Toolbox` despacha cuando una tool falla, tanto con una
  excepción controlada (`ToolExecutionExceptionInterface`) como con cualquier otra. La base se engancha
  a él para registrar el fallo (sección 4.5).

### 3.4 Límites verificados del mecanismo declarativo

1. **`#[Assert\...]` sobre un parámetro escalar no hace nada.**
   `ValidatorConstraintsDescriber::describeProperty()` hace `return` si el reflector es un
   `ReflectionParameter`, y el listener solo lee `#[Schema]` para argumentos escalares. En parámetros
   escalares **el único mecanismo declarativo es `#[Schema]`**, y solo su subconjunto aplicable en
   ejecución (el de la sección 3.3).
2. **Con un parámetro que sea un DTO sí funciona todo `Assert`.** El listener valida el objeto
   completo con attribute mapping, y el describer lee la metadata de la clase, así que las
   constraints llegan también al JSON Schema. **Patrón por defecto: escalares + `#[Schema]`** (schema
   plano, más fácil para el modelo). **Vía de escape: un DTO**, cuando haga falta toda la potencia de
   `Assert`.
3. **Un enum puro no es describible.** `TypeInfoDescriber::buildEnumSchema()` lanza
   `Enum "%s" is not backed`. Por eso el parámetro de rol de una tool es un `string` con
   `#[Schema(enum: [...])]` (sección 2.4). Respaldar el enum del dominio para evitarlo se descartó
   por producto (sección 8.2).
4. **El mensaje por defecto que llega al modelo es opaco.** Ver 3.5.

### 3.5 Traducción de errores: la regla del genérico opaco

El mapeo de excepciones a mensajes para el modelo vive en `ToolExceptionMapper`, y **su caso por
defecto es opaco**: solo las excepciones explícitamente traducidas devuelven un texto descriptivo;
cualquier otra devuelve un genérico. El motivo es de seguridad, no de estilo: el mensaje de una
excepción arbitraria puede contener rutas, SQL o datos internos, y ese texto **se envía al proveedor
y entra en el contexto del modelo**.

**Hay dos genéricos opacos distintos, y se decidió no unificarlos**, porque los pone quien captura:

- **El del proyecto**, `The operation could not be completed.` (`ToolCallException::unexpected()`):
  para lo que falla **dentro del bus** sin traductor. Lo pone el mapper de `AgentTool`.
- **El del paquete**, `An error occurred while executing tool "X".`: para lo que falla **antes del
  bus** (por ejemplo, construir un value object con una entrada que el `#[Schema]` no filtró, o que
  `AgentContext::actor()` lance por un fallo de cableado). Lo pone el `Toolbox` interno del paquete:
  su `catch (\Throwable)` convierte cualquier cosa en una `ToolExecutionException` cuyo
  `getToolCallResult()` es ese texto. **No lo pone el `FaultTolerantToolbox`**, que solo captura
  `ToolExecutionExceptionInterface` y `ToolNotFoundException`, no `\Throwable`.

Unificarlos exigiría envolver en el proyecto lo que el paquete ya cubre con el mismo resultado seguro.
`AgentContext::actor()` es el caso que lo ilustra: se lanza antes del bus, **no** se envuelve en
`ToolCallException`, y el modelo ve el genérico del paquete, que es justo lo que se quiere para un
error de cableado.

**Excepción consciente: los argumentos inválidos.** La regla del genérico opaco **no se aplica** a
`InvalidToolCallArgumentsException`, que lanza `ValidateToolCallArgumentsListener`: al modelo le llega
`<ruta>: <mensaje> (código)` por cada violación, porque necesita saber qué corregir para reintentar.
Medido para escalares y para DTO: **no aparecen ni el valor rechazado ni los nombres de clase**, porque
`startContext()` no fija raíz. Un test lo fija en negativo.

`ToolCallException`, `ToolExceptionMapper` y los traductores van en **Infrastructure**, no en Domain,
porque implementan y conocen interfaces del paquete.

### 3.6 La política de acceso de una tool es propia

> **Dónde está la política HTTP en este proyecto.** `config/packages/security.yaml` trae el
> `access_control` vacío. Las reglas reales están en `config/app/security.yaml`
> (`app.security_config.access_control`): por ejemplo `^/admin/users` con `[ROLE_ADMIN, ROLE_MANAGER]`
> y, como regla por defecto, `IS_AUTHENTICATED_FULLY`. Cuando este documento dice «la política HTTP»,
> se refiere a ese parámetro, y es ahí donde hay que mirar y editar las reglas, no en el bloque vacío
> de `config/packages/security.yaml`.

La política HTTP protege **endpoints**: es política de transporte. Una tool es otra vía de
entrada y **puede exigir condiciones distintas**. El ejemplo que lo zanja: si las herramientas de IA
fueran una funcionalidad de pago, la tool tendría que comprobar que la persona la ha contratado
(o su cuota, o su periodo de prueba), y eso no cabe en la política HTTP ni le incumbe al endpoint.
Una condición que **no es un rol** no puede delegarse en el Firewall.

Consecuencias para el fork:

- La autorización de una tool es **una política propia**, no una copia de la del endpoint equivalente.
  `ListUsersTool` acepta hoy `ROLE_ADMIN` y `ROLE_MANAGER`, igual que la regla `^/admin/users` del `access_control`;
  que coincidan es circunstancial y nada las ata.
- Con el diseño actual, esa política **se comprueba en la tool leyendo el actor de `AgentContext`**
  (el campo `ADAPT 4` de la plantilla).
- **Detalle abierto**: esa comprobación usa `hasRole()` con los casos de `UserRole` (no con literales) y **no pasa por
  `RoleHierarchy`**. Hoy no hay jerarquía configurada; si se configura, habrá que decidir si la política
  de las tools debe respetarla.

### 3.7 `#[IsGrantedTool]` depende del token de Security, no del transporte

`#[IsGrantedTool]` se apoya en `AuthorizationCheckerInterface`, es decir, **en el token de
seguridad**, no en HTTP. Medido con el dispatcher y el helper reales:

- Con un Admin en `AgentContext` y **sin token de Security**, el atributo deniega y el toolbox devuelve
  el genérico.
- Poniendo un `UsernamePasswordToken` de Admin, **el atributo funciona también fuera de HTTP**: un
  consumidor de Messenger o un comando pueden usarlo **si establecen el token**.

Lo que no funciona es fiarse del atributo cuando la identidad viaja **solo por `AgentContext`**, que es
el caso de la base hoy. Además, `AccessDeniedException` no implementa
`ToolExecutionExceptionInterface`, así que cae en el genérico del paquete: **el modelo nunca recibe un
«no tienes permiso» accionable**, y un traductor no lo arregla porque la excepción se lanza antes de
que la tool corra. Por eso una tool que necesite restringir por rol **lo comprueba ella misma** (3.6).
Si la frontera estableciera algún día el token de Security, el atributo pasaría a ser la vía preferible
y esa comprobación manual sobraría; no es una decisión tomada.

### 3.8 El actor: explícito, nunca del `RequestStack`

En cuanto la llamada sale de una petición HTTP —un consumidor de Messenger, un cron, un comando— no
hay request ni token de seguridad. La identidad del invocante viaja en el `AgentContext`, poblado por
el adaptador del puerto (sección 4.1). Es también el argumento que más adelante alimentará el registro
de consumo (`Usage`).

---

## 4. Las piezas de la base

```
backend/
  config/packages/ai.yaml                    # plataforma + agentes declarados
  config/services.yaml                       # traductores y mapper cableados por tag
  config/services_test.yaml                  # alias públicos y ScriptedPlatform
  src/Application/AI/                        # puertos y VOs: AgentGateway, AgentConversation, ReportGateway...
  src/Infrastructure/AI/
    Context/AgentContext.php                 # el actor, explícito
    Agent/                                   # un directorio por agente: prompt, tools, adaptador
      UserDirectory/                         #   forma agéntica (ListUsersTool, prompt.md)
      Report/                                #   forma de generación (DTO de ejemplo, prompt.md)
    StructuredOutput/
      StructuredOutputDecoder.php            # genérico: schema -> claves, listas, denormaliza, valida
    Toolbox/
      AgentTool.php                          # base: bus + actor + traducción de errores del bus
      ToolCallException.php                  # el fallo con mensaje redactado para el modelo
      ToolExceptionMapper.php                # excepción -> mensaje, genérico opaco por defecto
      Translator/                            # traductores por tipo de excepción, extensibles por tag
      IdentifierStrippingToolbox.php         # saneado de lo que la tool devuelve
      LogFailedToolCallSubscriber.php        # log de lo que falla dentro del bus
  specs/docs/AI_SUPPORT.md                   # este documento
```

Ninguna pieza de la base conoce un caso de uso. Lo que cada fork escribe encima son agentes, tools,
prompts y DTOs de salida.

### 4.1 `AgentContext` — la identidad del invocante

Quién originó la llamada al agente. Las tools leen el actor de aquí y nunca del `RequestStack`. Es un
servicio mutable de ámbito de ejecución, igual que el `TokenStorage` de Symfony, y **tiene que seguir
compartido**: todos los consumidores deben ver la misma instancia.

Lo puebla **solo el adaptador del puerto** (`SymfonyAiAgentGateway`): `enterAs()` antes de ejecutar el
agente y `leave()` en un `finally`. Entrar dos veces sin salir es un error de cableado, no una
sobrescritura (`LogicException`): sobrescribir en silencio ocultaría una frontera que olvidó limpiar, y
un `leave()` anidado borraría el actor de quien llamó primero. `leave()` existe porque **en un worker de
Messenger el contenedor sobrevive entre mensajes**: sin él, el actor del mensaje anterior se filtraría
al siguiente. Es idempotente para poder ir siempre en un `finally`.

`actor()` lanza `LogicException` en lugar de devolver `null`: una tool nunca debe decidir qué hacer
sin actor. **No se envuelve** en `ToolCallException`: se lanza antes del bus, cae en el genérico del
paquete y el modelo no ve el texto (sección 3.5).

La ejecución del agente es **perezosa**: las tools solo corren cuando se lee el contenido de la
respuesta. Por eso el adaptador lee la respuesta **dentro** del ámbito del contexto, y cualquier
implementación del puerto tiene que mantener el actor entrado mientras se consume (también una futura
respuesta en streaming).

### 4.2 `AgentTool` — la base de toda tool

Extiende `DuplexBusAccessor` y sobrescribe `execute()` (commands) y `ask()` (queries) para traducir
en un único sitio lo que lanza el bus: cualquier `\Throwable` pasa por `ToolExceptionMapper`, que lo
convierte en una `ToolCallException`.

**Límite conocido y deliberado**: solo se traduce **lo que pasa por el bus**. Lo que falla antes
—el actor ausente, `AccessDeniedException`, argumentos que faltan, un value object construido con una
entrada que el `#[Schema]` no filtró— cae en el genérico opaco **del paquete** (sección 3.5). Es el
comportamiento seguro por defecto.

### 4.3 `ToolCallException` — el fallo con un mensaje redactado para el modelo

Implementa `ToolExecutionExceptionInterface`, así que el `FaultTolerantToolbox` devuelve su
`getToolCallResult()` como resultado de la tool y el agente puede explicar el fallo y seguir en lugar de
cortar la conversación. Tiene dos constructores con nombre:

- `withReason($reason, $previous)`: un mensaje accionable, escrito para el modelo.
- `unexpected($previous)`: el genérico del proyecto, **en inglés** como el resto de mensajes de
  excepción de `src`: `The operation could not be completed.`

`getToolCallResult()` es lo único que ve el modelo. **La causa real viaja en `$previous`, pero eso no
basta para que llegue a los logs**: ver 4.5.

### 4.4 `ToolExceptionMapper` y sus traductores

Mismo patrón que el `DeliveryChannelDispatcher` del OTP: colección de implementaciones inyectada por
tag (`app.ai.tool_exception_translator`), **primera que soporta, gana**. Cada fork añade las suyas sin
tocar la base.

```php
interface ToolExceptionTranslator
{
    public function supports(\Throwable $exception): bool;

    /** Mensaje accionable, escrito para que el agente se lo explique a la persona. */
    public function translate(\Throwable $exception): string;
}
```

La base aporta los traductores de su jerarquía compartida —`NotFoundException`, `ForbiddenException`,
`UnprocessableEntityException` y `ValidatorException`—, con lo que las excepciones de dominio de
cualquier fork quedan cubiertas por herencia sin escribir nada. Un fork añade un traductor cuando quiere
un mensaje más específico que el de la clase padre. Sin traductor, el mapper devuelve el genérico opaco.

**Pautas para el fork:**

1. **El orden es el de registro, y el empate falla en silencio.** Las cuatro familias de la base no se
   solapan hoy. Pero un traductor para una **subclase** de una excepción ya cubierta por otro debe fijar
   su prioridad con `#[AsTaggedItem(priority: N)]` **y probar cuál gana**: si no, el de la base vence por
   orden de fichero, no falla nada y el mensaje específico simplemente no sale nunca.
2. **El mensaje de una excepción de dominio puede acabar en un proveedor externo.** Los traductores de
   la base reenvían `getMessage()` (`Reason: ...`) al contexto del modelo. **La regla para quien escribe
   una excepción de dominio: su mensaje no debe incluir datos personales ni secretos.** Ver 6.6, donde
   se explica por qué importa más aquí que en HTTP.
3. El `ValidatorExceptionTranslator` lista rutas y mensajes de las violaciones, **nunca el valor
   rechazado**.

### 4.5 Qué llega al log cuando una tool falla

El paquete **no registra** lo que falla dentro del bus. Verificado sobre `Toolbox::execute()` de la
0.13.0: la rama `catch (ToolExecutionExceptionInterface)` despacha `ToolCallFailed` y relanza, sin llamar
al logger; solo el `catch (\Throwable)` (lo que falla antes del bus) hace `$logger->warning(...)`, y en
ambos casos se despacha `ToolCallFailed`. Como `ToolCallException` implementa esa interfaz, **un fallo de
base de datos o un bug en un handler desaparecía sin rastro**.

El mecanismo que cierra ese hueco es **`LogFailedToolCallSubscriber`**, suscrito a `ToolCallFailed`:
registra con el `Logger` del proyecto (el que llega a `app.log` y a Rollbar) **la causa**, no la
`ToolCallException`. El criterio es el del `ExceptionListener` HTTP: **los fallos esperados no se
registran** (`NotFoundException`, `ForbiddenException`, `ValidatorException`, argumentos inválidos,
`AccessDeniedException`...) y sí los `LoggableException` y todo lo demás. Una `ToolCallException` sin
`previous` es una razón lanzada a propósito por la tool y tampoco se registra.

Garantías y límites:

- **No se vuelca el payload de los argumentos, solo sus claves.**
- El mensaje de la causa **puede contener valores** (un `InvalidEmailException` lleva el email), igual
  que en el canal HTTP, porque es lo que hace útil el log para diagnosticar. Por eso **estos logs son
  internos y nunca deben reenviarse al proveedor de IA**.
- Lo que falla **antes** del bus también pasa por el subscriber (recibe `ToolCallFailed` igual), así que
  se registra con `error()`. El `warning()` propio del paquete para esos casos, en cambio, **puede
  perderse**: el servicio `logger` del proyecto descarta todo lo que no llegue a `ERROR` (#116,
  abierta). El subscriber no depende de ese logger.

### 4.6 Cableado

```yaml
# config/services.yaml
App\Infrastructure\AI\Toolbox\Translator\:
    resource: '../src/Infrastructure/AI/Toolbox/Translator/'
    tags: [ 'app.ai.tool_exception_translator' ]

App\Infrastructure\AI\Toolbox\ToolExceptionMapper:
    arguments:
        $translators: !tagged_iterator app.ai.tool_exception_translator
```

El `StripIdentifiersFromToolboxesPass` decora automáticamente cada `ai.toolbox.*` con el saneado de
identificadores: un agente nuevo con tools lo hereda sin configurar nada. Un agente **sin tools** no
tiene toolbox y el pass no tiene nada que decorar.

---

## 5. Las dos formas de uso

Las dos están resueltas con ejemplo, declaradas en `ai.yaml` y cubiertas por tests. Declarar un agente
no tiene coste mientras nadie lo llame.

### 5.1 Agente con una tool de solo lectura (forma agéntica)

Es el agente `user_directory` con `ListUsersTool`, y es la **plantilla que se copia**. La tool es un
adaptador fino (sección 3.1): construye `GetUsers`, lo pasa por el bus, comprueba el tipo que devuelve y
lo mapea con el transformer; el `GetUsersQueryHandler` acota por rol (un manager solo ve a quienes creó).
Devuelve la salida del transformer tal cual, **sin acordarse de filtrar nada**: el saneado lo hace el
decorador `IdentifierStrippingToolbox` sobre cada `ai.toolbox`.

Lee `ListUsersTool` entera antes de copiarla: el docblock dice qué es lo que se mantiene en cualquier
tool de lectura y qué puntos (`ADAPT 1` a `4`) cambian con la query. Copiarla no es renombrarla.

**Qué puede y qué no puede devolver una tool.** Todo lo que una tool devuelve se serializa y entra en la
conversación, es decir, **llega al proveedor** y permanece en el contexto mientras dure. Por eso:

- **Una tool nunca devuelve un secreto**: contraseñas (también las temporales), tokens, claves, códigos.
  Un identificador que se cuela cuesta tokens; un secreto que se cuela es un secreto comprometido, y el
  saneado de identificadores **no lo cubre ni debe cubrirlo**. Cómo entrega una tool que crea una
  credencial el secreto a quien corresponde, sin pasarlo por el modelo, está **por decidir en #117**;
  hasta entonces, ninguna tool de la base devuelve una credencial y un fork no debe escribir una que lo
  haga.
- Devuelve solo lo que el modelo necesita para responder: cada campo extra es dato personal de más que
  viaja (sección 6.6). `ListUsersTool` devuelve emails, roles y fechas, y esos emails son datos
  personales que van al proveedor.

**Saneado de identificadores: qué garantiza y qué no.** `IdentifierStrippingToolbox` (registrado por
`StripIdentifiersFromToolboxesPass`) pasa cada resultado por `ToolPayloadSanitizer`. Reglas:

- Se quitan las claves `id`, `uuid`, `*_id` y `*_uuid` (con `_` obligatorio: `valid` se conserva).
- Se quita cualquier clave cuyo valor sea exactamente un UUID (así cae `created_by` aunque su nombre no lo
  delate), o una lista no vacía cuyos elementos sean todos UUID exactos (así cae `user_ids`).
- Se recorre todo el árbol de **arrays**. La lista mixta no se elimina como bloque, pero sus elementos
  array sí se sanean. La lista vacía se conserva.

**Lo que no cubre, y el fork debe conocer:**

- **Solo recorre arrays, no objetos.** Un objeto anidado en el payload lo serializa el
  `ToolResultConverter` con sus propiedades públicas intactas, y su `id` llega al modelo. Se decidió
  documentarlo en vez de cerrarlo: recorrer objetos obliga a normalizar antes y se pierden tipos, y la
  convención del proyecto es que los transformers devuelven arrays. **La garantía cubre payloads de
  array; una tool que devuelva objetos queda fuera.**
- Lo que no casa con ninguna regla llega al modelo: un `userId` o un `owner` con valor `42` (ni casa por
  nombre ni es un UUID), un UUID suelto en una lista sin clave propia, o un identificador dentro de un
  objeto.

**Tools con efectos: el caso avanzado.** La PoC original tenía una tool `create_user`. **No se incluye
como plantilla**, porque el perfil de riesgo es otro (sección 6.3) y su ejemplo original devolvía
la contraseña temporal al modelo. Si un fork necesita una tool con efectos: pasa por el mismo bus
(el handler revalida todo, sección 6.4), declara su política de acceso (3.6), **no devuelve secretos**,
y se documenta como tool con efectos en el fork. Antes de ponerla delante de nadie, aplica además la
regla del historial (6.5): hoy un historial sin firmar no es aceptable para un agente que escribe.

### 5.2 Agente sin tools con salida estructurada (forma generación)

Es la forma de un botón que genera un informe con secciones fijas. El schema de salida es un DTO con
`#[Schema]` y constraints de Symfony Validator, y el consumidor recibe un objeto tipado **sin tocar el
paquete**. Sin superficie HTTP: el consumidor es un comando, un handler o un test.

#### Cómo está hecho el ejemplo (#109)

- **Puerto**: `Application\AI\ReportGateway::generate(SecurityUser $actor, string $instructions): Report`.
  Recibe un `string` y no una `AgentConversation`: es generación de un disparo, y las invariantes de la
  conversación (ventana, longitud por turno, quién habla último) son del chat. `Report` tiene forma
  fija (`summary` + `sections`): un informe con otra forma necesita **otro puerto**, no este.
- **Dos tipos, uno por capa**. `Application\AI\Report`/`ReportSection` son value objects planos: lo que
  recibe el consumidor. `Infrastructure\AI\Agent\Report\ExampleReport`/`ExampleReportSection` son el DTO
  que se le pide al modelo, con `#[Schema]` y constraints, y viven en Infrastructure porque
  `Application` no importa `Infrastructure`. `SymfonyAiReportGateway` traduce de uno a otro; es un mapa
  de traducción entre capas, no duplicación.
- **Adaptador**: `Infrastructure\AI\Agent\SymfonyAiReportGateway`, mismo embudo que
  `SymfonyAiAgentGateway` (`enterAs()` fuera del `try`, `leave()` en el `finally`), aunque el agente no
  tenga tools que lean el actor: si un fork las añade, el camino ya tiene contexto. El agente
  `example_report` de `ai.yaml` no tiene `tools`, así que no hay toolbox. Está **atado al ejemplo**:
  `#[Autowire(service: 'ai.agent.example_report')]` y `ExampleReport::class` escritos en el código.
- **Decodificador**: `Infrastructure\AI\StructuredOutput\StructuredOutputDecoder` es una pieza
  **genérica de la base**: no conoce ninguna forma de salida y un fork la usa tal cual, con su clase.
- **Lo que recibe el consumidor**: un `Report` o una `AgentUnavailableException` (502, mensaje genérico,
  causa en `previous`). **No hay excepción nueva para la salida inválida.**

Lo medido sobre la 0.13.0, que conviene conocer antes de escribir una salida estructurada:

- **Con el FQCN como `response_format` el paquete no hace cumplir `additionalProperties: false`.** El
  schema que ve el modelo cierra el objeto (en la raíz y en cada sección), pero al recibir la respuesta
  deserializa con un `ObjectNormalizer` que ignora en silencio las claves que no mapean a una propiedad:
  un JSON con una clave de más llega como objeto válido y el dato sobrante se pierde sin rastro. Por eso
  el adaptador **no pasa la clase**: pide el schema como **array**, construido con la
  `ResponseFormatFactory` pública del SDK (el mismo schema, ni un carácter distinto), y el paquete
  devuelve el array decodificado tal cual.
- **Qué hace `StructuredOutputDecoder`.** Recorre el propio schema y el valor a la vez, a cualquier
  profundidad: todo objeto que declara sus propiedades no acepta una clave fuera de ellas, y todo array
  que declara sus `items` tiene que ser una lista JSON (un `"sections": {"1": ..., "5": ...}` se
  rechaza; medido que antes pasaba con las claves `[1, 5]`). Después denormaliza con la receta de
  serializador del paquete (fechas, enums respaldados y discriminador de clase incluidos) y valida las
  constraints, con las excepciones de su familia (`RuntimeException`, `ValidationException`). Si el
  schema usa `$ref`, `anyOf`, `oneOf` o `allOf`, **lanza una `LogicException`** en lugar de dejar sin
  comprobar las claves de lo que describen: es un fallo de programación del fork, no una respuesta del
  modelo. Todo lo que no encaja con el schema acaba en `AgentUnavailableException` con la causa en
  `previous`.
- **El `TypeError`, acotado.** Con el schema como array, un texto que no es JSON llega como
  `JsonException` dentro de la `RuntimeException` del paquete. Un JSON válido que no es objeto (`42`,
  `"x"`, `null`, `[1, 2]`) hace que el paquete lance un `TypeError` al construir su `ObjectResult`, no
  una excepción suya. El adaptador lo reenvuelve para que acabe igual, **y solo ese**: leer el resultado
  ejecuta toda la ejecución perezosa del agente, así que un `TypeError` de cualquier otro punto (un bug
  del SDK o de un processor del fork) se propaga tal cual. Se reconoce por dónde se lanzó (el primer
  frame de su traza es el constructor de `ObjectResult`) porque el paquete no deja leer el texto sin que
  construya ese resultado: su subscriber lo envuelve siempre que `response_format` está presente.
- Pasar un array **salta la comprobación de capacidad `OUTPUT_STRUCTURED`**
  (`MissingModelSupportException`): un modelo que no la soporte fallará en el proveedor, no antes. El
  modelo configurado debe declararla.
- **Las constraints de Symfony Validator sí funcionan aquí**, a diferencia de los parámetros escalares
  de una tool: el describer lee la metadata de la clase, así que `#[Assert\...]` sobre el DTO llega al
  JSON Schema que ve el modelo (`maxLength`, `minItems`, `maxItems`).
- `NotBlank` no recorta: para que un texto de solo espacios cuente como vacío hay que pedirlo con
  `normalizer: 'trim'`.
- **Estado compartido del paquete (leído en el código)**: `PlatformSubscriber` es un único servicio
  compartido y **reutiliza** el tipo de salida entre llamadas. En 0.13.0, `processInput()` fija
  `$outputType` solo cuando `response_format` es una clase o un objeto; con un array hace `return` antes
  y no lo resetea, y `processResult()` no lo limpia. En un proceso que sobrevive entre llamadas (un
  worker de Messenger), tras una llamada con clase **todas** las llamadas posteriores con el schema como
  array se deserializan a esa clase: `asObject()` devuelve un objeto en vez del array esperado,
  `StructuredOutputDecoder::decode()` lo rechaza como «not a JSON object» y esas llamadas acaban en 502
  hasta reiniciar el proceso. Hoy no pasa porque ningún agente de la base pide una clase; pasaría en
  cuanto un fork añadiera uno. Seguimiento en #130.
- El `ScriptedPlatform` del contenedor sustituye a toda la plataforma y con ella a la salida
  estructurada, así que los tests de esta forma construyen una plataforma real con el cliente de
  mentira del propio SDK (`MockModelClient`) y los subscribers del paquete. Un test de contenedor
  (`ReportGatewayWiringTest`, con el alias público `test.report_gateway` de `services_test.yaml`)
  comprueba además el puerto tal como lo cablea el contenedor, con el validador del framework: ahí el
  `ScriptedPlatform` devuelve ya el resultado como objeto.

#### Montar tu propia forma de generación

Paralelo a la sección 2. `SymfonyAiReportGateway` está atado al ejemplo, así que el fork **copia el
adaptador** y crea su propio puerto; no reutiliza `ReportGateway` salvo que su informe tenga justo la
forma `summary` + `sections`.

1. **Declara el agente en `ai.yaml` sin `tools`**, con `model`, `prompt` (un `prompt.md` junto al
   agente) y un modelo que declare `OUTPUT_STRUCTURED`.
2. **Escribe el DTO** en `Infrastructure/AI/Agent/<Agente>/` con `#[Schema]` y constraints de Symfony
   Validator. **Sin `$ref`, `anyOf`, `oneOf` ni `allOf`**: el decodificador lanza `LogicException`.
   Ponle límites (`Length(max: …)`, `Count(max: …)`) a todo lo que el modelo escribe libremente.
3. **Puerto y value object en `Application/AI/`**, con la forma que el consumidor necesita, y un
   adaptador copiado de `SymfonyAiReportGateway` en el que cambias **el servicio del `#[Autowire]`**
   (`ai.agent.<tu_agente>`), **la clase del DTO** (en `responseFormat()` y en `decode()`) y la
   traducción del DTO al value object.
4. **`StructuredOutputDecoder` tal cual**, sin tocarlo ni copiarlo: es genérico y se inyecta.
5. **Tests.** Los del adaptador, con una plataforma real y `MockModelClient` (modelo siguiendo
   `SymfonyAiReportGatewayTest`), incluidos los casos de salida mala. Y **un test de cableado con su
   propio alias público** en `services_test.yaml`, como `ReportGatewayWiringTest`: sin él, si el
   validador del contenedor dejara de leer los atributos del DTO, las constraints dejarían de aplicarse
   en producción sin que fallara ningún test.

---

## 6. Condiciones que el fork tiene que cumplir

Esta sección es la más importante de la guía. La base resuelve la parte técnica; **lo que sigue son
requisitos del consumidor** que ninguna pieza de la base puede cumplir por él.

### 6.1 Human-in-the-loop

**Nadie entrega a un cliente contenido generado sin revisión humana.** Es un requisito del consumidor,
no de la base: la base devuelve un `Report` o un texto, y qué se hace con él es decisión del producto.
Un informe generado por IA que se manda a un cliente, o una respuesta que dispara una acción, pasa por
una persona antes de que tenga efecto. Documenta en el fork **quién revisa, dónde y con qué
herramienta**.

### 6.2 Transparencia: el usuario sabe que habla con una IA

El **AI Act (art. 50)** exige que la persona sepa que está interactuando con un sistema de IA. Es
responsabilidad de la interfaz del consumidor, no de la base (que no tiene UI). El patrón de referencia
es `smart-city!420`: una **etiqueta** visible en el chat y un **aviso** en la interfaz. El contenido
generado también debe poder identificarse como tal cuando salga del producto.

### 6.3 Tools de solo lectura frente a tools con efectos

**Cada fork documenta cuáles de sus tools son de lectura y cuáles tienen efectos**, porque el perfil de
riesgo es muy distinto: un agente que solo lee puede, en el peor caso, contar algo que no debía (o
inventárselo); uno que escribe puede **cambiar el estado del sistema** por culpa de un prompt malicioso
o de un modelo que se equivoca. La clasificación es parte de la documentación del fork y condiciona lo
que exigen 6.4 y 6.5. El agente que incluye la base, `user_directory`, es de solo lectura.

### 6.4 Inyección de prompt: una restricción permanente

No es una decisión abierta: **lo que devuelve una tool de lectura es entrada no confiable**. Aquí son
emails y fechas; en un caso real serán notas, descripciones o correos en texto libre, que van directos
al contexto del modelo y pueden contener instrucciones.

La regla: **que el modelo lea un dato nunca autoriza una escritura.** Toda acción con efectos se
revalida en el handler, que no ve el prompt ni la conversación, y que es la única defensa que cuenta
(la cadena de defensa va de más blanda a más fiable: prompt, tool, handler). Un prompt que diga «no
borres nada» es una pista para el modelo, no un control.

### 6.5 La regla del historial

Hoy el cliente reenvía el historial completo en cada petición y el servidor **no lo contrasta** con lo
que dijo el modelo: el cliente puede inventar turnos `assistant`. Es un riesgo **aceptado mientras todas
las tools del agente sean de solo lectura**: afecta a la autenticidad de la conversación, no al coste
(la ventana y los límites de longitud lo acotan) ni al estado del sistema.

**En cuanto exista una tool con efectos, el historial enviado por el cliente deja de ser aceptable sin
firmar**: un turno `assistant` inventado puede hacer que el modelo crea que ya pidió confirmación, o que
ya recibió una autorización que nadie dio. Hay que firmar los turnos del servidor (o guardar el
historial en el servidor) antes de habilitar esa tool. Está escrito también en el docblock de
`AgentConversation`.

### 6.6 RGPD: qué viaja al proveedor y qué verificar

**Qué viaja** al proveedor de IA en cada llamada, y por tanto qué hay que justificar:

- El **prompt** del agente y los **turnos de la conversación** que manda el cliente (hasta 40, lo que
  la persona escribe es dato personal en cuanto lo sea).
- **Lo que devuelven las tools** (sección 5.1): en `user_directory`, emails, roles y fechas de usuarios.
- **Los mensajes de las excepciones traducidas** (`Reason: ...`, sección 4.4). Es el canal menos
  evidente: **el destinatario del mensaje es un tercero**, no el cliente. El mensaje de una
  `DomainException` construye su texto con el valor rechazado (`Invalid email address: %s`). Si el valor
  lo mandó el propio modelo, es inocuo; si el handler reconstruye un value object a partir de datos
  **persistidos**, acabaría en el contexto de un proveedor un dato que nadie decidió enviar. La
  diferencia está en el origen del dato, que el traductor no puede conocer, así que **no hay regla
  automática que acierte siempre**. Pauta: **el mensaje de una excepción de dominio no incluye datos
  personales ni secretos**. Quitar el motivo del mensaje deja al modelo sin saber qué corregir, y un
  mecanismo que marque las excepciones seguras (una interfaz marcadora, por ejemplo) es diseño sin
  decidir; si se construye, este es el motivo por el que hizo falta.

**Qué no debe viajar**: secretos (5.1), y identificadores internos (los quita el saneado, con los límites
de 5.1).

**Qué verificar antes de producción** (responsabilidad del fork, no de la base):

- **DPA y base jurídica** con el proveedor elegido, y con los proveedores finales si se usa un
  intermediario como OpenRouter que enruta a otros. Responsable y momento por definir en cada proyecto.
- **Retención**: si el proveedor guarda o entrena con los datos, y cómo se desactiva.
- **Ubicación del tratamiento** y transferencias internacionales.
- **Qué datos personales llegan realmente**: auditar lo que devuelve cada tool y lo que escriben las
  personas, no lo que se supone.
- **Logs**: los de la base son internos (4.5) y no deben reenviarse al proveedor; el `ExceptionListener`
  ya no registra el cuerpo de la petición (#129). Qué puede contener un log en general es #116.
- Que el aviso de 6.2 y la información al usuario cubren el tratamiento.

---

## 7. Qué queda fuera, y decisiones abiertas

Fuera del primer paso, a propósito:

- **`AgentDispatcher`, `Purpose` y `RoutingRule`.** Con dos agentes de ejemplo, el enrutado por
  propósito solo añade una indirección que habría que rediseñar en cuanto llegue el consumidor real. Se
  incorpora cuando haya dos consumidores de verdad.
- **`Usage` y coste.** Mismo razonamiento, y su diseño depende de decisiones abiertas (tabla de precios,
  qué se audita de las tools). El `AgentContext` ya nace explícito porque es el argumento que viajará
  ahí.
- **UI.** Responsabilidad de cada proyecto. Si un widget de chat resulta reutilizable, su sitio es
  **vue-base-project**: el directorio `frontend/` es un subtree de ese repositorio y cualquier cosa
  añadida por el lado de Symfony se pierde en el siguiente `frontend-sync`.
- **Streaming.** `Agent`/`Platform` ya lo soportan; es una opción a activar, no un rediseño.
- **Llamada asíncrona** (Messenger + notificación): una generación larga en un controlador se come el
  timeout del servidor web. Pendiente hasta saber cuánto tarda de verdad.

Abiertas, con su issue cuando la tiene:

- **Secretos que genera una tool** (#117).
- **Qué puede contener un log** y el nivel mínimo del logger (#116).
- **4xx específico** cuando el proveedor rechaza el contenido de la conversación (contexto excedido,
  contenido filtrado), que hoy cae en el 502 genérico (#127). Y el nivel de log y la agrupación de
  alertas ante caídas recurrentes del proveedor (#128).
- **El mensaje de las excepciones en las respuestas 500** (#114).
- **Reintentos y backoff** ante 429/5xx: comprobar el mecanismo `failover` del componente Platform antes
  de construir un decorator propio.
- **Cuotas por propósito.** `Usage` es el sitio natural y `ToolCallRequested` el punto donde aplicarlas.
- **Proveedor definitivo**: Anthropic directo frente a OpenRouter, según el tipo de datos que viajen
  (6.6).

---

## 8. Lo que la épica destapó en el resto del código

Correcciones que este enfoque hizo visibles y que **no son del ámbito de la IA**. Salieron de la épica y
tienen dueño:

1. **Reglas de negocio ausentes en los handlers** → **#121**. `CreateUserCommandHandler` no comprueba la
   longitud mínima de la contraseña (vive solo en `CreateUserValidator`) ni la unicidad del email. Una
   tool que creara usuarios tendría que reimplementarlas, que es el error de 3.1. Van al handler. La
   consistencia de las reglas del alta en el resto de vías (`app:create-user`) es #118.
2. **`UserRole` sigue siendo un enum puro, y respaldarlo se rechazó por producto.** Un enum respaldado
   tiene un solo valor por caso, así que ese literal ataría a la vez lo que guarda la columna JSONB y
   lo que consume el front, y los mapas que parecían duplicación son los puntos de traducción de cada
   frontera (motivo completo en #110). Lo que sí era un problema real y no depende de respaldar el
   enum: `RoleInputTransformer::toUserRole()` (y `DBALUserRepository`) **degradan a `UserRole::User` en
   silencio** ante un rol desconocido, y hoy solo lo tapa el `Choice` del validador. Se corrige
   **manteniendo los mapas y haciendo que fallen** en lugar de degradar. Esa corrección no tiene issue
   propia todavía.
3. **Un permiso denegado responde 500** → **#122**. `SecurityException` extiende `\Exception`
   directamente, fuera de la jerarquía compartida, y por eso no casa con ningún traductor ni con ninguna
   rama del `ExceptionListener`. Mientras siga así, un permiso denegado en el handler llega al modelo
   como el genérico opaco y al cliente HTTP como 500.
4. **Los tests de tools pasan por un `Toolbox` real**: resuelto (sección 2.7).

---

## 9. Recorrido de la épica

Se conserva porque las tres versiones proponen arquitecturas distintas y conviene que quede escrito por
qué se descartaron las dos primeras.

- **v1 — cliente HTTP propio (julio 2026, archivada).** Una abstracción propia (`AIProviderInterface` +
  `AnthropicProvider` escrito a mano) y fuera de alcance embeddings, RAG, tools y agentes. **Se descartó**
  porque, al decidir migrar MD Knowledge desde Flowise a Symfony AI, habría dejado dos pilas de IA
  desconectadas en el mismo Base Project, cada una con su gestión de errores y su política RGPD.
- **v2 — Symfony AI como motor (2026-09-10).** Sustituye el motor propio por `symfony/ai-platform` +
  `symfony/ai-agent`. La primitiva es el `Agent` del paquete: sin `Toolbox` es una generación simple,
  con `Toolbox` un flujo agéntico. Cambiar de proveedor o de modelo pasa a ser configuración.
- **PoC `user-management-agent` (2026-09-11).** Fork del Base Project con un asistente en el panel de
  administración con cuatro tools sobre usuarios (repositorio
  `ManagementDrives/developers/playground/user-management-agent`, commit `273f4b2`). Validó la
  integración y, sobre todo, **que las tools pueden delegar en la capa Application sin reimplementar
  nada**: la capa Application ya es la superficie de API del agente. Dejó fuera `AgentDispatcher`,
  `Purpose`, `Usage`, salida estructurada y streaming.
- **v3 — el modelo de capas (2026-09-24).** La v2 describía *qué* se construye pero no *dónde va cada
  responsabilidad dentro de una tool*. Añadió el modelo de capas de validación (sección 3), las piezas
  del paquete que la PoC no aprovechó, el actor explícito en lugar del `RequestStack` y un primer paso
  reducido.
- **Implementación (épica 7).** El esqueleto, las dos formas de uso con ejemplo, el endpoint de chat y
  esta guía. En el camino cambiaron varias decisiones del diseño de la v3, y este documento recoge las
  vigentes: el actor lo puebla el adaptador del puerto y no el controlador, los tests usan un
  `ScriptedPlatform` sobre la plataforma real en lugar de un `MockAgent`, y los prompts viven junto a
  cada agente en `src/Infrastructure/AI/Agent/<Agente>/prompt.md`.

| | v1 | v2 | v3 / vigente |
|---|---|---|---|
| Motor de bajo nivel | propio (`AnthropicProvider`) | `symfony/ai-platform` | igual que v2 |
| Primitiva | `AIProviderInterface::generate()` | `AgentInterface::call()` | igual que v2 |
| Tools / agentes | fuera de alcance | en alcance | en alcance, con el modelo de capas |
| Enrutado por propósito | `AIProviderDispatcher` | `AgentDispatcher` | **aplazado** hasta tener 2 consumidores |
| Auditoría `Usage` | en el primer paso | en el primer paso | **aplazada** al mismo momento |
| Identidad del invocante | no contemplada | no contemplada | `AgentContext` explícito |

---

## 10. Referencias

- Épica en GitLab: [epic 7](https://gitlab.com/groups/ManagementDrives/developers/base-projects/-/epics/7)
- PoC: `ManagementDrives/developers/playground/user-management-agent`, commit `273f4b2`, y su
  `backend/specs/docs/AI_AGENT.md`
- Patrón espejo en este repositorio: `src/Domain/OTP/` y `src/Infrastructure/OTP/` (interfaz de
  dominio + value objects + dispatcher + wiring por tags)
- [Symfony AI — Agent Component](https://symfony.com/doc/current/ai/components/agent.html)
- [Symfony AI — AI Bundle](https://symfony.com/doc/current/ai/bundles/ai-bundle.html)
- [Symfony AI — Platform Component](https://symfony.com/doc/current/ai/components/platform.html)
