<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\Webuser;
use App\Models\Role;

class AdminController extends Controller
{
    /** 1) Список пользователей с поиском по логину */
    public function users(Request $request)
    {
        $login = $request->query('login', '');
        $query = Webuser::with('role');

        if ($login !== '') {
            $query->where('Login', 'like', "%{$login}%");
        }

        $users = $query->get();

        return view('admin.users', [
            'users'  => $users,
            'search' => $login,
        ]);
    }

    /** 2) Форма редактирования */
    public function edit(Webuser $user)
    {
        $roles = Role::all();
        return view('admin.edit_user', compact('user', 'roles'));
    }

    /** 3) Сохранение редактирования */
    public function update(Request $req, Webuser $user)
    {
        $data = $req->validate([
            'login'    => 'required|string|min:3|max:200|unique:webusers,Login,' . $user->Id . ',Id',
            'role_id'  => 'required|integer|exists:role,ID',
            'password' => 'nullable|string|confirmed|min:6',
        ], [
            'login.required'     => 'Логин обязателен.',
            'login.unique'       => 'Логин уже занят.',
            'role_id.required'   => 'Выберите роль.',
            'role_id.exists'     => 'Неверная роль.',
            'password.min'       => 'Пароль минимум 6 символов.',
            'password.confirmed' => 'Пароли не совпадают.',
        ]);

        $user->Login      = $data['login'];
        $user->IdRole     = $data['role_id'];
        $user->LastUpdate = DB::raw('CURRENT_TIMESTAMP');

        if (!empty($data['password'])) {
            $user->Password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()
            ->route('admin.users.edit', $user->Id)
            ->with('status', 'Данные пользователя обновлены');
    }

    /** 4) Форма создания */
    public function showRegister()
    {
        $roles = Role::all();
        return view('admin.register', compact('roles'));
    }

    /** 5) Обработка создания */
    public function register(Request $req)
{
    $data = $req->validate([
        'login'    => 'required|string|min:3|max:200|unique:webusers,Login',
        'password' => 'required|string|confirmed|min:6',
        'role_id'  => 'required|integer|exists:role,ID',
    ], [
        'login.required'     => 'Логин обязателен.',
        'login.unique'       => 'Логин уже занят.',
        'password.required'  => 'Пароль обязателен.',
        'password.confirmed' => 'Пароли не совпадают.',
        'password.min'       => 'Пароль минимум 6 символов.',
        'role_id.required'   => 'Выберите роль.',
        'role_id.exists'     => 'Неверная роль.',
    ]);

    Webuser::create([
        'Login'      => $data['login'],
        'Password'   => Hash::make($data['password']),
        'IdRole'     => $data['role_id'],
        'IsActive'   => 1,                      // <— добавили!
        'LastUpdate' => DB::raw('CURRENT_TIMESTAMP'),
    ]);

    return redirect()
        ->route('admin.users')
        ->with('status', 'Пользователь успешно создан');
}

    /** 6) Блокировка / активация пользователя */
    public function toggleActive(Webuser $user)
    {
        $user->IsActive   = ! $user->IsActive;
        $user->LastUpdate = DB::raw('CURRENT_TIMESTAMP');
        $user->save();

        $msg = $user->IsActive
             ? 'Учётная запись активирована'
             : 'Учётная запись заблокирована';

        return redirect()
            ->route('admin.users')
            ->with('status', $msg);
    }
}
