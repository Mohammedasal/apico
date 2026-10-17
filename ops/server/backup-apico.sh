#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

APP_DIR="${APICO_APP_DIR:-/var/www/apico}"
BACKUP_DIR="${APICO_BACKUP_DIR:-/home/apico/backups}"
RETENTION_DAYS="${APICO_BACKUP_RETENTION_DAYS:-30}"
RETENTION_WEEKS="${APICO_BACKUP_RETENTION_WEEKS:-12}"
MODE="${1:-daily}"

if [[ "$MODE" != "daily" && "$MODE" != "predeploy" ]]; then
    echo "Usage: $0 [daily|predeploy]" >&2
    exit 2
fi

ENV_FILE="$APP_DIR/.env"
if [[ ! -r "$ENV_FILE" ]]; then
    echo "Cannot read Laravel environment file: $ENV_FILE" >&2
    exit 1
fi

env_value() {
    local key="$1"
    local value
    value="$(sed -n "s/^${key}=//p" "$ENV_FILE" | tail -n 1)"
    value="${value%$'\r'}"
    if [[ "$value" == \"*\" && "$value" == *\" ]]; then
        value="${value:1:${#value}-2}"
    elif [[ "$value" == \'*\' && "$value" == *\' ]]; then
        value="${value:1:${#value}-2}"
    fi
    printf '%s' "$value"
}

DB_CONNECTION="$(env_value DB_CONNECTION)"
DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"

if [[ "${DB_CONNECTION:-mysql}" != "mysql" ]]; then
    echo "This server backup script expects DB_CONNECTION=mysql." >&2
    exit 1
fi

for required in DB_DATABASE DB_USERNAME; do
    if [[ -z "${!required:-}" ]]; then
        echo "$required is missing from $ENV_FILE" >&2
        exit 1
    fi
done

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
mkdir -p "$BACKUP_DIR/daily" "$BACKUP_DIR/weekly" "$BACKUP_DIR/predeploy"

timestamp="$(TZ=Asia/Amman date +%Y%m%d-%H%M%S)"
target_dir="$BACKUP_DIR/$MODE"
target="$target_dir/apico-${MODE}-${timestamp}.sql.gz"
temporary="${target}.partial"
defaults_file="$(mktemp)"
lock_file="$BACKUP_DIR/.backup.lock"

cleanup() {
    rm -f "$defaults_file" "$temporary"
}
trap cleanup EXIT

cat > "$defaults_file" <<EOF
[client]
host=$DB_HOST
port=$DB_PORT
user=$DB_USERNAME
password=$DB_PASSWORD
default-character-set=utf8mb4
EOF
chmod 600 "$defaults_file"

exec 9>"$lock_file"
if ! flock -n 9; then
    echo "Another APICO backup is already running." >&2
    exit 1
fi

mysqldump \
    --defaults-extra-file="$defaults_file" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --events \
    --no-tablespaces \
    --set-gtid-purged=OFF \
    "$DB_DATABASE" | gzip -9 > "$temporary"

gzip -t "$temporary"
[[ -s "$temporary" ]]
mv "$temporary" "$target"
sha256sum "$target" > "${target}.sha256"

if [[ "$MODE" == "daily" ]]; then
    find "$BACKUP_DIR/daily" -type f -name 'apico-daily-*.sql.gz*' -mtime "+$RETENTION_DAYS" -delete

    if [[ "$(TZ=Asia/Amman date +%u)" == "7" ]]; then
        weekly="$BACKUP_DIR/weekly/apico-weekly-${timestamp}.sql.gz"
        cp --preserve=timestamps "$target" "$weekly"
        sha256sum "$weekly" > "${weekly}.sha256"
    fi

    find "$BACKUP_DIR/weekly" -type f -name 'apico-weekly-*.sql.gz*' -mtime "+$((RETENTION_WEEKS * 7))" -delete
fi

echo "$target"
