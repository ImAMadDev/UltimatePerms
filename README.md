# UltimatePerms

**An ultimate Permission Management plugin for PocketMine-MP**

![Version](https://img.shields.io/badge/version-1.0-blue)
![API](https://img.shields.io/badge/api-5.0.0%2B-brightgreen)

## Tabla de Contenidos

- [Características](#características)
- [Instalación](#instalación)
- [Configuración](#configuración)
- [Comandos](#comandos)
- [Sistema de Permisos](#sistema-de-permisos)
- [Grupos y Rangos](#grupos-y-rangos)
- [Almacenamiento](#almacenamiento)
- [API Pública](#api-pública)
- [Wiki Completa](https://github.com/ImAMadDev/UltimatePerms/wiki)

## Características

✅ **Sistema de Permisos Contextual** — Permisos que varían según el mundo o gamemode  
✅ **Grupos con Jerarquía** — Herencia lineal con detección de ciclos  
✅ **Rangos Temporales** — Expiración automática de permisos y rangos  
✅ **Múltiples Rangos por Jugador** — Combinación de varios grupos  
✅ **Chat Formatter Personalizado** — Prefijos, sufijos y colores dinámicos  
✅ **4 Backends de Almacenamiento** — YAML, JSON, SQLite, MySQL  
✅ **Eventos de Expiración** — `RankExpireEvent`, `PermissionExpireEvent`, `GroupPermExpireEvent`  
✅ **Wildcard Support** — Permisos con comodines (`essentials.*`) y negación específica  
✅ **Metadata Personalizada** — Key-value libre para extensibilidad  
✅ **API Pública** — Integración fácil con otros plugins  

## Instalación

### Requisitos

- **PocketMine-MP** 5.0.0 o superior
- **PHP 8.1+**

### Pasos

1. Copiar la carpeta del plugin a `plugins/`
2. Iniciar el servidor — el plugin creará `config.yml` automáticamente
3. Verificar instalación ejecutando `/uperms`

## Configuración

### config.yml

```yaml
# ── Chat & Display ────────────────────────────────────────────────────
# Placeholders: {prefix} {suffix} {name} {displayname}
#               {message} {group} {group_display} {chat_color}

chat:
  enabled: true
  format: "{prefix}{name}{suffix}§r: {chat_color}{message}"

nametag:
  enabled: true
  format: "{prefix}{name}{suffix}"

displayName:
  enabled: true

locale: en_US

# ── Storage ──────────────────────────────────────────────────────────
storage:
  # Opciones: yaml | json | sqlite | mysql
  type: json

  # Solo necesario si type: mysql
  mysql:
    host: 127.0.0.1
    port: 3306
    user: root
    password: ""
    database: ultimateperms
```

### Elegir Backend de Almacenamiento

**JSON (por defecto)**
```yaml
storage:
  type: json
```

**MySQL (recomendado para servidores grandes)**
```yaml
storage:
  type: mysql
  mysql:
    host: tu-servidor.com
    port: 3306
    user: ultimateperms
    password: tu-contraseña
    database: ultimateperms
```

## Comandos

### Comando Principal: `/uperms` · alias: `/up`, `/perms`

#### Usuarios (`ultimateperms.command.user`)

```bash
# Información del jugador
/uperms user <jugador> info

# Gestión de rangos
/uperms user <jugador> group set    <grupo> [duración]  # Reemplaza todos los rangos
/uperms user <jugador> group add    <grupo> [duración]  # Agrega rango adicional
/uperms user <jugador> group remove <grupo>             # Quita un rango

# Permisos personales (overrides)
/uperms user <jugador> perm set   <nodo> [true|false] [duración] [ctx=valor...]
/uperms user <jugador> perm unset <nodo>
/uperms user <jugador> perm check <nodo>

# Auditoría de permisos resueltos
/uperms user <jugador> audit [página]

# Prefix / Suffix personal
/uperms user <jugador> prefix set   <texto>
/uperms user <jugador> prefix unset
/uperms user <jugador> prefix get
/uperms user <jugador> suffix set   <texto>
/uperms user <jugador> suffix unset
/uperms user <jugador> suffix get

# Metadata
/uperms user <jugador> meta set   <clave> <valor>
/uperms user <jugador> meta unset <clave>
/uperms user <jugador> meta get   <clave>
```

#### Grupos (`ultimateperms.command.group`)

```bash
# Listar todos los grupos (ordenados por weight)
/uperms group list

# Crear / info / eliminar / clonar
/uperms group <nombre> create    [displayName] [weight]
/uperms group <nombre> info
/uperms group <nombre> delete    [confirm]
/uperms group <nombre> clone     <nuevoId> [displayName]

# Permisos del grupo
/uperms group <nombre> perm set   <nodo> [true|false] [duración] [ctx=valor...]
/uperms group <nombre> perm unset <nodo>
/uperms group <nombre> perm list  [página]

# Herencia (un solo padre por grupo)
/uperms group <nombre> parent set   <grupoPadre>
/uperms group <nombre> parent unset

# Display
/uperms group <nombre> setweight <número>
/uperms group <nombre> setprefix <texto>
/uperms group <nombre> setsuffix <texto>

# Metadata
/uperms group <nombre> meta set   <clave> <valor>
/uperms group <nombre> meta unset <clave>
/uperms group <nombre> meta get   <clave>
```

#### Diagnóstico

```bash
# Verificar origen de un permiso con trazabilidad
/uperms check    <jugador> <permiso>

# Simular permiso con contexto personalizado (sin aplicar)
/uperms simulate <jugador> <permiso> [ctx=valor...]
# alias: /uperms sim

# Listar permisos registrados por un plugin
/uperms inspect <nombrePlugin>

# Recargar config.yml y recalcular permisos de todos los jugadores online
/uperms reload

# Abrir interfaz gráfica (experimental)
/uperms ui
```

### Formato de Duración

| Sufijo | Unidad | Ejemplo |
|--------|--------|---------|
| `Y`    | Años   | `1Y`    |
| `M`    | Meses  | `2M`    |
| `w`    | Semanas| `3w`    |
| `d`    | Días   | `7d`    |
| `h`    | Horas  | `24h`   |
| `m`    | Minutos| `30m`   |
| `s`    | Segundos| `3600s`|

Se pueden combinar: `1d12h` = 36 horas. Omitir duración = permanente.

### Ejemplos Prácticos

```bash
# Crear rango YouTuber
/uperms group youtuber create "§c§lYouTuber" 65

# Establecer herencia
/uperms group youtuber parent set vip

# Agregar permisos
/uperms group youtuber perm set essentials.fly true

# Prohibir fly solo en PvP
/uperms group youtuber perm set essentials.fly false world=pvp

# Asignar a jugador permanente (reemplaza rangos anteriores)
/uperms user John group set youtuber

# Asignar temporalmente 7 días (sin reemplazar)
/uperms user John group add youtuber 7d

# Verificar origen de un permiso
/uperms check John essentials.fly

# Simular en otro contexto
/uperms simulate John essentials.fly world=pvp
```

## Sistema de Permisos

### Algoritmo de Resolución

El plugin resuelve permisos en este orden de prioridad:

1. **Overrides Personales** (máxima prioridad) — permisos asignados directamente al jugador; las negaciones explícitas siempre prevalecen
2. **Grupos del Jugador** (ordenados por weight DESC) — se procesan del más pesado al menos pesado
3. **Herencia de Grupos** — cada grupo puede heredar de un único padre
4. **Wildcard Support** — `essentials.*` cubre `essentials.fly`, `essentials.nick`, etc.
5. **Default: Denegado** — si un permiso no está definido en ningún nivel, se deniega

### Contextos

Los permisos pueden condicionarse a contextos. Los contextos disponibles son `world` y `gamemode`, calculados automáticamente cuando el jugador cambia de mundo o modo de juego.

```bash
# Permiso global (sin contexto = siempre aplica)
/uperms group vip perm set essentials.fly true

# Solo en mundo específico
/uperms group vip perm set essentials.fly true world=creative

# Solo en gamemode específico
/uperms group vip perm set essentials.nick true gamemode=survival

# Negación en contexto (sobreescribe herencia)
/uperms group vip perm set essentials.fly false world=pvp

# Múltiples contextos en un mismo nodo
/uperms user John perm set mycommand.use true world=spawn gamemode=survival
```

### Wildcards

```bash
# Otorgar todos los permisos bajo essentials
/uperms group admin perm set essentials.* true

# Negar uno específico dentro del wildcard
/uperms group admin perm set essentials.ban false

# Resultado: tiene todo de essentials EXCEPTO essentials.ban
```

## Grupos y Rangos

### Crear Grupo

```bash
/uperms group youtuber create "§c§lYouTuber" 65
```

**Parámetros:**
- `youtuber` — ID único e inmutable del grupo (lowercase)
- `"§c§lYouTuber"` — Nombre mostrado (soporta códigos de color `§`)
- `65` — Weight: mayor = más prioritario en resolución y para el prefijo visible

### Jerarquía

Cada grupo puede tener **un único padre**. La herencia es lineal y el plugin detecta y bloquea ciclos automáticamente:

```
default (weight=0)
  ↑
vip (weight=30)
  ↑
youtuber (weight=65)
  ↑
admin (weight=100)
```

```bash
# Establecer padre
/uperms group youtuber parent set vip

# Quitar herencia
/uperms group youtuber parent unset
```

**Protecciones automáticas:**
- Ciclos de herencia (A→B→C→A) — bloqueados
- Auto-herencia — bloqueada
- Padres inexistentes — bloqueados; al cargar, el padre se ignora con una advertencia en log

### Multi-Rango

Un jugador puede tener varios rangos simultáneamente:

```bash
/uperms user John group set vip           # Asigna vip, elimina otros rangos
/uperms user John group add helper 7d     # Agrega helper temporal, mantiene vip

# Permisos = unión de todos los grupos + overrides personales
# Prefijo = del grupo con mayor weight
```

### Display

```bash
/uperms group youtuber setprefix "§7[§c§lYT§7] §f"
/uperms group youtuber setsuffix " §c♦"

# Override personal para un jugador específico
/uperms user John prefix set "§b[CUSTOM] "
/uperms user John suffix set " §b⭐"

# Volver al prefijo del grupo
/uperms user John prefix unset
```

### Chat formatter — Placeholders

| Placeholder      | Valor                                       |
|------------------|---------------------------------------------|
| `{prefix}`       | Prefijo resuelto (personal > grupo primario > herencia) |
| `{suffix}`       | Sufijo resuelto (misma lógica)              |
| `{name}`         | Nombre del jugador                          |
| `{displayname}`  | Display name actual (nametag)               |
| `{message}`      | Mensaje del chat                            |
| `{group}`        | ID del grupo primario                       |
| `{group_display}`| DisplayName del grupo primario              |
| `{chat_color}`   | Meta `chat-color` (personal > grupo)        |

## Almacenamiento

### Tipos Disponibles

| Tipo     | Descripción                                | Recomendado para   |
|----------|--------------------------------------------|--------------------|
| `json`   | Un archivo JSON por jugador, grupos en un archivo separado | Inicio, desarrollo |
| `yaml`   | Equivalente en formato YAML                | Desarrollo          |
| `sqlite` | Base de datos SQLite local                 | Servidores medianos |
| `mysql`  | Base de datos MySQL remota                 | Servidores grandes  |

**Setup MySQL:**

```sql
CREATE DATABASE ultimateperms;
CREATE USER 'ultimateperms'@'localhost' IDENTIFIED BY 'contraseña';
GRANT ALL PRIVILEGES ON ultimateperms.* TO 'ultimateperms'@'localhost';
FLUSH PRIVILEGES;
```

## API Pública

Otros plugins pueden acceder a UltimatePerms declarando la dependencia en `plugin.yml`:

```yaml
depend:
  - UltimatePerms
```

### Obtener Instancias

```php
use appgallery\uperms\Loader;

$loader         = Loader::getInstance();
$sessionManager = $loader->getSessionManager();
$groupManager   = $loader->getGroupManager();
$storage        = $loader->getStorage();
```

### Consultar Sesión de Jugador

> **Nota:** Los métodos de sesión solo funcionan con jugadores **online**.

```php
// Por nombre de jugador
$session = $sessionManager->getByName("PlayerName");

// Por XUID
$session = $sessionManager->get($player->getXuid());

if ($session !== null) {
    // Verificar permiso (usa el caché resuelto)
    $session->hasPermission("essentials.fly");

    // Obtener rangos: [groupId => expiresAt|null]
    $groups = $session->getGroups();

    // Rango primario (mayor weight)
    $primary = $sessionManager->getPrimaryGroup($session); // Group|null

    // Prefix / suffix actuales
    $prefix = $session->getPrefix();
    $suffix = $session->getSuffix();

    // Metadata
    $value = $session->getMeta("max-homes");

    // Contexto actual
    $context = $session->getContext(); // ["world" => "...", "gamemode" => "..."]

    // Todos los permisos resueltos
    $all = $session->getResolvedPermissions(); // [permission => bool]
}
```

### Gestión de Rangos (vía SessionManager)

```php
// Asignar rango — elimina todos los rangos anteriores excepto el default
$sessionManager->setGroup($player, "vip");

// Asignar temporal
$expiresAt = time() + 7 * 86400; // 7 días
$sessionManager->setGroup($player, "vip", $expiresAt);

// Agregar rango sin reemplazar otros
$sessionManager->addGroup($player, "event-bonus", $expiresAt);

// Quitar rango (si el jugador se queda sin rangos, se asigna el grupo default)
$sessionManager->removeGroup($player, "event-bonus");
```

### Gestión de Permisos Personales

```php
use appgallery\uperms\permission\node\Node;

// Permiso permanente
$node = new Node(permission: "essentials.fly", state: true);
$sessionManager->addNode($player, $node);

// Permiso temporal con contexto
$node = new Node(
    permission: "mycommand.use",
    state: true,
    expiresAt: time() + 86400, // 1 día
    context: ["world" => "spawn"],
);
$sessionManager->addNode($player, $node);

// Quitar override personal
$sessionManager->removeNode($player, "essentials.fly");
```

### Consultar Grupos

```php
$group = $groupManager->get("youtuber"); // Group|null

if ($group !== null) {
    $group->getId();          // "youtuber"
    $group->getDisplayName(); // "§c§lYouTuber"
    $group->getWeight();      // 65
    $group->getParent();      // "vip" | null
    $group->getPrefix();      // "§7[§c§lYT§7] §f"
    $group->getSuffix();      // " §c♦"
    $group->getMetaValue("max-homes"); // string|null
    $group->getNodes();       // Node[]
}

// Grupo por defecto
$default = $groupManager->getDefault(); // siempre existe
```

### Eventos de Expiración

| Clase | Cuándo se dispara |
|---|---|
| `appgallery\uperms\event\GroupExpireEvent` | Un rango temporal de un jugador expira |
| `appgallery\uperms\event\PermissionExpireEvent` | Un nodo personal temporal de un jugador expira |
| `appgallery\uperms\event\GroupPermExpireEvent` | Un nodo temporal de un grupo expira |

```php
use appgallery\uperms\event\GroupExpireEvent;
use appgallery\uperms\event\PermissionExpireEvent;
use appgallery\uperms\event\GroupPermExpireEvent;
use pocketmine\event\Listener;

class MyListener implements Listener {

    // Cuando expira el rango de un jugador
    public function onGroupExpire(GroupExpireEvent $event): void {
        $player  = $event->getPlayer();
        $groupId = $event->getGroupId();
        $player->sendMessage("§cTu rango $groupId ha expirado.");
    }

    // Cuando expira un permiso personal de un jugador
    public function onPermExpire(PermissionExpireEvent $event): void {
        $player     = $event->getPlayer();
        $node       = $event->getNode();      // Node
        $permission = $event->getPermission(); // string
    }

    // Cuando expira un nodo temporal en un grupo
    public function onGroupPermExpire(GroupPermExpireEvent $event): void {
        $group      = $event->getGroup();      // Group
        $node       = $event->getNode();       // Node
        $permission = $event->getPermission(); // string
    }
}
```

## Permisos de Plugin

Todos los permisos tienen `default: op`.

| Nodo | Descripción |
|------|-------------|
| `ultimateperms.admin` | Acceso total al plugin |
| `ultimateperms.notify.expire` | Recibir notificaciones cuando expiran permisos o rangos |
| `ultimateperms.command` | Acceso a la ayuda general |
| `ultimateperms.command.check` | Verificar permisos de jugadores |
| `ultimateperms.command.inspect` | Inspeccionar permisos de plugins |
| `ultimateperms.command.reload` | Recargar configuración |
| `ultimateperms.command.simulate` | Simular permisos con contexto |
| `ultimateperms.command.ui` | Interfaz gráfica (experimental) |
| `ultimateperms.command.group` | Comandos de gestión de grupos |
| `ultimateperms.command.group.create` | Crear grupos |
| `ultimateperms.command.group.delete` | Eliminar grupos |
| `ultimateperms.command.group.info` | Ver información de grupos |
| `ultimateperms.command.group.edit` | Editar grupos |
| `ultimateperms.command.user` | Comandos de gestión de usuarios |
| `ultimateperms.command.user.audit` | Ver auditoría de permisos |
| `ultimateperms.command.user.info` | Ver información de jugadores |
| `ultimateperms.command.user.perm` | Gestionar overrides personales |
| `ultimateperms.command.user.meta` | Gestionar metadata de jugadores |
| `ultimateperms.command.user.group` | Asignar/quitar rangos a jugadores |

---

**Desarrollado por AppGallery**
