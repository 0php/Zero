<?php

return [
    'github_repo' => env('UPDATE_GITHUB_REPO', 'ZeroPHPFramework/Zero'),
    'github_branch' => env('UPDATE_GITHUB_BRANCH', 'main'),
    'manifest_url' => env('UPDATE_MANIFEST_URL', ''),
    'timeout' => (int) env('UPDATE_TIMEOUT', 15),
];
