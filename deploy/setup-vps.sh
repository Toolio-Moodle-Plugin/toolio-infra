#!/bin/bash
# ============================================================
# setup-vps.sh – Einmaliges Setup des VPS für Toolio-Deployments
# Als root ausführen: sudo bash setup-vps.sh
# ============================================================
set -euo pipefail

echo "=============================================="
echo "🔧 Toolio VPS-Setup"
echo "=============================================="

# 1. Deploy-User anlegen
if id "toolio-deploy" &>/dev/null; then
  echo "ℹ️  User toolio-deploy existiert bereits"
else
  useradd -m -s /bin/bash toolio-deploy
  echo "✅ User toolio-deploy angelegt"
fi

# 2. SSH-Verzeichnis vorbereiten
SSH_DIR="/home/toolio-deploy/.ssh"
mkdir -p "$SSH_DIR"
touch "$SSH_DIR/authorized_keys"
chmod 700 "$SSH_DIR"
chmod 600 "$SSH_DIR/authorized_keys"
chown -R toolio-deploy:toolio-deploy "$SSH_DIR"
echo "✅ SSH-Verzeichnis bereit"

# 3. Docker-Gruppe
usermod -aG docker toolio-deploy
echo "✅ toolio-deploy zur docker-Gruppe hinzugefügt"

# 4. Arbeitsverzeichnisse
mkdir -p /opt/toolio/deploy
mkdir -p /opt/toolio/staging
mkdir -p /opt/toolio/backups
chown -R toolio-deploy:toolio-deploy /opt/toolio/staging
chown -R toolio-deploy:toolio-deploy /opt/toolio/backups
chmod 755 /opt/toolio/deploy
echo "✅ Verzeichnisse angelegt"

# 5. Sudoers (nur rsync und chown – kein volles sudo)
SUDOERS_FILE="/etc/sudoers.d/toolio-deploy"
cat > "$SUDOERS_FILE" << 'EOF'
# toolio-deploy darf rsync und chown für Moodle-Deployments
toolio-deploy ALL=(root) NOPASSWD: /usr/bin/rsync
toolio-deploy ALL=(root) NOPASSWD: /usr/bin/chown
EOF
chmod 440 "$SUDOERS_FILE"
echo "✅ Sudoers-Regel erstellt"

echo ""
echo "=============================================="
echo "✅ Setup abgeschlossen!"
echo ""
echo "Nächste Schritte:"
echo "1. deploy.conf, deploy.sh, rollback.sh nach /opt/toolio/deploy/ kopieren"
echo "2. chmod +x /opt/toolio/deploy/deploy.sh /opt/toolio/deploy/rollback.sh"
echo "3. Den öffentlichen SSH-Schlüssel aus GitHub Actions eintragen:"
echo "   echo 'DEIN_PUBLIC_KEY' >> /home/toolio-deploy/.ssh/authorized_keys"
echo "=============================================="
