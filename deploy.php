<?php

namespace Deployer;

require 'recipe/common.php';

// Config

set('repository', 'git@github.com:jonesrussell/claudriel.git');
set('keep_releases', 5);

set('release_name', function (): string {
    return date('YmdHis');
});

add('shared_files', [
    '.env',
]);
add('shared_dirs', [
    'context',
]);

// Hosts

host('claudriel.northcloud.one')
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '~/claudriel');

// Tasks

task('deploy:copy_caddyfile', function (): void {
    run('cp {{release_path}}/Caddyfile {{deploy_path}}/Caddyfile');
});
after('deploy:symlink', 'deploy:copy_caddyfile');

task('deploy:reload_caddy', function (): void {
    run('sudo systemctl reload caddy || true');
});
after('deploy:copy_caddyfile', 'deploy:reload_caddy');

task('deploy:reload_php_fpm', function (): void {
    run('sudo systemctl restart php8.4-fpm || true');
});
after('deploy:reload_caddy', 'deploy:reload_php_fpm');

// Hooks

after('deploy:failed', 'deploy:unlock');
