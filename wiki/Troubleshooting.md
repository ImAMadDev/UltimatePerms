# Troubleshooting — Solución de Problemas

## Plugin No Carga

**Síntoma:**
```
[ERROR] Could not load plugin "UltimatePerms"
```

**Soluciones:**

```bash
# 1. Verificar PHP (debe ser 8.1+)
php -v

# 2. Verificar que plugin.yml existe en la carpeta del plugin
ls plugins/UltimatePerms/plugin.yml

# 3. Ver logs detallados
tail -n 100 server.log | grep -i ultimate

# 4. Verificar permisos de carpeta
chmod -R 755 plugins/UltimatePerms/
```

---

## Error de Almacenamiento

**Síntoma:**
```
[ERROR] Storage initialization failed
[ERROR] Cannot connect to database
```

### JSON / YAML / SQLite

```bash
# Verificar permisos de escritura
chmod -R 755 plugins/UltimatePerms/

# Verificar espacio en disco
df -h
```

### MySQL

```bash
# 1. Verificar que MySQL está corriendo
sudo systemctl status mysql

# 2. Probar conexión con las credenciales del config.yml
mysql -h 127.0.0.1 -u ultimateperms -p ultimateperms

# 3. Verificar las claves en config.yml
#    storage.mysql.host, .port, .user, .password, .database

# 4. Si el usuario no existe, crearlo:
mysql -u root -p
CREATE DATABASE ultimateperms CHARACTER SET utf8mb4;
CREATE USER 'ultimateperms'@'localhost' IDENTIFIED BY 'contraseña';
GRANT ALL PRIVILEGES ON ultimateperms.* TO 'ultimateperms'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Permisos No Se Aplican

**Síntoma:** El jugador no tiene acceso a un comando a pesar de tener el permiso asignado.

**Diagnóstico:**

```bash
# 1. Ver el estado actual del jugador
/uperms user PlayerName info

# 2. Verificar el origen del permiso con trazabilidad
/uperms check PlayerName essentials.fly
# Si dice Granted pero el comando no funciona:
# → El problema es del plugin que registra ese permiso, no de UltimatePerms

# 3. Simular en el contexto actual
/uperms simulate PlayerName essentials.fly
# o con contexto específico:
/uperms simulate PlayerName essentials.fly world=pvp

# 4. Ver todos los permisos resueltos
/uperms user PlayerName audit

# 5. Si los permisos parecen desactualizados, recargar
/uperms reload
```

**Causas comunes:**

- El permiso tiene un contexto (`world=pvp`) pero el jugador está en otro mundo
- El permiso tiene `state: false` en un override personal que sobreescribe el grupo
- El grupo del jugador no tiene el permiso (revisar con `/uperms group <g> info`)
- La sesión no se recargó tras modificar el grupo (usar `/uperms reload`)

---

## Expiración No Funciona

**Síntoma:** Un rango o permiso con duración sigue activo después de la fecha prevista.

```bash
# Ver cuándo expira el rango
/uperms user PlayerName info
# La expiración se muestra junto a cada rango/permiso

# La verificación es periódica (tarea en segundo plano)
# Si acabas de modificar algo, espera el próximo ciclo
# o recarga:
/uperms reload
```

**Nota:** La expiración la gestiona `ExpirationTask`, que se ejecuta automáticamente. El intervalo se define en el código (no es configurable en `config.yml`). Al expirar:
- El jugador recibe un mensaje de notificación
- Los admins con `ultimateperms.notify.expire` reciben aviso en el caso de nodos de grupo
- Se dispara el evento correspondiente (`GroupExpireEvent`, `PermissionExpireEvent`, `GroupPermExpireEvent`)

---

## Ciclo de Herencia

**Síntoma:**
```
[UltimatePerms] Inheritance cycle detected in group '...' — clearing parent to prevent infinite loops.
```

Este mensaje en el log indica que al cargar los grupos desde storage se detectó un ciclo. El plugin rompe el ciclo eliminando el padre del grupo conflictivo automáticamente.

**Prevención:** el plugin bloquea el ciclo en tiempo real cuando usas `/uperms group <n> parent set <p>`. El mensaje en log solo aparece si los datos en storage ya tenían el ciclo antes de arrancar.

**Jerarquía correcta (lineal, sin ciclos):**
```
default → vip → youtuber → admin
```

**Jerarquía incorrecta (crearía ciclo):**
```
a → b → c → a  ❌
```

---

## Comando Desconocido / Sintaxis Incorrecta

**Síntoma:**
```
Unknown subcommand: ...
```

**Verificar la sintaxis exacta:**

```bash
# Correcto:
/uperms user <nombre> info
/uperms group <nombre> info
/uperms group <nombre> parent set <padre>   # NO "parent add"
/uperms user <nombre> prefix unset          # NO "prefix remove"

