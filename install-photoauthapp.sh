#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
[[ -f "$APP_DIR/artisan" && -d "$APP_DIR/.git" ]] || { echo 'Установщик должен находиться в Git-каталоге PhotoAuthApp.'; exit 2; }
MODE="${1:-auto}"
if [[ "$MODE" == auto ]]; then
  if command -v docker >/dev/null && docker compose version >/dev/null 2>&1; then
    read -r -p 'Обнаружен Docker Compose. Режим: D (Docker) или H (Linux без Docker) [D]: ' choice
    [[ "$choice" =~ ^[Hh]$ ]] && MODE=host || MODE=docker
  else
    MODE=host
  fi
fi
[[ "$MODE" == docker || "$MODE" == host ]] || { echo 'Режим должен быть docker или host.'; exit 2; }
if [[ "$MODE" == docker ]]; then
  command -v docker >/dev/null || { echo 'Docker не найден. Установите и запустите Docker либо выберите режим host.'; exit 2; }
  docker compose version >/dev/null 2>&1 || { echo 'Docker Compose недоступен. Проверьте установку Docker.'; exit 2; }
  docker info --format '{{.ServerVersion}}' >/dev/null 2>&1 || { echo 'Движок Docker не отвечает. Запустите Docker и повторите установку.'; exit 2; }

  declare -A required_images=()
  while IFS= read -r line; do
    if [[ "$line" =~ ^[[:space:]]*FROM[[:space:]]+([^[:space:]]+) ]]; then
      [[ "${BASH_REMATCH[1]}" == scratch ]] || required_images["${BASH_REMATCH[1]}"]=1
    elif [[ "$line" =~ ^[[:space:]]*COPY[[:space:]]+--from=([^[:space:]]+) ]]; then
      image="${BASH_REMATCH[1]}"
      if [[ "$image" == *:* || "$image" == */* ]]; then required_images["$image"]=1; fi
    fi
  done < <(cat "$APP_DIR/Dockerfile" "$APP_DIR/update-agent/Dockerfile")
  while IFS= read -r line; do
    if [[ "$line" =~ ^[[:space:]]*image:[[:space:]]*([^[:space:]#]+) ]]; then
      image="${BASH_REMATCH[1]}"
      if [[ "$image" != *'$'* ]]; then required_images["$image"]=1; fi
    fi
  done < "$APP_DIR/compose.yaml"
  (( ${#required_images[@]} > 0 )) || { echo 'Не удалось определить Docker-образы проекта.'; exit 2; }
  missing_images=()
  echo 'Проверяю наличие Docker-образов на компьютере...'
  for image in "${!required_images[@]}"; do
    if docker image inspect "$image" >/dev/null 2>&1; then
      echo "Есть: $image"
    else
      echo "Нужно скачать: $image"
      missing_images+=("$image")
    fi
  done
  if (( ${#missing_images[@]} > 0 )); then
    read -r -p "Отсутствует образов: ${#missing_images[@]}. Разрешить Docker скачать их при запуске? y/N: " download_choice
    [[ "$download_choice" =~ ^[Yy]$ ]] || { echo 'Установка остановлена до скачивания Docker-образов.'; exit 2; }
  fi
  echo 'При первой сборке Docker также может скачать пакеты внутри образов.'
fi
if [[ "$MODE" == host ]] && { ! command -v openssl >/dev/null || ! command -v curl >/dev/null; }; then
  read -r -p 'Не найдены OpenSSL или curl. Установить через apt-get? y/N: ' install_choice
  [[ "$install_choice" =~ ^[Yy]$ ]] || { echo 'Установка остановлена до появления обязательных компонентов.'; exit 2; }
  if command -v apt-get >/dev/null && command -v sudo >/dev/null; then
    sudo apt-get update
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y openssl curl
  else
    echo 'Автоустановка этих компонентов доступна для Debian/Ubuntu с apt-get и sudo.'
    exit 2
  fi
fi
ENV_NAME=.env
if [[ "$MODE" == docker && ! -f "$APP_DIR/.env" && -f "$APP_DIR/.env.production" ]]; then ENV_NAME=.env.production; fi
if [[ "$MODE" == docker && -f "$APP_DIR/.env" && -f "$APP_DIR/.env.production" ]]; then
  read -r -p 'Найдены .env и .env.production. Использовать .env.production? y/N: ' env_choice
  [[ "$env_choice" =~ ^[Yy]$ ]] && ENV_NAME=.env.production
fi
ENV_FILE="$APP_DIR/$ENV_NAME"
if [[ ! -f "$ENV_FILE" ]]; then
  TEMPLATE=.env.example
  [[ "$ENV_NAME" == .env.production ]] && TEMPLATE=.env.example.production
  cp "$APP_DIR/$TEMPLATE" "$ENV_FILE"
  chmod 600 "$ENV_FILE"
fi

env_value() {
  sed -n "s/^$1=//p" "$ENV_FILE" | tail -n 1 | tr -d '\r"'
}
set_env() {
  local key="$1" value="$2"
  if grep -q "^${key}=" "$ENV_FILE"; then
    sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
  else
    printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
  fi
}
ensure_secret() {
  local key="$1" value
  value="$(env_value "$key")"
  if [[ -z "$value" ]]; then
    value="$(openssl rand -hex 32)"
    set_env "$key" "$value"
  fi
  if [[ "$key" == UPDATE_AGENT_TOKEN && ${#value} -lt 32 ]]; then
    echo "$key слишком короткий."
    exit 2
  fi
}

command -v openssl >/dev/null || { echo 'Не найден OpenSSL.'; exit 2; }
command -v curl >/dev/null || { echo 'Не найден curl.'; exit 2; }
if [[ -z "$(env_value APP_KEY)" ]]; then set_env APP_KEY "base64:$(openssl rand -base64 32 | tr -d '\n')"; fi
ensure_secret SETUP_ACCESS_KEY
ensure_secret API_TOKEN
ensure_secret UPDATE_AGENT_TOKEN

read -r -p "Порт сайта [$(env_value APP_PORT | sed 's/^$/27020/')]: " chosen_port
PORT="${chosen_port:-$(env_value APP_PORT)}"
PORT="${PORT:-27020}"
[[ "$PORT" =~ ^[0-9]+$ ]] && (( 10#$PORT >= 1 && 10#$PORT <= 65535 )) || { echo 'Порт должен быть числом от 1 до 65535.'; exit 2; }
PORT="$((10#$PORT))"
set_env APP_PORT "$PORT"

if [[ "$MODE" == docker ]]; then
  DEFAULT_URL="$(env_value APP_URL)"
  [[ "$DEFAULT_URL" =~ ^https?://[^[:space:]/]+(:[0-9]+)?$ && "$DEFAULT_URL" != *SERVER_IP_OR_DOMAIN* ]] || DEFAULT_URL="http://localhost:$PORT"
  if [[ "$DEFAULT_URL" =~ :[0-9]+$ ]]; then DEFAULT_URL="${DEFAULT_URL%:*}:$PORT"; else DEFAULT_URL="$DEFAULT_URL:$PORT"; fi
  read -r -p "Адрес приложения в браузере [$DEFAULT_URL]: " APP_URL
  APP_URL="${APP_URL:-$DEFAULT_URL}"
  [[ "$APP_URL" =~ ^http://[^[:space:]/]+(:[0-9]+)?$ ]] || { echo 'Укажите HTTP-адрес без пути.'; exit 2; }
  set_env APP_URL "$APP_URL"
  set_env APP_ENV_FILE "$ENV_NAME"
  set_env APP_ENV production
  set_env APP_DEBUG false
  set_env UPDATE_RUNTIME docker
  set_env UPDATE_AGENT_URL http://updater:19100
  docker compose --profile updates --env-file "$ENV_FILE" -f "$APP_DIR/compose.yaml" up -d --build
else
  read -r -p "Локальный порт агента обновлений [$(env_value UPDATE_AGENT_PORT | sed 's/^$/19100/')]: " chosen_agent_port
  AGENT_PORT="${chosen_agent_port:-$(env_value UPDATE_AGENT_PORT)}"
  AGENT_PORT="${AGENT_PORT:-19100}"
  [[ "$AGENT_PORT" =~ ^[0-9]+$ ]] && (( 10#$AGENT_PORT >= 1 && 10#$AGENT_PORT <= 65535 )) || { echo 'Порт агента должен быть от 1 до 65535.'; exit 2; }
  AGENT_PORT="$((10#$AGENT_PORT))"
  [[ "$AGENT_PORT" != "$PORT" ]] || { echo 'Порты сайта и агента должны различаться.'; exit 2; }
  missing=()
  for dependency in php composer python3 git sudo systemctl nginx ss; do
    command -v "$dependency" >/dev/null || missing+=("$dependency")
  done
  if (( ${#missing[@]} > 0 )); then
    echo "Не найдены компоненты: ${missing[*]}"
    read -r -p 'Установить недостающие компоненты через системный менеджер пакетов? y/N: ' install_choice
    [[ "$install_choice" =~ ^[Yy]$ ]] || { echo 'Установка остановлена до появления обязательных компонентов.'; exit 2; }
    if command -v apt-get >/dev/null && command -v sudo >/dev/null; then
      sudo apt-get update
      sudo DEBIAN_FRONTEND=noninteractive apt-get install -y nginx php-cli php-fpm php-mysql php-xml php-mbstring php-zip php-gd php-curl composer python3 git curl openssl iproute2
    else
      echo 'Автоустановка компонентов сейчас доступна для Debian/Ubuntu с apt-get и sudo.'
      exit 2
    fi
  fi
  for dependency in php composer python3 git sudo systemctl nginx ss; do
    command -v "$dependency" >/dev/null || { echo "Не найдена обязательная программа: $dependency"; exit 2; }
  done
  read -r -p "Адрес приложения [http://localhost:$PORT]: " APP_URL
  APP_URL="${APP_URL:-http://localhost:$PORT}"
  [[ "$APP_URL" =~ ^http://[^[:space:]/]+(:[0-9]+)?$ ]] || { echo 'Укажите HTTP-адрес без пути.'; exit 2; }
  [[ "$APP_URL" == *":$PORT" ]] || { echo 'Порт в адресе должен совпадать с выбранным портом сайта.'; exit 2; }
  SERVICE="$(systemctl list-unit-files --type=service --no-legend 'php*-fpm.service' | awk '$2 != "masked" {print $1; exit}')"
  [[ -n "$SERVICE" ]] || read -r -p 'Служба PHP-FPM (например php8.4-fpm.service): ' SERVICE
  [[ "$SERVICE" =~ ^[A-Za-z0-9_.@:-]+\.service$ ]] || { echo 'Укажите полное имя службы.'; exit 2; }
  sudo systemctl enable --now "$SERVICE"
  FPM_SOCKET="$(find /run/php -maxdepth 1 -type s -name 'php*-fpm.sock' -print -quit 2>/dev/null || true)"
  [[ -n "$FPM_SOCKET" ]] || { echo 'Не найден сокет PHP-FPM в /run/php.'; exit 2; }
  SITE_FILE=/etc/nginx/sites-available/photoauthapp.conf
  if [[ -e "$SITE_FILE" ]] && ! grep -q '^# Managed by PhotoAuthApp installer$' "$SITE_FILE"; then
    echo "Конфигурация $SITE_FILE уже существует и не принадлежит установке PhotoAuthApp. Остановлено без изменений."
    exit 2
  fi
  if [[ -e "$SITE_FILE" ]] && ! grep -Fq "root \"$APP_DIR/public\";" "$SITE_FILE"; then
    echo "Конфигурация $SITE_FILE относится к другому каталогу проекта. Остановлено без изменений."
    exit 2
  fi
  if [[ ! -e "$SITE_FILE" ]] && sudo ss -ltn "sport = :$PORT" | grep -q LISTEN; then
    echo "Порт $PORT уже занят. Выберите другой."
    exit 2
  fi
  APP_OWNER="$(stat -c '%U' "$APP_DIR")"
  [[ "$APP_OWNER" != root ]] || { echo 'Каталог проекта должен принадлежать отдельному пользователю, а не root.'; exit 2; }
  WEB_USER="$(systemctl show -p User --value "$SERVICE" 2>/dev/null || true)"
  if [[ -z "$WEB_USER" || "$WEB_USER" == root ]]; then
    WEB_USER=www-data
  fi
  if ! id "$WEB_USER" >/dev/null 2>&1; then
    read -r -p 'Системный пользователь веб-сервера: ' WEB_USER
    id "$WEB_USER" >/dev/null 2>&1 || { echo 'Пользователь веб-сервера не найден.'; exit 2; }
  fi
  if ! sudo -u "$WEB_USER" test -r "$ENV_FILE"; then
    sudo chgrp "$(id -gn "$WEB_USER")" "$ENV_FILE"
    sudo chmod 660 "$ENV_FILE"
  fi
  if ! sudo -u "$WEB_USER" test -r "$APP_DIR/public/index.php"; then
    sudo chgrp -R "$(id -gn "$WEB_USER")" "$APP_DIR"
    sudo find "$APP_DIR" -type d -exec chmod g+rx {} +
    sudo find "$APP_DIR" -type f -exec chmod g+r {} +
  fi
  sudo install -d -m 775 -o "$APP_OWNER" -g "$(id -gn "$WEB_USER")" "$APP_DIR/storage/app" "$APP_DIR/storage/framework" "$APP_DIR/storage/logs" "$APP_DIR/bootstrap/cache" "$APP_DIR/public/uploads"
  sudo chmod -R g+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/public/uploads"
  set_env APP_URL "$APP_URL"
  set_env UPDATE_RUNTIME host
  set_env UPDATE_AGENT_PORT "$AGENT_PORT"
  set_env UPDATE_AGENT_URL "http://127.0.0.1:$AGENT_PORT"
  set_env APP_ENV production
  set_env APP_DEBUG false
  (cd "$APP_DIR" && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader && php artisan storage:link)
  SITE_TEMP="$(mktemp)"
  cat > "$SITE_TEMP" <<EOF
# Managed by PhotoAuthApp installer
server {
    listen $PORT;
    server_name _;
    root "$APP_DIR/public";
    index index.php;
    client_max_body_size 20m;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass unix:$FPM_SOCKET;
    }
    location ~ /\. { deny all; }
    location ~ ^/storage/.*\.php$ { deny all; }
}
EOF
  sudo install -m 644 "$SITE_TEMP" "$SITE_FILE"
  rm -f "$SITE_TEMP"
  sudo ln -sfn "$SITE_FILE" /etc/nginx/sites-enabled/photoauthapp.conf
  sudo nginx -t
  sudo systemctl enable --now nginx
  sudo systemctl enable --now "$SERVICE"
  sudo systemctl reload nginx
  sudo bash "$APP_DIR/install-update-agent-linux.sh" "$APP_DIR" "$SERVICE" "$APP_URL/login" "$ENV_FILE" "$AGENT_PORT"
fi

READY=0
for attempt in {1..12}; do
  if curl -fsSL --max-time 5 "$APP_URL/setup/auth" | grep -q 'data-photoauth-setup'; then READY=1; break; fi
  sleep 2
done
[[ "$READY" -eq 1 ]] || { echo 'Страница PhotoAuthApp не открылась. Проверьте адрес и настройку веб-сервера для папки public.'; exit 1; }
echo "Откройте $APP_URL/setup/auth"
echo "Ключ первоначальной настройки (сохраните до завершения): $(env_value SETUP_ACCESS_KEY)"
if command -v xdg-open >/dev/null; then xdg-open "$APP_URL/setup/auth" >/dev/null 2>&1 || true; fi
