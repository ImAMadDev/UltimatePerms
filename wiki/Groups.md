# Gestión de Grupos

## ¿Qué es un Grupo?

Un **Grupo** es una colección reutilizable de permisos y propiedades de display que se asigna a jugadores.

```
Grupo "vip":
├── id:          vip
├── displayName: "§b[VIP]"
├── weight:      30
├── parent:      default  ← un único grupo padre
├── prefix:      "§b[VIP] "
├── suffix:      ""
├── nodes:       [essentials.fly=true, essentials.fly=false{world=pvp}]
└── meta:        {max-homes: "10"}
```

---

## Crear Grupo

```bash
/uperms group <nombre> create [displayName] [weight]
```

**Parámetros:**

| Parámetro | Obligatorio | Descripción |
|-----------|-------------|-------------|
| `<nombre>` | Sí | ID único del grupo (inmutable) |
| `[displayName]` | No | Nombre mostrado, soporta `§` (por defecto = ID) |
| `[weight]` | No | Prioridad numérica (por defecto `0`) |

**Ejemplos:**

```bash
/uperms group vip create
/uperms group vip create "§b[VIP]"
/uperms group vip create "§b[VIP]" 30
/uperms group admin create "§c§l[ADMIN]" 100
```

---

## Ver Información

```bash
/uperms group <nombre> info
```

Muestra: ID, displayName, weight, padre (si tiene), nodos de permiso (con estado/expiración/contexto), prefix, suffix, metadata.

**Ejemplo:**

```bash
/uperms group vip info
```

---

## Listar Todos los Grupos

```bash
/uperms group list
```

Lista todos los grupos ordenados por weight descendente (mayor prioridad primero).

---

## Configurar un Grupo

### Weight

Determina la prioridad. Mayor weight = más prioritario en la resolución de permisos y para elegir el prefijo visible del jugador.

```bash
/uperms group <nombre> setweight <número>
```

**Ejemplo:**
```bash
/uperms group admin     setweight 100
/uperms group youtuber  setweight 65
/uperms group vip       setweight 30
/uperms group default   setweight 0
```

---

### Prefix

```bash
/uperms group <nombre> setprefix <texto>
```

El texto puede contener espacios y códigos de color `§`.

**Ejemplos:**
```bash
/uperms group admin    setprefix "§c§l[ADMIN]§r "
/uperms group youtuber setprefix "§7[§c§lYT§7] "
/uperms group vip      setprefix "§b[VIP] "
/uperms group helper   setprefix "§2[HELPER] "
```

---

### Suffix

```bash
/uperms group <nombre> setsuffix <texto>
```

**Ejemplo:**
```bash
/uperms group vip setsuffix " §b⭐"
# Resultado en chat: [VIP] John ⭐: mensaje
```

---

## Jerarquía (Herencia)

Cada grupo puede heredar de **un único padre**. La herencia es lineal y se recorre recursivamente al resolver permisos.

### Establecer Padre

```bash
/uperms group <nombre> parent set <grupoPadre>
```

**Ejemplo:**
```bash
/uperms group vip parent set default
# vip ahora hereda todos los permisos de default
```

**Estructura típica:**

```
default (weight=0)
  ↑ hereda
vip (weight=30)
  ↑ hereda
youtuber (weight=65)
  ↑ hereda
admin (weight=100)
```

**Cómo funciona la herencia:**

```
# default tiene: [essentials.spawn=true]
# vip hereda de default y añade: [essentials.fly=true]
#
# Permisos efectivos de vip = {essentials.spawn=true, essentials.fly=true}
```

---

### Quitar Padre

```bash
/uperms group <nombre> parent unset
```

El grupo deja de heredar de cualquier padre; solo conserva sus propios nodos.

---

### Protecciones Automáticas

El plugin bloquea al ejecutar el comando si:

1. **Ciclo de herencia**
   ```bash
   /uperms group a parent set b
   /uperms group b parent set c
   /uperms group c parent set a  # ❌ Crearía ciclo A→B→C→A
   ```

2. **Auto-herencia**
   ```bash
   /uperms group vip parent set vip  # ❌ No puede heredar de sí mismo
   ```

3. **Padre inexistente**
   ```bash
   /uperms group vip parent set inexistente  # ❌ El grupo no existe
   ```

> **Al arrancar el servidor:** si el padre de un grupo no existe en storage, se ignora y se registra una advertencia en el log.

---

## Gestionar Permisos del Grupo

### Agregar / Modificar Permiso

```bash
/uperms group <nombre> perm set <nodo> [true|false] [duración] [ctx=valor...]
```

Si `true|false` se omite, se asume `true`.

**Ejemplos:**

```bash
# Permiso global permanente
/uperms group vip perm set essentials.fly true

# Negación permanente
/uperms group vip perm set essentials.ban false

# Permiso temporal (7 días)
/uperms group event perm set essentials.kit.event true 7d

# Solo en un mundo
/uperms group vip perm set essentials.gamemode true world=creative

# Negación contextual
/uperms group vip perm set essentials.fly false world=pvp

# Múltiples contextos (AND)
/uperms group vip perm set mycommand.use true world=spawn gamemode=survival

# Wildcard
/uperms group admin perm set pocketmine.command.* true
```

---

