@i18n('yaml')
en:
  title: Verify Email
  heading: Verify Your Email
  intro: Before you can access your dashboard, please confirm your email address. We have sent a verification link to your inbox.
  resend_label: Resend verification link
  send_email: Send Email
  back_signin: Return to sign in
id:
  title: Verifikasi Email
  heading: Verifikasi Email Anda
  intro: Sebelum Anda dapat mengakses dasbor, silakan konfirmasi alamat email Anda. Kami telah mengirim tautan verifikasi ke inbox Anda.
  resend_label: Kirim ulang tautan verifikasi
  send_email: Kirim Email
  back_signin: Kembali ke masuk
it:
  title: Verifica email
  heading: Verifica la tua email
  intro: Prima di accedere alla dashboard, conferma il tuo indirizzo email. Abbiamo inviato un link di verifica alla tua casella di posta.
  resend_label: Reinvia link di verifica
  send_email: Invia email
  back_signin: Torna all'accesso
cn:
  title: 验证邮箱
  heading: 验证你的邮箱
  intro: 在访问仪表盘之前，请确认你的邮箱地址。我们已向你的收件箱发送验证链接。
  resend_label: 重新发送验证链接
  send_email: 发送邮件
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

    <div class="card shadow-sm p-4">
        <form method="POST" action="{{ route('email.verification.resend') }}" class="mb-3">
            <div class="mb-3">
                <label for="email" class="form-label">@t('resend_label')</label>
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
                <button type="submit" class="btn btn-primary">@t('send_email')</button>
                <a href="{{ route('auth.login.show') }}" class="btn btn-link">@t('back_signin')</a>
            </div>
        </form>
    </div>
</div>
@endsection