# El comando /uperms sin argumentos muestra la ayuda con los subcomandos disponibles
/uperms
```

---

## Sin Permiso Para Ejecutar el Comando

**Síntoma:**
```
§cNo tienes permiso para esto.
```

Todos los subcomandos de UltimatePerms requieren permisos con `default: op`. Si no eres OP ni tienes el permiso explícito, no puedes ejecutarlos.

```bash
# Verificar si tienes el permiso necesario
/uperms check TuNombre ultimateperms.command.user
/uperms check TuNombre ultimateperms.command.group

# Solución: pedir a un OP que te asigne el grupo con los permisos necesarios
/uperms user TuNombre group set admin
```

---

## Contexto No Funciona

**Síntoma:** Un permiso con `world=pvp` no se aplica aunque el jugador está en ese mundo.

```bash
# 1. Verificar el nombre exacto del mundo (es el nombre de la carpeta, no el alias)
#    El contexto usa el folderName del mundo, no el displayName

# 2. Simular para debuggear
/uperms simulate PlayerName essentials.fly world=pvp

# 3. Verificar que el nodo tiene el contexto correcto
/uperms group vip perm list
# o
/uperms user PlayerName perm check essentials.fly  # No existe este subcomando
/uperms check PlayerName essentials.fly             # Este sí existe
```

**Valores de gamemode:** `survival`, `creative`, `adventure`, `spectator` (en minúsculas, son los nombres del enum de PMMP).

---

## Datos No Se Persisten

**Síntoma:** Los cambios hechos con comandos se pierden al reiniciar el servidor.

```bash
# 1. Verificar permisos de escritura en la carpeta de datos
ls -la plugins/UltimatePerms/

# 2. Ver logs al momento de guardar (al desconectar jugadores o al reiniciar)
tail -n 50 server.log | grep -i "ultimateperms"

# 3. Verificar espacio en disco
df -h
```

Los datos se persisten:
- Al ejecutar un comando que modifica grupos o jugadores (sincrónico inmediato)
- Al desconectar un jugador (`SessionManager::close()`)
- Al desactivar el plugin (`SessionManager::saveAll()`)

---

## Grupo Default Faltante

**Síntoma:**
```
[UltimatePerms] Default group not found — creating it automatically.
```

Este es un mensaje de **advertencia**, no de error. El plugin crea el grupo `default` automáticamente con weight=0 y display name `§7Default`. No requiere acción adicional.

---

## Padre de Grupo Ignorado al Arrancar

**Síntoma:**
```
[UltimatePerms] Group 'vip' references unknown parent 'xxx' — ignoring.
```

Al cargar los grupos desde storage, el padre referenciado no existe. El plugin ignora ese padre (lo establece a `null`) y continúa. Esto puede ocurrir si eliminaste el grupo padre manualmente de los archivos de storage sin actualizar los grupos hijos.

**Solución:** restaurar el grupo padre o reasignar el padre correcto:

```bash
/uperms group vip parent set default
```

---

## Diagnóstico General

Pasos para identificar cualquier problema:

```bash
# 1. Ver estado del jugador
/uperms user PlayerName info

# 2. Ver origen del permiso problemático
/uperms check PlayerName el.permiso.aqui

# 3. Simular en contexto diferente
/uperms simulate PlayerName el.permiso.aqui world=pvp

# 4. Ver todos los permisos resueltos
/uperms user PlayerName audit

# 5. Ver info del grupo
/uperms group grupoquestion info

# 6. Ver lista de permisos del grupo
/uperms group grupoquestion perm list

# 7. Recargar si los cambios no se reflejan
/uperms reload

# 8. Ver logs del servidor
tail -n 200 server.log | grep -i ultimate
```

---

## Reportar un Bug

Al abrir un issue incluye:

1. **Versión de PocketMine-MP** — ejecutar `/status` en consola
2. **Versión de PHP** — `php -v`
3. **Backend de almacenamiento** — `json` / `yaml` / `sqlite` / `mysql`
4. **Últimas 100 líneas del log** — `tail -n 100 server.log | grep -i ultimate`
5. **Pasos exactos para reproducir**
6. **Salida de** `/uperms check <jugador> <permiso>` si aplica
7. **Salida de** `/uperms user <jugador> info` si aplica
