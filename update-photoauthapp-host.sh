#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-${PHOTOAUTHAPP_DIR:-}}"
TARGET_SHA="${TARGET_SHA:-}"
CURRENT_SHA="${CURRENT_SHA:-}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-0}"
STATE_DIR="${UPDATE_STATE_DIR:-$APP_DIR/storage/app/update-agent}"
RESTART_TARGET="${UPDATE_RESTART_TARGET:-}"
HEALTH_URL="${UPDATE_HEALTH_URL:-http://127.0.0.1/login}"

if [[ -z "$APP_DIR" || ! -d "$APP_DIR/.git" ]]; then
  echo "Не задан каталог Git-проекта для агента ОС."
  exit 2
fi
cd "$APP_DIR"
if [[ -z "$RESTART_TARGET" ]]; then
  echo "Не настроена служба systemd для перезапуска приложения."
  exit 2
fi
if [[ -n "$(git status --porcelain --untracked-files=all)" ]]; then
  echo "Обновление остановлено: в проекте есть локальные изменения или файлы."
  exit 1
fi

SOURCE_HEAD="$(git rev-parse HEAD)"
CURRENT="${CURRENT_SHA:-$SOURCE_HEAD}"
if ! git merge-base --is-ancestor "$SOURCE_HEAD" "$TARGET_SHA"; then
  echo "Целевая версия не является продолжением текущей ветки."
  exit 1
fi
MIGRATIONS_CHANGED="$(git diff --name-only "$CURRENT" "$TARGET_SHA" -- database/migrations)"
if [[ -n "$MIGRATIONS_CHANGED" && "$RUN_MIGRATIONS" != "1" ]]; then
  echo "Для миграций требуется отдельное подтверждение."
  exit 3
fi

restore_code() {
  git reset --hard "$SOURCE_HEAD"
  if [[ -f composer.lock ]]; then composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader || true; fi
  sudo -n /bin/systemctl restart "$RESTART_TARGET" || true
}

git merge --ff-only "$TARGET_SHA"
if [[ -f composer.lock ]]; then
  if ! composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader; then
    restore_code
    exit 1
  fi
fi

if [[ -n "$MIGRATIONS_CHANGED" ]]; then
  mkdir -p "$STATE_DIR/schema-backups"
  chmod 700 "$STATE_DIR" "$STATE_DIR/schema-backups"
  SCHEMA_BACKUP="$STATE_DIR/schema-backups/schema-$(date -u +%Y%m%dT%H%M%SZ).sql"
  if ! php update-agent/dump-schema.php "$SCHEMA_BACKUP"; then
    rm -f "$SCHEMA_BACKUP"
    restore_code
    echo "Не удалось сохранить схему MySQL; миграции не запускались."
    exit 1
  fi
  chmod 600 "$SCHEMA_BACKUP"
  echo "PHOTOAUTH_MIGRATION_STARTED"
  if ! php artisan migrate --force; then
    sudo -n /bin/systemctl restart "$RESTART_TARGET" || true
    echo "Миграция завершилась ошибкой; проверьте приложение и базу вручную."
    exit 1
  fi
fi

if ! sudo -n /bin/systemctl restart "$RESTART_TARGET"; then
  if [[ -z "$MIGRATIONS_CHANGED" ]]; then restore_code; fi
  echo "Не удалось перезапустить службу $RESTART_TARGET."
  exit 1
fi

for attempt in {1..18}; do
  if curl -fsS "$HEALTH_URL" >/dev/null; then
    echo "Обновление завершено: $(git rev-parse --short HEAD)"
    exit 0
  fi
  sleep 5
done

if [[ -z "$MIGRATIONS_CHANGED" ]]; then restore_code; fi
echo "Проверка приложения не прошла после обновления."
exit 1
