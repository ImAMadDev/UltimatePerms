# Sistema de Rangos y Expiración

## ¿Qué es un Rango?

En UltimatePerms, un **rango** es la asignación de un grupo a un jugador. Los grupos son plantillas globales; los rangos son las instancias por jugador.

```
GRUPO "vip":                      RANGO "vip" en el jugador Steve:
• Permisos globales               • groupId: "vip"
• Prefijo, sufijo, metadata       • expiresAt: 1704715200  ← Unix timestamp
• Compartido por todos los        •            null         ← Permanente
  jugadores con ese rango
```

### Representación en Storage

Los rangos se guardan en el campo `groups` de la sesión del jugador:

```json
{
  "groups": {
    "admin":    null,
    "vip":      1704715200,
    "streamer": 1704528000
  }
}
```

- `null` → permanente
- entero → Unix timestamp de expiración

---

## Comandos de Rangos

> **Importante:** Los comandos de gestión de rangos solo funcionan con jugadores **online**.

### `group set` — Asignar (reemplaza rangos actuales)

```bash
/uperms user <jugador> group set <grupo> [duración]
```

Elimina todos los rangos del jugador excepto el grupo `default`, y asigna el especificado.

```bash
/uperms user Steve group set admin          # Permanente
/uperms user Steve group set vip 7d         # Temporal 7 días
/uperms user Steve group set helper 24h     # Temporal 24 horas
```

### `group add` — Agregar rango adicional

```bash
/uperms user <jugador> group add <grupo> [duración]
```

Agrega el rango **sin quitar** los que ya tiene (multi-rango).

```bash
/uperms user Steve group add streamer 30d   # Steve conserva sus rangos + streamer
```

### `group remove` — Quitar rango

```bash
/uperms user <jugador> group remove <grupo>
```

Quita el rango especificado. Si el jugador se queda sin ningún rango, se le asigna automáticamente el grupo `default`.

```bash
/uperms user Steve group remove streamer
```

---

## Formato de Duración

El parser de duración es `DurationParser::toTimestamp()`. Los sufijos exactos reconocidos son:

| Sufijo | Unidad  | Segundos equivalentes |
|--------|---------|-----------------------|
| `y`    | Años    | 31 536 000 (365d)     |
| `M`    | Meses   | 2 592 000 (30d)       |
| `w`    | Semanas | 604 800 (7d)          |
| `d`    | Días    | 86 400                |
| `h`    | Horas   | 3 600                 |
| `m`    | Minutos | 60                    |
| `s`    | Segundos| 1                     |

> **Nota:** Los sufijos son **case-sensitive** a nivel de regex pero el parser aplica `strtolower()` antes de parsear, lo que hace que `M` (mes) y `m` (minuto) sean equivalentes. Usa siempre sufijos en minúscula para evitar ambigüedad.

Se pueden combinar: `1d12h` = 36 horas, `1w3d` = 10 días.

**Valores especiales** que el parser interpreta como **permanente** (`null`):
- `0`
- `permanent`
- `perm`
- Cualquier cadena que no contenga un número seguido de un sufijo válido

**Cálculo:**

```
/uperms user Steve group add vip 7d

time() actual          = 1704110400 (Unix timestamp)
duración               = 7 * 86400 = 604800 s
expiresAt              = 1704110400 + 604800 = 1704715200
```

### Ejemplos

```bash
/uperms user Alex group add vip      7d      # 7 días
/uperms user Alex group add trial    3d      # 3 días
/uperms user Alex group add premium  30d     # 30 días
/uperms user Alex group add booster  1w      # 1 semana
/uperms user Alex group add youtuber 1M      # 30 días (1 mes)
/uperms user Alex group add donor    365d    # 1 año
/uperms user Alex group add donor    1y      # 1 año (equivalente)
/uperms user Alex group add event    12h     # 12 horas
/uperms user Alex group add temp     30m     # 30 minutos
```

---

## Expiración Automática

### Cómo Funciona

El `ExpirationTask` es una tarea repetitiva registrada por el plugin. En cada ejecución:

**Para cada sesión activa (jugadores online):**