### Quitar Permiso

```bash
/uperms group <nombre> perm unset <nodo>
```

**Ejemplo:**

```bash
/uperms group vip perm unset essentials.fly
```

---

### Listar Permisos

```bash
/uperms group <nombre> perm list [página]
```

Muestra 10 nodos por página. Cada nodo incluye estado, expiración y contexto.

---

## Metadata del Grupo

Key-value libre. Todos los valores se almacenan como `string`.

```bash
/uperms group <nombre> meta set   <clave> <valor>
/uperms group <nombre> meta unset <clave>
/uperms group <nombre> meta get   <clave>
```

**Casos de uso:**

```bash
/uperms group vip meta set max-homes 10
/uperms group vip meta set max-plots 3
/uperms group vip meta set chat-color §b
```

> La clave `chat-color` es reconocida por el chat formatter y aplica como color del mensaje.

---

## Clonar Grupo

```bash
/uperms group <original> clone <nuevoId> [displayName]
```

Crea un nuevo grupo copiando todos los nodos, weight, prefix, suffix, parent y metadata del original. El nuevo grupo puede modificarse sin afectar al original.

**Ejemplo:**

```bash
/uperms group vip clone vip-temp "§b[VIP TEMP]"
# vip-temp tiene exactamente los mismos datos que vip
```

---

## Eliminar Grupo

```bash
/uperms group <nombre> delete
/uperms group <nombre> delete confirm
```

**Proceso:**

```bash
/uperms group youtuber delete
# Pide confirmación

/uperms group youtuber delete confirm
# Grupo eliminado
```

**Efectos de la eliminación:**
- El grupo **no puede recuperarse**.
- Todos los grupos que tuvieran a este como padre **pierden la referencia** al padre (se les limpia automáticamente y se persisten).
- **No elimina** el grupo de los jugadores que lo tengan asignado; sin embargo, al refrescar su sesión el sistema no encontrará el grupo y lo omitirá en la resolución.

**Protecciones:**
- El grupo `default` **no puede eliminarse** nunca.

---

## Ejemplos Completos

### Jerarquía Básica

```bash
# Crear rangos
/uperms group default  create "§7[User]"   0
/uperms group vip      create "§b[VIP]"   30
/uperms group admin    create "§c[ADMIN]" 100

# Herencia lineal
/uperms group vip   parent set default
/uperms group admin parent set vip

# Display
/uperms group vip   setprefix "§b[VIP] "
/uperms group admin setprefix "§c[ADMIN] "

# Permisos
/uperms group vip   perm set essentials.fly  true
/uperms group vip   perm set essentials.nick true
/uperms group admin perm set pocketmine.command.* true
```

---

### Jerarquía con Staff

```bash
/uperms group helper    create "§2[HELPER]"  40
/uperms group moderator create "§e[MOD]"     50
/uperms group admin     create "§c[ADMIN]"  100

/uperms group helper    parent set default
/uperms group moderator parent set helper
/uperms group admin     parent set moderator

# Permisos por nivel
/uperms group helper    perm set essentials.kick true
/uperms group helper    perm set essentials.mute true
/uperms group moderator perm set essentials.ban  true
/uperms group admin     perm set pocketmine.command.* true
```

---

### Permisos con Contexto

```bash
/uperms group builder create "§6[BUILDER]" 20

# Fly global
/uperms group builder perm set essentials.fly true

# Pero no en PvP ni en Spawn
/uperms group builder perm set essentials.fly false world=pvp
/uperms group builder perm set essentials.fly false world=spawn

# Comandos exclusivos de build
/uperms group builder perm set essentials.schematic.save  true world=build
/uperms group builder perm set essentials.schematic.paste true world=build
```

---

### Nodos Temporales en un Grupo

```bash
/uperms group event-winner create "§d[WINNER]" 25

# Permisos que expiran en 1 día
/uperms group event-winner perm set essentials.kit.winner true 1d
/uperms group event-winner perm set essentials.fly        true 1d
```

Cuando un nodo del grupo expira, el `ExpirationTask` lo elimina del grupo, persiste el grupo, refresca a todos los jugadores que lo tienen, y notifica a admins con `ultimateperms.notify.expire`.

---

## Referencia Rápida

| Acción | Comando |
|--------|---------|
| Crear | `/uperms group <n> create [display] [weight]` |
| Ver info | `/uperms group <n> info` |
| Listar todos | `/uperms group list` |
| Clonar | `/uperms group <n> clone <newId> [display]` |
| Eliminar | `/uperms group <n> delete [confirm]` |
| Perm agregar | `/uperms group <n> perm set <p> [true\|false]` |
| Perm quitar | `/uperms group <n> perm unset <p>` |
| Perm listar | `/uperms group <n> perm list [página]` |
| Weight | `/uperms group <n> setweight <n>` |
| Prefix | `/uperms group <n> setprefix <txt>` |
| Suffix | `/uperms group <n> setsuffix <txt>` |
| Parent set | `/uperms group <n> parent set <padre>` |
| Parent unset | `/uperms group <n> parent unset` |
| Meta set | `/uperms group <n> meta set <k> <v>` |
| Meta get | `/uperms group <n> meta get <k>` |
| Meta unset | `/uperms group <n> meta unset <k>` |
