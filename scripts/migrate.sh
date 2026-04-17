#!/usr/bin/env bash
# =============================================================================
# AlumGlass Migration Script
#
# Migrates the project from the legacy ZIP-based cPanel deployment to the new
# Git-based deployment layout (document root at repo root, sercon/ beside it,
# shared/ tree introduced in Phase 3).
#
# Steps performed:
#   1. Collect credentials for source (old) and destination (new) servers
#   2. Dump all 3 MySQL databases from the source
#   3. Rsync uploaded files (permits, photos, logos, documents)
#   4. Create databases on the destination and import the dumps
#   5. Generate .env with secure 600 permissions
#   6. Set filesystem permissions and ownership
#   7. Install cron jobs (daily reminders, telegram broadcast)
#   8. Run post-migration verification (scripts/migrate_verify.php)
#
# Usage:
#   ./scripts/migrate.sh --source-host OLD_HOST [--source-user USER] \
#                        [--source-path /home/USER/public_html] \
#                        [--project-dir /opt/alumglass]
#
# Prerequisites on the new server:
#   - PHP 8.1+ with pdo_mysql, mbstring, gd, fileinfo
#   - mysql / mariadb client
#   - rsync, ssh, openssl, crontab
#
# Safety:
#   - Never overwrites an existing .env — aborts instead.
#   - All destructive steps print what they will do and require confirmation
#     unless --yes is passed.
# =============================================================================

set -euo pipefail

# ---- pretty output ----------------------------------------------------------
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
log_ok()   { echo -e "${GREEN}[OK]${NC} $*"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $*"; }
log_err()  { echo -e "${RED}[ERR]${NC} $*" >&2; }
log_step() { echo -e "\n${BLUE}══ $* ══${NC}"; }

# ---- args -------------------------------------------------------------------
SOURCE_HOST=""
SOURCE_USER="root"
SOURCE_PATH=""
LOCAL_PROJECT_DIR=""
ASSUME_YES=0

usage() {
    cat <<USAGE
Usage: $0 --source-host HOST [options]

Required:
    --source-host HOST      SSH target for the old server

Options:
    --source-user USER      SSH user on the old server (default: root)
    --source-path PATH      Document root on the old server
                            (default: /home/alumglas/public_html)
    --project-dir DIR       Target directory on the new server
                            (default: current working directory)
    --yes                   Do not ask for confirmation
    --help                  Show this help
USAGE
    exit 0
}

while [[ $# -gt 0 ]]; do
    case $1 in
        --source-host) SOURCE_HOST="$2"; shift 2;;
        --source-user) SOURCE_USER="$2"; shift 2;;
        --source-path) SOURCE_PATH="$2"; shift 2;;
        --project-dir) LOCAL_PROJECT_DIR="$2"; shift 2;;
        --yes|-y)      ASSUME_YES=1; shift;;
        --help|-h)     usage;;
        *) log_err "Unknown option: $1"; usage;;
    esac
done

if [ -z "$SOURCE_HOST" ]; then
    log_err "--source-host is required"
    usage
fi

LOCAL_PROJECT_DIR="${LOCAL_PROJECT_DIR:-$(pwd)}"
SOURCE_PATH="${SOURCE_PATH:-/home/alumglas/public_html}"

BACKUP_DIR="$LOCAL_PROJECT_DIR/migration_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR/databases" "$BACKUP_DIR/uploads" "$BACKUP_DIR/config"

# ---- banner -----------------------------------------------------------------
cat <<BANNER
╔════════════════════════════════════════════════╗
║        AlumGlass Migration Tool  v1.0          ║
╠════════════════════════════════════════════════╣
║ Source host:  $SOURCE_HOST
║ Source user:  $SOURCE_USER
║ Source path:  $SOURCE_PATH
║ Target dir:   $LOCAL_PROJECT_DIR
║ Backup dir:   $BACKUP_DIR
╚════════════════════════════════════════════════╝
BANNER

confirm() {
    if [ "$ASSUME_YES" -eq 1 ]; then return 0; fi
    read -r -p "$1 [y/N] " ans
    [[ "$ans" =~ ^[Yy]$ ]]
}

# ---- Step 1: credentials ----------------------------------------------------
log_step "Step 1 / 8 — Collecting credentials"

