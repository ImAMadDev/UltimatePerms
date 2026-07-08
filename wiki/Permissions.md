# Sistema de Permisos

## Conceptos Fundamentales

### ¿Qué es un Nodo (Node)?

Un nodo es la unidad mínima del sistema de permisos. Contiene:

| Atributo     | Tipo                   | Descripción |
|--------------|------------------------|-------------|
| `permission` | `string`               | Nombre del permiso. Puede ser un wildcard (`essentials.*` o `*`) |
| `state`      | `bool`                 | `true` = otorgado, `false` = negado explícitamente |
| `expiresAt`  | `?int`                 | Unix timestamp de expiración. `null` = permanente |
| `context`    | `array<string,string>` | Condiciones de aplicación. `[]` = siempre aplica |

### Contextos Built-in

El plugin actualiza el contexto del jugador automáticamente:

| Clave | Cuándo se actualiza | Valores posibles |
|-------|---------------------|------------------|
| `world` | Al cambiar de mundo (`EntityTeleportEvent`) | Nombre de la carpeta del mundo |
| `gamemode` | Al cambiar de gamemode (`PlayerGameModeChangeEvent`) | `survival`, `creative`, `adventure`, `spectator` |

---

## Algoritmo de Resolución

El plugin construye el mapa de permisos de un jugador en este orden (de mayor a menor prioridad):

### 1. Grupos por weight DESC

Para cada grupo del jugador, ordenados de mayor a menor weight, se recorre la cadena de herencia completa (grupo → padre → abuelo → …). El resultado es un mapa plano `[permission => bool]`.

**Regla:** el primer nodo que cubra el permiso y que aplique en el contexto actual gana. Una negación explícita (`false`) tiene exactamente la misma precedencia que un `true` — lo que gana es el que aparece primero en el orden de recorrido.

### 2. Overrides Personales

Se aplican **después** de los grupos, sobreescribiendo el mapa de grupos. Un override personal `false` siempre prevalece sobre cualquier cosa heredada de grupos.

### 3. Wildcards

Un nodo `essentials.*` cubre cualquier permiso que empiece por `essentials.`. Una negación específica (`essentials.ban = false`) dentro de un wildcard (`essentials.* = true`) gana porque el nodo específico es más largo y se evalúa primero en el algoritmo de cobertura.

### 4. Contextos

Un nodo con contexto solo aplica si **todos** los pares `clave=valor` del nodo coinciden con el contexto actual del jugador. Un nodo global (`context: []`) aplica siempre.

Si para un mismo permiso existen dos nodos en el mismo nivel (ej: el mismo grupo) — uno global y uno con contexto — el resolver usa el más específico (con contexto) cuando éste aplique.

### 5. Default: Denegado

Si ningún nodo cubre el permiso, se retorna `false` automáticamente.

---

## Flujo Ilustrado

### Escenario

```
Jugador: John
Rangos:  youtuber (weight=65, padre=vip), helper (weight=30)
Override personal: essentials.fly = false
Contexto: world=pvp

Buscando: essentials.fly
```

### Resolución paso a paso

```
1. Override personal
   → essentials.fly = false   ← ENCONTRADO
   → Resultado: FALSE (retorna inmediatamente)

─────────────────────────────────────────────

Buscando: essentials.nick (misma sesión, sin override)

1. Override personal
   → no definido

2. Grupos por weight DESC:
   a) youtuber (weight=65)
      → no tiene essentials.nick
      → Herencia: vip
         → vip tiene: essentials.nick = true (global)
         → aplica en contexto world=pvp? Sí (es global)
         → Resultado: TRUE (retorna inmediatamente)
```

---

## Contextos en Profundidad

### Reglas de Aplicación

Un nodo con `context: {world: "pvp"}` aplica **solo** si `world == "pvp"` en el contexto actual del jugador. Los demás pares del contexto del jugador se ignoran.

Un nodo con `context: {world: "pvp", gamemode: "survival"}` aplica **solo si ambas** condiciones se cumplen al mismo tiempo.

### Precedencia entre Global y Contextual

En la implementación actual, un nodo contextual que aplica **no sobreescribe** automáticamente a un nodo global del mismo nombre en el mismo nivel — el que gana es el que aparece primero en el array de nodos del grupo. Por eso la forma correcta de hacer excepciones es agregar el nodo contextual **además** del global.

**Ejemplo correcto:**

```bash
# Global: permitir fly
/uperms group vip perm set essentials.fly true

# Excepción: negar en PvP  (se agrega, no reemplaza al global)
/uperms group vip perm set essentials.fly false world=pvp

# En spawn:    true  (aplica el global)
# En pvp:      false (aplica el contextual — nodo más específico)
# En creative: true  (aplica el global)
```

---

## Comandos de Permisos

### Permisos Personales (overrides)

```bash
# Otorgar permiso permanente
/uperms user John perm set essentials.fly

# Otorgar / Negar con estado explícito
/uperms user John perm set essentials.fly true
/uperms user John perm set essentials.kick false

# Temporal
/uperms user John perm set essentials.fly true 7d

# Con contexto
/uperms user John perm set essentials.fly false world=pvp

# Temporal + contexto
/uperms user John perm set mycommand.use true 1d world=spawn

# Eliminar override (vuelve a resolverse desde grupos)
/uperms user John perm unset essentials.fly
```

