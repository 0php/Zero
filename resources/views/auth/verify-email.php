<?php

use Zero\Lib\View;

View::layout('layouts/app');

View::startSection('content');
?>
<div class="container py-5" style="max-width: 520px;">
    <h1 class="mb-4 text-center">Verify Your Email</h1>

    <p class="text-muted">Before you can access your dashboard, please confirm your email address. We have sent a verification link to your inbox.</p>

    <?php if (!empty($status ?? '')): ?>
        <div class="alert alert-info" role="alert">
            {{ $status ?? '' }}
        </div>
    <?php endif; ?>

    <div class="card shadow-sm p-4">
        <form method="POST" action="<?= route('email.verification.resend'); ?>" class="mb-3">
            <div class="mb-3">
                <label for="email" class="form-label">Resend verification link</label>
                <input
                    type="email"
                    class="form-control {{ isset($errors['email']) ? 'is-invalid' : '' }}"
                    id="email"
                    name="email"
                    value="{{ $old['email'] ?? '' }}"
                    required
                >
                <?php if (isset($errors['email'])): ?>
                    <div class="invalid-feedback">
                        {{ $errors['email'] ?? '' }}
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Send Email</button>
                <a href="<?= route('auth.login.show'); ?>" class="btn btn-link">Return to sign in</a>
            </div>
        </form>
    </div>
</div>
<?php View::endSection(); ?>