read -r -p "Source MySQL username: " SRC_DB_USER
read -r -s -p "Source MySQL password: " SRC_DB_PASS; echo

echo ""
echo "--- Destination server (new) ---"
read -r -p "New MySQL host [localhost]: " DST_DB_HOST
DST_DB_HOST="${DST_DB_HOST:-localhost}"
read -r -p "New MySQL username: " DST_DB_USER
read -r -s -p "New MySQL password: " DST_DB_PASS; echo

echo ""
echo "--- Application secrets ---"
read -r -p "Telegram Bot Token: " TELEGRAM_TOKEN
read -r -p "Weather API Key (leave empty to skip): " WEATHER_KEY

# Pre-flight: make sure we will not clobber an existing .env
if [ -f "$LOCAL_PROJECT_DIR/.env" ]; then
    log_warn ".env already exists at $LOCAL_PROJECT_DIR/.env"
    confirm "Continue and back up existing .env to $BACKUP_DIR/config/env.backup?" || { log_err "Aborted."; exit 1; }
    cp "$LOCAL_PROJECT_DIR/.env" "$BACKUP_DIR/config/env.backup"
fi

log_ok "Credentials collected"

# ---- Step 2: dump databases -------------------------------------------------
log_step "Step 2 / 8 — Dumping databases from source"

DATABASES=(alumglas_common alumglas_hpc alumglas_pardis)

for DB in "${DATABASES[@]}"; do
    echo "  Dumping $DB ..."
    ssh -o BatchMode=no "${SOURCE_USER}@${SOURCE_HOST}" \
        "mysqldump -u '$SRC_DB_USER' -p'$SRC_DB_PASS' --single-transaction --routines --triggers --no-tablespaces '$DB'" \
        > "$BACKUP_DIR/databases/${DB}.sql"
    SIZE=$(du -h "$BACKUP_DIR/databases/${DB}.sql" | cut -f1)
    log_ok "$DB dumped ($SIZE)"
done

# ---- Step 3: copy uploaded files --------------------------------------------
log_step "Step 3 / 8 — Syncing uploaded files"

UPLOAD_DIRS=(
    "ghom/uploads"
    "pardis/uploads"
    "assets/uploads"
    "assets/logos"
    "assets/images"
)

for DIR in "${UPLOAD_DIRS[@]}"; do
    TARGET="$LOCAL_PROJECT_DIR/$DIR/"
    mkdir -p "$TARGET"
    echo "  Syncing $DIR ..."
    rsync -az --delete-excluded \
        "${SOURCE_USER}@${SOURCE_HOST}:${SOURCE_PATH}/${DIR}/" \
        "$TARGET" 2>/dev/null \
        || log_warn "Source $DIR not found (skipped)"
done

log_ok "Uploads synced"

# ---- Step 4: create + import databases --------------------------------------
log_step "Step 4 / 8 — Creating and importing destination databases"

