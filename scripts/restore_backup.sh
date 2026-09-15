#!/bin/bash
# =========================================
# Restaurar desde backup
# Uso: ./restore_backup.sh archivo_backup.sql.gz
# =========================================

set -e

if [ -z "$1" ]; then
    echo "Uso: $0 <archivo_backup.sql.gz>"
    exit 1
fi

BACKUP_FILE="$1"
DB_NAME="horion_time"
DB_USER="root"

echo "⚠️  ATENCIÓN: Esto sobrescribirá la base de datos '$DB_NAME'"
read -p "¿Continuar? (s/N): " confirm
if [[ ! "$confirm" =~ ^[sS]$ ]]; then
    echo "Operación cancelada."
    exit 0
fi

echo "Restaurando desde $BACKUP_FILE..."

if [[ "$BACKUP_FILE" == *.gz ]]; then
    gunzip -c "$BACKUP_FILE" | mysql -u "$DB_USER" "$DB_NAME"
else
    mysql -u "$DB_USER" "$DB_NAME" < "$BACKUP_FILE"
fi

echo "[OK] Restauración completada"