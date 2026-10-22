<?php

use Symfony\Component\Yaml\Yaml;

it('pins every Docker build stage to an image digest', function () {
    $dockerfile = file_get_contents(dirname(__DIR__, 2).'/Dockerfile');

    preg_match_all('/^FROM (\S+)/m', $dockerfile, $matches);

    expect($matches[1])->toHaveCount(3);

    foreach ($matches[1] as $image) {
        expect($image)->toMatch('/:\d+\.\d+\.\d+[^@]*@sha256:[a-f0-9]{64}$/');
    }
});

it('uses a production PHP release containing the critical security fixes', function () {
    $dockerfile = file_get_contents(dirname(__DIR__, 2).'/Dockerfile');

    expect(preg_match('/^FROM dunglas\/frankenphp:[^\s]*-php(8\.4\.\d+)-bookworm@sha256:/m', $dockerfile, $matches))->toBe(1);
    expect(version_compare($matches[1], '8.4.27', '>='))->toBeTrue();
});

it('requires the same explicit application release for all production PHP services', function () {
    $compose = Yaml::parseFile(dirname(__DIR__, 2).'/docker-compose.prod.yml');
    $images = [];

    foreach (['app', 'scheduler', 'queue'] as $service) {
        $images[] = $compose['services'][$service]['image'];
    }

    expect(array_unique($images))->toHaveCount(1);
    expect($images[0])->toStartWith('ghcr.io/reneweiser/voluntify:${IMAGE_TAG:?')
        ->not->toContain('latest');
});

it('pins the production database to an exact release and digest', function () {
    $compose = Yaml::parseFile(dirname(__DIR__, 2).'/docker-compose.prod.yml');

    expect($compose['services']['mariadb']['image'])->toMatch('/^mariadb:11\.\d+\.\d+@sha256:[a-f0-9]{64}$/');
});

it('disables inherited HTTP healthchecks for CLI workers', function () {
    $compose = Yaml::parseFile(dirname(__DIR__, 2).'/docker-compose.prod.yml');

    foreach (['scheduler', 'queue'] as $service) {
        expect($compose['services'][$service]['healthcheck']['disable'])->toBeTrue();
    }
});

it('exports the release before backup and avoids database recreation during deployment', function () {
    $script = file_get_contents(dirname(__DIR__, 2).'/bin/deploy-prod');

    expect(strpos($script, 'export IMAGE_TAG="${image_tag}"'))
        ->toBeLessThan(strpos($script, 'backup_path='));
    expect($script)->toContain('up -d --no-deps app')
        ->toContain('up -d --no-deps scheduler queue')
        ->not->toContain('up -d app scheduler queue');
});
