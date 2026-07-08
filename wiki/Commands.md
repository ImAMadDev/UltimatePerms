# Commands Reference

## Comando Principal

```bash
/uperms          # Mostrar ayuda (muestra solo los subcomandos a los que tienes permiso)
/up              # Alias
/perms           # Alias
```

Requiere: `ultimateperms.command`

---

## Comandos de Usuario

> Requieren: `ultimateperms.command.user`  
> **Importante:** todos los subcomandos de usuario solo funcionan con jugadores **online**.

### Info

```bash
/uperms user <jugador> info
```

Muestra:
- Rangos actuales con su expiración
- Overrides personales (nodos directos)
- Contexto actual (mundo, gamemode)
- Prefijo y sufijo

**Ejemplo:**
```bash
/uperms user John info
```

---

### Gestión de Rangos

#### `group set` — Reemplazar rangos

```bash
/uperms user <jugador> group set <grupo> [duración]
```

Elimina todos los rangos actuales (excepto el grupo `default`) y asigna únicamente el especificado.

**Ejemplos:**
```bash
/uperms user John group set vip          # Permanente
/uperms user John group set vip 7d       # 7 días
/uperms user John group set admin 1h     # 1 hora
/uperms user John group set helper 30m   # 30 minutos
```

---

#### `group add` — Agregar rango adicional

```bash
/uperms user <jugador> group add <grupo> [duración]
```

Agrega un rango **sin quitar** los que ya tiene (multi-rango).

**Ejemplos:**
```bash
/uperms user John group add helper 7d
# Ahora John tiene: su rango anterior + helper (7 días)
```

---

#### `group remove` — Quitar rango

```bash
/uperms user <jugador> group remove <grupo>
```

Quita un rango específico. Si el jugador se queda sin rangos, se asigna automáticamente el grupo `default`.

**Ejemplo:**
```bash
/uperms user John group remove helper
```

---

### Gestión de Permisos Personales

#### `perm set` — Otorgar / Negar

```bash
/uperms user <jugador> perm set <nodo> [true|false] [duración] [ctx=valor...]
```

Asigna un override personal. Si `true|false` se omite, se asume `true`.

**Ejemplos:**
```bash
/uperms user John perm set essentials.fly                        # true, permanente
/uperms user John perm set essentials.kick false                 # negación permanente
/uperms user John perm set essentials.nick true 7d               # temporal 7 días
/uperms user John perm set essentials.fly false world=pvp        # negación en contexto
/uperms user John perm set mycommand.use true 1d world=spawn     # temporal + contexto
```

---

#### `perm unset` — Eliminar override

```bash
/uperms user <jugador> perm unset <nodo>
```

Elimina el override personal. El permiso pasa a resolverse desde los grupos.

**Ejemplo:**
```bash
/uperms user John perm unset essentials.fly
```

---

#### `perm check` — Verificar con trazabilidad

```bash
/uperms user <jugador> perm check <nodo>
```

Muestra si el jugador tiene el permiso y de dónde viene (override personal, grupo directo, herencia, o default).

**Ejemplo:**
```bash
/uperms user John perm check essentials.fly
```

---

### Auditoría

```bash
/uperms user <jugador> audit [página]
```

Lista todos los permisos resueltos actualmente para el jugador (10 por página).

**Ejemplo:**
```bash
/uperms user John audit
/uperms user John audit 2
```

---

### Prefix / Suffix Personal

Los overrides personales tienen prioridad sobre el prefix/suffix del grupo.

#### Prefix

```bash
/uperms user <jugador> prefix set <texto>    # Establece prefix personal
/uperms user <jugador> prefix unset          # Elimina override → vuelve al del grupo
/uperms user <jugador> prefix get            # Muestra el prefix actual
```

**Ejemplos:**
```bash
/uperms user John prefix set "§b[CUSTOM] "
/uperms user John prefix get
/uperms user John prefix unset
```

---

#### Suffix

```bash
/uperms user <jugador> suffix set <texto>
/uperms user <jugador> suffix unset
/uperms user <jugador> suffix get
```

---

### Metadata de Jugador

