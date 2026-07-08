# Backends de Almacenamiento

## Comparación

| | JSON | YAML | SQLite | MySQL |
|---|---|---|---|---|
| **Configuración** | Ninguna | Ninguna | Ninguna | Requiere servidor MySQL |
| **Ideal para** | Desarrollo, pequeños | Desarrollo | Medianos | Grandes, redes |
| **Múltiples servidores** | ❌ | ❌ | ❌ | ✅ |
| **Archivos generados** | Uno por entidad | Uno por entidad | Un `.db` único | Tablas en BD |

---

## Configuración en `config.yml`

La clave `storage.type` acepta: `json` | `yaml` | `sqlite` | `mysql`.

```yaml
storage:
  type: json   # Cambiar aquí
```

---

## JSON

### Descripción

Almacena cada grupo y cada jugador en un archivo `.json` independiente.

### Estructura en disco

```
plugins/UltimatePerms/
├── groups/
│   ├── default.json
│   ├── vip.json
│   └── admin.json
└── players/
    ├── <XUID1>.json
    └── <XUID2>.json
```

### Formato de archivo de grupo

```json
{
  "id": "admin",
  "displayName": "§c[Admin]",
  "weight": 100,
  "parent": "moderator",
  "nodes": [
    {
      "permission": "pocketmine.command.*",
      "state": true,
      "expiresAt": null,
      "context": {}
    },
    {
      "permission": "essentials.command.ban",
      "state": false,
      "expiresAt": null,
      "context": {"world": "pvp"}
    }
  ],
  "prefix": "§c[ADMIN] §r",
  "suffix": " §6★§r",
  "meta": {
    "chat-color": "§c"
  }
}
```

### Formato de archivo de jugador

```json
{
  "xuid": "1234567890123456",
  "username": "Steve",
  "groups": {
    "admin": null,
    "vip-temp": 1704715200
  },
  "nodes": [
    {
      "permission": "essentials.fly",
      "state": false,
      "expiresAt": null,
      "context": {"world": "pvp"}
    }
  ],
  "prefix": "",
  "suffix": "",
  "meta": {}
}
```

> El valor de `groups` es un objeto `{ groupId: expiresAt|null }`. `null` = permanente; entero = Unix timestamp de expiración.

### Configuración

```yaml
storage:
  type: json
```

**Ventajas:** sin dependencias, archivos legibles y editables manualmente.  
**Desventajas:** más lento con muchos jugadores, difícil de hacer consultas globales.

---

## YAML

### Descripción

Equivalente a JSON en formato YAML. Los archivos usan extensión `.yml`.

### Configuración

```yaml
storage:
  type: yaml
```

**Ventajas:** más legible que JSON para edición manual.  
**Desventajas:** mismo rendimiento que JSON.

---

## SQLite

### Descripción

Base de datos SQLite embebida. Un único archivo `.db` contiene todos los datos.

### Configuración

```yaml
storage:
  type: sqlite
```

Sin configuración adicional. El archivo se crea automáticamente en la carpeta de datos del plugin.

### Ventajas y desventajas

**Ventajas:** mejor rendimiento que JSON/YAML para búsquedas, sin servidor externo.  
**Desventajas:** no apto para múltiples instancias de PocketMine accediendo al mismo archivo.

---

## MySQL

### Descripción

Base de datos MySQL externa. Ideal para redes de servidores donde múltiples instancias deben compartir los mismos datos.

### Configuración

```yaml
storage:
  type: mysql
  mysql:
    host: 127.0.0.1
    port: 3306
    user: ultimateperms
    password: "contraseña-segura"
    database: ultimateperms
```

**Claves disponibles:** `host`, `port`, `user`, `password`, `database`.

### Setup inicial de la base de datos

