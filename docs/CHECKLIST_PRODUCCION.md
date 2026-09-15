# ✅ Checklist de Despliegue en Producción

## Pre-Despliegue

### Configuración
- [ ] Variables de entorno configuradas en `.env`
- [ ] `JWT_SECRET` cambiado (mínimo 64 caracteres aleatorios)
- [ ] `CSRF_SECRET` cambiado
- [ ] Contraseñas de BD fuertes (mínimo 20 caracteres)
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] Timezone configurado: `America/Bogota`

### Base de Datos
- [ ] Todos los scripts SQL ejecutados en orden (1-15)
- [ ] Seeds ejecutados
- [ ] Usuario de BD con permisos limitados (no root)
- [ ] Backups automáticos configurados
- [ ] Prueba de restauración de backup realizada

### Seguridad
- [ ] Certificado SSL instalado y válido
- [ ] HTTPS forzado (redirect 301)
- [ ] HSTS habilitado
- [ ] Headers de seguridad verificados (securityheaders.com)
- [ ] `mod_rewrite` y `mod_headers` activos
- [ ] Archivos sensibles bloqueados (.env, .sql, .md)
- [ ] Directorios `app/` y `database/` no accesibles vía web
- [ ] Permisos correctos: `uploads/` escribible, resto solo lectura

### Rendimiento
- [ ] GZIP habilitado
- [ ] Cache de assets estáticos configurado
- [ ] Índices de BD creados
- [ ] `opcache` habilitado en PHP
- [ ] `memory_limit` ≥ 256M
- [ ] `upload_max_filesize` ≥ 16M

### Funcionalidad
- [ ] Login funciona correctamente
- [ ] Marcación biométrica (cámara) funciona
- [ ] Geolocalización GPS funciona
- [ ] Upload de archivos funciona
- [ ] Exportación Excel/PDF/CSV funciona
- [ ] API REST responde correctamente
- [ ] Notificaciones por email funcionan
- [ ] Cron jobs ejecutándose

### Monitoreo
- [ ] Logs de errores configurados (`/var/log/php_errors.log`)
- [ ] Logs de acceso Apache configurados
- [ ] Alertas de backup configuradas
- [ ] Monitoreo de uptime (UptimeRobot, Pingdom)
- [ ] Métricas de rendimiento (New Relic, Datadog)

### Documentación
- [ ] README.md actualizado
- [ ] API Collection documentada
- [ ] Manual de usuario entregado
- [ ] Capacitación al cliente realizada

## Post-Despliegue

### Primeros 7 días
- [ ] Monitoreo diario de errores
- [ ] Verificar que backups se generen
- [ ] Revisar logs de auditoría
- [ ] Validar performance con carga real
- [ ] Soporte activo al cliente

### Primer mes
- [ ] Revisión de seguridad completa
- [ ] Optimización de queries lentas
- [ ] Ajuste de rate limits según uso real
- [ ] Documentación de incidentes (si los hubo)
- [ ] Plan de mantenimiento mensual establecido

## Comandos de Verificación Rápida

```bash
# Verificar SSL
curl -I https://app.horiontime.co

# Verificar headers de seguridad
curl -I https://app.horiontime.co | grep -E "Strict-Transport|X-Frame|X-Content"

# Verificar API
curl https://app.horiontime.co/api/auth/login -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@horiontime.co","password":"admin123"}'

# Verificar permisos de uploads
ls -la public/uploads/

# Verificar cron jobs
crontab -l

# Verificar logs
tail -f /var/log/apache2/error.log
tail -f /var/log/php_errors.log