@i18n('yaml')
en:
  title: Reset Password
  heading: Reset Password
  new_password: New Password
  confirm_password: Confirm Password
  submit: Reset Password
  back_signin: Back to sign in
id:
  title: Reset Kata Sandi
  heading: Reset Kata Sandi
  new_password: Kata Sandi Baru
  confirm_password: Konfirmasi Kata Sandi
  submit: Reset Kata Sandi
  back_signin: Kembali ke masuk
it:
  title: Reimposta password
  heading: Reimposta password
  new_password: Nuova password
  confirm_password: Conferma password
  submit: Reimposta password
  back_signin: Torna all'accesso
cn:
  title: 重置密码
  heading: 重置密码
  new_password: 新密码
  confirm_password: 确认密码
  submit: 重置密码
  back_signin: 返回登录
@endi18n

@layout('layouts.app', ['title' => __('title')])

@section('content')
<div class="container py-5" style="max-width: 520px;">
    <h1 class="mb-4 text-center">@t('heading')</h1>

    @if (!empty($errors ?? []))
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('auth.password.update') }}" class="card shadow-sm p-4">
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <div class="mb-3">
            <label for="password" class="form-label">@t('new_password')</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">@t('confirm_password')</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">@t('submit')</button>
            <a href="{{ route('auth.login.show') }}" class="btn btn-link">@t('back_signin')</a>
        </div>
    </form>
</div>
@endsection
