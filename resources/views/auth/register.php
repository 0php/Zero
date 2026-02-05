@i18n('yaml')
en:
  title: Register
  heading: Create an Account
  name: Name
  email: Email
  password: Password
  confirm_password: Confirm Password
  submit: Register
  already: Already registered? Sign in
id:
  title: Daftar
  heading: Buat Akun
  name: Nama
  email: Email
  password: Kata Sandi
  confirm_password: Konfirmasi Kata Sandi
  submit: Daftar
  already: Sudah terdaftar? Masuk
it:
  title: Registrati
  heading: Crea un account
  name: Nome
  email: Email
  password: Password
  confirm_password: Conferma password
  submit: Registrati
  already: Già registrato? Accedi
cn:
  title: 注册
  heading: 创建账户
  name: 姓名
  email: 邮箱
  password: 密码
  confirm_password: 确认密码
  submit: 注册
  already: 已注册？登录
@endi18n

@layout('layouts.app', ['title' => __('title')])

@section('content')
<div class="container py-5" style="max-width: 520px;">
    <h1 class="mb-4 text-center">@t('heading')</h1>

    @if (!empty($status ?? ''))
        <div class="alert alert-success" role="alert">
            {{ $status ?? '' }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.register.store') }}" class="card shadow-sm p-4">
        <div class="mb-3">
            <label for="name" class="form-label">@t('name')</label>
            <input
                type="text"
                class="form-control {{ isset($errors['name']) ? 'is-invalid' : '' }}"
                id="name"
                name="name"
                value="{{ $old['name'] ?? '' }}"
                required
            >
            @if (isset($errors['name']))
                <div class="invalid-feedback">
                    {{ $errors['name'] ?? '' }}
                </div>
            @endif
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">@t('email')</label>
            <input
                type="email"
                class="form-control {{ isset($errors['email']) ? 'is-invalid' : '' }}"
                id="email"
                name="email"
                value="{{ $old['email'] ?? '' }}"
                required
            >
            @if (isset($errors['email']))
                <div class="invalid-feedback">
                    {{ $errors['email'] ?? '' }}
                </div>
            @endif
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">@t('password')</label>
            <input
                type="password"
                class="form-control {{ isset($errors['password']) ? 'is-invalid' : '' }}"
                id="password"
                name="password"
                required
            >
            @if (isset($errors['password']))
                <div class="invalid-feedback">
                    {{ $errors['password'] ?? '' }}
                </div>
            @endif
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">@t('confirm_password')</label>
            <input
                type="password"
                class="form-control {{ isset($errors['password_confirmation']) ? 'is-invalid' : '' }}"
                id="password_confirmation"
                name="password_confirmation"
                required
            >
            @if (isset($errors['password_confirmation']))
                <div class="invalid-feedback">
                    {{ $errors['password_confirmation'] ?? '' }}
                </div>
            @endif
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">@t('submit')</button>
            <a href="{{ route('auth.login.show') }}" class="btn btn-link">@t('already')</a>
        </div>
    </form>
</div>
@endsection