```
ExpirationTask::processPlayers()
  ├─ session->flushExpiredGroups()
  │    → retorna IDs de rangos expirados
  │    → los elimina del array interno
  │
  ├─ Para cada rango expirado:
  │    ├─ (new GroupExpireEvent($player, $groupId))->call()
  │    ├─ Registra en log
  │    └─ Si el jugador se quedó sin rangos → asigna "default"
  │
  ├─ session->flushExpiredNodes()
  │    → retorna Node[] expirados
  │    → los elimina del array interno
  │
  ├─ Para cada nodo expirado:
  │    ├─ (new PermissionExpireEvent($player, $node))->call()
  │    └─ Registra en log
  │
  └─ Si hubo cambios:
       ├─ storage->savePlayer(...)
       ├─ session->markClean()
       ├─ sessionManager->refresh($session)  ← recalcula permisos
       └─ Notifica al jugador (mensaje localizado)
```

**Para cada grupo cargado:**

```
ExpirationTask::processGroups()
  ├─ group->flushExpiredNodes()
  │    → retorna Node[] expirados del grupo
  │
  ├─ Si hubo expirados:
  │    ├─ groupManager->save($group)
  │    ├─ sessionManager->refreshByGroup($groupId)  ← recalcula todos los jugadores con ese grupo
  │    ├─ (new GroupPermExpireEvent($group, $node))->call()
  │    └─ Notifica a jugadores con ultimateperms.notify.expire
  │
  └─ Continúa con el siguiente grupo
```

### Condición de Expiración

Un rango o nodo expira cuando:

```php
$expiresAt !== null && time() > $expiresAt
```

La tarea se ejecuta en ticks de PocketMine-MP (`20 * interval` ticks). El intervalo es configurable en código (valor por defecto: `60` segundos).

### Ejemplo con Múltiples Rangos

```
Jugador: Alex
Rangos:
  admin    → null          (permanente)
  vip      → 1704715200    (expira el 2024-01-08 12:00)
  streamer → 1704528000    (expira el 2024-01-06 06:00)

ExpirationTask ejecuta el 2024-01-06 07:00:
  ✓ admin:    activo (permanente)
  ✓ vip:      activo (2 días restantes)
  ✗ streamer: EXPIRADO
               → GroupExpireEvent disparado
               → Rango eliminado de la sesión
               → Permisos recalculados
               → Alex recibe notificación

ExpirationTask ejecuta el 2024-01-08 13:00:
  ✓ admin:    activo
  ✗ vip:      EXPIRADO
               → GroupExpireEvent disparado
               → Rango eliminado
               → Alex se queda solo con "admin" (no se asigna default porque aún tiene admin)
               → Permisos recalculados
```

---

## Notificaciones al Jugador

Al expirar un **rango**, el jugador online recibe un mensaje. Al expirar un **nodo personal**, también recibe un mensaje. Los textos están en el archivo de locale configurado en `config.yml → locale`.

Al expirar un **nodo de grupo**, los administradores online con el permiso `ultimateperms.notify.expire` reciben una notificación.

Los jugadores **offline** cuando expira su rango o nodo **no reciben notificación** en el momento de la expiración (la sesión no existe). Sin embargo, los datos se actualizarán la próxima vez que el jugador se conecte, ya que el storage ya tiene los datos correctos persistidos.

---

## Eventos de Expiración

Los tres eventos son **no cancelables** — la expiración ya ocurrió cuando se disparan.

### `GroupExpireEvent` (en `RankExpireEvent.php`)

Se dispara cuando un rango temporal de un jugador **online** expira.

```php
use appgallery\uperms\event\GroupExpireEvent;
use pocketmine\event\Listener;

class MyListener implements Listener {

    public function onRankExpire(GroupExpireEvent $event): void {
        $player  = $event->getPlayer();   // Player
        $groupId = $event->getGroupId();  // string

        $player->sendMessage("§cTu rango §e$groupId§c ha expirado.");
    }
}
```

### `PermissionExpireEvent`

Se dispara cuando un nodo personal temporal de un jugador **online** expira.

```php
use appgallery\uperms\event\PermissionExpireEvent;

public function onPermExpire(PermissionExpireEvent $event): void {
    $player     = $event->getPlayer();     // Player
    $node       = $event->getNode();       // Node
    $permission = $event->getPermission(); // string
}
```

