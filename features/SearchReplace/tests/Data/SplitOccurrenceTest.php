<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\SearchReplace\Data\Item;
use Vitamin2\Juicebox\Features\SearchReplace\Data\SplitOccurrence;

covers(SplitOccurrence::class);

it('holds the matched text across the text nodes and the context around it', function (): void {
    $match = new SplitOccurrence(new Item('entry', 'ueber-uns', 'de', 'Über uns'), 'Inhalt', 'Ein starkes Team für Sie', 4, 12);

    expect($match->text())->toBe('starkes Team')
        ->and($match->contextBefore())->toBe('Ein ')
        ->and($match->contextAfter())->toBe(' für Sie');
});

it('shows 40 characters before and after the match without cutting a character', function (): void {
    $joined = 'Ä'.str_repeat('ä', 40).'Acme Corp'.str_repeat('ö', 40).'Ö';
    $match = new SplitOccurrence(new Item('entry', 'ueber-uns', 'de', 'Über uns'), 'Inhalt', $joined, 82, 9);

    expect($match->text())->toBe('Acme Corp')
        ->and($match->contextBefore())->toBe(str_repeat('ä', 40))
        ->and($match->contextAfter())->toBe(str_repeat('ö', 40));
});
