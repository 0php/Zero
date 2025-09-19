<?php

use Zero\Lib\View;

View::layout('layouts/app');

View::startSection('content');
?>
<div class="container py-5" style="max-width: 520px;">
    <h1 class="mb-4 text-center">Reset Password</h1>

    <?php if (!empty($errors ?? [])): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $message): ?>
                    <li>{{ $message }}</li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="/password/reset" class="card shadow-sm p-4">
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
            <a href="/login" class="btn btn-link">Back to sign in</a>
        </div>
    </form>
</div>
<?php View::endSection(); ?>