### `GroupPermExpireEvent`

Se dispara cuando un nodo temporal de un **grupo** expira.

```php
use appgallery\uperms\event\GroupPermExpireEvent;

public function onGroupPermExpire(GroupPermExpireEvent $event): void {
    $group      = $event->getGroup();      // Group
    $node       = $event->getNode();       // Node
    $permission = $event->getPermission(); // string
}
```

---

## Multi-Rango

Un jugador puede tener varios rangos al mismo tiempo. Los permisos son la unión de todos ellos, y el prefijo visible se toma del grupo con **mayor weight** (`SessionManager::getPrimaryGroup()`).

```bash
# Steve es VIP permanente
/uperms user Steve group set vip

# Además participa en un evento (3 días)
/uperms user Steve group add event-winner 3d

# Ahora Steve tiene:
# - vip       (permanente)
# - event-winner (3d)
# Prefijo = el de mayor weight entre los dos
# Permisos = unión de ambos grupos + overrides personales

# Al expirar event-winner, Steve sigue teniendo vip
```

---

## Casos de Uso

### VIP Temporal (compra en tienda)

```bash
/uperms group vip create "§b§l[VIP]" 30
/uperms group vip parent set default
/uperms group vip perm set essentials.fly true
/uperms group vip perm set essentials.kit.vip true
/uperms group vip setprefix "§b[VIP] "

# Al comprar: asignar 30 días
/uperms user Steve group add vip 30d

# Ver cuándo expira
/uperms user Steve info
```

### Prueba Gratuita (trial)

```bash
/uperms group trial create "§6[Trial]" 5
/uperms group trial parent set default
/uperms group trial perm set essentials.kit.trial true

# Nuevo jugador recibe 3 días de trial
/uperms user NewPlayer group add trial 3d
```

### Penalización Temporal

```bash
/uperms group muted create "§8[Muted]" 1000
/uperms group muted perm set pocketmine.command.say false

# Silenciar por 24 horas
/uperms user Cheater group add muted 24h
# Al expirar, pierde el rango "muted" automáticamente
```

### Rol Permanente con Privilegio Temporal

```bash
# Admin permanente
/uperms user Steve group set admin

# Durante un evento, Steve también tiene el rol "event-host"
/uperms user Steve group add event-host 2d

# Después de 2 días: Steve vuelve a tener solo "admin"
```

---

## Troubleshooting

### El rango no expira

```bash
# Ver cuándo expira según el storage
/uperms user Steve info
# Muestra el timestamp de expiración junto al rango

# Verificar la hora del servidor (debe coincidir con la hora real)
date +%s

# La expiración solo aplica a jugadores ONLINE
# Si el jugador está offline, los datos ya están actualizados en storage
# pero la sesión no existe hasta que vuelva a conectar
```

### El rango expira antes de lo esperado

```bash
# Verificar la duración usada
# Sufijos válidos (una sola letra): y M w d h m s
# ¡No funcionan!: "30days", "1week", "1month", "3mo"
# ¡Sí funcionan!: 30d, 1w, 1M, 7d

# Combinar duraciones
/uperms user Steve group add vip 30d    # ✓ 30 días
/uperms user Steve group add vip 720h   # ✓ 30 días (equivalente)
```

### El comando falla con "No session found"

Los comandos de rango requieren que el jugador esté **online**. Si el jugador está offline, el comando no puede ejecutarse.

---

## Tabla Resumen

| Concepto | Detalle |
|---|---|
| **Rango permanente** | `expiresAt = null` |
| **Rango temporal** | `expiresAt = Unix timestamp` |
| **`group set`** | Reemplaza todos los rangos (mantiene `default`) |
| **`group add`** | Agrega sin reemplazar |
| **`group remove`** | Quita un rango; asigna `default` si no queda ninguno |
| **Sufijos de duración** | `y M w d h m s` (una sola letra cada uno) |
| **Rango primario** | El de mayor weight — define el prefijo visible |
| **Expiración** | `ExpirationTask` periódica, solo para jugadores **online** |
| **Evento al expirar** | `GroupExpireEvent`, `PermissionExpireEvent`, `GroupPermExpireEvent` |
| **Sin rangos** | Se asigna el grupo `default` automáticamente |
