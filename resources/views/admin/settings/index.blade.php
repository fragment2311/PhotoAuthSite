<!DOCTYPE html>
<html lang="ru">
<head>
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"/>
  <meta http-equiv="Pragma" content="no-cache"/>
  <meta http-equiv="Expires" content="0"/>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Настройки</title>

  <style>
    *, *::before, *::after { box-sizing: border-box; }

    body{
      margin:0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background:#f3f4f6;
      color:#111827;
    }

.settings-container{
  margin: 24px auto 24px;   /* подвинуты выше */
  padding: 0 1rem;
  max-width: 1500px;
}



    h1 { margin: 0 0 1rem; font-size: 1.8rem; }
    h2 { margin: 1.6rem 0 .75rem; font-size: 1.25rem; font-weight: 600; }

   .settings-card{
  background:#fff;
  border:1px solid #e1e1e1;
  border-radius:10px;
  padding: 1.2rem 1.4rem 1.5rem;
  box-shadow: 0 1px 3px rgba(0,0,0,.06);
  width: 100%;        /* занимаем всю ширину контейнера */
  margin: 0 auto;     /* и остаёмся центрированными */
  /* max-width убрали */
}



    .row{
      display:flex;
      gap:1rem;
      flex-wrap:wrap;
      align-items:flex-end;
    }
    .field{
      display:flex;
      flex-direction:column;
      gap:.35rem;
      min-width: 240px;
    }
    .field label{
      font-size: 0.95rem;
      color:#374151;
    }
    .field input,
    .field select{
      font-size: 1rem;
      padding: .55rem .7rem;
      border: 1px solid #bbb;
      border-radius: 8px;
      background:#fff;
      width: 100%;
    }

    .actions{
      display:flex;
      gap:.6rem;
      flex-wrap:wrap;
      margin-top: 1rem;
    }

    .alert{
      padding: .75rem 1rem;
      border-radius: 8px;
      margin: 0 0 1rem;
      border: 1px solid transparent;
      font-size: .96rem;
    }
    .alert-ok{
      background:#d4edda;
      color:#155724;
      border-color:#c3e6cb;
    }
    .alert-err{
      background:#f8d7da;
      color:#721c24;
      border-color:#f5c6cb;
    }

    .help{
      margin-top: .35rem;
      font-size: .9rem;
      color:#6b7280;
    }

    .pill{
      display:inline-flex;
      align-items:center;
      gap:.5rem;
      padding:.25rem .6rem;
      border-radius:999px;
      font-size:.9rem;
      border:1px solid #e5e7eb;
      background:#f9fafb;
      color:#111827;
    }
    .pill-ok{
      border-color:#c3e6cb;
      background:#d4edda;
      color:#155724;
    }
    .pill-err{
      border-color:#f5c6cb;
      background:#f8d7da;
      color:#721c24;
    }
    .pill-warn{
      border-color:#ffeeba;
      background:#fff3cd;
      color:#856404;
    }

    .divider{ height:1px; background:#e5e7eb; margin:1.2rem 0; }

    .update-card{margin-bottom:1rem}
    #photoauth-updates [hidden]{display:none !important}
    #photoauth-updates .settings-btn{min-height:44px;margin:0}
    #photoauth-updates .update-cancel{
      min-height:44px;padding:.55rem .75rem;border:0;background:transparent;
      color:#4b5563;font:inherit;text-decoration:underline;cursor:pointer;
    }
    #photoauth-updates .update-cancel:hover{color:#111827}
    #photoauth-updates .update-cancel:focus-visible{outline:2px solid #2563eb;outline-offset:2px;border-radius:4px}
    .update-status{margin:.35rem 0 .75rem;color:#4b5563}
    .update-list{margin:.45rem 0 .8rem 1.25rem;padding:0}
    .update-warning{padding:.75rem 1rem;border:1px solid #f0c36d;border-radius:8px;background:#fff8e5;color:#713f12;margin:.75rem 0}
    .update-log{white-space:pre-wrap;max-height:260px;overflow:auto;background:#111827;color:#e5e7eb;padding:.75rem;border-radius:8px;font-size:.85rem}

    /* КНОПКИ СТРАНИЦЫ НАСТРОЕК (чтобы не ломать .btn из шапки/Bootstrap) */
    .settings-btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:.55rem .9rem;
      border-radius:8px;
      border:1px solid #2563eb;
      background:#2563eb;
      color:#fff;
      font-size:.95rem;
      font-weight:600;
      text-decoration:none;
      cursor:pointer;
      user-select:none;
      line-height:1.2;
      transition:background-color .15s ease, border-color .15s ease, transform .03s ease, box-shadow .15s ease;
    }
    .settings-btn:hover{
      background:#1d4ed8;
      border-color:#1d4ed8;
      box-shadow:0 1px 3px rgba(37,99,235,.4);
    }
    .settings-btn:active{ transform:translateY(1px); box-shadow:none; }

    .settings-btn-secondary{
      background:#f9fafb;
      border-color:#d1d5db;
      color:#111827;
      margin: 15px 0px 0px 0px;
    }
    .settings-btn-secondary:hover{
      background:#e5e7eb;
      border-color:#9ca3af;
      box-shadow:none;
    }

    /* Кастомный input type=file */
    .filebox{
      display:flex;
      align-items:center;
      gap:.75rem;
      flex-wrap:wrap;
    }
    .filebox input[type="file"]{
      position:absolute;
      left:-9999px;
      width:1px;
      height:1px;
      opacity:0;
    }
    .filebox label.settings-btn{
      color:#fff;
      background:#2563eb;
      border-color:#2563eb;
    }
    .filename{
      font-size:.9rem;
      color:#4b5563;
      padding:.5rem .7rem;
      border:1px solid #d1d5db;
      border-radius:8px;
      background:#f9fafb;
      min-width:260px;
      max-width:480px;
      overflow:hidden;
      text-overflow:ellipsis;
      white-space:nowrap;
    }

    .copy-token-status{
      display:none;
      align-self:center;
      margin-top:0;
      font-size:.9rem;
      color:#2563eb;
    }
    .copy-token-status.is-error{
      color:#b91c1c;
    }

    @media (max-width:700px){
      .container { padding: 0 .75rem; margin-top: 70px; }
      .field { min-width: 100%; }
    }
  </style>

  <link rel="stylesheet" href="{{ asset('css/header-buttons.css') }}">
</head>
<body>
  @if(!($setupMode ?? false))
    @include('layouts.header', ['headerTitle' => 'Настройки'])
  @endif

  <div class="settings-container">

    @if(session('ok'))
      <div class="alert alert-ok">{{ session('ok') }}</div>
    @endif

    @if(session('err'))
      <div class="alert alert-err">{{ session('err') }}</div>
    @endif

    @if(($setupMode ?? false) && !empty($missingCritical ?? []))
      <div class="alert alert-err">
        <b>Не заполнены критичные параметры:</b>
        <ul style="margin:8px 0 0 18px;">
          @foreach(($missingCritical ?? []) as $field)
            <li>{{ $field }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-err">
        <b>Ошибки:</b>
        <ul style="margin:8px 0 0 18px;">
          @foreach($errors->all() as $e)
            <li>{{ $e }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @php
      $license     = $license ?? null;
      $hasLicense  = $license && ($license['exists'] ?? false);
      $isValid     = $license && ($license['valid'] ?? false);
      $isGrace     = $license && ($license['is_grace'] ?? false);
      $isGraceOver = $license && ($license['is_grace_over'] ?? false);

      $pillClass = 'pill-err';
      $pillText  = 'Лицензия не найдена';

      if ($hasLicense && $isValid && !$isGrace && !$isGraceOver) {
        $pillClass = 'pill-ok';
        $pillText  = 'Лицензия активна';
      } elseif ($hasLicense && $isValid && $isGrace && !$isGraceOver) {
        $pillClass = 'pill-warn';
        $pillText  = 'Просрочена (grace)';
      } elseif ($hasLicense && $isGraceOver) {
        $pillClass = 'pill-err';
        $pillText  = 'Лицензия просрочена (grace закончился)';
      }

      $remainPillClass = $isGrace ? 'pill-warn' : '';
    @endphp

    @if(!($setupMode ?? false))
      @include('admin.settings._updates')
    @endif

    <div class="settings-card">
      <h2>Лицензия</h2>

      <div class="row" style="align-items:center; margin-bottom:.35rem;">
        <span class="pill {{ $pillClass }}">{{ $pillText }}</span>

        @if($hasLicense && !empty($license['valid_until']))
          <span class="pill">
            Срок до: <b>{{ $license['valid_until'] }}</b>
          </span>
        @endif

        @if($hasLicense && $isValid && !empty($license['remaining_human']))
          <span class="pill {{ $remainPillClass }}">
            @if($isGrace && !$isGraceOver)
              До конца grace: <b>{{ $license['remaining_human'] }}</b>
            @else
              До окончания: <b>{{ $license['remaining_human'] }}</b>
            @endif
          </span>
        @endif

        @if($isGrace && !$isGraceOver)
          <span class="pill pill-warn"><b>GRACE</b> • Просрочена, доступ пока разрешён.</span>
        @endif

        @if($isGraceOver)
          <span class="pill pill-err"><b>GRACE</b> • Закончился, доступ заблокирован.</span>
        @endif
      </div>

      @if(!$hasLicense || !$isValid || $isGraceOver)
        <div class="help" style="color:#b91c1c; margin-bottom:.35rem;">
          @if(!$hasLicense)
            Файл <b>license.lic</b> не найден. Загрузите файл лицензии, чтобы включить функции.
          @elseif($isGraceOver)
            Срок действия лицензии и grace-период закончились. Продлите лицензию и загрузите новый файл.
          @else
            Лицензия недействительна: {{ $license['reason'] ?? 'уточните причину в логах.' }}
          @endif
        </div>
      @endif

      <form method="POST" action="{{ route('admin.settings.license.upload') }}" enctype="multipart/form-data" style="margin-top:.75rem;">
        @csrf
        <div class="row">
          <div class="field" style="min-width:520px;">
            <label>Файл лицензии (license.lic)</label>
            <div class="filebox">
              <label class="settings-btn" for="license_file">Выбрать файл</label>
              <span id="license_filename" class="filename">Файл не выбран</span>
              <input id="license_file" name="license_file" type="file" accept=".lic,application/octet-stream">
            </div>
            <div class="help">Загрузка файла <b>license.lic</b> заменяет текущую лицензию на сервере.</div>
          </div>

          <div class="actions" style="margin-top:1.7rem;">
            <button id="license_upload_btn" type="submit" class="settings-btn">Загрузить / заменить</button>
          </div>
        </div>
      </form>

      <div class="divider"></div>

      <form method="POST" action="{{ ($setupMode ?? false) ? route('setup.settings.save') : route('admin.settings.save') }}" enctype="multipart/form-data">
        @csrf

        <h2>Лицензия (client_id)</h2>
        <div class="row">
          <div class="field" style="min-width:420px;">
            <label for="license_client_id">Ожидаемый client_id лицензии</label>
            <input
              id="license_client_id"
              name="license_client_id"
              value="{{ $s['license_client_id'] ?? config('license.client_id', '') }}"
              placeholder="Напр.: my-company-branch-01"
            >
            <div class="help">
              Укажите <b>client_id</b> из лицензии (это может быть ИНН или название компании).
            </div>
          </div>
        </div>

        <h2>База данных</h2>
        <div class="row">
          <div class="field">
            <label for="db_host">Host</label>
            <input id="db_host" name="db_host" value="{{ $s['db_host'] ?? '' }}">
          </div>

          <div class="field" style="min-width:120px;">
            <label for="db_port">Port</label>
            <input id="db_port" name="db_port" value="{{ $s['db_port'] ?? '3306' }}">
          </div>

          <div class="field">
            <label for="db_name">Database</label>
            <input id="db_name" name="db_name" value="{{ $s['db_name'] ?? '' }}">
          </div>

          <div class="field">
            <label for="db_user">User</label>
            <input id="db_user" name="db_user" value="{{ $s['db_user'] ?? '' }}">
          </div>

          <div class="field">
            <label for="db_pass">Password</label>
            <input id="db_pass" type="password" name="db_pass" value="{{ $s['db_pass'] ?? '' }}">
          </div>
        </div>

        <h2>Доступ к первичной настройке</h2>
        <div class="row">
          <div class="field" style="min-width:320px;">
            <label for="setup_master_login">Мастер-логин</label>
            <input id="setup_master_login" name="setup_master_login" value="{{ $s['setup_master_login'] ?? 'MASTER' }}" placeholder="Напр.: MASTER">
          </div>

          <div class="field" style="min-width:320px;">
            <label for="setup_master_password">Мастер-пароль</label>
            <input id="setup_master_password" type="password" name="setup_master_password" placeholder="Оставьте пустым, чтобы не менять">
          </div>

          <div class="field" style="min-width:320px;">
            <label for="setup_master_password_confirmation">Подтверждение мастер-пароля</label>
            <input id="setup_master_password_confirmation" type="password" name="setup_master_password_confirmation" placeholder="Повторите пароль">
          </div>
        </div>

        <h2>Галерея и HTTP-источник</h2>
        <div class="row">
          <div class="field" style="min-width:320px;">
            <label for="gallery_root_dir">Корневая папка галереи</label>
            <input id="gallery_root_dir" name="gallery_root_dir" value="{{ $s['gallery_root_dir'] ?? '' }}" placeholder="Напр.: photos/main">
          </div>

          <div class="field" style="min-width:360px;">
            <label for="photos_staff_path">Путь к staff-фото</label>
            <input id="photos_staff_path" name="photos_staff_path" value="{{ $s['photos_staff_path'] ?? '' }}" placeholder="Напр.: /photos/main/PhotosStaff">
          </div>
        </div>

        <div class="row" style="margin-top:.5rem;">
          <div class="field" style="min-width:420px;">
            <label for="photos_not_found_upload">Загрузить файл-заглушку</label>
            <div class="filebox">
              <label class="settings-btn" for="photos_not_found_upload">Выбрать файл</label>
              <span id="photos_not_found_filename" class="filename">Файл не выбран</span>
              <input id="photos_not_found_upload" name="photos_not_found_upload" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.svg,image/*">
            </div>
          </div>
        </div>

        <h2>Хранилище фото</h2>

        @php
          $storageType = $s['photo_storage_type'] ?? 'share';
        @endphp

        <div class="row">
          <div class="field" style="min-width:260px;">
            <label for="photo_storage_type">Тип хранилища</label>
            <select id="photo_storage_type" name="photo_storage_type">
              <option value="share" @selected($storageType==='share')>Расшаренная папка</option>
              <option value="ftps" @selected($storageType==='ftps')>FTPS</option>
            </select>
          </div>
        </div>

        {{-- Расшаренная папка --}}
        <div id="block_share" style="margin-top:.75rem; display:none;">
          <div class="row">
            <div class="field" style="min-width:520px;">
              <label for="photo_share_path">Путь к папке</label>
              <input id="photo_share_path" name="photo_share_path" value="{{ $s['photo_share_path'] ?? '' }}" placeholder="Напр.: \\SERVER\Share\Photos или /mnt/photos">
            </div>
            <div class="field" style="min-width:260px;">
              <label for="photo_share_subdir">Подпапка (опц.)</label>
              <input id="photo_share_subdir" name="photo_share_subdir" value="{{ $s['photo_share_subdir'] ?? '' }}" placeholder="Напр.: passages">
            </div>
          </div>
        </div>

        {{-- FTPS --}}
        <div id="block_ftps" style="margin-top:.75rem; display:none;">
          <div class="row">
            <div class="field">
              <label for="photo_ftps_host">Host</label>
              <input id="photo_ftps_host" name="photo_ftps_host" value="{{ $s['photo_ftps_host'] ?? '' }}" placeholder="ftps.example.com">
            </div>

            <div class="field" style="min-width:120px;">
              <label for="photo_ftps_port">Port</label>
              <input id="photo_ftps_port" name="photo_ftps_port" value="{{ $s['photo_ftps_port'] ?? '21' }}">
            </div>

            <div class="field">
              <label for="photo_ftps_user">User</label>
              <input id="photo_ftps_user" name="photo_ftps_user" value="{{ $s['photo_ftps_user'] ?? '' }}">
            </div>

            <div class="field">
              <label for="photo_ftps_pass">Password</label>
              <input id="photo_ftps_pass" type="password" name="photo_ftps_pass" value="{{ $s['photo_ftps_pass'] ?? '' }}">
            </div>
          </div>

          <div class="row" style="margin-top:.5rem;">
            <div class="field" style="min-width:320px;">
              <label for="photo_ftps_dir">Удалённая папка</label>
              <input id="photo_ftps_dir" name="photo_ftps_dir" value="{{ $s['photo_ftps_dir'] ?? '' }}" placeholder="/photos">
            </div>

            @php $passive = $s['photo_ftps_passive'] ?? '1'; @endphp
            @php $ssl = $s['photo_ftps_ssl'] ?? '1'; @endphp
            @php $allowSelfSigned = (string)($s['photo_ftps_allow_self_signed'] ?? '0'); @endphp
            @php $serverCertThumbprint = $s['photo_ftps_server_cert_thumbprint'] ?? ''; @endphp

            <div class="field" style="min-width:200px;">
              <label for="photo_ftps_passive">Режим</label>
              <select id="photo_ftps_passive" name="photo_ftps_passive">
                <option value="1" @selected($passive==='1')>Passive</option>
                <option value="0" @selected($passive==='0')>Active</option>
              </select>
            </div>

            <div class="field" style="min-width:220px;">
              <label for="photo_ftps_ssl">Шифрование</label>
              <select id="photo_ftps_ssl" name="photo_ftps_ssl">
                <option value="1" @selected($ssl==='1')>FTPS (SSL)</option>
                <option value="0" @selected($ssl==='0')>Обычный FTP</option>
              </select>
            </div>

            <div class="field" style="min-width:260px;">
              <label for="photo_ftps_allow_self_signed">Сертификат сервера</label>
              <select id="photo_ftps_allow_self_signed" name="photo_ftps_allow_self_signed">
                <option value="0" @selected($allowSelfSigned==='0')>Только доверенный (CA)</option>
                <option value="1" @selected($allowSelfSigned==='1')>Разрешить самоподписанный</option>
              </select>
            </div>
          </div>

          <div class="row" style="margin-top:.5rem;">
            <div class="field" id="photo_ftps_server_cert_thumbprint_wrap" style="min-width:420px; display:none;">
              <label for="photo_ftps_server_cert_thumbprint">Отпечаток сертификата (опционально)</label>
              <input
                id="photo_ftps_server_cert_thumbprint"
                name="photo_ftps_server_cert_thumbprint"
                value="{{ $serverCertThumbprint }}"
                placeholder="Напр.: AA:BB:CC:... или AABBCC..."
              >
            </div>
          </div>
        </div>

        <div class="row">
          <div class="field" style="min-width:420px;">
            <div class="actions" style="margin-top:0;">
              <button
                type="button"
                id="copy_device_pairs_token_btn"
                class="settings-btn settings-btn-secondary"
                data-copy-url="{{ ($setupMode ?? false) ? route('setup.settings.devicePairsApiToken') : route('admin.settings.devicePairsApiToken') }}"
              >
                Скопировать токен
              </button>
              <button
                type="button"
                id="generate_device_pairs_token_btn"
                class="settings-btn settings-btn-secondary"
                data-generate-url="{{ ($setupMode ?? false) ? route('setup.settings.devicePairsApiToken.generate') : route('admin.settings.devicePairsApiToken.generate') }}"
              >
                Сгенерировать токен
              </button>
              <span id="copy_device_pairs_token_status" class="copy-token-status" aria-live="polite"></span>
            </div>
          </div>
        </div>

        <div class="row" style="margin-top:.5rem;">
          <div class="field" style="min-width:420px;">
            <a
              href="{{ route('admin.settings.licenseLog.download') }}"
              class="settings-btn settings-btn-secondary"
              style="margin-top:0;"
            >
              Скачать лог лицензии
            </a>
          </div>
        </div>

        <div class="actions">
          <button type="submit" class="settings-btn">Сохранить</button>
          <a href="{{ url()->previous() }}" class="settings-btn settings-btn-secondary">Назад</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function(){
      const sel = document.getElementById('photo_storage_type');
      const bShare = document.getElementById('block_share');
      const bFtps  = document.getElementById('block_ftps');
      const certMode = document.getElementById('photo_ftps_allow_self_signed');
      const certThumbprintWrap = document.getElementById('photo_ftps_server_cert_thumbprint_wrap');

      function applyStorage(){
        const v = sel ? sel.value : 'share';
        if (bShare) bShare.style.display = (v === 'share') ? 'block' : 'none';
        if (bFtps)  bFtps.style.display  = (v === 'ftps') ? 'block' : 'none';
      }

      function applyCertMode(){
        const allowSelfSigned = certMode ? certMode.value === '1' : false;
        if (certThumbprintWrap) {
          certThumbprintWrap.style.display = allowSelfSigned ? 'flex' : 'none';
        }
      }

      if (sel) {
        sel.addEventListener('change', applyStorage);
        applyStorage();
      }

      if (certMode) {
        certMode.addEventListener('change', applyCertMode);
        applyCertMode();
      }

      const lf = document.getElementById('license_file');
      const ln = document.getElementById('license_filename');
      const uploadBtn = document.getElementById('license_upload_btn');
      const notFoundInput = document.getElementById('photos_not_found_upload');
      const notFoundName = document.getElementById('photos_not_found_filename');
      const copyTokenBtn = document.getElementById('copy_device_pairs_token_btn');
      const generateTokenBtn = document.getElementById('generate_device_pairs_token_btn');
      const copyTokenStatus = document.getElementById('copy_device_pairs_token_status');
      const csrfToken = '{{ csrf_token() }}';
      let copyTokenStatusTimer = null;

      function setDisabledUpload(disabled){
        if (!uploadBtn) return;
        uploadBtn.disabled = disabled;
        uploadBtn.style.opacity = disabled ? '0.55' : '1';
        uploadBtn.style.cursor  = disabled ? 'not-allowed' : 'pointer';
      }

      if (lf && ln) {
        setDisabledUpload(true);
        lf.addEventListener('change', function(){
          const f = lf.files && lf.files[0] ? lf.files[0] : null;
          ln.textContent = f ? f.name : 'Файл не выбран';
          setDisabledUpload(!f);
        });
      }

      if (notFoundInput && notFoundName) {
        notFoundInput.addEventListener('change', function(){
          const f = notFoundInput.files && notFoundInput.files[0] ? notFoundInput.files[0] : null;
          notFoundName.textContent = f ? f.name : 'Файл не выбран';
        });
      }

      function showCopyTokenStatus(text, isError, timeoutMs) {
        if (!copyTokenStatus) return;

        if (copyTokenStatusTimer) {
          clearTimeout(copyTokenStatusTimer);
          copyTokenStatusTimer = null;
        }

        copyTokenStatus.textContent = text || '';
        copyTokenStatus.style.display = text ? 'inline' : 'none';
        copyTokenStatus.classList.toggle('is-error', !!isError);

        if (timeoutMs && timeoutMs > 0) {
          copyTokenStatusTimer = setTimeout(function(){
            copyTokenStatus.textContent = '';
            copyTokenStatus.style.display = 'none';
            copyTokenStatus.classList.remove('is-error');
            copyTokenStatusTimer = null;
          }, timeoutMs);
        }
      }

      async function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(text);
          return;
        }

        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', 'readonly');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();

        const ok = document.execCommand('copy');
        document.body.removeChild(ta);

        if (!ok) {
          throw new Error('copy_failed');
        }
      }

      async function fetchToken(url, method) {
        const options = {
          method: method,
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
        };

        if (method === 'POST') {
          options.headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const res = await fetch(url, options);
        const payload = await res.json();

        if (!res.ok || !payload || payload.ok !== true || !payload.token) {
          throw new Error(payload && payload.message ? payload.message : 'token_not_available');
        }

        return String(payload.token);
      }

      if (copyTokenBtn) {
        copyTokenBtn.addEventListener('click', async function(){
          const url = copyTokenBtn.dataset.copyUrl;
          if (!url) {
            return;
          }

          copyTokenBtn.disabled = true;
          copyTokenBtn.style.opacity = '0.7';
          showCopyTokenStatus('Копирование...', false, 0);

          try {
            const token = await fetchToken(url, 'GET');
            await copyToClipboard(token);
            showCopyTokenStatus('Скопировано в буфер обмена', false, 2200);
          } catch (e) {
            showCopyTokenStatus('Не удалось скопировать токен', true, 3000);
          } finally {
            copyTokenBtn.disabled = false;
            copyTokenBtn.style.opacity = '1';
          }
        });
      }

      if (generateTokenBtn) {
        generateTokenBtn.addEventListener('click', async function(){
          const url = generateTokenBtn.dataset.generateUrl;
          if (!url) {
            return;
          }

          generateTokenBtn.disabled = true;
          generateTokenBtn.style.opacity = '0.7';
          showCopyTokenStatus('Генерация...', false, 0);

          try {
            const token = await fetchToken(url, 'POST');
            await copyToClipboard(token);
            showCopyTokenStatus('Сгенерировано и скопировано', false, 2400);
          } catch (e) {
            showCopyTokenStatus('Не удалось сгенерировать токен', true, 3000);
          } finally {
            generateTokenBtn.disabled = false;
            generateTokenBtn.style.opacity = '1';
          }
        });
      }
    })();
  </script>
</body>
</html>
