#!/usr/bin/env python3
"""Small internal API for the administrator-triggered PhotoAuthApp updater."""

from __future__ import annotations

import hmac
import json
import os
import re
import subprocess
import threading
import time
import uuid
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlparse


ROOT = Path(os.environ.get("PHOTOAUTHAPP_DIR", "/workspace"))
STATE_DIR = Path(os.environ.get("UPDATE_STATE_DIR", "/data"))
STATE_FILE = STATE_DIR / "state.json"
TOKEN = os.environ.get("UPDATE_AGENT_TOKEN", "")
BRANCH = os.environ.get("UPDATE_BRANCH", "main")
MODE = os.environ.get("UPDATE_MODE", "docker").lower()
LOCK = threading.Lock()
STATE_LOCK = threading.Lock()
STATE: dict = {"check": None, "check_job": None, "job": None, "installed_sha": None}
SHA_RE = re.compile(r"^[0-9a-f]{40}$")


def read_state() -> None:
    global STATE
    try:
        STATE = json.loads(STATE_FILE.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        pass
    for key in ("check_job", "job"):
        value = STATE.get(key) or {}
        if value.get("status") in {"queued", "running"}:
            value["status"] = "failed"
            value["stage"] = "агент перезапущен"
            value["error"] = "Агент перезапустился во время операции. Проверьте состояние приложения перед повторным запуском."
            value["log"] = value.get("log", "")
            value["block_updates"] = bool(value.get("run_migrations"))
            STATE[key] = value


def save_state() -> None:
    STATE_FILE.parent.mkdir(parents=True, exist_ok=True)
    temporary = STATE_FILE.with_suffix(".tmp")
    temporary.write_text(json.dumps(STATE, ensure_ascii=False), encoding="utf-8")
    temporary.replace(STATE_FILE)


def set_state(**values) -> None:
    with STATE_LOCK:
        STATE.update(values)
        save_state()


def run(args: list[str], timeout: int = 30) -> str:
    result = subprocess.run(
        args,
        cwd=ROOT,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        timeout=timeout,
        check=False,
    )
    if result.returncode:
        raise RuntimeError((result.stdout or "Команда завершилась с ошибкой")[-6000:])
    return result.stdout.strip()


def update_check() -> dict:
    dirty = run(["git", "status", "--porcelain", "--untracked-files=all"])
    if dirty:
        raise RuntimeError("В каталоге приложения есть локальные файлы или изменения. Сначала разберите их вручную.")

    run(["git", "fetch", "--quiet", "origin", BRANCH], timeout=90)
    source_head = run(["git", "rev-parse", "HEAD"])
    current = STATE.get("installed_sha") or source_head
    available = run(["git", "rev-parse", f"origin/{BRANCH}"])
    run(["git", "merge-base", "--is-ancestor", current, available])
    commits_raw = run(
        ["git", "log", "--format=%h%x09%s", "-20", f"{current}..{available}"]
    ) if current != available else ""
    migrations_raw = run(
        ["git", "diff", "--name-only", current, available, "--", "database/migrations"]
    ) if current != available else ""
    commits = [line.split("\t", 1)[-1] for line in commits_raw.splitlines() if line]
    migrations = [line for line in migrations_raw.splitlines() if line]
    return {
        "current_sha": current,
        "source_sha": source_head,
        "available_sha": available,
        "current_version": current[:12],
        "available_version": available[:12],
        "available": current != available,
        "changes": commits,
        "migrations": migrations,
        "checked_at": int(time.time()),
        "runtime_mode": MODE,
        "runtime_platform": "windows" if os.name == "nt" else "linux",
    }


def run_update(target_sha: str, run_migrations: bool, job_id: str) -> None:
    log = ""
    migration_started = False
    try:
        set_state(job={"id": job_id, "status": "running", "stage": "проверка версии", "target_sha": target_sha, "run_migrations": run_migrations, "log": ""})
        check = update_check()
        if check["available_sha"] != target_sha:
            raise RuntimeError("Ветка GitHub изменилась после проверки. Проверьте обновления ещё раз.")
        if check["migrations"] and not run_migrations:
            raise RuntimeError("Для этой версии нужны миграции. Подтвердите их отдельным предупреждением.")

        set_state(job={"id": job_id, "status": "running", "stage": "установка", "target_sha": target_sha, "run_migrations": run_migrations, "log": ""})
        env = os.environ.copy()
        env["APP_DIR"] = str(ROOT)
        env["CURRENT_SHA"] = check["current_sha"]
        env["TARGET_SHA"] = target_sha
        env["RUN_MIGRATIONS"] = "1" if run_migrations else "0"
        env["BACKUP_DIR"] = str(STATE_DIR / "schema-backups")
        try:
            if MODE == "docker":
                command = ["bash", "./update-photoauthapp.sh"]
            elif MODE == "host" and os.name == "nt":
                command = ["powershell.exe", "-NoProfile", "-ExecutionPolicy", "Bypass", "-File", str(ROOT / "update-photoauthapp-host.ps1")]
            elif MODE == "host":
                command = ["bash", "./update-photoauthapp-host.sh"]
            else:
                raise RuntimeError("Неподдерживаемый режим агента обновлений")
            proc = subprocess.run(
                command,
                cwd=ROOT,
                env=env,
                text=True,
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                timeout=3600,
                check=False,
            )
        except subprocess.TimeoutExpired as exc:
            output = exc.stdout or ""
            full_log = output.decode("utf-8", errors="replace") if isinstance(output, bytes) else output
            migration_started = "PHOTOAUTH_MIGRATION_STARTED" in full_log
            log = full_log[-12000:]
            raise RuntimeError((log or "Истекло время ожидания обновления")[-12000:])
        full_log = proc.stdout or ""
        migration_started = "PHOTOAUTH_MIGRATION_STARTED" in full_log
        log = full_log[-12000:]
        if proc.returncode:
            raise RuntimeError(log or "Обновление завершилось с ошибкой")
        set_state(installed_sha=target_sha)
        try:
            refreshed_check = update_check()
        except Exception:
            refreshed_check = None
        set_state(
            check=refreshed_check,
            check_job=None,
            job={"id": job_id, "status": "completed", "stage": "готово", "target_sha": target_sha, "log": log},
        )
    except Exception as exc:  # Report bounded diagnostics to the admin UI.
        current = STATE.get("job") or {}
        if current.get("id") == job_id:
            details = log or str(exc)[-12000:]
            set_state(job={
                "id": job_id,
                "status": "failed",
                "stage": "ошибка",
                "target_sha": target_sha,
                "run_migrations": run_migrations,
                "block_updates": migration_started,
                "log": details,
            })
    finally:
        LOCK.release()


def run_check(check_id: str) -> None:
    try:
        set_state(check_job={"id": check_id, "status": "running", "error": None})
        check = update_check()
        set_state(check=check, check_job={"id": check_id, "status": "completed", "error": None})
    except Exception as exc:
        set_state(check_job={"id": check_id, "status": "failed", "error": str(exc)[-4000:]})
    finally:
        LOCK.release()


class Handler(BaseHTTPRequestHandler):
    server_version = "PhotoAuthUpdater/1.0"

    def log_message(self, _format, *_args):
        return

    def _json(self, status: int, data: dict) -> None:
        payload = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(payload)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(payload)

    def _authorized(self) -> bool:
        supplied = self.headers.get("Authorization", "")
        expected = f"Bearer {TOKEN}"
        return bool(TOKEN) and hmac.compare_digest(supplied, expected)

    def _body(self) -> dict:
        length = int(self.headers.get("Content-Length", "0"))
        if length < 0 or length > 65536:
            raise ValueError("Недопустимый размер запроса")
        raw = self.rfile.read(length) if length else b"{}"
        data = json.loads(raw.decode("utf-8"))
        if not isinstance(data, dict):
            raise ValueError("Ожидался JSON-объект")
        return data

    def _route(self) -> str:
        return urlparse(self.path).path

    def do_GET(self):
        if self._route() == "/health":
            return self._json(200, {"status": "ok", "mode": MODE, "platform": "windows" if os.name == "nt" else "linux"})
        if not self._authorized():
            return self._json(401, {"error": "Не авторизовано"})
        if self._route() == "/status":
            return self._json(200, STATE)
        self._json(404, {"error": "Не найдено"})

    def do_POST(self):
        if not self._authorized():
            return self._json(401, {"error": "Не авторизовано"})
        if self._route() == "/check":
            failed_job = STATE.get("job") or {}
            if failed_job.get("block_updates"):
                return self._json(409, {"error": "Предыдущее обновление остановилось во время миграции. Проверьте приложение и БД, затем подтвердите ручную проверку."})
            if not LOCK.acquire(blocking=False):
                return self._json(409, {"error": "Операция обновления уже выполняется"})
            check_id = str(uuid.uuid4())
            set_state(job=None, check_job={"id": check_id, "status": "queued", "error": None})
            threading.Thread(target=run_check, args=(check_id,), daemon=True).start()
            return self._json(202, {"check_id": check_id, "status": "queued"})
        if self._route() == "/start":
            try:
                failed_job = STATE.get("job") or {}
                if failed_job.get("block_updates"):
                    return self._json(409, {"error": "Сначала подтвердите ручную проверку после ошибки миграции."})
                data = self._body()
                target_sha = str(data.get("target_sha", ""))
                run_migrations = data.get("run_migrations") is True
                if not SHA_RE.fullmatch(target_sha):
                    return self._json(422, {"error": "Неверный идентификатор версии"})
                check = STATE.get("check") or {}
                if check.get("available_sha") != target_sha:
                    return self._json(409, {"error": "Сначала проверьте доступную версию ещё раз"})
                if check.get("migrations") and not run_migrations:
                    return self._json(409, {"error": "Для запуска миграций требуется отдельное подтверждение"})
                if not LOCK.acquire(blocking=False):
                    return self._json(409, {"error": "Обновление уже выполняется"})
                job_id = str(uuid.uuid4())
                set_state(job={"id": job_id, "status": "queued", "stage": "в очереди", "target_sha": target_sha, "run_migrations": run_migrations, "log": ""})
                threading.Thread(target=run_update, args=(target_sha, run_migrations, job_id), daemon=True).start()
                return self._json(202, {"job_id": job_id, "status": "queued"})
            except (ValueError, json.JSONDecodeError) as exc:
                return self._json(400, {"error": str(exc)})
        if self._route() == "/acknowledge-recovery":
            if not LOCK.acquire(blocking=False):
                return self._json(409, {"error": "Операция обновления ещё выполняется"})
            try:
                failed_job = STATE.get("job") or {}
                if not failed_job.get("block_updates"):
                    return self._json(409, {"error": "Нет ошибки миграции, требующей ручной проверки"})
                target_sha = failed_job.get("target_sha")
                if not isinstance(target_sha, str) or not SHA_RE.fullmatch(target_sha):
                    return self._json(409, {"error": "Не найден идентификатор версии для ручного подтверждения"})
                set_state(installed_sha=target_sha, check=None, check_job=None, job=None)
                return self._json(200, {"status": "acknowledged"})
            finally:
                LOCK.release()
        self._json(404, {"error": "Не найдено"})


def main() -> None:
    if MODE not in {"docker", "host"}:
        raise SystemExit("UPDATE_MODE должен быть docker или host")
    read_state()
    STATE_DIR.mkdir(parents=True, exist_ok=True)
    if len(TOKEN) < 32:
        raise SystemExit("UPDATE_AGENT_TOKEN должен содержать не менее 32 символов случайного секрета")
    default_host = "127.0.0.1" if MODE == "host" else "0.0.0.0"
    host = os.environ.get("UPDATE_AGENT_HOST", default_host)
    port = int(os.environ.get("UPDATE_AGENT_PORT", "19100"))
    ThreadingHTTPServer((host, port), Handler).serve_forever()


if __name__ == "__main__":
    main()
