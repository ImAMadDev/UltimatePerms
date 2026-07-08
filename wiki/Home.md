# UltimatePerms Wiki

Bienvenido a la wiki oficial de **UltimatePerms**, el sistema de gestión de permisos para **PocketMine-MP API 5.0.0+**.

## Índice

### Empezar Rápido

1. **[Instalación](Installation.md)** — Instalar y configurar el plugin
2. **[Comandos](Commands.md)** — Referencia completa de todos los comandos

### Documentación Principal

3. **[Sistema de Permisos](Permissions.md)** — Cómo funcionan los permisos
4. **[Grupos y Jerarquía](Groups.md)** — Gestión de grupos
5. **[Almacenamiento](Storage.md)** — Backends disponibles

### Para Desarrolladores

6. **[API Pública](API.md)** — Integrar UltimatePerms en otros plugins
7. **[Temas Avanzados](Advanced.md)** — Contextos custom, extensiones

### Ayuda

8. **[Solución de Problemas](Troubleshooting.md)** — Errores comunes y soluciones

---

## ¿Qué es UltimatePerms?

**UltimatePerms** es un sistema de gestión de permisos para PocketMine-MP que ofrece:

✅ Control granular de permisos por nodo  
✅ Grupos con jerarquía lineal (un padre por grupo)  
✅ Permisos contextuales — mundo (`world`) y modo de juego (`gamemode`)  
✅ Rangos y permisos temporales con expiración automática  
✅ Múltiples rangos por jugador  
✅ Chat formatter personalizable con placeholders  
✅ 4 backends de almacenamiento: YAML, JSON, SQLite, MySQL  
✅ API pública para integración con otros plugins  
✅ Eventos de expiración para rangos y permisos  

---

## Características Principales

### Permisos Contextuales

Los permisos pueden condicionarse al mundo o gamemode del jugador. El contexto se actualiza automáticamente cuando el jugador cambia de mundo (`EntityTeleportEvent`) o de modo de juego (`PlayerGameModeChangeEvent`).

```bash
# Fly global
/uperms group vip perm set essentials.fly true

# Desactivar fly solo en el mundo PvP
/uperms group vip perm set essentials.fly false world=pvp
```

### Grupos con Jerarquía

Organiza rangos en una estructura lineal. Cada grupo puede tener **un único padre**:

```
default (weight=0)
  ↑
vip (weight=30)
  ↑
youtuber (weight=65)
  ↑
admin (weight=100)
```

- Cada grupo hereda permisos de su padre (y de los ancestros de éste)
- El weight determina la prioridad
- El plugin detecta y bloquea ciclos de herencia automáticamente

### Expiración Automática

```bash
# Rango temporal 7 días (reemplaza rangos existentes)
/uperms user John group set youtuber 7d

# Agregar rango temporal sin quitar otros
/uperms user John group add helper 3d

# Permiso personal temporal
/uperms user John perm set essentials.fly true 1d
```

El intervalo de verificación se configura en `config.yml` → `expiration-check-interval` (en segundos, por defecto `60`).

### Múltiples Backends de Almacenamiento

Elige en `config.yml` → `storage.type`:

- **`json`** — Un archivo JSON por jugador (por defecto)
- **`yaml`** — Equivalente en formato YAML
- **`sqlite`** — Base de datos SQLite local
- **`mysql`** — Base de datos MySQL remota

### Chat Formatter

El formato de chat y nametag es configurable desde `config.yml`:

```yaml
chat:
  enabled: true
  format: "{prefix}{name}{suffix}§r: {chat_color}{message}"

nametag:
  enabled: true
  format: "{prefix}{name}{suffix}"
```

**Placeholders disponibles:**

| Placeholder      | Descripción |
|------------------|-------------|
| `{prefix}`       | Prefijo resuelto (override personal → grupo primario → herencia) |
| `{suffix}`       | Sufijo resuelto (misma lógica) |
| `{name}`         | Nombre del jugador |
| `{displayname}`  | Display name actual |
| `{message}`      | Mensaje del chat |
| `{group}`        | ID del grupo primario |
| `{group_display}`| DisplayName del grupo primario |
| `{chat_color}`   | Meta `chat-color` (personal → grupo primario) |

