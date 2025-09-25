<?php View::layout('layouts/app'); ?>

<?php View::startSection('content'); ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="mb-0">{{ $title ?? 'Zero Framework' }}</h1>

                <?php $authUser = Auth::user(); ?>

                <?php if ($authUser): ?>
                    <form method="POST" action="<?= route('auth.logout'); ?>" class="d-flex align-items-center gap-3">
                        <span class="text-muted">
                            Hello, {{ $authUser->name }}
                        </span>
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Log Out</button>
                    </form>
                <?php else: ?>
                    <div class="text-end">
                        <p class="mb-1 text-muted">Welcome! Please log in to continue.</p>
                        <a href="<?= route('auth.login.show'); ?>" class="btn btn-primary btn-sm">Go to Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
