<?php
session_start();

// Параметры подключения к MySQL
$dbHost = '192.168.9.10';
$dbPort = 5432;               // по умолчанию 3306
$dbName = 'Skud';
$dbUser = 'ctrluser';
$dbPass = '52000Turn!';

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
