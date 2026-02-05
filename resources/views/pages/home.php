@i18n('yaml')
en:
  title: Zero Framework
  greeting: Hello, {{ $name }}
  logout: Log Out
  welcome_prompt: Welcome! Please log in to continue.
  go_login: Go to Login
  switcher_label: Language
  lang_en: English
  lang_id: Indonesian
  lang_it: Italian
  lang_cn: Chinese
id:
  title: Zero Framework
  greeting: Halo, {{ $name }}
  logout: Keluar
  welcome_prompt: Selamat datang! Silakan masuk untuk melanjutkan.
  go_login: Ke Login
  switcher_label: Bahasa
  lang_en: Inggris
  lang_id: Indonesia
  lang_it: Italia
  lang_cn: Tiongkok
it:
  title: Zero Framework
  greeting: Ciao, {{ $name }}
  logout: Esci
  welcome_prompt: Benvenuto! Accedi per continuare.
  go_login: Vai al login
  switcher_label: Lingua
  lang_en: Inglese
  lang_id: Indonesiano
  lang_it: Italiano
  lang_cn: Cinese
cn:
  title: Zero Framework
  greeting: 你好，{{ $name }}
  logout: 退出
  welcome_prompt: 欢迎！请登录以继续。
  go_login: 前往登录
  switcher_label: 语言
  lang_en: 英语
  lang_id: 印尼语
  lang_it: 意大利语
  lang_cn: 中文
@endi18n

@layout('layouts.app', ['title' => $title ?? __('title')])

@section('content')
@php
    $authUser = Auth::user();
@endphp

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <h1 class="mb-0">{{ $title ?? __('title') }}</h1>
                @if ($authUser)
                    <form method="POST" action="{{ route('auth.logout') }}" class="d-flex align-items-center gap-3">
                        <span class="text-muted">
                            @t('greeting', ['name' => $authUser->name])
                        </span>
                        <button type="submit" class="btn btn-outline-secondary btn-sm">@t('logout')</button>
                    </form>
                @else
                    <div class="text-end">
                        <p class="mb-1 text-muted">@t('welcome_prompt')</p>
                        <a href="{{ route('auth.login.show') }}" class="btn btn-primary btn-sm">@t('go_login')</a>
                    </div>
                @endif
            </div>

            <div class="d-flex justify-content-end mb-3">
                <div>
                    <label class="form-label small text-muted mb-1" for="lang-switcher">@t('switcher_label')</label>
                    <select id="lang-switcher" class="form-select form-select-sm" style="min-width: 170px;" onchange="if (this.value) window.location.href = this.value;">
                        <option value="/en">@t('lang_en')</option>
                        <option value="/id">@t('lang_id')</option>
                        <option value="/it">@t('lang_it')</option>
                        <option value="/cn">@t('lang_cn')</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
