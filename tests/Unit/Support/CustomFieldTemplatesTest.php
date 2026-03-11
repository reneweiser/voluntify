<?php

use App\Support\CustomFieldTemplates;

it('returns all available templates', function () {
    $templates = CustomFieldTemplates::all();

    expect($templates)->toBeArray()->not->toBeEmpty();
});

it('each template has required keys', function () {
    foreach (CustomFieldTemplates::all() as $name => $template) {
        expect($template)->toHaveKeys(['label', 'type', 'options', 'required'], "Template '{$name}' missing keys");
    }
});

it('select templates include choices in options', function () {
    $selectTemplates = collect(CustomFieldTemplates::all())
        ->filter(fn ($t) => $t['type'] === 'select');

    expect($selectTemplates)->not->toBeEmpty();

    foreach ($selectTemplates as $name => $template) {
        expect($template['options']['choices'] ?? null)->toBeArray()->not->toBeEmpty("Template '{$name}' missing choices");
    }
});
