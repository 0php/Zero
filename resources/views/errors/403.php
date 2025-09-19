<?php

use Zero\Lib\View;

View::layout('layouts/app');
View::startSection('content');
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="text-center p-5 shadow-sm rounded" style="background: #161b22; color: #c9d1d9;">
                <h1 class="display-4 mb-3" style="color: #f77669;">Forbidden</h1>
                <p class="lead mb-4 text-muted">
                    <?= htmlspecialchars((string) ($message ?? 'You do not have permission to access this page.'), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <a href="/" class="btn btn-primary">Back to home</a>
            </div>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
