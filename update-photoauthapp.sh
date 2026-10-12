#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/opt/photoauthapp}"
COMPOSE_FILE="${COMPOSE_FILE:-compose.yaml}"
ENV_FILE="${ENV_FILE:-.env.production}"
BRANCH="${BRANCH:-main}"
TARGET_SHA="${TARGET_SHA:-}"
CURRENT_SHA="${CURRENT_SHA:-}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-0}"
BACKUP_DIR="${BACKUP_DIR:-./update-agent-backups/schema}"
cd "$APP_DIR"

if [[ -z "${COMPOSE_PROJECT_NAME:-}" ]]; then
  COMPOSE_PROJECT_NAME="$(docker inspect --format '{{ index .Config.Labels "com.docker.compose.project" }}' viewentrance-app 2>/dev/null || true)"
fi
export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-$(basename "$APP_DIR")}"

if [[ ! "$TARGET_SHA" =~ ^[0-9a-f]{40}$ ]]; then
  echo "Не передана проверенная версия обновления."
  exit 2
fi
if [[ -n "$(git status --porcelain --untracked-files=all)" ]]; then
  echo "Обновление отменено: в $APP_DIR есть локальные изменения или файлы."
  exit 1
fi

git fetch origin "$BRANCH"
SOURCE_HEAD="$(git rev-parse HEAD)"
CURRENT="${CURRENT_SHA:-$SOURCE_HEAD}"
REMOTE="$(git rev-parse "origin/$BRANCH")"
if [[ "$TARGET_SHA" != "$REMOTE" ]]; then
  echo "Обновление отменено: ветка GitHub изменилась после проверки."
  exit 1
fi
if ! git merge-base --is-ancestor "$CURRENT" "$TARGET_SHA"; then
  echo "Обновление отменено: локальная ветка разошлась с origin/$BRANCH."
  exit 1
fi
if ! git merge-base --is-ancestor "$SOURCE_HEAD" "$TARGET_SHA"; then
  echo "Обновление отменено: исходный checkout не является предком целевой версии."
  exit 1
fi
if [[ "$CURRENT" == "$TARGET_SHA" ]]; then
  echo "Установлена последняя версия: ${CURRENT:0:12}"
  exit 0
fi

MIGRATIONS_CHANGED="$(git diff --name-only "$CURRENT" "$TARGET_SHA" -- database/migrations)"
if [[ -n "$MIGRATIONS_CHANGED" && "$RUN_MIGRATIONS" != "1" ]]; then
  echo "Обновление содержит миграции; требуется отдельное подтверждение."
  exit 3
fi

PREVIOUS_IMAGE="$(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" images -q app | head -n 1)"
IMAGE_NAME="$(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config --images | head -n 1)"
ROLLBACK_IMAGE="${IMAGE_NAME%:*}:update-rollback"
if [[ -n "$PREVIOUS_IMAGE" ]]; then
  docker image tag "$PREVIOUS_IMAGE" "$ROLLBACK_IMAGE"
fi
restore_before_migrations() {
  if [[ -n "$PREVIOUS_IMAGE" ]]; then
    docker image tag "$ROLLBACK_IMAGE" "$IMAGE_NAME"
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build --no-deps app
  fi
  git reset --hard "$SOURCE_HEAD"
}

git merge --ff-only "$TARGET_SHA"
export APP_ENV_FILE="$ENV_FILE"
if ! docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" build app; then
  restore_before_migrations
  exit 1
fi

if [[ -n "$MIGRATIONS_CHANGED" ]]; then
  if ! docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" stop app; then
    restore_before_migrations
    exit 1
  fi
  if ! docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" rm -f app; then
    restore_before_migrations
    exit 1
  fi

  mkdir -p "$BACKUP_DIR"
  chmod 700 "$BACKUP_DIR"
  SCHEMA_BACKUP="$BACKUP_DIR/schema-$(date -u +%Y%m%dT%H%M%SZ).sql"
  if ! docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm --no-deps --entrypoint php app update-agent/dump-schema.php - > "$SCHEMA_BACKUP"; then
    rm -f "$SCHEMA_BACKUP"
    restore_before_migrations
    echo "Не удалось сохранить схему MySQL. Миграции не запускались."
    exit 1
  fi
  if [[ ! -s "$SCHEMA_BACKUP" ]]; then
    rm -f "$SCHEMA_BACKUP"
    restore_before_migrations
    echo "Резервная копия схемы пустая. Миграции не запускались."
    exit 1
  fi
  chmod 600 "$SCHEMA_BACKUP"

  echo "PHOTOAUTH_MIGRATION_STARTED"
  if ! docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm --no-deps app php artisan migrate --force; then
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-deps app || true
    echo "Миграция завершилась ошибкой. Выполнена попытка запустить приложение; проверьте его доступность и состояние БД."
    exit 1
  fi
  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-deps app
else
  if ! docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-deps app; then
    if [[ -n "$PREVIOUS_IMAGE" ]]; then
      docker image tag "$ROLLBACK_IMAGE" "$IMAGE_NAME"
      docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build --no-deps app
    fi
    git reset --hard "$SOURCE_HEAD"
    exit 1
  fi
fi

for attempt in {1..18}; do
  if docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T app curl -fsS http://127.0.0.1:27017/login >/dev/null; then
    echo "Обновление завершено: $(git rev-parse --short HEAD)"
    exit 0
  fi
  sleep 5
done

if [[ -n "$MIGRATIONS_CHANGED" ]]; then
  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-deps app || true
  echo "Проверка приложения не прошла после миграций. Автоматический откат БД невозможен без копии строк данных. Проверьте журналы."
  exit 1
fi

echo "Проверка приложения не прошла; возвращаю предыдущий образ и версию."
if [[ -n "$PREVIOUS_IMAGE" ]]; then
  docker image tag "$ROLLBACK_IMAGE" "$IMAGE_NAME"
  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build --no-deps app
fi
    git reset --hard "$SOURCE_HEAD"
exit 1
