<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\Redirects\Filters\RedirectEnabled;
use Vitamin2\Juicebox\Features\Redirects\Filters\RedirectFilter;
use Vitamin2\Juicebox\Features\Redirects\Filters\RedirectMatchType;
use Vitamin2\Juicebox\Features\Redirects\Filters\RedirectType;

covers(RedirectFilter::class, RedirectEnabled::class, RedirectMatchType::class, RedirectType::class);

it('offers the :dataset filter as a radio of its values', function (RedirectFilter $filter, string $field, array $options): void {
    expect($filter->fieldItems())->toBe([$field => ['type' => 'radio', 'options' => $options]]);
})->with([
    'enabled' => [fn (): RedirectFilter => new RedirectEnabled, 'enabled', ['1' => 'Enabled', '0' => 'Disabled']],
    'match type' => [fn (): RedirectFilter => new RedirectMatchType, 'match_type', ['exact' => 'Exact match', 'regex' => 'Regex match']],
    'type' => [fn (): RedirectFilter => new RedirectType, 'type', ['301' => 'Permanent (301)', '302' => 'Temporary (302)', '410' => 'Gone (410)']],
]);
