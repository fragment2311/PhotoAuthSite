<section class="settings-card update-card" id="photoauth-updates"
         data-check-url="{{ route('admin.updates.check') }}"
         data-status-url="{{ route('admin.updates.status') }}"
         data-start-url="{{ route('admin.updates.start') }}"
         data-recovery-url="{{ route('admin.updates.acknowledgeRecovery') }}"
         data-csrf="{{ csrf_token() }}">
  <h2 style="margin-top:0">Обновление PhotoAuthApp</h2>
  <p class="update-status" id="photoauth-update-status" role="status" aria-live="polite">Определяем способ запуска и проверяем обновления…</p>
  <div id="photoauth-update-details" hidden>
    <p><b>Установленная версия:</b> <span id="photoauth-update-current"></span></p>
    <p><b>Доступная версия:</b> <span id="photoauth-update-available"></span></p>
    <p><b>Изменения:</b></p>
    <ul class="update-list" id="photoauth-update-changes"></ul>
    <div id="photoauth-update-migrations-block" hidden>
      <p><b>Миграции базы данных:</b></p>
      <ul class="update-list" id="photoauth-update-migrations"></ul>
      <div class="update-warning">
        Перед запуском сохраняется только структура БД. Копия не содержит записи; миграция может изменить или удалить данные. Продолжайте только после ознакомления со списком.
      </div>
    </div>
  </div>
  <div class="actions">
    <button type="button" class="settings-btn" id="photoauth-update-check" hidden>Проверить обновления</button>
    <button type="button" class="settings-btn" id="photoauth-update-start" hidden>Установить обновление</button>
    <button type="button" class="update-cancel" id="photoauth-update-cancel" hidden>Отмена</button>
    <button type="button" class="settings-btn" id="photoauth-update-recovery" hidden>Подтвердить восстановление</button>
  </div>
  <details id="photoauth-update-log-details" style="margin-top:.75rem" hidden>
    <summary>Подробности</summary>
    <pre class="update-log" id="photoauth-update-log"></pre>
  </details>
</section>

