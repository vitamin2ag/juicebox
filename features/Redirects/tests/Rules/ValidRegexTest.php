<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Vitamin2\Juicebox\Features\Redirects\Rules\ValidRegex;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

mutates(ValidRegex::class);

/**
 * @param  array<array-key, mixed>  $data
 * @return array<array-key, mixed>
 */
function regexErrors(array $data): array
{
    return Validator::make($data, ['source' => [new ValidRegex(resolve(RedirectRepository::class))]])->errors()->get('source');
}

it('rejects a regex source that does not compile, with :dataset', function (string $source): void {
    expect(regexErrors(['source' => $source, 'match_type' => 'regex']))->toBe(['This source is not a valid regex.']);
})->with([
    'an open group' => '/kampagne/(',
    'the delimiter' => '/kampagne`x',
    'a trailing backslash' => '/kampagne\\',
]);

it('accepts :dataset', function (array $data): void {
    expect(regexErrors($data))->toBeEmpty();
})->with([
    'a regex source that compiles' => [['source' => '/kampagne/(.*)', 'match_type' => 'regex']],
    'an exact source that would not compile' => [['source' => '/kampagne/(', 'match_type' => 'exact']],
    'a source without a match type' => [['source' => '/kampagne/(']],
    'a regex source that is no string' => [['source' => ['/kampagne/('], 'match_type' => 'regex']],
]);

it('words its rejection in the language of the application', function (): void {
    app()->setLocale('fr');

    expect(regexErrors(['source' => '/kampagne/(', 'match_type' => 'regex']))->toBe(["Cette source n'est pas une expression régulière valide."]);
});
