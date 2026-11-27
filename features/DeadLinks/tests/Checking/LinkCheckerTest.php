<?php

declare(strict_types=1);

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\CheckResult;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\LinkChecker;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\Reason;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\SiteDomain;
use Vitamin2\Juicebox\Features\DeadLinks\Tests\__Fixtures__\DeadLinksFixtures;

covers(LinkChecker::class, CheckResult::class, Reason::class, SiteDomain::class);

beforeEach(function (): void {
    DeadLinksFixtures::sites();
    Http::preventStrayRequests();
});

/**
 * @param  array<int, string>  $urls
 * @return array<array-key, CheckResult>
 */
function checkedDeadLinks(array $urls): array
{
    return resolve(LinkChecker::class)->check($urls)->all();
}

/**
 * The requests that were sent, as `<method> <url>`, sorted.
 *
 * @return array<int, string>
 */
function sentDeadLinkRequests(): array
{
    return Http::recorded()
        ->map(fn (array $pair): string => "{$pair[0]->method()} {$pair[0]->url()}")
        ->sort()
        ->values()
        ->all();
}

it('accepts a reference to a published entry, with or without an anchor or a query', function (): void {
    DeadLinksFixtures::entry('kontakt', ['title' => 'Kontakt']);

    expect(checkedDeadLinks(['entry::kontakt', 'entry::kontakt#team', 'entry::kontakt?tab=1#team']))->toEqual([
        'entry::kontakt' => new CheckResult('entry::kontakt'),
        'entry::kontakt#team' => new CheckResult('entry::kontakt#team'),
        'entry::kontakt?tab=1#team' => new CheckResult('entry::kontakt?tab=1#team'),
    ]);
});

it('reports a reference to a missing or an unpublished entry', function (): void {
    DeadLinksFixtures::entry('entwurf', ['title' => 'Entwurf'], published: false);

    expect(checkedDeadLinks(['entry::gone', 'entry::entwurf#team']))->toEqual([
        'entry::gone' => new CheckResult('entry::gone', Reason::MissingEntry),
        'entry::entwurf#team' => new CheckResult('entry::entwurf#team', Reason::UnpublishedEntry),
    ]);
});

it('accepts a reference to an asset and reports one to a missing asset or container', function (): void {
    DeadLinksFixtures::assets('assets', ['docs/preise.pdf']);

    expect(checkedDeadLinks(['asset::assets::docs/preise.pdf', 'asset::assets::docs/alt.pdf', 'asset::archiv::docs/preise.pdf']))->toEqual([
        'asset::assets::docs/preise.pdf' => new CheckResult('asset::assets::docs/preise.pdf'),
        'asset::assets::docs/alt.pdf' => new CheckResult('asset::assets::docs/alt.pdf', Reason::MissingAsset),
        'asset::archiv::docs/preise.pdf' => new CheckResult('asset::archiv::docs/preise.pdf', Reason::MissingAsset),
    ]);
});

it('resolves a URL of the site through Statamic without a request', function (): void {
    DeadLinksFixtures::entry('kontakt', ['title' => 'Kontakt']);
    DeadLinksFixtures::entry('entwurf', ['title' => 'Entwurf'], published: false);
    DeadLinksFixtures::term('news', ['de' => ['title' => 'News']]);

    expect(checkedDeadLinks(['/kontakt', '/kontakt?tab=1#team', 'http://localhost/kontakt', '/tags/news', '/entwurf']))->toEqual([
        '/kontakt' => new CheckResult('/kontakt'),
        '/kontakt?tab=1#team' => new CheckResult('/kontakt?tab=1#team'),
        'http://localhost/kontakt' => new CheckResult('http://localhost/kontakt'),
        '/tags/news' => new CheckResult('/tags/news'),
        '/entwurf' => new CheckResult('/entwurf', Reason::UnpublishedEntry),
    ]);
});

it('resolves the URLs of every site', function (): void {
    DeadLinksFixtures::entry('kontakt', ['title' => 'Kontakt']);
    DeadLinksFixtures::entry('contact', ['title' => 'Contact'], 'en', 'kontakt');

    expect(checkedDeadLinks(['/en/contact', 'http://localhost/en/contact']))->toEqual([
        '/en/contact' => new CheckResult('/en/contact'),
        'http://localhost/en/contact' => new CheckResult('http://localhost/en/contact'),
    ]);
});

it('resolves the URLs of sites on their own domains and a path on the domain of the default site', function (): void {
    Site::setSites([
        'de' => ['url' => 'https://example.ch/', 'locale' => 'de_CH'],
        'en' => ['url' => 'https://example.com/', 'locale' => 'en_US'],
    ]);
    DeadLinksFixtures::entry('kontakt', ['title' => 'Kontakt']);
    DeadLinksFixtures::entry('contact', ['title' => 'Contact'], 'en', 'kontakt');
    Http::fake(['https://example.ch/contact' => Http::response(status: 404)]);

    expect(checkedDeadLinks(['https://example.ch/kontakt', 'https://example.com/contact', '/kontakt', '/contact']))->toEqual([
        'https://example.ch/kontakt' => new CheckResult('https://example.ch/kontakt'),
        'https://example.com/contact' => new CheckResult('https://example.com/contact'),
        '/kontakt' => new CheckResult('/kontakt'),
        '/contact' => new CheckResult('/contact', Reason::PageNotFound, 404),
    ]);
});

it('checks the entry of a page in a structure', function (): void {
    DeadLinksFixtures::entry('team', ['title' => 'Team']);
    DeadLinksFixtures::entry('entwurf', ['title' => 'Entwurf'], published: false);
    DeadLinksFixtures::structure(['team', 'entwurf']);

    expect(checkedDeadLinks(['/team', '/entwurf']))->toEqual([
        '/team' => new CheckResult('/team'),
        '/entwurf' => new CheckResult('/entwurf', Reason::UnpublishedEntry),
    ]);
});

