# API Pública para Otros Plugins

> Documentación de la API pública de UltimatePerms basada en el código fuente.

## Índice

1. [Dependencia](#dependencia)
2. [Acceso a Instancias](#acceso-a-instancias)
3. [SessionManager](#sessionmanager)
4. [PlayerSession](#playersession)
5. [GroupManager](#groupmanager)
6. [Group](#group)
7. [Nodos de Permiso (Node)](#nodos-de-permiso-node)
8. [PermissionResolver](#permissionresolver)
9. [Eventos de Expiración](#eventos-de-expiración)
10. [Ejemplos Prácticos](#ejemplos-prácticos)

---

## Dependencia

En tu `plugin.yml`:

```yaml
depend:
  - UltimatePerms
```

---

## Acceso a Instancias

```php
use appgallery\uperms\Loader;
use appgallery\uperms\player\SessionManager;
use appgallery\uperms\group\GroupManager;

$loader         = Loader::getInstance();
$sessionManager = $loader->getSessionManager();  // o SessionManager::getInstance()
$groupManager   = $loader->getGroupManager();
$storage        = $loader->getStorage();          // IStorage (sincrónico)
```

---

## SessionManager

`appgallery\uperms\player\SessionManager`

> **Nota:** Los métodos que reciben `Player` o retornan `PlayerSession` solo funcionan cuando el jugador está **online**.

### Obtener Sesiones

```php
// Por XUID (string)
$session = $sessionManager->get($player->getXuid()); // ?PlayerSession

// Por nombre (case-insensitive)
$session = $sessionManager->getByName("Steve");      // ?PlayerSession

// Todas las sesiones activas
$all = $sessionManager->getAll(); // PlayerSession[]

// ¿Está online?
$online = $sessionManager->isOnline($player->getXuid()); // bool

// Cantidad de sesiones activas
$count = $sessionManager->count(); // int
```

### Grupo Primario

```php
// El grupo con mayor weight entre los rangos activos del jugador
$primary = $sessionManager->getPrimaryGroup($session); // Group|null
```

### Operaciones de Rango

```php
// set: elimina todos los rangos excepto el default, asigna el nuevo
$sessionManager->setGroup($player, "vip");
$sessionManager->setGroup($player, "vip", time() + 7 * 86400); // temporal

// add: agrega sin reemplazar
$sessionManager->addGroup($player, "event-bonus", time() + 86400);

// remove: si el jugador se queda sin rangos, se asigna el default
$sessionManager->removeGroup($player, "event-bonus");
```

Estos métodos persisten los datos de forma **sincrónica** y recalculan los permisos automáticamente.

### Operaciones de Nodos Personales

```php
use appgallery\uperms\permission\node\Node;

$node = new Node(
    permission: "essentials.fly",
    state: true,
    expiresAt: null,  // null = permanente
    context: [],       // [] = global
);

$sessionManager->addNode($player, $node);
$sessionManager->removeNode($player, "essentials.fly");
```

### Refresh Manual

```php
$sessionManager->refresh($session);           // Recalcula una sesión
$sessionManager->refreshByGroup("vip");       // Recalcula todas las sesiones con ese grupo
$sessionManager->refreshAll();                // Recalcula todas las sesiones activas
$sessionManager->refreshDisplayName($session);// Recalcula solo el display name
```

---

## PlayerSession

`appgallery\uperms\player\PlayerSession`

### Rangos

```php
$session->getGroups();                // array<string, int|null>  groupId => expiresAt|null
$session->hasGroup("vip");            // bool
$session->addGroup("vip");            // Agrega (también marca dirty y recalcula)
$session->addGroup("vip", $expires);  // Agrega temporal
$session->removeGroup("vip");         // Quita
$session->setGroups($newGroups);      // Reemplaza todos los rangos de una vez

$session->flushExpiredGroups();       // Limpia expirados, retorna string[] de IDs expirados
```

### Nodos Personales

```php
$session->getPersonalNodes();             // Node[]
$session->getPersonalNode("essentials.fly"); // ?Node
$session->addNode($node);                 // Agrega/reemplaza override (recalcula)
$session->removeNode("essentials.fly");   // Quita override (recalcula)
$session->setPersonalNodes($nodes);       // Reemplaza todos de una vez

$session->flushExpiredNodes();            // Limpia expirados, retorna Node[]
```

### Permisos Resueltos

```php
// Verificar un permiso en el caché resuelto (rápido, sin cálculo)
$session->hasPermission("essentials.fly"); // bool

// Obtener mapa completo [permission => bool]
$session->getResolvedPermissions();        // array<string, bool>

// Aplicar un mapa ya resuelto externamente
$session->applyResolved($resolvedFromGroups); // recalcula y pushea al attachment
```

### Contexto

```php
$session->getContext();                    // array<string, string>
$session->setContext(["world" => "pvp"]); // Reemplaza todo y recalcula
$session->updateContext("world", "pvp");  // Actualiza una clave y recalcula si cambió
$session->removeContext("world");         // Elimina clave y recalcula
```

### Prefix / Suffix

```php
$session->getPrefix();             // string ('' si no tiene override personal)
$session->setPrefix("§b[VIP] ");  // Override personal
$session->getSuffix();
$session->setSuffix(" §b⭐");
```

> El prefijo/sufijo **visible** en el chat se resuelve en `UPChatFormatter::resolvePrefix()` con prioridad:
> override personal → grupo primario → herencia de grupo.

### Metadata

```php
$session->getMeta("max-homes");          // ?string
$session->getAllMeta();                   // array<string, string>
$session->setMeta("max-homes", "10");    // Establece (marca dirty)
$session->unsetMeta("max-homes");        // Elimina (marca dirty)
$session->setMetaMap($metaArray);        // Reemplaza todo
```

### Identificadores

```php
$session->getPlayer();    // Player
$session->getXuid();      // string
$session->getUsername();  // string

$session->isDirty();      // bool (hay cambios no persistidos)
$session->markClean();    // Marca como persistido
```

---

## GroupManager

`appgallery\uperms\group\GroupManager`

```php
// Obtener grupo por ID (null si no existe)
$group = $groupManager->get("vip");         // ?Group

// Grupo default (siempre existe)
$default = $groupManager->getDefault();     // Group

// Todos los IDs de grupos
$ids = $groupManager->getAllIds();          // string[]

// IDs de todos los grupos incluyendo el registry
$groupManager->getRegistry();              // GroupRegistry

// ID del grupo por defecto
$groupManager->getDefaultGroupId();        // "default"

// Crear grupo (retorna null si ya existe)
$group = $groupManager->create("moderator", "§6[Mod]", 50); // ?Group

// Eliminar grupo (false si no existe o es el default)
$deleted = $groupManager->delete("moderator"); // bool

// Clonar grupo (retorna null si el origen no existe o el destino ya existe)
$cloned = $groupManager->clone("vip", "vip-event", "§b[VIP Event]"); // ?Group

// Persistir un grupo
$groupManager->save($group);

// Persistir todos los grupos
$groupManager->saveAll();
```

---

## Group

`appgallery\uperms\group\Group`

### Identidad

```php
$group->getId();                          // string (inmutable)
$group->getDisplayName();                 // string
$group->setDisplayName("§c[NEW]");
$group->getWeight();                      // int
$group->setWeight(100);
```

### Herencia

```php
$group->getParent();                      // ?string (ID del padre, o null)
$group->setParent("vip");                 // Establece padre
$group->setParent(null);                  // Quita padre
$group->isParent("vip");                  // bool (¿el padre es "vip"?)
```

### Nodos

```php
$group->getNodes();                       // Node[] (todos, incluyendo expirados)
$group->getActiveNodes($context);         // Node[] (activos y que aplican en contexto)
$group->getNode("essentials.fly");        // ?Node
$group->addNode($node);                   // Agrega o reemplaza nodo con mismo permission
$group->removeNode("essentials.fly");     // Quita por permission string
$group->flushExpiredNodes();              // Limpia expirados, retorna Node[]
```

### Prefix / Suffix

```php
$group->getPrefix();                      // string
$group->setPrefix("§b[VIP] ");
$group->getSuffix();
$group->setSuffix(" §b⭐");
```

### Metadata

```php
$group->getMeta();                        // array<string, string>
$group->getMetaValue("chat-color");       // ?string
$group->setMetaValue("chat-color", "§b");
$group->unsetMetaValue("chat-color");
```

### Serialización

```php
$data  = $group->serialize();            // array (para storage)
$group = Group::deserialize($data);      // Group (desde storage)
```

---

## Nodos de Permiso (Node)

`appgallery\uperms\permission\node\Node`

### Constructor

```php
use appgallery\uperms\permission\node\Node;

$node = new Node(
    permission: "essentials.fly",   // string
    state: true,                     // bool (true=otorgado, false=negado)
    expiresAt: null,                 // ?int Unix timestamp, null=permanente
    context: [],                     // array<string,string> [] = global
);
```

### Métodos

```php
$node->getPermission();     // string
$node->getState();          // bool
$node->getExpiresAt();      // ?int

$node->isPermanent();       // bool (expiresAt === null)
$node->isExpired();         // bool (expiresAt !== null && time() > expiresAt)
$node->isActive();          // bool (!isExpired())
$node->isGlobal();          // bool (context vacío)
$node->isWildcard();        // bool (termina en .* o es *)

// ¿Aplica en este contexto?
$node->appliesIn(["world" => "pvp"]);   // bool
// Un nodo global siempre aplica. Un nodo con contexto aplica si cumple TODOS los pares.

// ¿Cubre este permiso? (soporta wildcards)
$node->covers("essentials.fly");        // bool
```

### Ejemplos de construcción

```php
// Permiso permanente global
$node = new Node("essentials.fly", true);

// Permiso temporal
$node = new Node("essentials.kit.vip", true, time() + 30 * 86400);

// Permiso solo en mundo spawn
$node = new Node("mycommand.use", true, null, ["world" => "spawn"]);

// Negación explícita en PvP
$node = new Node("essentials.fly", false, null, ["world" => "pvp"]);

// Wildcard
$node = new Node("pocketmine.command.*", true);
```

---

## PermissionResolver

`appgallery\uperms\permission\PermissionResolver`

```php
use appgallery\uperms\permission\PermissionResolver;
use appgallery\uperms\group\GroupRegistry;

$resolver = new PermissionResolver();
$registry = $groupManager->getRegistry(); // GroupRegistry
```

### Resolver Permisos de Grupos

```php
// Retorna mapa plano [permission => bool] combinando todos los grupos con herencia
$groupIds = array_keys($session->getGroups());
$groups   = array_filter(
    array_map(fn($id) => $groupManager->get($id), $groupIds)
);

$context       = $session->getContext();
$groupResolved = $resolver->resolveGroups($groups, $registry, $context);
// array<string, bool>
```

Orden de resolución: grupos ordenados por weight DESC; dentro de cada grupo, la cadena de herencia (grupo, padre, abuelo…). Las negaciones explícitas siempre tienen máxima prioridad.

### Combinar con Overrides Personales

```php
$final = $resolver->buildFinal(
    $groupResolved,
    $session->getPersonalNodes(),
    $context
);
// array<string, bool>  — los overrides personales sobreescriben el mapa de grupos
```

### Tracing — Origen de un Permiso

```php
use appgallery\uperms\permission\PermissionTrace;

$trace = $resolver->trace(
    permission:    "essentials.fly",
    personalNodes: $session->getPersonalNodes(),
    groups:        $groups,           // Group[]
    registry:      $registry,
    context:       $session->getContext(),
);

// Resultado
$trace->getState();   // bool
$trace->getSource();  // SOURCE_PERSONAL | SOURCE_GROUP | SOURCE_INHERITED | SOURCE_DEFAULT
$trace->getVia();     // ?string (ID del grupo que lo aporta)
$trace->getNode();    // ?Node
$trace->describe();   // string (descripción legible, usada por /uperms check)

// Constantes de fuente
PermissionTrace::SOURCE_PERSONAL;   // override personal del jugador
PermissionTrace::SOURCE_GROUP;      // nodo directo del grupo
PermissionTrace::SOURCE_INHERITED;  // nodo heredado de un grupo padre
PermissionTrace::SOURCE_DEFAULT;    // no definido → false por defecto
```

---

## Eventos de Expiración

El plugin dispara eventos **no cancelables** cuando algo expira. El intervalo de verificación es configurable en `config.yml` (`expiration-check-interval`, en segundos).

### Clases de Evento

| Clase | Namespace | Descripción |
|-------|-----------|-------------|
| `GroupExpireEvent` | `appgallery\uperms\event` | Un rango temporal de un jugador expiró |
| `PermissionExpireEvent` | `appgallery\uperms\event` | Un nodo personal temporal de un jugador expiró |
| `GroupPermExpireEvent` | `appgallery\uperms\event` | Un nodo temporal de un grupo expiró |

### API de los Eventos

```php
// GroupExpireEvent
$event->getPlayer();    // Player
$event->getGroupId();   // string

// PermissionExpireEvent
$event->getPlayer();    // Player
$event->getNode();      // Node
$event->getPermission(); // string

// GroupPermExpireEvent
$event->getGroup();     // Group
$event->getNode();      // Node
$event->getPermission(); // string
```

### Registrar Listeners

```php
use appgallery\uperms\event\GroupExpireEvent;
use appgallery\uperms\event\PermissionExpireEvent;
use appgallery\uperms\event\GroupPermExpireEvent;
use pocketmine\event\Listener;

class MyListener implements Listener {

    public function onRankExpire(GroupExpireEvent $event): void {
        $player  = $event->getPlayer();
        $groupId = $event->getGroupId();
        $player->sendMessage("§cTu rango $groupId ha expirado.");
    }

    public function onPermExpire(PermissionExpireEvent $event): void {
        $player = $event->getPlayer();
        $node   = $event->getNode();
        $player->sendMessage("§cTu permiso {$node->getPermission()} ha expirado.");
    }

    public function onGroupPermExpire(GroupPermExpireEvent $event): void {
        $group = $event->getGroup();
        $node  = $event->getNode();
        // Notificar admins, loggear en BD, etc.
    }
}
```

> **Nota sobre notificaciones integradas:** el plugin ya notifica al jugador cuando un rango o permiso personal expira (mensajes configurables en el locale). Los admins con el permiso `ultimateperms.notify.expire` reciben la notificación de expiración de nodos de grupo.

---

## Ejemplos Prácticos

### Ejemplo 1: Asignar VIP tras un pago

```php
use appgallery\uperms\Loader;

$sessionManager = Loader::getInstance()->getSessionManager();
$session = $sessionManager->getByName($playerName);

if ($session === null) {
    // Jugador no está online; guardar en BD propia y aplicar al conectarse
    return;
}

// VIP por 30 días
$expiresAt = time() + 30 * 86400;
$sessionManager->addGroup($session->getPlayer(), "vip", $expiresAt);

$session->getPlayer()->sendMessage("§e✓ ¡Eres VIP por 30 días!");
```

---

### Ejemplo 2: Verificar si un jugador tiene un rango

```php
$session = $sessionManager->getByName("Steve");
if ($session === null) {
    return; // No está online
}

if ($session->hasGroup("admin")) {
    // Es admin
}

// Rango primario (mayor weight)
$primary = $sessionManager->getPrimaryGroup($session);
if ($primary !== null) {
    echo "Rango principal: " . $primary->getId();
}
```

---

### Ejemplo 3: Agregar permiso personal temporal con contexto

```php
use appgallery\uperms\permission\node\Node;

$node = new Node(
    permission: "myserver.arena.enter",
    state:      true,
    expiresAt:  time() + 3600, // 1 hora
    context:    ["world" => "arena"],
);

$sessionManager->addNode($player, $node);
```

---

### Ejemplo 4: Crear y configurar un grupo programáticamente

```php
$groupManager = Loader::getInstance()->getGroupManager();

// Crear (retorna null si ya existe)
$group = $groupManager->create("donator", "§6[Donator]", 40);

if ($group !== null) {
    $group->setPrefix("§6[D] §r");
    $group->setParent("default");
    $group->setMetaValue("max-homes", "10");

    // Agregar un nodo de permiso
    $node = new \appgallery\uperms\permission\node\Node("cosmetics.particle.*", true);
    $group->addNode($node);

    $groupManager->save($group);

    // Refrescar jugadores que ya tienen ese grupo
    $sessionManager->refreshByGroup("donator");
}
```

---

### Ejemplo 5: Leer el mapa de permisos resueltos

```php
$session = $sessionManager->getByName("Steve");
if ($session === null) return;

// Forma rápida: caché ya resuelto
$hasFly = $session->hasPermission("essentials.fly");

// Mapa completo
$all = $session->getResolvedPermissions(); // [permission => bool]

foreach ($all as $perm => $state) {
    echo ($state ? "✓" : "✗") . " $perm\n";
}
```

---

## Referencia Rápida de Métodos

### SessionManager

```php
// Obtener sesiones
get(string $xuid): ?PlayerSession
getByName(string $username): ?PlayerSession
getAll(): PlayerSession[]
getPrimaryGroup(PlayerSession $session): ?Group
isOnline(string $xuid): bool
count(): int

// Rangos
setGroup(Player $player, string $groupId, ?int $expiresAt = null): void
addGroup(Player $player, string $groupId, ?int $expiresAt = null): void
removeGroup(Player $player, string $groupId): void

// Nodos
addNode(Player $player, Node $node): void
removeNode(Player $player, string $permission): void

// Refresh
refresh(PlayerSession $session): void
refreshByGroup(string $groupId): void
refreshAll(): void
refreshDisplayName(PlayerSession $session): void

// Contexto
updateContext(Player $player, string $key, string $value): void

// Persistencia
saveAll(): void
```

### PlayerSession

```php
// Rangos
getGroups(): array<string, int|null>
hasGroup(string $groupId): bool
addGroup(string $groupId, ?int $expiresAt = null): void
removeGroup(string $groupId): void
setGroups(array $groups): void
flushExpiredGroups(): string[]

// Nodos personales
getPersonalNodes(): Node[]
getPersonalNode(string $permission): ?Node
addNode(Node $node): void
removeNode(string $permission): void
setPersonalNodes(Node[] $nodes): void
flushExpiredNodes(): Node[]

// Permisos resueltos
hasPermission(string $permission): bool
getResolvedPermissions(): array<string, bool>
applyResolved(array $resolvedFromGroups): void

// Contexto
getContext(): array<string, string>
setContext(array $context): void
updateContext(string $key, string $value): void
removeContext(string $key): void

// Prefix / Suffix
getPrefix(): string
setPrefix(string $prefix): void
getSuffix(): string
setSuffix(string $suffix): void

// Meta
getMeta(string $key): ?string
getAllMeta(): array<string, string>
setMeta(string $key, string $value): void
unsetMeta(string $key): void
setMetaMap(array $meta): void

// Identificadores / Estado
getPlayer(): Player
getXuid(): string
getUsername(): string
isDirty(): bool
markClean(): void
```

### GroupManager

```php
get(string $id): ?Group
getDefault(): Group
getAllIds(): string[]
getDefaultGroupId(): string
getRegistry(): GroupRegistry

create(string $id, string $displayName, int $weight = 0): ?Group
delete(string $id): bool
clone(string $sourceId, string $newId, string $newDisplayName): ?Group
save(Group $group): void
saveAll(): void
```

### Group

```php
// Identidad
getId(): string
getDisplayName(): string
setDisplayName(string $displayName): void
getWeight(): int
setWeight(int $weight): void

// Herencia
getParent(): ?string
setParent(?string $groupId): void
isParent(string $groupId): bool

// Nodos
getNodes(): Node[]
getActiveNodes(array $context = []): Node[]
getNode(string $permission): ?Node
addNode(Node $node): void
removeNode(string $permission): void
flushExpiredNodes(): Node[]

// Display
getPrefix(): string
setPrefix(string $prefix): void
getSuffix(): string
setSuffix(string $suffix): void

// Meta
getMeta(): array<string, string>
getMetaValue(string $key): ?string
setMetaValue(string $key, string $value): void
unsetMetaValue(string $key): void

// Serialización
serialize(): array
deserialize(array $data): static
```
