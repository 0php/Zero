@i18n('yaml')
en:
  title: Reset your password
  heading: Reset your password
  greeting: Hi {{ $name }},
  fallback_name: there
  intro: We received a request to reset the password for your account. Click the button below to choose a new password:
  button: Reset Password
  notice: If you did not request a password reset, you can safely ignore this email.
  signature: — The Zero Framework Team
id:
  title: Reset kata sandi Anda
  heading: Reset kata sandi Anda
  greeting: Hai {{ $name }},
  fallback_name: di sana
  intro: Kami menerima permintaan untuk mereset kata sandi akun Anda. Klik tombol di bawah untuk memilih kata sandi baru:
  button: Reset Kata Sandi
  notice: Jika Anda tidak meminta reset kata sandi, Anda dapat mengabaikan email ini.
  signature: — Tim Zero Framework
it:
  title: Reimposta la tua password
  heading: Reimposta la tua password
  greeting: Ciao {{ $name }},
  fallback_name: lì
  intro: Abbiamo ricevuto una richiesta di reimpostare la password del tuo account. Fai clic sul pulsante qui sotto per scegliere una nuova password:
  button: Reimposta password
  notice: Se non hai richiesto il reset della password, puoi ignorare questa email.
  signature: — Il team Zero Framework
cn:
  title: 重置你的密码
  heading: 重置你的密码
  greeting: 你好，{{ $name }}，
  fallback_name: 那里
  intro: 我们收到重置你账户密码的请求。点击下面按钮选择新密码：
  button: 重置密码
  notice: 如果你没有请求重置密码，请忽略此邮件。
  signature: — Zero Framework 团队
@endi18n

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ __('title') }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
    <h1 style="color: #0d6efd;">{{ __('heading') }}</h1>
    <p>@t('greeting', ['name' => $name ?? __('fallback_name')])</p>
    <p>@t('intro')</p>
    <p style="margin: 24px 0;">
        <a href="{{ $resetUrl }}" style="background: #0d6efd; color: #fff; padding: 12px 20px; border-radius: 6px; text-decoration: none;">@t('button')</a>
    </p>
    <p>@t('notice')</p>
    <p style="margin-top: 32px;">@t('signature')</p>
</body>
</html>
