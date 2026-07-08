# Temas Avanzados

## Arquitectura del Plugin

### Estructura de Carpetas

```
src/appgallery/uperms/
├── Loader.php                     # Punto de entrada (PluginBase)
├── EventListener.php              # PlayerJoinEvent, PlayerQuitEvent,
│                                  # EntityTeleportEvent, PlayerGameModeChangeEvent,
│                                  # PlayerChatEvent
│
├── chat/
│   └── UPChatFormatter.php        # Implementa ChatFormatter de PMMP
│                                  # Resuelve prefix/suffix/chat-color con herencia
│
├── command/
│   ├── SubCommand.php             # Clase base de subcomandos
│   ├── UPermsCommand.php          # Comando /uperms (alias: /up, /perms)
│   └── sub/
│       ├── group/
│       │   └── GroupSub.php       # Router de subcomandos de grupo
│       ├── user/
│       │   └── UserSub.php        # Router de subcomandos de usuario
│       ├── CheckSub.php           # /uperms check
│       ├── InspectSub.php         # /uperms inspect
│       ├── ReloadSub.php          # /uperms reload
│       ├── SimulateSub.php        # /uperms simulate
│       └── UISub.php              # /uperms ui
│
├── group/
│   ├── Group.php                  # Modelo de grupo
│   ├── GroupManager.php           # CRUD + persistencia
│   ├── GroupRegistry.php          # Registro en memoria + detección de ciclos
│   └── GroupSerializer.php        # Serialización de Group a/desde array
│
├── permission/
│   ├── PermissionResolver.php     # Resolución de permisos (grupos + overrides)
│   ├── PermissionTrace.php        # Resultado trazable de resolución
│   └── node/
│       ├── Node.php               # Modelo de nodo de permiso
│       └── NodeRegistry.php       # Carga permisos registrados por plugins
│
├── player/
│   ├── PlayerSession.php          # Estado de jugador online
│   ├── SessionManager.php         # Gestión del ciclo de vida de sesiones
│   ├── SessionFactory.php         # Creación de sesiones desde datos persistidos
│   ├── PlayerSerializer.php       # Serialización de PlayerSession a/desde array
│   ├── ExpirationTask.php         # Task repetitiva que limpia expirados
│   └── context/
│       ├── ContextManager.php     # Registro de calculadores de contexto
│       ├── IContextCalculator.php # Interfaz de calculador de contexto
│       ├── WorldContextCalculator.php
│       └── GamemodeContextCalculator.php
│
├── storage/
│   ├── IStorage.php               # Interfaz sincrónica de storage
│   ├── IAsyncStorage.php          # Interfaz asincrónica de storage
│   ├── StorageFactory.php         # Crea la implementación según config
│   ├── AsyncStorageManager.php    # Implementación asincrónica (wraps IStorage)
│   ├── JsonStorage.php
│   ├── YamlStorage.php
│   ├── SqliteStorage.php
│   └── MysqlStorage.php
│
├── event/
│   ├── RankExpireEvent.php        # Contiene clase GroupExpireEvent
│   ├── PermissionExpireEvent.php
│   └── GroupPermExpireEvent.php
│
├── locale/
│   └── LocaleManager.php          # Carga y resuelve mensajes del locale
│
└── util/
    └── DurationParser.php         # Parsea duraciones: "7d", "1h30m", etc.
```

### Flujo de Datos Principal

```
Jugador conecta
  → EventListener::onJoin()
  → SessionManager::open()
    → Carga datos desde IStorage
    → Construye PlayerSession
    → Calcula contexto inicial (world, gamemode)
    → PermissionResolver::resolveGroups() + buildFinal()
    → Aplica al PermissionAttachment de PMMP

Jugador cambia de mundo
  → EventListener::onWorldChange()
  → SessionManager::updateContext("world", folderName)
    → Si cambió → recalcula permisos
    → UPChatFormatter::applyDisplayName()

Jugador desconecta
  → EventListener::onQuit()
  → SessionManager::close()
    → PlayerSerializer::serialize()
    → IStorage::savePlayer()
    → Libera la sesión de memoria

ExpirationTask (periódica)
  → Recorre todas las sesiones activas
  → Llama flushExpiredGroups() y flushExpiredNodes()
  → Dispara eventos de expiración
  → Persiste cambios y refresca sesiones

Comando /uperms user <j> group set <g>
  → SessionManager::setGroup()
    → session->setGroups([groupId => null])
    → IStorage::savePlayer()
    → SessionManager::refresh()
      → PermissionResolver::resolveGroups()
      → session->applyResolved()
```

---

## Contextos Personalizados

El plugin expone una interfaz para agregar calculadores de contexto propios. Cada calculador proporciona un par `clave => valor` que se añade al contexto del jugador y puede usarse en nodos de permiso.

### Interfaz

```php
namespace appgallery\uperms\player\context;

use pocketmine\player\Player;

interface IContextCalculator {
    /** Retorna la clave del contexto (ej: "time", "season") */
    public function getKey(): string;

    /** Calcula el valor para este jugador. null = no aplica */
    public function getValueFor(Player $player): ?string;
}
```

### Registrar un Calculador Personalizado

```php
use appgallery\uperms\player\context\ContextManager;

class MyPlugin extends \pocketmine\plugin\PluginBase {

    public function onEnable(): void {
        // Registrar antes de que los jugadores se conecten
        ContextManager::getInstance()->register(new TimeContextCalculator());
    }
}
```

