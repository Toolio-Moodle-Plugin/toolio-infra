#!/bin/bash
# ============================================================
# deploy.sh – Moodle-Plugin auf dem VPS deployen
# Aufruf: /opt/toolio/deploy/deploy.sh <plugin_component>
# Beispiel: /opt/toolio/deploy/deploy.sh block_toolio
# ============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/deploy.conf"

PLUGIN_COMPONENT="${1:?Fehler: Plugin-Komponente fehlt. Beispiel: block_toolio}"

# --- Plugin-Typ und Namen ableiten ---
PREFIX="${PLUGIN_COMPONENT%%_*}"
NAME="${PLUGIN_COMPONENT#*_}"

# --- Moodle-Unterverzeichnis bestimmen ---
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
    echo "   Unterstützte Typen: block, mod, local, theme, auth, report, availability," >&2
    echo "   enrol, repository, filter, question, editor, tiny, assignfeedback, assignsubmission" >&2
    exit 1
    ;;
esac

DEST="$VOLUME_PATH/$SUBDIR"
STAGING="/opt/toolio/staging/$PLUGIN_COMPONENT"
BACKUP_BASE="/opt/toolio/backups/$PLUGIN_COMPONENT"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo "=============================================="
echo "🚀 Deploy: $PLUGIN_COMPONENT"
echo "   Staging:  $STAGING"
echo "   Ziel:     $DEST"
echo "=============================================="

# --- Staging-Verzeichnis prüfen ---
if [ ! -d "$STAGING" ]; then
  echo "❌ Staging-Verzeichnis nicht gefunden: $STAGING" >&2
  exit 1
fi

# Pfad des Plugins INNERHALB des Containers (dort lesbar, das Host-Volume ist root-only)
CONTAINER_PATH="$MOODLE_ROOT/public/$SUBDIR"

# --- Version VOR dem Deploy lesen (über den Container) ---
OLD_VERSION=$(docker exec "$CONTAINER_NAME" sh -c "grep -oP '\\\$plugin->version\\s*=\\s*\\K[0-9]+' '$CONTAINER_PATH/version.php' 2>/dev/null" || echo "0")
[ -z "$OLD_VERSION" ] && OLD_VERSION="0"

# --- Backup erstellen (nur wenn Plugin bereits existiert) ---
echo "📦 Erstelle Backup..."
mkdir -p "$BACKUP_BASE"
if docker exec "$CONTAINER_NAME" test -d "$CONTAINER_PATH"; then
  sudo rsync -a "$DEST/" "$BACKUP_BASE/$TIMESTAMP/"
  echo "   Gespeichert: $BACKUP_BASE/$TIMESTAMP"
else
  echo "   (Kein vorheriges Plugin – kein Backup nötig)"
fi

# --- Plugin deployen (rsync mit sudo, legt Zielverzeichnis selbst an) ---
echo "📂 Synchronisiere Dateien nach $DEST ..."
sudo rsync -av --delete "$STAGING/" "$DEST/"
sudo chown -R 33:33 "$DEST"   # 33 = www-data UID

# --- Version NACH dem Deploy lesen (über den Container) ---
NEW_VERSION=$(docker exec "$CONTAINER_NAME" sh -c "grep -oP '\\\$plugin->version\\s*=\\s*\\K[0-9]+' '$CONTAINER_PATH/version.php' 2>/dev/null" || echo "0")
[ -z "$NEW_VERSION" ] && NEW_VERSION="0"

echo ""
echo "   Alte Version: $OLD_VERSION"
echo "   Neue Version: $NEW_VERSION"

# --- Moodle Upgrade (nur wenn Version geändert) ---
if [ "$OLD_VERSION" != "$NEW_VERSION" ]; then
  echo "⬆️  Neue Version – starte Moodle Upgrade..."
  docker exec "$CONTAINER_NAME" php "$MOODLE_ROOT/admin/cli/upgrade.php" --non-interactive
else
  echo "✅ Version unverändert – Upgrade übersprungen"
fi

# --- Cache immer leeren (schnell, schadet nie) ---
echo "🧹 Leere Moodle-Cache..."
docker exec "$CONTAINER_NAME" php "$MOODLE_ROOT/admin/cli/purge_caches.php"

echo ""
echo "✅ Deploy abgeschlossen: $PLUGIN_COMPONENT"
