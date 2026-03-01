<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"/>
    <meta http-equiv="Pragma" content="no-cache"/>
    <meta http-equiv="Expires" content="0"/>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Вход</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #f4f4f4;
            font-family: Arial, sans-serif;
        }
        .box {
            display: flex;
            flex-direction: column;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            width: 400px;
            box-sizing: border-box;
        }
        h2 { margin: 0 0 20px; font-size: 24px; text-align: center; }
        .status {
            color: #374151;
            font-size: 14px;
            margin-bottom: 15px;
            text-align: center;
        }
        .error {
            color: #c00;
            font-size: 14px;
            margin-bottom: 10px;
            text-align: center;
        }
        form { display: flex; flex-direction: column; }
        input[type="text"],
        input[type="password"],
        input[type="submit"] {
            width: 100%;
            margin-bottom: 15px;
            font-size: 18px;
            padding: 12px;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="text"],
        input[type="password"] { border: 1px solid #ccc; }
        input[type="submit"] {
            background: #28a745;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        input[type="submit"]:hover { background: #218838; }
    </style>
</head>
<body>
<div class="box">
    <h2>Вход</h2>

    <div class="status">Для мастер-пароля оставьте поле «Логин» пустым.</div>

    @if(session('err'))
        <div class="error">{{ session('err') }}</div>
    @endif


    @error('login')   <div class="error">{{ $message }}</div> @enderror
    @error('password')<div class="error">{{ $message }}</div> @enderror

    <form method="POST" action="{{ route('setup.auth.submit') }}">
        @csrf
        <input type="text" name="login" placeholder="Логин" value="{{ old('login') }}" autofocus>
        <input type="password" name="password" placeholder="Пароль" required>
        <input type="submit" value="Войти">
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.history && window.history.pushState) {
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function() {
            window.history.pushState(null, null, window.location.href);
        };
    }

    fetch(window.location.href, {
        headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    });
});
</script>
</body>
</html>