### Ejemplo: Contexto por Hora del Día

```php
use appgallery\uperms\player\context\IContextCalculator;
use pocketmine\player\Player;

class TimeContextCalculator implements IContextCalculator {

    public function getKey(): string {
        return 'time';
    }

    public function getValueFor(Player $player): ?string {
        $hour = (int) date('H');

        return match(true) {
            $hour < 12  => 'morning',
            $hour < 18  => 'afternoon',
            default     => 'evening',
        };
    }
}
```

Uso en comandos:

```bash
# Kit disponible solo por la mañana
/uperms group vip perm set essentials.kit.breakfast true time=morning
```

### Ejemplo: Contexto por Nivel de XP

```php
class XpContextCalculator implements IContextCalculator {

    public function getKey(): string {
        return 'xp-tier';
    }

    public function getValueFor(Player $player): ?string {
        $level = $player->getXpLevel();

        return match(true) {
            $level < 10  => 'beginner',
            $level < 50  => 'intermediate',
            default      => 'expert',
        };
    }
}
```

```bash
# Permiso solo para expertos
/uperms group vip perm set myserver.arena.elite true xp-tier=expert
```

---

## Integrar UltimatePerms en Otro Plugin

### Dependencia

```yaml
# plugin.yml de tu plugin
depend:
  - UltimatePerms
```

### Acceso básico

```php
use appgallery\uperms\Loader;
use appgallery\uperms\player\SessionManager;
use appgallery\uperms\group\GroupManager;
use appgallery\uperms\event\GroupExpireEvent;
use pocketmine\event\Listener;

class MyPlugin extends \pocketmine\plugin\PluginBase {

    private SessionManager $sessions;
    private GroupManager   $groups;

    public function onEnable(): void {
        $loader = Loader::getInstance();

        $this->sessions = $loader->getSessionManager();
        $this->groups   = $loader->getGroupManager();

        // Registrar contexto personalizado
        \appgallery\uperms\player\context\ContextManager::getInstance()
            ->register(new MyContextCalculator());

        // Escuchar eventos de expiración
        $this->getServer()->getPluginManager()
            ->registerEvents(new MyListener(), $this);
    }
}

class MyListener implements Listener {

    public function onRankExpire(GroupExpireEvent $event): void {
        $player  = $event->getPlayer();
        $groupId = $event->getGroupId();
        $player->sendMessage("§cTu rango §e$groupId§c ha expirado.");
    }
}
```

---

## Migración desde Otros Plugins de Permisos

No existe un comando de migración automática. El proceso general es:

1. Exportar los datos del plugin anterior (YAML, JSON, BD)
2. Crear los grupos en UltimatePerms con los comandos correspondientes
3. Agregar los permisos a cada grupo
4. Asignar rangos a los jugadores

### Ejemplo: Importar grupos desde un archivo JSON externo

```php
use appgallery\uperms\Loader;
use appgallery\uperms\permission\node\Node;

$groupManager = Loader::getInstance()->getGroupManager();
$data = json_decode(file_get_contents('export.json'), true);

foreach ($data['groups'] as $g) {
    $group = $groupManager->create(
        id:          $g['id'],
        displayName: $g['displayName'] ?? $g['id'],
        weight:      $g['weight'] ?? 0,
    );

    if ($group === null) {
        continue; // Ya existe
    }

    if (!empty($g['parent'])) {
        $group->setParent($g['parent']);
    }

    foreach ($g['permissions'] as $perm => $state) {
        $group->addNode(new Node(
            permission: $perm,
            state:      (bool) $state,
        ));
    }

    $groupManager->save($group);
}
```

> **Importante:** `GroupManager::create()` no registra el grupo en el `GroupRegistry`; usa `create()` que sí lo hace internamente. No instancies `Group` directamente y llames a `save()` — eso omitiría el registro y el grupo no estaría disponible en memoria.

---

## Escalabilidad

| Escenario | Recomendación |
|-----------|---------------|
| < 50 jugadores | `json`, 5-10 grupos |
| 50-500 jugadores | `sqlite`, 10-30 grupos |
| > 500 jugadores | `mysql`, grupos ilimitados |
| Red multi-servidor | `mysql` compartido |

El cuello de botella principal no es el número de grupos sino el número de nodos por grupo y la frecuencia de recálculo de permisos. Usar wildcards (`essentials.*`) en lugar de muchos nodos específicos reduce el tamaño de los mapas resueltos.

---

## AsyncStorageManager

El `Loader` expone `getStorageManager()` que retorna `IAsyncStorage`. Esta interfaz permite operaciones de storage en background (sin bloquear el hilo principal) útiles para cargar datos de jugadores que aún no están en memoria.

```php
$storageManager = Loader::getInstance()->getStorageManager();

// Cargar datos de un jugador en background
$storageManager->loadPlayerAsync($xuid, function(?array $data) {
    if ($data !== null) {
        // Procesar datos
    }
});

// Guardar en background
$storageManager->savePlayerAsync($serializedData, function() {
    // Guardado completado
});
```

> **Nota:** El storage sincrónico (`getStorage()`) es el que usa el `SessionManager` internamente. `AsyncStorageManager` es para operaciones de background desde otros plugins.
