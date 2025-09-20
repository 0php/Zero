<?php

use Zero\Lib\View;

View::layout('layouts/app');

View::startSection('content');
?>
<div class="container py-5" style="max-width: 480px;">
    <h1 class="mb-4 text-center">Sign In</h1>

    <?php if (!empty($status ?? '')): ?>
        <div class="alert alert-success" role="alert">
            {{ $status ?? '' }}
        </div>
    <?php endif; ?>

    <form method="POST" action="/login" class="card shadow-sm p-4">
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
            <?php if (isset($errors['email'])): ?>
                <div class="invalid-feedback">
                    {{ $errors['email'] ?? '' }}
                </div>
            <?php endif; ?>
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
            <?php if (isset($errors['password'])): ?>
                <div class="invalid-feedback">
                    {{ $errors['password'] ?? '' }}
                </div>
            <?php endif; ?>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">Log In</button>
            <a href="/password/forgot" class="btn btn-link">Forgot password?</a>
            <a href="/register" class="btn btn-link">Create an account</a>
            <a href="/" class="btn btn-link">Back to home</a>
        </div>
    </form>
</div>
<?php View::endSection(); ?>
