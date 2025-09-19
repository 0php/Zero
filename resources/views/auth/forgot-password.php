<?php

use Zero\Lib\View;

View::layout('layouts/app');

View::startSection('content');
?>
<div class="container py-5" style="max-width: 520px;">
    <h1 class="mb-4 text-center">Forgot Password</h1>

    <p class="text-muted">Enter the email associated with your account and we will send a password reset link.</p>

    <?php if (!empty($status ?? '')): ?>
        <div class="alert alert-info" role="alert">
            {{ $status ?? '' }}
        </div>
    <?php endif; ?>

    <?php if (!empty($errors['email'] ?? '')): ?>
        <div class="alert alert-danger" role="alert">
            {{ $errors['email'] ?? '' }}
        </div>
    <?php endif; ?>

    <form method="POST" action="/password/forgot" class="card shadow-sm p-4">
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input
                type="email"
                class="form-control"
                id="email"
                name="email"
                required
            >
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">Email Password Reset Link</button>
            <a href="/login" class="btn btn-link">Back to sign in</a>
        </div>
    </form>
</div>
<?php View::endSection(); ?>
