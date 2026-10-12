#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "$EUID" -ne 0 ]]; then
  echo "Запустите установщик от root: sudo bash install-update-agent-linux.sh"
  exit 1
fi
for dependency in systemctl sudo visudo python3 git php composer curl openssl; do
  command -v "$dependency" >/dev/null 2>&1 || { echo "Не найдена обязательная программа: $dependency"; exit 2; }
done
python3 -c 'import sys; raise SystemExit(0 if sys.version_info >= (3, 10) else 1)' || { echo "Для агента требуется Python 3.10 или новее."; exit 2; }

APP_DIR="${1:-$(pwd)}"
if [[ ! -f "$APP_DIR/artisan" || ! -d "$APP_DIR/.git" ]]; then
  echo "Укажите папку проекта PhotoAuthApp с файлами artisan и .git."
  exit 2
fi
RESTART_SERVICE="${2:-}"
if [[ -z "$RESTART_SERVICE" ]]; then
  read -r -p "Имя службы systemd для перезапуска PHP/Apache (например php8.4-fpm.service): " RESTART_SERVICE
fi
if [[ ! "$RESTART_SERVICE" =~ ^[A-Za-z0-9_.@:-]+\.service$ ]]; then
  echo "Укажите полное имя службы systemd, оканчивающееся на .service."
  exit 2
fi
systemctl cat "$RESTART_SERVICE" >/dev/null 2>&1 || { echo "Служба $RESTART_SERVICE не найдена в systemd."; exit 2; }
HEALTH_URL="${3:-}"
if [[ -z "$HEALTH_URL" ]]; then
  read -r -p "Адрес проверки приложения [http://127.0.0.1/login]: " HEALTH_URL
fi
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1/login}"
if [[ ! "$HEALTH_URL" =~ ^https?://[A-Za-z0-9._:/?-]+$ ]]; then
  echo "Укажите корректный URL проверки приложения без пробелов."
  exit 2
fi
AGENT_PORT="${5:-19100}"
[[ "$AGENT_PORT" =~ ^[0-9]+$ ]] && (( 10#$AGENT_PORT >= 1 && 10#$AGENT_PORT <= 65535 )) || { echo 'Порт агента должен быть от 1 до 65535.'; exit 2; }
AGENT_PORT="$((10#$AGENT_PORT))"
ENV_NAME="${4:-}"
if [[ -z "$ENV_NAME" ]]; then
  read -r -p "Файл окружения проекта [.env]: " ENV_NAME
fi
ENV_NAME="${ENV_NAME:-.env}"
ENV_FILE="$ENV_NAME"
[[ "$ENV_FILE" = /* ]] || ENV_FILE="$APP_DIR/$ENV_FILE"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Не найден файл .env проекта. Создайте и настройте его перед установкой агента."
  exit 2
fi
UPDATE_AGENT_TOKEN="$(sed -n 's/^UPDATE_AGENT_TOKEN=//p' "$ENV_FILE" | tail -n 1 | tr -d '\r"' || true)"
if [[ -z "$UPDATE_AGENT_TOKEN" ]]; then
  UPDATE_AGENT_TOKEN="$(openssl rand -hex 32)"
  if grep -q '^UPDATE_AGENT_TOKEN=' "$ENV_FILE"; then
    sed -i "s/^UPDATE_AGENT_TOKEN=.*/UPDATE_AGENT_TOKEN=$UPDATE_AGENT_TOKEN/" "$ENV_FILE"
  else
    printf '\nUPDATE_AGENT_TOKEN=%s\n' "$UPDATE_AGENT_TOKEN" >> "$ENV_FILE"
  fi
fi
if [[ ${#UPDATE_AGENT_TOKEN} -lt 32 ]]; then
  echo "Токен должен содержать не менее 32 символов."
  exit 2
fi
if [[ ! "$UPDATE_AGENT_TOKEN" =~ ^[A-Za-z0-9_-]+$ ]]; then
  echo "UPDATE_AGENT_TOKEN в .env должен содержать только буквы, цифры, дефис или подчёркивание."
  exit 2
fi

APP_DIR="$(cd "$APP_DIR" && pwd)"
APP_USER="$(stat -c '%U' "$APP_DIR")"
if [[ "$APP_USER" == "root" || -z "$APP_USER" ]]; then
  echo "Папка проекта должна принадлежать отдельному пользователю-развёртывания, а не root."
  exit 2
fi
STATE_DIR="/var/lib/photoauthapp-updater"
ENV_FILE="/etc/photoauthapp-updater.env"
UNIT_FILE="/etc/systemd/system/photoauthapp-updater.service"
SUDOERS_FILE="/etc/sudoers.d/photoauthapp-updater"
systemd_quote() {
  local value="$1"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '"%s"' "$value"
}

install -d -o "$APP_USER" -g "$(id -gn "$APP_USER")" -m 700 "$STATE_DIR"
umask 077
{
  printf 'PHOTOAUTHAPP_DIR=%s\n' "$(systemd_quote "$APP_DIR")"
  printf 'UPDATE_STATE_DIR=%s\n' "$(systemd_quote "$STATE_DIR")"
  printf 'UPDATE_AGENT_TOKEN=%s\n' "$UPDATE_AGENT_TOKEN"
  printf 'UPDATE_MODE=host\nUPDATE_AGENT_HOST=127.0.0.1\nUPDATE_AGENT_PORT=%s\n' "$AGENT_PORT"
  printf 'UPDATE_RESTART_TARGET=%s\n' "$RESTART_SERVICE"
  printf 'UPDATE_HEALTH_URL=%s\n' "$(systemd_quote "$HEALTH_URL")"
} > "$ENV_FILE"
cat > "$UNIT_FILE" <<EOF
[Unit]
Description=PhotoAuthApp host update agent
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=$APP_USER
Group=$(id -gn "$APP_USER")
EnvironmentFile=$ENV_FILE
Environment=COMPOSER_ALLOW_SUPERUSER=1
Environment=GIT_CONFIG_COUNT=1
Environment=GIT_CONFIG_KEY_0=safe.directory
Environment=GIT_CONFIG_VALUE_0=$(systemd_quote "$APP_DIR")
WorkingDirectory=$(systemd_quote "$APP_DIR")
ExecStart=/usr/bin/python3 $(systemd_quote "$APP_DIR/update-agent/server.py")
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF
printf '%s ALL=(root) NOPASSWD: /bin/systemctl restart %s\n' "$APP_USER" "$RESTART_SERVICE" > "$SUDOERS_FILE"
chmod 600 "$ENV_FILE" "$SUDOERS_FILE"
chmod 644 "$UNIT_FILE"
visudo -cf "$SUDOERS_FILE"
systemctl daemon-reload
systemctl enable --now photoauthapp-updater.service
runuser -u "$APP_USER" -- php "$APP_DIR/artisan" config:clear
systemctl restart "$RESTART_SERVICE"
unset UPDATE_AGENT_TOKEN
echo "Агент запущен как служба ОС и слушает только 127.0.0.1:$AGENT_PORT. Токен добавлен в .env проекта."
