@i18n('yaml')
en:
  title: Dashboard
  welcome: Welcome back, {{ $name }}!
  welcome_fallback: Welcome back, friend!
  intro: Stay on top of your account activity and recent updates in one place.
  snapshot: Account snapshot
  name: Name
  email: Email
  joined: Joined
  joined_fallback: —
  profile_error: We could not load your profile details. Please try signing in again.
  logout: Log Out
  back_home: Back to home
id:
  title: Dasbor
  welcome: Selamat datang kembali, {{ $name }}!
  welcome_fallback: Selamat datang kembali, teman!
  intro: Tetap pantau aktivitas akun dan pembaruan terbaru di satu tempat.
  snapshot: Ringkasan akun
  name: Nama
  email: Email
  joined: Bergabung
  joined_fallback: —
  profile_error: Kami tidak dapat memuat detail profil Anda. Silakan coba masuk lagi.
  logout: Keluar
  back_home: Kembali ke beranda
it:
  title: Dashboard
  welcome: Bentornato, {{ $name }}!
  welcome_fallback: Bentornato, amico!
  intro: Tieni sotto controllo l'attività del tuo account e gli ultimi aggiornamenti in un unico posto.
  snapshot: Riepilogo account
  name: Nome
  email: Email
  joined: Iscritto
  joined_fallback: —
  profile_error: Non siamo riusciti a caricare i dettagli del profilo. Prova ad accedere di nuovo.
  logout: Esci
  back_home: Torna alla home
cn:
  title: 仪表盘
  welcome: 欢迎回来，{{ $name }}！
  welcome_fallback: 欢迎回来，朋友！
  intro: 在一个地方掌握账户活动和最新更新。
  snapshot: 账户概览
  name: 姓名
  email: 邮箱
  joined: 加入时间
  joined_fallback: —
  profile_error: 无法加载您的个人资料详情。请重新登录。
  logout: 退出
  back_home: 返回首页
@endi18n

@layout('layouts.app', ['title' => __('title')])

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="mb-4">
                @if ($user)
                    <h1 class="mb-2">@t('welcome', ['name' => $user->name])</h1>
                @else
                    <h1 class="mb-2">@t('welcome_fallback')</h1>
                @endif
                <p class="text-muted mb-0">@t('intro')</p>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">@t('snapshot')</h2>
                    @if ($user)
                        <dl class="row mb-0">
                            <dt class="col-sm-3">@t('name')</dt>
                            <dd class="col-sm-9">{{ $user->name }}</dd>

                            <dt class="col-sm-3">@t('email')</dt>
                            <dd class="col-sm-9">{{ $user->email }}</dd>

                            <dt class="col-sm-3">@t('joined')</dt>
                            <dd class="col-sm-9">{{ $user->created_at ?? __('joined_fallback') }}</dd>
                        </dl>
                    @else
                        <p class="mb-0">@t('profile_error')</p>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('auth.logout') }}" class="d-inline">
                <button type="submit" class="btn btn-outline-secondary">@t('logout')</button>
            </form>
            <a href="{{ route('home') }}" class="btn btn-link">@t('back_home')</a>
        </div>
    </div>
</div>
@endsection
