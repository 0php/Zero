@layout('layouts.app', ['title' => ($status ?? 400) . ' ' . __('title_suffix')])

@section('content')
<div class="container py-5 text-center">
    <h1 class="display-4 fw-bold mb-3">{{ $status ?? 400 }}</h1>
    <p class="lead text-muted mb-4">{{ $message ?? __('message') }}</p>
    <a href="{{ route('home') }}" class="btn btn-primary">@t('back_home')</a>

</div>
@endsection