---

## Flujo Típico de Uso

### 1. Crear Grupos

```bash
/uperms group default  create "§7[Default]"   0
/uperms group vip      create "§b§lVIP"       30
/uperms group youtuber create "§c§lYouTuber"  65
/uperms group youtuber parent set vip
```

### 2. Agregar Permisos

```bash
# Globales
/uperms group vip perm set essentials.fly  true
/uperms group vip perm set essentials.nick true

# Contextuales
/uperms group vip perm set essentials.fly false world=pvp
```

### 3. Configurar Display

```bash
/uperms group vip setprefix "§b[VIP] "
/uperms group vip setsuffix " §b⭐"
```

### 4. Asignar a Jugadores

```bash
# Permanente (reemplaza rangos anteriores)
/uperms user John group set vip

# Temporal (sin reemplazar otros rangos)
/uperms user John group add youtuber 7d
```

### 5. Verificar

```bash
/uperms user John info
/uperms check John essentials.fly
/uperms simulate John essentials.fly world=pvp
/uperms user John audit
```

---

## Conceptos Clave

### Nodo (Node)

Un permiso individual con cuatro atributos:

| Atributo     | Tipo          | Descripción |
|--------------|---------------|-------------|
| `permission` | `string`      | Nombre del permiso (puede ser wildcard `*`) |
| `state`      | `bool`        | `true` = otorgado, `false` = negado |
| `expiresAt`  | `?int`        | Unix timestamp de expiración, `null` = permanente |
| `context`    | `array<string,string>` | Mapa de contexto; `[]` = global |

### Grupo (Group)

| Campo        | Tipo      | Descripción |
|--------------|-----------|-------------|
| `id`         | `string`  | ID único e inmutable |
| `displayName`| `string`  | Nombre visual (soporta `§`) |
| `weight`     | `int`     | Prioridad (mayor = más importante) |
| `parent`     | `?string` | ID del grupo padre (herencia lineal) |
| `prefix`     | `string`  | Prefijo de chat/nametag |
| `suffix`     | `string`  | Sufijo de chat/nametag |
| `nodes`      | `Node[]`  | Permisos asignados |
| `meta`       | `array`   | Key-value personalizado |

### PlayerSession

Estado de un jugador online:

| Campo                | Descripción |
|----------------------|-------------|
| `groups`             | Rangos activos con su expiración |
| `personalNodes`      | Overrides de permiso directos |
| `resolvedPermissions`| Caché de permisos efectivos calculados |
| `context`            | Mundo y gamemode actuales |
| `prefix` / `suffix`  | Override personal de display |
| `meta`               | Metadata del jugador |

---

## Resolución de Permisos

El plugin resuelve permisos en este orden de prioridad:

1. **Overrides Personales** — máxima prioridad; las negaciones (`false`) siempre prevalecen
2. **Grupos por weight DESC** — del más pesado al menos pesado
3. **Herencia** — cada grupo incluye los nodos de su cadena de ancestros
4. **Wildcards** — `essentials.*` cubre `essentials.fly`, `essentials.nick`, etc.
5. **Default: Denegado** — si no está definido en ningún nivel → `false`

---

## Atajos de Comandos

| Comando | Descripción |
|---------|-------------|
| `/uperms` | Mostrar ayuda |
| `/uperms user <j> info` | Ver información del jugador |
| `/uperms user <j> audit` | Ver todos los permisos resueltos |
| `/uperms group list` | Listar todos los grupos |
| `/uperms check <j> <perm>` | Verificar origen de un permiso |
| `/uperms simulate <j> <perm> [ctx=v]` | Simular permiso sin aplicar |
| `/uperms reload` | Recargar configuración |

---

**Desarrollado por AppGallery**
