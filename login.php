<?php
session_start();

/**
 * Пробует загрузить Laravel bootstrap, чтобы при необходимости расшифровать
 * секреты из storage/app/settings.json (формат AppSettingsFileStore).
 */
$laravelBootstrapped = false;
if (is_file(__DIR__ . '/vendor/autoload.php') && is_file(__DIR__ . '/bootstrap/app.php')) {
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $app = require __DIR__ . '/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $laravelBootstrapped = true;
    } catch (Throwable $e) {
        $laravelBootstrapped = false;
    }
}

/**
 * Читает настройки приложения из файла.
 */
$settings = [];
$settingsPath = __DIR__ . '/storage/app/settings.json';
if (is_file($settingsPath)) {
    $raw = @file_get_contents($settingsPath);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $settings = $decoded;
        }
    }
}

/**
 * Возвращает значение из settings.json, затем из ENV, затем fallback.
 */
$pick = static function (string $settingsKey, string $envKey, $fallback = null) use ($settings) {
    if (array_key_exists($settingsKey, $settings) && $settings[$settingsKey] !== null && $settings[$settingsKey] !== '') {
        return $settings[$settingsKey];
    }

    $env = getenv($envKey);
    if ($env !== false && $env !== '') {
        return $env;
    }

    return $fallback;
};

// Параметры подключения к БД из settings.json/.env
$dbHost = (string) $pick('db_host', 'DB_HOST', '127.0.0.1');
$dbPort = (int) $pick('db_port', 'DB_PORT', 3306);
$dbName = (string) $pick('db_name', 'DB_DATABASE', 'laravel');
$dbUser = (string) $pick('db_user', 'DB_USERNAME', 'root');
$dbPass = (string) $pick('db_pass', 'DB_PASSWORD', '');

// Если пароль в settings.json зашифрован Laravel Crypt::encryptString()
if ($laravelBootstrapped && $dbPass !== '') {
    try {
        $dbPass = Illuminate\Support\Facades\Crypt::decryptString($dbPass);
    } catch (Throwable $e) {
        // Оставляем как есть (вдруг пароль был в открытом виде).
    }
}

$error = '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Ошибка подключения к БД: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    // Берём первый хешированный пароль из таблицы users
    $stmt = $pdo->query('SELECT id, password FROM users LIMIT 1');
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id']       = $user['id'];
        header('Location: gallery.php');
        exit;
    } else {
        $error = 'Неверный пароль.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Вход</title>
  <style>
    body { display:flex; align-items:center; justify-content:center; height:100vh; background:#f0f0f0; margin:0; }
    .box { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); width:280px; }
    h2 { text-align:center; margin-top:0; }
    .error { color:#c00; text-align:center; margin-bottom:10px; }
    input[type="password"], input[type="submit"] {
      width:100%; padding:10px; margin-bottom:10px; box-sizing:border-box;
      border-radius:4px; border:1px solid #ccc;
    }
    input[type="submit"] { background:#28a745; color:#fff; border:0; cursor:pointer; }
    input[type="submit"]:hover { background:#218838; }
  </style>
</head>
<body>
  <div class="box">
    <h2>Авторизация</h2>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="">
      <input type="password" name="password" placeholder="Введите пароль" required autofocus>
      <input type="submit" value="Войти">
    </form>
  </div>
</body>
</html>