```sql
CREATE DATABASE ultimateperms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ultimateperms'@'localhost' IDENTIFIED BY 'contraseña-segura';
GRANT ALL PRIVILEGES ON ultimateperms.* TO 'ultimateperms'@'localhost';
FLUSH PRIVILEGES;
```

El plugin crea las tablas automáticamente al iniciar.

### MySQL en servidor remoto

```yaml
storage:
  type: mysql
  mysql:
    host: 192.168.1.50   # IP del servidor MySQL
    port: 3306
    user: ultimateperms
    password: "contraseña"
    database: ultimateperms
```

El usuario debe tener permisos de acceso desde la IP del servidor PocketMine:

```sql
CREATE USER 'ultimateperms'@'192.168.1.100' IDENTIFIED BY 'contraseña';
GRANT ALL PRIVILEGES ON ultimateperms.* TO 'ultimateperms'@'192.168.1.100';
FLUSH PRIVILEGES;
```

**Ventajas:** sincronización entre servidores, backups profesionales, escalable.  
**Desventajas:** requiere configuración y servidor MySQL disponible.

---

## Cambiar de Backend

No existe un comando de migración automática. El proceso manual es:

1. Hacer backup de los datos actuales (carpeta `plugins/UltimatePerms/` o dump de BD)
2. Exportar los datos con el backend actual (archivos o dump SQL)
3. Cambiar `storage.type` en `config.yml`
4. Iniciar el servidor — el plugin iniciará con storage vacío
5. Reintroducir los grupos manualmente con `/uperms group <n> create ...` o importar los archivos del nuevo formato

> **Nota:** No existe `/uperms migrate`. Las migraciones se hacen manualmente.

---

## Backup

### JSON / YAML

```bash
# Copia de seguridad de todo el directorio de datos
cp -r plugins/UltimatePerms/groups/  backups/groups_$(date +%Y%m%d)/
cp -r plugins/UltimatePerms/players/ backups/players_$(date +%Y%m%d)/
```

### SQLite

```bash
cp plugins/UltimatePerms/*.db backups/ultimateperms_$(date +%Y%m%d).db

# Verificar integridad
sqlite3 plugins/UltimatePerms/*.db "PRAGMA integrity_check;"
```

### MySQL

```bash
# Dump completo
mysqldump -u ultimateperms -p ultimateperms > ultimateperms_$(date +%Y%m%d).sql

# Dump comprimido
mysqldump -u ultimateperms -p ultimateperms | gzip > ultimateperms_$(date +%Y%m%d).sql.gz

# Restaurar
mysql -u ultimateperms -p ultimateperms < ultimateperms_YYYYMMDD.sql
```

---

## Troubleshooting

### Error: "Cannot write to storage" (JSON/YAML/SQLite)

```bash
# Verificar permisos de carpeta
chmod -R 755 plugins/UltimatePerms/

# Verificar espacio en disco
df -h
```

### Error: "Access denied" (MySQL)

```bash
# Probar conexión manual
mysql -h 127.0.0.1 -u ultimateperms -p ultimateperms

# Recrear usuario si es necesario
mysql -u root -p
DROP USER 'ultimateperms'@'localhost';
CREATE USER 'ultimateperms'@'localhost' IDENTIFIED BY 'nueva_contraseña';
GRANT ALL PRIVILEGES ON ultimateperms.* TO 'ultimateperms'@'localhost';
FLUSH PRIVILEGES;
```

### Error: "Cannot connect to MySQL"

```bash
# Verificar que el servicio está activo
sudo systemctl status mysql

# Verificar host y puerto en config.yml
# Verificar que el firewall permite el puerto 3306
```

---

## Tabla de Selección

| Situación | Backend recomendado |
|-----------|---------------------|
| Desarrollo / testing | `json` |
| Servidor pequeño (< 50 jugadores) | `json` |
| Servidor mediano (50-500 jugadores) | `sqlite` |
| Servidor grande (> 500 jugadores) | `mysql` |
| Red de múltiples servidores | `mysql` |