<script>
(function () {
  const root = document.getElementById('photoauth-updates');
  if (!root) return;

  const statusNode = document.getElementById('photoauth-update-status');
  const details = document.getElementById('photoauth-update-details');
  const currentNode = document.getElementById('photoauth-update-current');
  const availableNode = document.getElementById('photoauth-update-available');
  const changesNode = document.getElementById('photoauth-update-changes');
  const migrationsBlock = document.getElementById('photoauth-update-migrations-block');
  const migrationsNode = document.getElementById('photoauth-update-migrations');
  const checkButton = document.getElementById('photoauth-update-check');
  const startButton = document.getElementById('photoauth-update-start');
  const cancelButton = document.getElementById('photoauth-update-cancel');
  const recoveryButton = document.getElementById('photoauth-update-recovery');
  const logDetails = document.getElementById('photoauth-update-log-details');
  const logNode = document.getElementById('photoauth-update-log');
  let checkData = null;
  let polling = false;

  async function request(url, method, body) {
    const response = await fetch(url, {
      method,
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': root.dataset.csrf
      },
      body: body ? JSON.stringify(body) : undefined
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(data.error || 'Не удалось выполнить запрос.');
      error.status = response.status;
      throw error;
    }
    if (!response.headers.get('content-type')?.includes('application/json')) {
      throw new Error('Сеанс авторизации истёк. Войдите в систему и повторите проверку.');
    }
    return data;
  }

  function fillList(node, values, emptyText) {
    node.replaceChildren();
    const items = Array.isArray(values) ? values : [];
    if (!items.length) {
      const li = document.createElement('li');
      li.textContent = emptyText;
      node.appendChild(li);
      return;
    }
    items.forEach((value) => {
      const li = document.createElement('li');
      li.textContent = String(value);
      node.appendChild(li);
    });
  }

  function renderCheck(data) {
    checkData = data;
    details.hidden = false;
    currentNode.textContent = data.current_version || '—';
    availableNode.textContent = data.available_version || '—';
    fillList(changesNode, data.changes, data.available ? 'Изменения не описаны.' : 'Список изменений пуст.');
    fillList(migrationsNode, data.migrations, 'Миграций нет.');
    migrationsBlock.hidden = !(data.migrations && data.migrations.length);
    startButton.hidden = !data.available;
    checkButton.hidden = Boolean(data.available);
    cancelButton.hidden = !data.available;
    recoveryButton.hidden = true;
    const runtime = data.runtime_mode === 'docker'
      ? 'Docker'
      : `без Docker (${data.runtime_platform === 'windows' ? 'Windows' : 'Linux'})`;
    statusNode.textContent = data.available
      ? `Доступно обновление. Способ обновления: ${runtime}.`
      : `Установлена актуальная версия. Способ обновления: ${runtime}.`;
  }

  function showJob(job) {
    if (!job) return false;
    if (job.status === 'queued' || job.status === 'running') {
      statusNode.textContent = job.status === 'queued'
        ? 'Обновление ожидает запуска…'
        : `Выполняется обновление: ${job.stage || 'обработка'}…`;
      checkButton.disabled = true;
      startButton.disabled = true;
      checkButton.hidden = true;
      startButton.hidden = true;
      cancelButton.hidden = true;
      recoveryButton.hidden = true;
      return true;
    }
    checkButton.disabled = false;
    startButton.disabled = false;
    if (job.status === 'completed') {
      statusNode.textContent = 'Обновление завершено.';
      startButton.hidden = true;
      cancelButton.hidden = true;
    } else if (job.status === 'failed') {
      statusNode.textContent = job.error || 'Обновление завершилось с ошибкой.';
      if (job.block_updates) {
        statusNode.textContent = 'Обновление остановилось во время миграции. Проверьте доступность приложения, базу и подробности ниже.';
        startButton.hidden = true;
        cancelButton.hidden = true;
        recoveryButton.hidden = false;
      }
    }
    if (job.log || job.error) {
      logNode.textContent = job.log || job.error;
      logDetails.hidden = false;
    }
    return false;
  }

  async function poll() {
    if (polling) return;
    polling = true;
    let keepPolling = true;
    try {
      const state = await request(root.dataset.statusUrl, 'GET');
      const checkJob = state.check_job;
      const activeCheck = checkJob && (checkJob.status === 'queued' || checkJob.status === 'running');
      const activeUpdate = state.job && (state.job.status === 'queued' || state.job.status === 'running');

      if (state.check) renderCheck(state.check);
      if (activeUpdate) {
        showJob(state.job);
        keepPolling = true;
      } else if (activeCheck) {
        statusNode.textContent = 'Проверяем GitHub…';
        checkButton.disabled = true;
        checkButton.hidden = true;
        startButton.hidden = true;
        cancelButton.hidden = true;
        recoveryButton.hidden = true;
        keepPolling = true;
      } else if (checkJob && checkJob.status === 'failed') {
        statusNode.textContent = checkJob.error || 'Не удалось проверить обновления.';
        checkButton.disabled = false;
        checkButton.hidden = false;
        if (state.job && state.job.status === 'failed') showJob(state.job);
        keepPolling = false;
      } else {
        keepPolling = showJob(state.job);
      }
    } catch (_error) {
      statusNode.textContent = 'Связь с приложением временно прервана; проверяем статус…';
      keepPolling = true;
    } finally {
      polling = false;
      if (keepPolling) window.setTimeout(poll, 1800);
    }
  }

  checkButton.addEventListener('click', async function () {
    checkButton.disabled = true;
    checkButton.hidden = true;
    statusNode.textContent = 'Проверяем GitHub…';
    try {
      await request(root.dataset.checkUrl, 'POST', {});
      poll();
    } catch (error) {
      checkButton.disabled = false;
      checkButton.hidden = false;
      statusNode.textContent = error.message;
      if (error.status === 409) poll();
    }
  });

  startButton.addEventListener('click', async function () {
    if (!checkData || !checkData.available) return;
    const hasMigrations = Boolean(checkData.migrations && checkData.migrations.length);
    if (hasMigrations && !window.confirm(
      'В обновлении есть миграции. Будет сохранена только структура базы, без записей. Миграции могут изменить или удалить данные. Запустить обновление и миграции?'
    )) return;

    startButton.disabled = true;
    checkButton.disabled = true;
    statusNode.textContent = 'Запускаем обновление…';
    try {
      await request(root.dataset.startUrl, 'POST', {
        target_sha: checkData.available_sha,
        run_migrations: hasMigrations
      });
      poll();
    } catch (error) {
      startButton.disabled = false;
      checkButton.disabled = false;
      statusNode.textContent = error.message;
      if (error.status === 409) poll();
    }
  });

  cancelButton.addEventListener('click', function () {
    startButton.hidden = true;
    cancelButton.hidden = true;
    details.hidden = true;
    checkButton.hidden = false;
    statusNode.textContent = 'Установка отменена. Проверка обновления сохранена до следующей проверки.';
  });

  recoveryButton.addEventListener('click', async function () {
    if (!window.confirm('Подтвердите, что вы вручную проверили приложение и базу после ошибки миграции и завершили необходимое восстановление. Эта кнопка только снимет блок агента.')) return;
    recoveryButton.disabled = true;
    try {
      await request(root.dataset.recoveryUrl, 'POST', {});
      recoveryButton.disabled = false;
      recoveryButton.hidden = true;
      statusNode.textContent = 'Проверка подтверждена. Повторно проверяем обновления…';
      await request(root.dataset.checkUrl, 'POST', {});
      poll();
    } catch (error) {
      recoveryButton.disabled = false;
      statusNode.textContent = error.message;
    }
  });

  request(root.dataset.checkUrl, 'POST', {}).then(poll).catch((error) => {
    statusNode.textContent = error.message;
    checkButton.hidden = false;
    if (error.status === 409) poll();
  });
})();
</script>
