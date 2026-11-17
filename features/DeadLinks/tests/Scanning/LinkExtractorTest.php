<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\DeadLinks\Scanning\LinkExtractor;
use Vitamin2\Juicebox\Features\DeadLinks\Tests\__Fixtures__\DeadLinksFixtures;

covers(LinkExtractor::class);

/**
 * @return array<int, string>
 */
function extractedDeadLinks(mixed $values): array
{
    return new LinkExtractor()->extract($values)
        ->map(fn (array $link): string => "{$link['field']}: {$link['url']}")
        ->all();
}

it('finds the links of a page with its top-level field', function (): void {
    expect(extractedDeadLinks(DeadLinksFixtures::data('page')))->toBe([
        'link: entry::kontakt',
        'download: asset::assets::docs/preise.pdf',
        'website: https://example.com/team',
        'home: /',
        'body: /angebot',
        'body: https://example.org/news',
        'content: entry::angebot',
        'content: www.example.net',
        'content: asset::assets::bilder/team.jpg',
        'content: https://example.com/bard',
        'sections: https://example.com/team',
        'sections: /standorte',
        'sections: entry::team',
    ]);
});

it('takes a whole value :dataset as a link', function (string $value, string $url): void {
    expect(extractedDeadLinks(['field' => $value]))->toBe(["field: {$url}"]);
})->with([
    'pointing to an entry' => ['entry::abc', 'entry::abc'],
    'pointing to an asset' => ['asset::assets::a.pdf', 'asset::assets::a.pdf'],
    'in Bard form' => ['statamic://entry::abc', 'entry::abc'],
    'in Bard form with an anchor' => ['statamic://entry::abc#team', 'entry::abc#team'],
    'with http' => ['http://example.com', 'http://example.com'],
    'with https in capitals' => ['HTTPS://example.com/A', 'HTTPS://example.com/A'],
    'with a mistyped scheme' => ['https::/google.com', 'https::/google.com'],
    'relative to the site' => ['/kontakt?tab=1', '/kontakt?tab=1'],
    'ending in punctuation' => ['https://example.com/a.', 'https://example.com/a.'],
]);

it('ignores a whole value :dataset', function (mixed $value): void {
    expect(extractedDeadLinks(['field' => $value]))->toBeEmpty();
})->with([
    'that is protocol-relative' => ['//cdn.example.com/a.js'],
    'that is a plain word' => ['kontakt'],
    'that is a host without a scheme' => ['www.example.com'],
    'that names an entry without the prefix' => ['abc-123'],
    'that is an anchor' => ['#team'],
    'that is an email address' => ['mailto:info@example.com'],
    'that is empty' => [''],
    'that is a number' => [42],
    'that is a boolean' => [true],
    'that is null' => [null],
]);

it('finds the :dataset in a text', function (string $text, array $urls): void {
    $found = new LinkExtractor()->extract(['field' => $text]);

    expect($found->pluck('field')->unique()->all())->toBe(['field'])
        ->and($found->pluck('url')->all())->toBe($urls);
})->with([
    'double-quoted href' => ['<a class="x" href="/kontakt">Kontakt</a>', ['/kontakt']],
    'single-quoted href' => ["<a href='statamic://entry::abc'>A</a>", ['entry::abc']],
    'href with spaces around the equals sign' => ['<a HREF = "www.example.com">A</a>', ['www.example.com']],
    'href with an escaped ampersand' => ['<a href="https://example.com/?a=1&amp;b=2">A</a>', ['https://example.com/?a=1&b=2']],
    'markdown link' => ['See [the offer](/angebot "Offer") now', ['/angebot']],
    'markdown link to a host without a scheme' => ['[Shop](www.example.com/shop)', ['www.example.com/shop']],
    'bare URL' => ['Visit https://example.com/news today', ['https://example.com/news']],
    'bare URL at the end of a sentence' => ['Visit https://example.com/news.', ['https://example.com/news']],
    'bare URL in brackets' => ['(see https://example.com/a), or', ['https://example.com/a']],
    'bare URL in bold' => ['**https://example.com/a**!', ['https://example.com/a']],
    'URL in a markdown link and as its label' => ['[https://example.com/a](https://example.com/a)', ['https://example.com/a']],
    'URL in an HTML link and as its label' => ['<a href="https://example.com/a">https://example.com/a</a>', ['https://example.com/a']],
    'links of several kinds' => ['[A](/a) <a href="/b">B</a> http://example.com/c;', ['/a', '/b', 'http://example.com/c']],
]);

it('skips the :dataset target of a link in a text', function (string $text): void {
    expect(extractedDeadLinks(['field' => $text]))->toBeEmpty();
})->with([
    'email' => ['<a href="mailto:info@example.com">Mail</a>'],
    'phone' => ['[Call](tel:+41711234567)'],
    'anchor' => ['<a href="#team">Team</a>'],
    'script' => ['<a href="javascript:void(0)">Open</a>'],
    'empty' => ['<a href="">Nothing</a>'],
    'other scheme' => ['[Chat](whatsapp:+41711234567)'],
]);

it('takes a :dataset Bard link mark as a link', function (string $href, string $url): void {
    $mark = ['type' => 'link', 'attrs' => ['href' => $href, 'title' => null]];

    expect(extractedDeadLinks(['content' => [['type' => 'text', 'marks' => [$mark], 'text' => 'Link']]]))
        ->toBe(["content: {$url}"]);
})->with([
    'statamic' => ['statamic://entry::abc', 'entry::abc'],
    'relative' => ['/kontakt', '/kontakt'],
    'schemeless' => ['www.example.com', 'www.example.com'],
]);

it('skips a Bard link mark :dataset', function (array $mark): void {
    expect(extractedDeadLinks(['content' => [['type' => 'text', 'marks' => [$mark], 'text' => 'Link']]]))->toBeEmpty();
})->with([
    'to an email address' => [['type' => 'link', 'attrs' => ['href' => 'mailto:info@example.com']]],
    'without an href' => [['type' => 'link', 'attrs' => ['href' => null]]],
    'of another type' => [['type' => 'bold', 'attrs' => ['href' => 'www.example.com']]],
]);

it('lists a link once per field', function (): void {
    expect(extractedDeadLinks([
        'title' => 'https://example.com/a',
        'content' => ['https://example.com/a', ['text' => 'See https://example.com/a and /b']],
        'teaser' => 'https://example.com/a',
    ]))->toBe([
        'title: https://example.com/a',
        'content: https://example.com/a',
        'teaser: https://example.com/a',
    ]);
});

it('reads values from :dataset', function (iterable $values): void {
    expect(extractedDeadLinks($values))->toBe(['link: /a', 'nested: /b']);
})->with([
    'an array' => [['link' => '/a', 'nested' => [['link' => '/b']]]],
    'a collection' => [collect(['link' => '/a', 'nested' => collect([['link' => '/b']])])],
]);

it('finds nothing in values that are not a list', function (mixed $values): void {
    expect(extractedDeadLinks($values))->toBeEmpty();
})->with([
    'a string' => ['https://example.com'],
    'null' => [null],
]);