### Permisos de Grupo

```bash
# Agregar
/uperms group vip perm set essentials.fly true

# Con contexto
/uperms group vip perm set essentials.fly false world=pvp

# Temporal (7 días)
/uperms group event perm set essentials.kit.event true 7d

# Wildcard
/uperms group admin perm set pocketmine.command.* true

# Quitar
/uperms group vip perm unset essentials.fly

# Listar (10 por página)
/uperms group vip perm list
/uperms group vip perm list 2
```

---

## Wildcards

Un nodo `essentials.*` cubre cualquier permiso cuya cadena comience por `essentials.`.  
El nodo `*` cubre **cualquier** permiso.

```bash
# Admin tiene TODO de pocketmine.command
/uperms group admin perm set pocketmine.command.* true

# EXCEPTO ban (negación específica — el nodo específico gana)
/uperms group admin perm set pocketmine.command.ban false

# Resultado:
# ✓ pocketmine.command.kick
# ✗ pocketmine.command.ban
# ✓ pocketmine.command.op
```

---

## Negación Explícita

Usar `false` en un nodo no solo "no otorga" el permiso — lo **niega activamente**, impidiendo que lo aporte cualquier otro nodo de menor prioridad (grupo con menor weight o herencia).

```bash
# Helper tiene todo de essentials excepto ban
/uperms group helper perm set essentials.* true
/uperms group helper perm set essentials.ban false

# Override personal que niega un permiso heredado de grupo
/uperms user John perm set essentials.fly false
# → John no puede volar aunque su grupo lo permita
```

---

## Expiración de Permisos

Los nodos con `expiresAt` se verifican periódicamente por el `ExpirationTask` (configurable en `config.yml`). Cuando expira:

- **Nodo personal de jugador:** se dispara `PermissionExpireEvent`, se notifica al jugador y se recalculan sus permisos.
- **Nodo de grupo:** se dispara `GroupPermExpireEvent`, se notifica a admins con `ultimateperms.notify.expire`, se persiste el grupo y se refrescan todos los jugadores que lo tienen.

```bash
# Permiso personal temporal
/uperms user John perm set essentials.fly true 7d    # 7 días
/uperms user John perm set essentials.fly true 24h   # 24 horas
/uperms user John perm set essentials.fly true 30m   # 30 minutos

# Permiso de grupo temporal
/uperms group event perm set essentials.kit.bonus true 1d
```

**Formato de duración:** `Y` años, `M` meses, `w` semanas, `d` días, `h` horas, `m` minutos, `s` segundos. Se combinan: `1d12h` = 36 horas.

---

## Verificación y Diagnóstico

### `/uperms check` — Origen del Permiso

```bash
/uperms check John essentials.fly
```

Muestra el resultado y la fuente: override personal, grupo directo, herencia, o default.

### `/uperms simulate` — Probar con Contexto

```bash
/uperms simulate John essentials.fly world=pvp
/uperms sim      John essentials.fly gamemode=creative
```

Resuelve el permiso **como si** el jugador estuviera en ese contexto, sin modificar nada.

### `/uperms user <j> audit` — Todos los Permisos Resueltos

```bash
/uperms user John audit
/uperms user John audit 2   # página 2
```

Lista todos los permisos del mapa resuelto en caché (10 por página).

---

## Permisos del Plugin

Todos los permisos del plugin tienen `default: op`.

| Nodo | Descripción |
|------|-------------|
| `ultimateperms.admin` | Acceso total |
| `ultimateperms.notify.expire` | Recibir notificaciones de expiración de nodos de grupo |
| `ultimateperms.command` | Acceso a la ayuda general |
| `ultimateperms.command.check` | `/uperms check` |
| `ultimateperms.command.inspect` | `/uperms inspect` |
| `ultimateperms.command.reload` | `/uperms reload` |
| `ultimateperms.command.simulate` | `/uperms simulate` |
| `ultimateperms.command.ui` | `/uperms ui` |
| `ultimateperms.command.group` | Comandos de grupo |
| `ultimateperms.command.group.create` | Crear grupos |
| `ultimateperms.command.group.delete` | Eliminar grupos |
| `ultimateperms.command.group.info` | Ver info de grupos |
| `ultimateperms.command.group.edit` | Editar grupos |
| `ultimateperms.command.user` | Comandos de usuario |
| `ultimateperms.command.user.audit` | Ver auditoría de permisos |
| `ultimateperms.command.user.info` | Ver info de jugadores |
| `ultimateperms.command.user.perm` | Gestionar nodos personales |
| `ultimateperms.command.user.meta` | Gestionar metadata de jugadores |
| `ultimateperms.command.user.group` | Asignar/quitar rangos |

---

## Referencia Rápida

| Acción | Comando |
|--------|---------|
| Otorgar permiso personal | `/uperms user <j> perm set <p> true` |
| Negar permiso personal | `/uperms user <j> perm set <p> false` |
| Con contexto | `/uperms user <j> perm set <p> true world=pvp` |
| Temporal | `/uperms user <j> perm set <p> true 7d` |
| Eliminar override | `/uperms user <j> perm unset <p>` |
| Verificar origen | `/uperms check <j> <p>` |
| Simular contexto | `/uperms simulate <j> <p> world=pvp` |
| Ver todos resueltos | `/uperms user <j> audit` |
