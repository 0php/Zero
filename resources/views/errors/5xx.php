@layout('layouts.app', ['title' => ($status ?? 500) . ' Error'])

@section('content')
<div class="container py-5 text-center">
    <h1 class="display-4 fw-bold mb-3">{{ $status ?? 500 }}</h1>
    <p class="lead text-muted mb-4">{{ $message ?? 'We encountered an internal error. Please try again shortly.' }}</p>
    <a href="{{ route('home') }}" class="btn btn-primary">Return Home</a>
</div>
@endsection
