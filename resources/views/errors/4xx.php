@layout('layouts.app', ['title' => ($status ?? 400) . ' Error'])

@section('content')
<div class="container py-5 text-center">
    <h1 class="display-4 fw-bold mb-3">{{ $status ?? 400 }}</h1>
    <p class="lead text-muted mb-4">{{ $message ?? 'Something seems off. The page you are looking for might have moved or no longer exists.' }}</p>
    <a href="{{ route('home') }}" class="btn btn-primary">Back to Home</a>
</div>
@endsection
