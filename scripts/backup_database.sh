#!/bin/bash
# =========================================
# HORION TIME - Script de Backup Automático
# Ejecutar vía cron: 0 2 * * * /path/to/backup_database.sh
# =========================================

set -e

# Configuración
DB_NAME="horion_time"
DB_USER="root"
DB_PASS=""
BACKUP_DIR="/var/backups/horion_time"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Crear directorio si no existe
mkdir -p "$BACKUP_DIR"

echo "[$(date)] Iniciando backup de $DB_NAME..."

# Backup de base de datos
BACKUP_FILE="$BACKUP_DIR/horion_time_$DATE.sql.gz"
mysqldump -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --set-gtid-purged=OFF \
    "$DB_NAME" | gzip > "$BACKUP_FILE"

# Verificar que el backup no esté vacío
if [ ! -s "$BACKUP_FILE" ]; then
    echo "[ERROR] Backup vacío o fallido"
    exit 1
fi

# Backup de archivos de usuario (uploads)
UPLOADS_BACKUP="$BACKUP_DIR/uploads_$DATE.tar.gz"
tar -czf "$UPLOADS_BACKUP" -C /var/www/horion-time/public/uploads . 2>/dev/null || true

# Eliminar backups antiguos
find "$BACKUP_DIR" -type f -mtime +$RETENTION_DAYS -delete

# Calcular tamaño
SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
UPLOADS_SIZE=$(du -h "$UPLOADS_BACKUP" 2>/dev/null | cut -f1 || echo "0")

echo "[OK] Backup completado:"
echo "  - DB: $BACKUP_FILE ($SIZE)"
echo "  - Uploads: $UPLOADS_BACKUP ($UPLOADS_SIZE)"
echo "  - Retención: $RETENTION_DAYS días"

# Opcional: Subir a S3/GCS
# aws s3 cp "$BACKUP_FILE" "s3://horion-backups/db/$DATE.sql.gz"

# Notificar por email (si hay error)
if [ $? -ne 0 ]; then
    echo "Backup fallido en $(hostname)" | mail -s "ALERTA: Backup Horion Time" admin@horiontime.co
fi