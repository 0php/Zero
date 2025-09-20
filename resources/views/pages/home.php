<?php

use Zero\Lib\Auth\Auth as AuthManager;
use Zero\Lib\View;

View::layout('layouts/app');

View::startSection('content');

$authUser = AuthManager::user();
$userName = $authUser->name ?? null;

$title = $title ?? 'Zero Framework';
$subtitle = $subtitle ?? 'DBML now supports expressive queries inspired by Laravel\'s Eloquent. Below are a few
                builder examples showing joins, grouping, nested clauses, subqueries, and pagination.';
$showAuthActions = $showAuthActions ?? true;

$roles = [];

foreach ($authUser->roles as $role) {
    $roles[] = \App\Models\Role::query()->where('id', $role->role_id)->first();
}

?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="mb-0"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>

                <?php if ($showAuthActions): ?>
                    <?php if ($userName): ?>
                        <form method="POST" action="/logout" class="d-flex align-items-center gap-3">
                            <span class="text-muted">Hello, <?= htmlspecialchars((string) $userName, ENT_QUOTES, 'UTF-8') ?> </span>
                                <?php foreach ($roles as $role): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars((string) $role->name, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endforeach; ?>
                            
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Log Out</button>
                        </form>
                    <?php else: ?>
                        <a href="/login" class="btn btn-primary btn-sm">Log In</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <p class="text-center text-muted mb-4">
                <?= nl2br(htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8')) ?>
            </p>

            <?php if (!empty($examples)): ?>
                <?php foreach ($examples as $example): ?>
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header">
                            <strong><?= htmlspecialchars($example['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <div class="card-body">
                            <h6 class="text-muted">SQL</h6>
                            <pre class="bg-light p-3 rounded border"><code><?= htmlspecialchars($example['sql'], ENT_QUOTES, 'UTF-8') ?></code></pre>

                            <h6 class="text-muted mt-3">Bindings</h6>
                            <pre class="bg-light p-3 rounded border"><code><?= htmlspecialchars(
                                json_encode($example['bindings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></code></pre>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    No examples available.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php View::endSection(); ?>
