<?php

use App\Enums\EventStatus;

it('returns true from isPublished for PublishedOpen', function () {
    expect(EventStatus::PublishedOpen->isPublished())->toBeTrue();
});

it('returns true from isPublished for PublishedClosed', function () {
    expect(EventStatus::PublishedClosed->isPublished())->toBeTrue();
});

it('returns false from isPublished for Draft', function () {
    expect(EventStatus::Draft->isPublished())->toBeFalse();
});

it('returns false from isPublished for Archived', function () {
    expect(EventStatus::Archived->isPublished())->toBeFalse();
});

it('returns correct label for Draft', function () {
    expect(EventStatus::Draft->label())->toBe('Draft');
});

it('returns correct label for PublishedOpen', function () {
    expect(EventStatus::PublishedOpen->label())->toBe('Published (Open)');
});

it('returns correct label for PublishedClosed', function () {
    expect(EventStatus::PublishedClosed->label())->toBe('Published (Closed)');
});

it('returns correct label for Archived', function () {
    expect(EventStatus::Archived->label())->toBe('Archived');
});