it('requests a URL of the site that Statamic does not know', function (): void {
    Http::fake([
        'http://localhost/sitemap.xml' => Http::response(),
        'http://localhost/alt' => Http::response(status: 404),
    ]);

    expect(checkedDeadLinks(['/sitemap.xml', '/alt']))->toEqual([
        '/sitemap.xml' => new CheckResult('/sitemap.xml', status: 200),
        '/alt' => new CheckResult('/alt', Reason::PageNotFound, 404),
    ]);
});

it('rates an answer that is :dataset', function (int $status, ?Reason $reason): void {
    Http::fake(['https://example.com/seite' => Http::response(status: $status)]);

    expect(checkedDeadLinks(['https://example.com/seite']))->toEqual([
        'https://example.com/seite' => new CheckResult('https://example.com/seite', $reason, $status),
    ]);
})->with([
    'ok' => [200, null],
    'a bad request' => [400, Reason::HttpError],
    'unauthorized' => [401, null],
    'forbidden' => [403, null],
    'not found' => [404, Reason::PageNotFound],
    'a method not allowed' => [405, Reason::HttpError],
    'gone' => [410, Reason::PageNotFound],
    'too many requests' => [429, null],
    'a server error' => [500, Reason::HttpError],
    'unavailable' => [503, Reason::HttpError],
]);

it('asks again with GET only when HEAD gets an error', function (): void {
    Http::fake([
        'https://example.com/ok' => Http::response(),
        'https://example.com/kein-head' => Http::sequence()->push(status: 405)->push(),
        'https://example.com/alt' => Http::sequence()->push(status: 404)->push(status: 404),
    ]);

    expect(checkedDeadLinks(['https://example.com/ok', 'https://example.com/kein-head', 'https://example.com/alt']))->toEqual([
        'https://example.com/ok' => new CheckResult('https://example.com/ok', status: 200),
        'https://example.com/kein-head' => new CheckResult('https://example.com/kein-head', status: 200),
        'https://example.com/alt' => new CheckResult('https://example.com/alt', Reason::PageNotFound, 404),
    ])->and(sentDeadLinkRequests())->toBe([
        'GET https://example.com/alt',
        'GET https://example.com/kein-head',
        'HEAD https://example.com/alt',
        'HEAD https://example.com/kein-head',
        'HEAD https://example.com/ok',
    ]);
});

it('reports a link whose server does not answer in time or at all', function (): void {
    Http::fake([
        'https://langsam.example.com/*' => Http::failedConnection('cURL error 28: Operation timed out'),
        'https://unbekannt.example.com/*' => Http::failedConnection(),
    ]);

    expect(checkedDeadLinks(['https://langsam.example.com/', 'https://unbekannt.example.com/']))->toEqual([
        'https://langsam.example.com/' => new CheckResult('https://langsam.example.com/', Reason::ConnectionFailed),
        'https://unbekannt.example.com/' => new CheckResult('https://unbekannt.example.com/', Reason::ConnectionFailed),
    ]);
});

it('reports a value :dataset as an invalid URL without a request', function (string $url): void {
    expect(checkedDeadLinks([$url]))->toEqual([$url => new CheckResult($url, Reason::InvalidUrl)]);
})->with([
    'with a mistyped scheme' => 'https::/google.com',
    'without a scheme' => 'www.example.com',
    'that is a word' => 'kontakt',
    'that is a number' => '2024',
    'that is protocol-relative' => '//cdn.example.com/a.js',
    'with another scheme' => 'ftp://example.com/a.pdf',
    'with a space in the host' => 'https:// example.com/',
    'with a port out of range' => 'https://example.com:99999/',
    'without a host' => 'http://',
]);

it('requests a URL with umlauts encoded', function (): void {
    Http::fake(['https://www.linkedin.com/company/f%C3%B6rdervereincocomo/' => Http::response()]);

    expect(checkedDeadLinks(['https://www.linkedin.com/company/fördervereincocomo/']))->toEqual([
        'https://www.linkedin.com/company/fördervereincocomo/' => new CheckResult('https://www.linkedin.com/company/fördervereincocomo/', status: 200),
    ]);
});

it('checks each URL once and keeps it as the key of its result', function (): void {
    Http::fake(['https://example.com/a' => Http::response()]);

    expect(checkedDeadLinks(['2024', 'https://example.com/a', '2024', 'https://example.com/a']))->toEqual([
        '2024' => new CheckResult('2024', Reason::InvalidUrl),
        'https://example.com/a' => new CheckResult('https://example.com/a', status: 200),
    ]);
    Http::assertSentCount(1);
});

it('sends its own user agent and waits as long as the timeout says', function (): void {
    config(['juicebox.dead_links.timeout' => 3]);
    $sent = [];
    Http::fake(function (Request $request, array $options) use (&$sent): PromiseInterface {
        $sent[] = [$request->header('User-Agent'), $options['timeout']];

        return Http::response();
    });

    checkedDeadLinks(['https://example.com/']);

    expect($sent)->toBe([[['Mozilla/5.0 (compatible; Juicebox Dead Links; +https://vitamin2.ch)'], 3]]);
});

it('sends ten requests at a time', function (): void {
    $log = [];
    Http::fake(function () use (&$log): PromiseInterface {
        $log[] = 'sent';

        return Http::response();
    });
    Event::listen(ResponseReceived::class, function () use (&$log): void {
        $log[] = 'received';
    });

    checkedDeadLinks(array_map(fn (int $page): string => "https://example.com/{$page}", range(1, 11)));

    expect(array_search('received', $log, true))->toBe(10);
});
