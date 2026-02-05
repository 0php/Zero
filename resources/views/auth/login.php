@i18n('yaml')
en:
  title: Sign In
  heading: Sign In
  email: Email
  password: Password
  submit: Log In
  forgot: Forgot password?
  create_account: Create an account
  back_home: Back to home
id:
  title: Masuk
  heading: Masuk
  email: Email
  password: Kata Sandi
  submit: Masuk
  forgot: Lupa kata sandi?
  create_account: Buat akun
  back_home: Kembali ke beranda
it:
  title: Accedi
  heading: Accedi
  email: Email
  password: Password
  submit: Accedi
  forgot: Password dimenticata?
  create_account: Crea un account
  back_home: Torna alla home
cn:
  title: 登录
  heading: 登录
  email: 邮箱
  password: 密码
  submit: 登录
  forgot: 忘记密码？
  create_account: 创建账户
  back_home: 返回首页
@endi18n

@layout('layouts.app', ['title' => __('title')])

@section('content')
<div class="container py-5" style="max-width: 480px;">
    <h1 class="mb-4 text-center">@t('heading')</h1>

    @if (!empty($status ?? ''))
        <div class="alert alert-success" role="alert">
            {{ $status ?? '' }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.login.attempt') }}" class="card shadow-sm p-4">
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

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">@t('submit')</button>
            <a href="{{ route('auth.password.forgot') }}" class="btn btn-link">@t('forgot')</a>
            <a href="{{ route('auth.register.show') }}" class="btn btn-link">@t('create_account')</a>
            <a href="{{ route('home') }}" class="btn btn-link">@t('back_home')</a>
        </div>
    </form>
</div>
@endsection
