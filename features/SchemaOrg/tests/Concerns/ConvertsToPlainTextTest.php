<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\SchemaOrg\Concerns\ConvertsToPlainText;

mutates(ConvertsToPlainText::class);

/**
 * @param  string|array<mixed>|null  $value
 */
function convertsToPlainTextFixture(string|array|null $value): ?string
{
    $trait = new class
    {
        use ConvertsToPlainText {
            plainText as public;
        }
    };

    return $trait->plainText($value);
}

it('strips tags and decodes entities', function (): void {
    expect(convertsToPlainTextFixture('<p>Hello &amp; welcome</p>'))->toBe('Hello & welcome');
});

it('squishes whitespace', function (): void {
    expect(convertsToPlainTextFixture("  Multiple   spaces\n\nand newlines  "))->toBe('Multiple spaces and newlines');
});

it('returns null for blank input', function (string $input): void {
    expect(convertsToPlainTextFixture($input))->toBeNull();
})->with(['empty' => [''], 'spaces' => ['   '], 'empty tags' => ['<p></p>']]);

it('returns null for null input', function (): void {
    expect(convertsToPlainTextFixture(null))->toBeNull();
});

it('returns null for an array that holds no text', function (array $value): void {
    expect(convertsToPlainTextFixture($value))->toBeNull();
})->with([
    'empty' => [[]],
    'no bard nodes' => [['not' => 'bard']],
]);
