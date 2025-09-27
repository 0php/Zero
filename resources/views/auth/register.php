@layout('layouts.app', ['title' => 'Register'])

@section('content')
<div class="container py-5" style="max-width: 520px;">
    <h1 class="mb-4 text-center">Create an Account</h1>

    @if (!empty($status ?? ''))
        <div class="alert alert-success" role="alert">
            {{ $status ?? '' }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.register.store') }}" class="card shadow-sm p-4">
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input
                type="text"
                class="form-control {{ isset($errors['name']) ? 'is-invalid' : '' }}"
                id="name"
                name="name"
                value="{{ $old['name'] ?? '' }}"
                required
            >
            @if (isset($errors['name']))
                <div class="invalid-feedback">
                    {{ $errors['name'] ?? '' }}
                </div>
            @endif
        </div>

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

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirm Password</label>
            <input
                type="password"
                class="form-control {{ isset($errors['password_confirmation']) ? 'is-invalid' : '' }}"
                id="password_confirmation"
                name="password_confirmation"
                required
            >
            @if (isset($errors['password_confirmation']))
                <div class="invalid-feedback">
                    {{ $errors['password_confirmation'] ?? '' }}
                </div>
            @endif
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">Register</button>
            <a href="{{ route('auth.login.show') }}" class="btn btn-link">Already registered? Sign in</a>
        </div>
    </form>
</div>
@endsection
