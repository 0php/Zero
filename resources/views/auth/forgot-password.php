@layout('layouts.app', ['title' => 'Forgot Password'])

@section('content')
<div class="container py-5" style="max-width: 520px;">
    <h1 class="mb-4 text-center">Forgot Password</h1>

    <p class="text-muted">Enter the email associated with your account and we will send a password reset link.</p>

    @if (!empty($status ?? ''))
        <div class="alert alert-info" role="alert">
            {{ $status ?? '' }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.password.email') }}" class="card shadow-sm p-4">
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
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
            <button type="submit" class="btn btn-primary">Email Password Reset Link</button>
            <a href="{{ route('auth.login.show') }}" class="btn btn-link">Back to sign in</a>
        </div>
    </form>
</div>
@endsection
