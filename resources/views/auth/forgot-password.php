@i18n('yaml')
en:
  title: Forgot Password
  heading: Forgot Password
  intro: Enter the email associated with your account and we will send a password reset link.
  email: Email address
  submit: Email Password Reset Link
  back_signin: Back to sign in
id:
  title: Lupa Kata Sandi
  heading: Lupa Kata Sandi
  intro: Masukkan email yang terkait dengan akun Anda dan kami akan mengirim tautan reset kata sandi.
  email: Alamat email
  submit: Kirim tautan reset password
  back_signin: Kembali ke masuk
it:
  title: Password dimenticata
  heading: Password dimenticata
  intro: Inserisci l'email associata al tuo account e ti invieremo un link per reimpostare la password.
  email: Indirizzo email
  submit: Invia link di reset password
  back_signin: Torna all'accesso
cn:
  title: 忘记密码
  heading: 忘记密码
  intro: 输入与你的账户关联的邮箱，我们将发送重置密码链接。
  email: 邮箱地址
  submit: 发送重置密码链接
  back_signin: 返回登录
@endi18n

@layout('layouts.app', ['title' => __('title')])

@section('content')
<div class="container py-5" style="max-width: 520px;">
    <h1 class="mb-4 text-center">@t('heading')</h1>

    <p class="text-muted">@t('intro')</p>

    @if (!empty($status ?? ''))
        <div class="alert alert-info" role="alert">
            {{ $status ?? '' }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.password.email') }}" class="card shadow-sm p-4">
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

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">@t('submit')</button>
            <a href="{{ route('auth.login.show') }}" class="btn btn-link">@t('back_signin')</a>
        </div>
    </form>
</div>
@endsection
