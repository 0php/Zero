@i18n('yaml')
en:
  title: Verify your email
  heading: Confirm your email address
  greeting: Hi {{ $name }},
  fallback_name: there
  intro: Thanks for creating an account. Please click the button below to verify your email address:
  button: Verify Email
  notice: This link will expire in 60 minutes. If you did not create an account, please disregard this message.
  signature: — The Zero Framework Team
id:
  title: Verifikasi email Anda
  heading: Konfirmasi alamat email Anda
  greeting: Hai {{ $name }},
  fallback_name: di sana
  intro: Terima kasih telah membuat akun. Klik tombol di bawah untuk memverifikasi alamat email Anda:
  button: Verifikasi Email
  notice: Tautan ini akan kedaluwarsa dalam 60 menit. Jika Anda tidak membuat akun, abaikan pesan ini.
  signature: — Tim Zero Framework
it:
  title: Verifica la tua email
  heading: Conferma il tuo indirizzo email
  greeting: Ciao {{ $name }},
  fallback_name: lì
  intro: Grazie per aver creato un account. Fai clic sul pulsante qui sotto per verificare il tuo indirizzo email:
  button: Verifica email
  notice: Questo link scadrà tra 60 minuti. Se non hai creato un account, ignora questo messaggio.
  signature: — Il team Zero Framework
cn:
  title: 验证你的邮箱
  heading: 确认你的邮箱地址
  greeting: 你好，{{ $name }}，
  fallback_name: 那里
  intro: 感谢你创建账户。请点击下方按钮验证你的邮箱地址：
  button: 验证邮箱
  notice: 此链接将在60分钟后过期。如果你没有创建账户，请忽略此邮件。
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
        <a href="{{ $verificationUrl }}" style="background: #0d6efd; color: #fff; padding: 12px 20px; border-radius: 6px; text-decoration: none;">@t('button')</a>
    </p>
    <p>@t('notice')</p>
    <p style="margin-top: 32px;">@t('signature')</p>
</body>
</html>
