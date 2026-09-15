# 🕐 HORION TIME - Sistema de Control de Asistencia

![Version](https://img.shields.io/badge/version-1.0.0-green)
![PHP](https://img.shields.io/badge/PHP-8.2+-blue)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange)
![License](https://img.shields.io/badge/license-Proprietary-red)

**HORION TIME** es un sistema empresarial de control de asistencia con biometría facial, geolocalización, motor de reglas configurable y API REST. Diseñado con una interfaz futurista 3D inspirada en Clockify.

## 📋 Tabla de Contenidos

- [Características](#-características)
- [Requisitos](#-requisitos)
- [Instalación](#-instalación)
- [Configuración](#-configuración)
- [Uso](#-uso)
- [API REST](#-api-rest)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Seguridad](#-seguridad)
- [Soporte](#-soporte)

## ✨ Características

### Módulos Implementados

| # | Módulo | Descripción |
|---|--------|-------------|
| 1 | **Autenticación** | Login, roles, permisos, CSRF |
| 2 | **Empresas y Sedes** | Multi-empresa, multi-sede |
| 3 | **Marcación Biométrica** | Foto, facial, QR, GPS |
| 4 | **Horarios y Turnos** | Calendario drag & drop |
| 5 | **Novedades** | Vacaciones, incapacidades, permisos |
| 6 | **Horas Extras** | Motor de recargos configurable |
| 7 | **Dashboard** | Estadísticas en tiempo real |
| 8 | **Reportes** | Excel, PDF, CSV |
| 9 | **Auditoría** | Logs completos del sistema |
| 10 | **Notificaciones** | Email, push, in-app |
| 11 | **Portal Empleado** | Autogestión |
| 12 | **Portal Supervisor** | Aprobación rápida |
| 13 | **Configuración** | Motor de reglas JSON |
| 14 | **Kioscos** | Modo tablet dedicado |
| 15 | **API REST** | JWT, rate limiting |

### Características Técnicas

- ✅ **100% PHP Nativo** - Sin dependencias de Composer
- ✅ **MySQL 8.0+** con índices optimizados
- ✅ **JWT** para autenticación API
- ✅ **Docker Ready** con docker-compose
- ✅ **HTTPS** forzado con HSTS
- ✅ **Responsive** y mobile-first
- ✅ **i18n** (Español/Inglés)
- ✅ **Backup** automático

## 📦 Requisitos

### Opción A: Instalación Tradicional

- PHP 8.2 o superior
- MySQL 8.0 o MariaDB 10.5+
- Apache 2.4+ con `mod_rewrite` habilitado
- Extensiones PHP: `pdo_mysql`, `gd`, `json`, `mbstring`, `curl`
- Certificado SSL (Let's Encrypt recomendado)

### Opción B: Docker (Recomendado)

- Docker 20.10+
- Docker Compose 2.0+
- 2GB RAM mínimo
- 10GB disco

## 🚀 Instalación

### Método 1: Docker (Recomendado)

```bash
# 1. Clonar repositorio
git clone https://github.com/tu-usuario/horion-time.git
cd horion-time

# 2. Configurar variables de entorno
cp .env.example .env
nano .env  # Editar contraseñas y secretos

# 3. Levantar contenedores
docker-compose up -d

# 4. Ejecutar migraciones
docker-compose exec app php scripts/migrate.php

# 5. Acceder
# App: http://localhost:8080
# phpMyAdmin: http://localhost:8081 (solo perfil dev)
```

### Método 2: Instalación Manual

```bash
# 1. Clonar en document root
cd /var/www/html
git clone https://github.com/tu-usuario/horion-time.git horion-time
cd horion-time

# 2. Crear base de datos
mysql -u root -p
CREATE DATABASE horion_time CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'horion_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';
GRANT ALL PRIVILEGES ON horion_time.* TO 'horion_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# 3. Ejecutar scripts SQL en orden
mysql -u horion_user -p horion_time < database/schema_part1.sql
mysql -u horion_user -p horion_time < database/schema_part2.sql
# ... repetir hasta schema_part15.sql
mysql -u horion_user -p horion_time < database/seeds.sql

# 4. Configurar permisos
chown -R www-data:www-data /var/www/html/horion-time
chmod -R 755 /var/www/html/horion-time
chmod -R 777 /var/www/html/horion-time/public/uploads

# 5. Configurar Apache VirtualHost
cp docker/apache.conf /etc/apache2/sites-available/horion.conf
nano /etc/apache2/sites-available/horion.conf  # Ajustar ServerName
a2ensite horion.conf
a2enmod rewrite headers
systemctl reload apache2

# 6. Configurar certificado SSL
certbot --apache -d app.horiontime.co

# 7. Configurar variables de entorno
cp .env.example .env
nano .env  # Editar credenciales
```

## ⚙️ Configuración

### Variables de Entorno Críticas

```env
# Generar secretos seguros
JWT_SECRET=$(openssl rand -hex 32)
CSRF_SECRET=$(openssl rand -hex 16)
DB_PASSWORD=$(openssl rand -base64 24)
```

### Cron Jobs (Producción)

```bash
# Editar crontab
crontab -e

# Agregar:
# Backup diario a las 2 AM
0 2 * * * /var/www/html/horion-time/scripts/backup_database.sh

# Procesar cola de notificaciones cada 5 minutos
*/5 * * * * php /var/www/html/horion-time/cron/process_notifications.php

# Limpiar logs antiguos diariamente
0 3 * * * php /var/www/html/horion-time/cron/cleanup_logs.php

# Generar alertas automáticas cada 10 minutos
*/10 * * * * php /var/www/html/horion-time/cron/generate_alerts.php
```

## 📱 Uso

### Credenciales Iniciales

| Rol | Email | Contraseña |
|-----|-------|------------|
| SuperAdmin | admin@horiontime.co | admin123 |
| Admin Empresa | empresa@horiontime.co | admin123 |
| Supervisor | jefe@horiontime.co | admin123 |
| Empleado | empleado@horiontime.co | admin123 |

**⚠️ IMPORTANTE**: Cambiar todas las contraseñas inmediatamente después del primer login.

### Acceso por Rol

- **SuperAdmin**: Gestión multiempresa, configuración global
- **Admin Empresa**: Gestión completa de una empresa
- **Supervisor**: Aprobación de novedades/horas extras de su equipo
- **Empleado**: Portal de autogestión

## 🔌 API REST

### Autenticación

```bash
# Login
curl -X POST https://app.horiontime.co/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@horiontime.co","password":"admin123"}'

# Respuesta
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 604800
  }
}
```

### Endpoints Disponibles

| Método | Endpoint | Descripción | Auth |
|--------|----------|-------------|------|
| POST | `/api/auth/login` | Login | ❌ |
| POST | `/api/auth/refresh` | Refrescar token | ✅ |
| POST | `/api/auth/registro-marcacion` | Marcar asistencia | ✅ |
| GET | `/api/usuarios/{id}/asistencia` | Obtener asistencia | ✅ |
| GET | `/api/reportes/asistencia` | Reporte general | ✅ |

Ver documentación completa en `docs/api-collection.json`.

## 📁 Estructura del Proyecto

```
horion-time/
├── app/
│   ├── Config/          # Configuración DB
│   ├── Controllers/     # Controladores MVC
│   │   └── Api/         # Controladores API REST
│   ├── Helpers/         # JWT, Security, Audit, I18n
│   ├── Lang/            # Traducciones (es, en)
│   ├── Middleware/      # ApiAuth, RateLimit, CORS
│   ├── Models/          # Modelos de datos
│   ├── Services/        # NotificationEngine, AlertRules
│   └── Views/           # Vistas PHP
│       ├── layouts/     # Header, sidebar, footer
│       ├── dashboard/
│       ├── asistencia/
│       ├── novedades/
│       └── ...
├── database/
│   ├── schema_part1-15.sql
│   └── seeds.sql
├── docs/
│   └── api-collection.json
├── public/
│   ├── index.php        # Router principal
│   ├── api.php          # Router API
│   ├── .htaccess        # Seguridad + rewrites
│   ├── assets/
│   │   ├── css/horion-style.css
│   │   └── js/horion-app.js
│   └── uploads/         # Archivos de usuarios
├── scripts/
│   ├── backup_database.sh
│   ├── backup_database.bat
│   └── restore_backup.sh
├── tests/
│   └── Unit/
├── cron/                # Scripts para cron jobs
├── docker/              # Configs Docker
├── Dockerfile
├── docker-compose.yml
├── .env.example
└── README.md
```

## 🔒 Seguridad

### Checklist de Producción

- [x] HTTPS forzado con HSTS
- [x] Headers de seguridad (OWASP)
- [x] CSRF tokens en todos los formularios
- [x] Passwords con bcrypt (cost 10)
- [x] Prepared statements (anti SQL injection)
- [x] Sanitización de inputs (anti XSS)
- [x] Rate limiting en API
- [x] JWT con firma HS256
- [x] Validación de archivos (tipo, tamaño)
- [x] Auditoría completa de acciones críticas
- [x] Backups automáticos diarios
- [x] Variables de entorno para secretos
- [x] Archivos sensibles bloqueados (.env, .sql)

### Reportar Vulnerabilidades

Si encuentras una vulnerabilidad, envía un email a **security@horiontime.co** con:
- Descripción del problema
- Pasos para reproducir
- Impacto potencial

**No abras issues públicos para vulnerabilidades.**

## 📞 Soporte

- **Documentación**: `docs/`
- **Issues**: GitHub Issues
- **Email**: soporte@horiontime.co
- **Teléfono**: +57 300 123 4567

## 📄 Licencia

Copyright © 2026 Horion Time S.A.S. Todos los derechos reservados.

---

**Desarrollado con ❤️ por el equipo Horion Time**#   h o r i o n - t i m e  
 