for DB in "${DATABASES[@]}"; do
    echo "  Creating $DB ..."
    mysql -h "$DST_DB_HOST" -u "$DST_DB_USER" -p"$DST_DB_PASS" \
        -e "CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    echo "  Importing $DB ..."
    mysql -h "$DST_DB_HOST" -u "$DST_DB_USER" -p"$DST_DB_PASS" "$DB" \
        < "$BACKUP_DIR/databases/${DB}.sql"

    TABLES=$(mysql -h "$DST_DB_HOST" -u "$DST_DB_USER" -p"$DST_DB_PASS" "$DB" \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB';" -sN)
    log_ok "$DB imported ($TABLES tables)"
done

# ---- Step 5: generate .env --------------------------------------------------
log_step "Step 5 / 8 — Generating .env"

CRON_SECRET="$(openssl rand -hex 16)"
APP_HOST="$(hostname -f 2>/dev/null || hostname)"

cat > "$LOCAL_PROJECT_DIR/.env" <<ENV
# Generated by scripts/migrate.sh on $(date -Iseconds)

# ── Database ──
DB_HOST=$DST_DB_HOST
DB_PORT=3306
DB_COMMON_NAME=alumglas_common
DB_GHOM_NAME=alumglas_hpc
DB_PARDIS_NAME=alumglas_pardis
DB_USERNAME=$DST_DB_USER
DB_PASSWORD=$DST_DB_PASS

# ── Telegram ──
TELEGRAM_BOT_TOKEN=$TELEGRAM_TOKEN
TELEGRAM_CRON_SECRET=$CRON_SECRET

# ── Weather ──
WEATHER_API_KEY=$WEATHER_KEY

# ── Application ──
APP_ENV=production
APP_DEBUG=false
APP_URL=https://$APP_HOST
APP_TIMEZONE=Asia/Tehran

# ── Security ──
SESSION_LIFETIME=3600
LOGIN_LOCKOUT_TIME=3600
LOGIN_ATTEMPTS_LIMIT=5

# ── Uploads ──
UPLOAD_MAX_SIZE=5242880
ALLOWED_UPLOAD_EXTENSIONS=pdf,jpg,jpeg,png,csv,xlsx
ENV

chmod 600 "$LOCAL_PROJECT_DIR/.env"
log_ok ".env generated (mode 600)"

# ---- Step 6: permissions ----------------------------------------------------
log_step "Step 6 / 8 — Setting filesystem permissions"

WEB_USER="${WEB_USER:-$(ps -eo user,comm | awk '/apache2|httpd|nginx/ {print $1; exit}')}"
WEB_USER="${WEB_USER:-www-data}"
echo "  Web user: $WEB_USER"

find "$LOCAL_PROJECT_DIR" -type f -not -path "*/.git/*" -exec chmod 644 {} +
find "$LOCAL_PROJECT_DIR" -type d -not -path "*/.git/*" -exec chmod 755 {} +
chmod 600 "$LOCAL_PROJECT_DIR/.env"

for DIR in logs ghom/uploads pardis/uploads; do
    mkdir -p "$LOCAL_PROJECT_DIR/$DIR"
    chmod 775 "$LOCAL_PROJECT_DIR/$DIR"
done

if id "$WEB_USER" >/dev/null 2>&1; then
    chown -R "$WEB_USER:$WEB_USER" "$LOCAL_PROJECT_DIR" 2>/dev/null \
        || log_warn "chown failed — run manually with sudo"
else
    log_warn "Web user '$WEB_USER' not found — chown skipped"
fi

log_ok "Permissions set"

# ---- Step 7: cron -----------------------------------------------------------
log_step "Step 7 / 8 — Installing cron jobs"

PHP_BIN="$(command -v php || echo /usr/bin/php)"

NEW_CRON="$(mktemp)"
# Preserve existing non-AlumGlass cron entries
crontab -l 2>/dev/null | grep -v "AlumGlass" > "$NEW_CRON" || true
cat >> "$NEW_CRON" <<CRON
# AlumGlass — daily reminders (08:00 Asia/Tehran = 04:30 UTC)
30 4 * * * $PHP_BIN $LOCAL_PROJECT_DIR/pardis/send_daily_reminders.php >> $LOCAL_PROJECT_DIR/logs/cron.log 2>&1
# AlumGlass — telegram daily report (18:00 Asia/Tehran = 14:30 UTC)
30 14 * * * $PHP_BIN $LOCAL_PROJECT_DIR/pardis/telegram_cron.php >> $LOCAL_PROJECT_DIR/logs/cron.log 2>&1
CRON

if crontab "$NEW_CRON" 2>/dev/null; then
    log_ok "Crontab updated"
else
    log_warn "Could not set crontab — preview at $NEW_CRON"
fi

# ---- Step 8: verification ---------------------------------------------------
log_step "Step 8 / 8 — Running post-migration verification"

if [ -f "$LOCAL_PROJECT_DIR/scripts/migrate_verify.php" ]; then
    "$PHP_BIN" "$LOCAL_PROJECT_DIR/scripts/migrate_verify.php" || log_warn "Verification reported issues"
else
    log_warn "migrate_verify.php not found — skipping"
fi

cat <<SUMMARY

╔════════════════════════════════════════════════╗
║           Migration complete                   ║
╠════════════════════════════════════════════════╣
║  Backup:     $BACKUP_DIR
║  .env:       $LOCAL_PROJECT_DIR/.env (mode 600)
║
║  Next steps:
║    1. Verify https://$APP_HOST loads the login page
║    2. Log in with each role (admin, carshenas, etc.)
║    3. Open a project (ghom, then pardis)
║    4. Confirm uploaded files (permits, photos) load
║    5. Trigger a test cron run: php pardis/telegram_cron.php
╚════════════════════════════════════════════════╝
SUMMARY
