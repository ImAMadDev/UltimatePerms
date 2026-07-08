# Installation Guide

## Requisitos

- **PocketMine-MP** API **5.0.0** o superior
- **PHP 8.1+**

## Instalación

1. Copiar la carpeta `UltimatePerms/` a `plugins/`
2. Iniciar el servidor — el plugin crea `config.yml` automáticamente

   ```
   plugins/
   ├── UltimatePerms/
   │   ├── src/
   │   ├── resources/
   │   ├── plugin.yml
   │   └── ...
   └── OtroPlugin/
   ```

3. Verificar instalación:

   ```bash
   /uperms
   # Debe mostrar la ayuda del plugin
   ```

---

## Configuración — `config.yml`

El archivo se crea en `plugins/UltimatePerms/config.yml` con los valores por defecto:

```yaml
# ── Chat & Display ────────────────────────────────────────────────────
# Placeholders: {prefix} {suffix} {name} {displayname}
#               {message} {group} {group_display} {chat_color}

# Formato del mensaje en el chat
chat:
  enabled: true
  format: "{prefix}{name}{suffix}§r: {chat_color}{message}"

# Formato del nombre sobre la cabeza y en el tablist
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

### Notas sobre las claves

- `chat.format` / `nametag.format` — plantillas de texto con placeholders
- `chat.enabled`, `nametag.enabled`, `displayName.enabled` — activan cada funcionalidad
- `locale` — nombre del archivo de locale en `resources/locale/` (ej: `en_US`, `es_ES`)
- `storage.type` — motor de almacenamiento: `json` | `yaml` | `sqlite` | `mysql`
- `storage.mysql.*` — solo se usa si `type: mysql`

---

## Elegir Backend de Almacenamiento

### JSON (por defecto)

```yaml
storage:
  type: json
```

Sin configuración adicional. Los grupos se guardan en `plugins/UltimatePerms/groups/` (un archivo por grupo) y los jugadores en `plugins/UltimatePerms/players/` (un archivo por XUID).

**Recomendado para:** servidores pequeños, desarrollo, testing.

---

### YAML

```yaml
storage:
  type: yaml
```

Equivalente a JSON en formato YAML. Los archivos de grupos y jugadores usan extensión `.yml`.

---

### SQLite

```yaml
storage:
  type: sqlite
```

Sin configuración adicional. Crea un archivo único en `plugins/UltimatePerms/`.

**Recomendado para:** servidores medianos.

---

### MySQL

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

**Prerequisitos:**

```sql
CREATE DATABASE ultimateperms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ultimateperms'@'localhost' IDENTIFIED BY 'contraseña-segura';
GRANT ALL PRIVILEGES ON ultimateperms.* TO 'ultimateperms'@'localhost';
FLUSH PRIVILEGES;
```

**Recomendado para:** servidores grandes, redes de servidores.

---

## Post-Instalación

### 1. El grupo `default` se crea solo

Al arrancar sin ningún grupo en storage, el plugin crea automáticamente el grupo `default` (weight=0). Este grupo **no puede eliminarse**.

### 2. Crear tus grupos

```bash
/uperms group vip      create "§b[VIP]"   30
/uperms group moderator create "§2[MOD]"  50
/uperms group admin    create "§c[ADMIN]" 100
```

### 3. Configurar jerarquía

```bash
/uperms group vip      parent set default
/uperms group moderator parent set vip
/uperms group admin    parent set moderator
```

### 4. Agregar permisos

```bash
/uperms group vip      perm set essentials.fly  true
/uperms group vip      perm set essentials.nick true
/uperms group moderator perm set essentials.kick true
/uperms group moderator perm set essentials.ban  true
/uperms group admin    perm set pocketmine.command.* true
```

### 5. Asignar a jugadores

```bash
/uperms user John group set vip          # Permanente
/uperms user John group set vip 7d       # 7 días
```

### 6. Verificar

```bash
/uperms user John info
/uperms check John essentials.fly
```

---

## Localización

El idioma se configura con la clave `locale` en `config.yml`. Los archivos de mensajes están en `resources/locale/`.

Cambiar idioma:

```yaml
locale: es_ES   # Español
locale: en_US   # Inglés (por defecto)
```

---

## Troubleshooting de Instalación

### Plugin no carga

```bash
# Ver logs
tail -n 100 server.log | grep -i ultimate

# Verificar PHP
php -v   # debe ser 8.1+

# Verificar permisos de carpeta
chmod -R 755 plugins/UltimatePerms/
```

### Error de almacenamiento MySQL

```bash
# Probar conexión
mysql -h 127.0.0.1 -u ultimateperms -p ultimateperms

# Verificar que MySQL está corriendo
sudo systemctl status mysql
```

---

## Próximos Pasos

- [Comandos](Commands.md) — Referencia completa
- [Grupos](Groups.md) — Gestión de grupos
- [Permisos](Permissions.md) — Sistema de permisos
- [Almacenamiento](Storage.md) — Detalles de cada backend
- [API](API.md) — Integración con otros plugins
