@layout('layouts.app', ['title' => 'Sign In'])

@section('content')
<div class="container py-5" style="max-width: 480px;">
    <h1 class="mb-4 text-center">Sign In</h1>

    @if (!empty($status ?? ''))
        <div class="alert alert-success" role="alert">
            {{ $status ?? '' }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.login.attempt') }}" class="card shadow-sm p-4">
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
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

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input
                type="password"
                class="form-control {{ isset($errors['password']) ? 'is-invalid' : '' }}"
                id="password"
                name="password"
                required
            >
            @if (isset($errors['password']))
                <div class="invalid-feedback">
                    {{ $errors['password'] ?? '' }}
                </div>
            @endif
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">Log In</button>
            <a href="{{ route('auth.password.forgot') }}" class="btn btn-link">Forgot password?</a>
            <a href="{{ route('auth.register.show') }}" class="btn btn-link">Create an account</a>
            <a href="{{ route('home') }}" class="btn btn-link">Back to home</a>
        </div>
    </form>
</div>
@endsection