Key-value libre. Los valores son strings. Se pueden usar para integración con otros plugins (ej: `max-homes`, `chat-color`).

```bash
/uperms user <jugador> meta set   <clave> <valor>
/uperms user <jugador> meta unset <clave>
/uperms user <jugador> meta get   <clave>
```

**Ejemplos:**
```bash
/uperms user John meta set max-homes 5
/uperms user John meta set chat-color §b
/uperms user John meta get max-homes
/uperms user John meta unset max-homes
```

---

## Comandos de Grupo

> Requieren: `ultimateperms.command.group`

### List

```bash
/uperms group list
```

Lista todos los grupos ordenados por weight descendente (mayor prioridad primero).

---

### Create

```bash
/uperms group <nombre> create [displayName] [weight]
```

Crea un nuevo grupo.

- `<nombre>` — ID único (inmutable, lowercase)
- `[displayName]` — Nombre mostrado, soporta `§` (por defecto igual al ID)
- `[weight]` — Prioridad numérica (por defecto `0`)

**Ejemplos:**
```bash
/uperms group vip create
/uperms group vip create "§b§lVIP"
/uperms group vip create "§b§lVIP" 30
```

---

### Info

```bash
/uperms group <nombre> info
```

Muestra: ID, displayName, weight, padre, prefijo, sufijo, cantidad de nodos y metadata.

---

### Delete

```bash
/uperms group <nombre> delete
/uperms group <nombre> delete confirm
```

Requiere confirmación. El grupo `default` no puede eliminarse. Al eliminar un grupo, sus grupos hijos pierden la referencia al padre (se les limpia automáticamente).

**Ejemplo:**
```bash
/uperms group youtuber delete
# Responde con instrucción para confirmar

/uperms group youtuber delete confirm
```

---

### Clone

```bash
/uperms group <nombre> clone <nuevoId> [displayName]
```

Copia un grupo a uno nuevo con todos sus permisos, peso, prefijo, sufijo y metadata.

**Ejemplo:**
```bash
/uperms group vip clone vip-temp "§b[VIP TEMP]"
```

---

### Permisos del Grupo

#### `perm set`

```bash
/uperms group <nombre> perm set <nodo> [true|false] [duración] [ctx=valor...]
```

Agrega o reemplaza un nodo en el grupo.

**Ejemplos:**
```bash
/uperms group vip perm set essentials.fly true
/uperms group vip perm set essentials.kit.vip true 30d
/uperms group vip perm set essentials.fly false world=pvp
/uperms group admin perm set pocketmine.command.* true
/uperms group vip perm set essentials.fly true 7d world=creative
```

---

#### `perm unset`

```bash
/uperms group <nombre> perm unset <nodo>
```

Elimina un nodo del grupo.

---

#### `perm list`

```bash
/uperms group <nombre> perm list [página]
```

Lista todos los nodos del grupo (10 por página). Muestra estado, expiración y contexto.

---

### Herencia (Parent)

Cada grupo puede tener **un único padre**. El plugin detecta y bloquea automáticamente los ciclos de herencia.

#### `parent set`

```bash
/uperms group <nombre> parent set <grupoPadre>
```

**Ejemplo:**
```bash
/uperms group youtuber parent set vip
# youtuber hereda todos los permisos de vip (y de los ancestros de vip)
```

**Protecciones:**
- Bloquea ciclos: si `A → B → C → A`, se rechaza
- Bloquea auto-herencia
- Verifica que el padre exista

---

#### `parent unset`

```bash
/uperms group <nombre> parent unset
```

Elimina la herencia del grupo.

---

### Display del Grupo

#### Setweight

```bash
/uperms group <nombre> setweight <número>
```

Mayor weight = mayor prioridad en resolución de permisos y para determinar el prefijo visible del jugador.

**Ejemplo:**
```bash
/uperms group admin     setweight 100
/uperms group youtuber  setweight 65
/uperms group vip       setweight 30
/uperms group default   setweight 0
```

---

#### Setprefix

```bash
/uperms group <nombre> setprefix <texto>
```

El texto puede incluir espacios y códigos de color `§`.

