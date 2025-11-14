@extends('layouts.app')

@section('title', 'Пользователи')

@section('content')
  <h1>Список пользователей</h1>
  <table border="1" cellpadding="5" cellspacing="0">
    <thead>
      <tr><th>ID</th><th>Логин</th><th>Роль</th></tr>
    </thead>
    <tbody>
      @foreach($users as $u)
        <tr>
          <td>{{ $u->Id }}</td>
          <td>{{ $u->Login }}</td>
          <td>{{ $u->NameRole }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endsection
