#!/bin/bash
# ============================================================
# rollback.sh – Letztes Backup eines Plugins wiederherstellen
# Aufruf: /opt/toolio/deploy/rollback.sh <plugin_component>
# Beispiel: /opt/toolio/deploy/rollback.sh block_toolio
# ============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/deploy.conf"

PLUGIN_COMPONENT="${1:?Fehler: Plugin-Komponente fehlt. Beispiel: block_toolio}"

PREFIX="${PLUGIN_COMPONENT%%_*}"
NAME="${PLUGIN_COMPONENT#*_}"

case "$PREFIX" in
  block)            SUBDIR="blocks/$NAME"                        ;;
  mod)              SUBDIR="mod/$NAME"                           ;;
  local)            SUBDIR="local/$NAME"                        ;;
  theme)            SUBDIR="theme/$NAME"                         ;;
  auth)             SUBDIR="auth/$NAME"                          ;;
  report)           SUBDIR="report/$NAME"                        ;;
  availability)     SUBDIR="availability/condition/$NAME"        ;;
  enrol)            SUBDIR="enrol/$NAME"                         ;;
  repository)       SUBDIR="repository/$NAME"                    ;;
  filter)           SUBDIR="filter/$NAME"                        ;;
  question)         SUBDIR="question/type/$NAME"                 ;;
  editor)           SUBDIR="lib/editor/$NAME"                    ;;
  tiny)             SUBDIR="lib/editor/tiny/plugins/$NAME"       ;;
  assignfeedback)   SUBDIR="mod/assign/feedback/$NAME"           ;;
  assignsubmission) SUBDIR="mod/assign/submission/$NAME"         ;;
  *)
    echo "❌ Unbekannter Plugin-Typ: '$PREFIX'" >&2
    exit 1
    ;;
esac

DEST="$VOLUME_PATH/$SUBDIR"
BACKUP_BASE="/opt/toolio/backups/$PLUGIN_COMPONENT"

# --- Letztes Backup finden ---
LAST_BACKUP=$(ls -t "$BACKUP_BASE" 2>/dev/null | head -1)
if [ -z "$LAST_BACKUP" ]; then
  echo "❌ Kein Backup gefunden für: $PLUGIN_COMPONENT" >&2
  echo "   Pfad: $BACKUP_BASE" >&2
  exit 1
fi

echo "=============================================="
echo "⏪ Rollback: $PLUGIN_COMPONENT"
echo "   Backup:  $BACKUP_BASE/$LAST_BACKUP"
echo "   Ziel:    $DEST"
echo "=============================================="

sudo rsync -av --delete "$BACKUP_BASE/$LAST_BACKUP/" "$DEST/"
sudo chown -R 33:33 "$DEST"

echo "⬆️  Moodle Upgrade nach Rollback..."
docker exec "$CONTAINER_NAME" php "$MOODLE_ROOT/admin/cli/upgrade.php" --non-interactive

echo "🧹 Cache leeren..."
docker exec "$CONTAINER_NAME" php "$MOODLE_ROOT/admin/cli/purge_caches.php"

echo ""
echo "✅ Rollback abgeschlossen: $PLUGIN_COMPONENT → $LAST_BACKUP"
