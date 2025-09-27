@layout('layouts.app', ['title' => 'Dashboard'])

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="mb-4">
                <h1 class="mb-2">Welcome back, {{ $user->name ?? 'friend' }}!</h1>
                <p class="text-muted mb-0">Stay on top of your account activity and recent updates in one place.</p>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Account snapshot</h2>
                    @if ($user)
                        <dl class="row mb-0">
                            <dt class="col-sm-3">Name</dt>
                            <dd class="col-sm-9">{{ $user->name }}</dd>

                            <dt class="col-sm-3">Email</dt>
                            <dd class="col-sm-9">{{ $user->email }}</dd>

                            <dt class="col-sm-3">Joined</dt>
                            <dd class="col-sm-9">{{ $user->created_at ?? '—' }}</dd>
                        </dl>
                    @else
                        <p class="mb-0">We could not load your profile details. Please try signing in again.</p>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('auth.logout') }}" class="d-inline">
                <button type="submit" class="btn btn-outline-secondary">Log Out</button>
            </form>
            <a href="{{ route('home') }}" class="btn btn-link">Back to home</a>
        </div>
    </div>
</div>
@endsection