**Ejemplo:**
```bash
/uperms group youtuber setprefix "§7[§c§lYT§7] §f"
```

---

#### Setsuffix

```bash
/uperms group <nombre> setsuffix <texto>
```

---

### Metadata del Grupo

```bash
/uperms group <nombre> meta set   <clave> <valor>
/uperms group <nombre> meta unset <clave>
/uperms group <nombre> meta get   <clave>
```

**Clave especial reconocida por el chat formatter:** `chat-color`

---

## Diagnóstico

### Check

```bash
/uperms check <jugador> <permiso>
```

Verifica si el jugador tiene el permiso y muestra el origen exacto:
- Override personal
- Grupo directo
- Herencia de grupo padre
- Default (no definido → `false`)

**Ejemplo:**
```bash
/uperms check John essentials.fly
```

Requiere: `ultimateperms.command.check`

---

### Simulate

```bash
/uperms simulate <jugador> <permiso> [ctx=valor...]
/uperms sim      <jugador> <permiso> [ctx=valor...]
```

Simula la resolución del permiso con un contexto personalizado (puede sobreescribir el contexto actual del jugador). **No modifica nada.**

**Ejemplo:**
```bash
/uperms simulate John essentials.fly world=pvp
/uperms sim John pocketmine.command.kick gamemode=adventure
```

Requiere: `ultimateperms.command.simulate`

---

### Inspect

```bash
/uperms inspect <nombrePlugin>
```

Lista todos los permisos registrados por un plugin cargado en el servidor, mostrando si el ejecutor del comando los tiene o no.

**Ejemplo:**
```bash
/uperms inspect EssentialsX
```

Requiere: `ultimateperms.command.inspect`

---

### Reload

```bash
/uperms reload
```

Recarga `config.yml` y el archivo de locale, luego recalcula los permisos de todos los jugadores online.

Requiere: `ultimateperms.command.reload`

---

## Formato de Duración

La duración se especifica como una cadena combinando unidades:

| Unidad | Sufijo | Ejemplo  |
|--------|--------|----------|
| Años   | `Y`    | `1Y`     |
| Meses  | `M`    | `2M`     |
| Semanas| `w`    | `3w`     |
| Días   | `d`    | `7d`     |
| Horas  | `h`    | `24h`    |
| Minutos| `m`    | `30m`    |
| Segundos| `s`   | `3600s`  |

Se pueden combinar: `1Y2M3w4d5h6m7s`.  
Omitir duración = **permanente**.

---

## Casos de Uso

### Jerarquía de Rangos

```bash
# Crear rangos
/uperms group default  create "§7[Default]"   0
/uperms group vip      create "§b[VIP]"      30
/uperms group youtuber create "§c[YT]"       65
/uperms group admin    create "§4[ADMIN]"   100

# Establecer herencia
/uperms group vip      parent set default
/uperms group youtuber parent set vip
/uperms group admin    parent set youtuber

# Agregar permisos
/uperms group vip   perm set essentials.fly  true
/uperms group vip   perm set essentials.nick true
/uperms group admin perm set pocketmine.command.* true

# Asignar a jugadores
/uperms user John group set vip
/uperms user Jane group set admin
```

---

### Permisos Contextuales

```bash
# Fly global
/uperms group vip perm set essentials.fly true

# Desactivar fly solo en PvP
/uperms group vip perm set essentials.fly false world=pvp

# Reactivar fly solo en Creative (sobreescribe la negación de arriba si el mundo es creative)
/uperms group vip perm set essentials.fly true world=creative
```

---

### Rangos Temporales

```bash
# VIP por 7 días (reemplaza rangos)
/uperms user John group set vip 7d

# Permiso especial temporal
/uperms user John perm set essentials.kit.bonus true 1d

# Consultar cuándo expira
/uperms user John info
```

---

### Multi-Rango

```bash
# John es VIP permanente
/uperms user John group set vip

# Además Helper temporal 3 días
/uperms user John group add helper 3d

# Verificar
/uperms user John info
# Tiene: vip (permanente) + helper (3d)
# Cuando helper expire → sigue siendo vip
```
