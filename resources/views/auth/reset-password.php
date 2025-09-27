@layout('layouts.app', ['title' => 'Reset Password'])

@section('content')
<div class="container py-5" style="max-width: 520px;">
    <h1 class="mb-4 text-center">Reset Password</h1>

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
            <label for="password" class="form-label">New Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirm Password</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">Reset Password</button>
            <a href="{{ route('auth.login.show') }}" class="btn btn-link">Back to sign in</a>
        </div>
    </form>
</div>
@endsection
