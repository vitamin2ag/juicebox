<?php

declare(strict_types=1);

use Statamic\Tags\TagNotFoundException;
use Vitamin2\Juicebox\Tags\Juicebox;
use Vitamin2\Juicebox\Tests\Feature\Tags\__Fixtures__\FakeTag;
use Vitamin2\Juicebox\Tests\Feature\Tags\__Fixtures__\FakeTagNoWildcard;

mutates(Juicebox::class);

/**
 * @param  array<string, mixed>  $params
 * @param  array<string, mixed>  $context
 */
function makeJuiceboxTag(string $tag, array $params = [], string $content = '', array $context = [], ?Juicebox $instance = null): Juicebox
{
    $instance ??= new class extends Juicebox
    {
        protected function tagClass(string $tag): ?string
        {
            return [
                'fake' => FakeTag::class,
                'nowildcard' => FakeTagNoWildcard::class,
            ][$tag] ?? null;
        }
    };

    $instance->setProperties([
        'parser' => null,
        'content' => $content,
        'context' => $context,
        'params' => $params,
        'tag' => $tag,
        'tag_method' => 'wildcard',
    ]);

    return $instance;
}

beforeEach(function (): void {
    FakeTag::$lastParams = null;
    FakeTag::$lastContent = null;
    FakeTag::$lastContext = null;
    FakeTag::$lastTag = null;
    FakeTag::$lastMethod = null;
});

it('returns null from wildcard when tag is not prefixed with juicebox', function (): void {
    expect(makeJuiceboxTag('something::fake:index')->wildcard())->toBeNull();
});

it('proxies to the correct tag', function (): void {
    expect(makeJuiceboxTag('juicebox::fake:index')->wildcard())->toBe('fake:index');
});

it('defaults to the index method when no method is given', function (): void {
    expect(makeJuiceboxTag('juicebox::fake')->wildcard())->toBe('fake:index');
});

it('calls an explicit method', function (): void {
    expect(makeJuiceboxTag('juicebox::fake:custom')->wildcard())->toBe('fake:custom');
});

it('throws for an unknown tag name', function (): void {
    expect(fn (): mixed => makeJuiceboxTag('juicebox::unknown:index')->wildcard())
        ->toThrow(TagNotFoundException::class, 'Tag [unknown] could not be found.');
});

it('falls back to the wildcard method when the direct method is missing', function (): void {
    expect(makeJuiceboxTag('juicebox::fake:missing')->wildcard())->toBe('fake:wildcard:missing');
});

it('throws when neither method nor wildcard exists', function (): void {
    expect(fn (): mixed => makeJuiceboxTag('juicebox::nowildcard:missing')->wildcard())
        ->toThrow(TagNotFoundException::class, 'Tag [nowildcard] could not be found.');
});

it('forwards params to the delegated tag', function (): void {
    makeJuiceboxTag('juicebox::fake:index', ['foo' => 'bar'])->wildcard();

    expect(FakeTag::$lastParams?->get('foo'))->toBe('bar');
});

it('forwards content and context to the delegated tag', function (): void {
    makeJuiceboxTag('juicebox::fake:index', content: 'inner', context: ['page' => 'home'])->wildcard();

    expect(FakeTag::$lastContent)->toBe('inner')
        ->and(FakeTag::$lastContext?->all())->toBe(['page' => 'home']);
});

it('forwards the tag and method names to the delegated tag', function (): void {
    makeJuiceboxTag('juicebox::fake:index')->wildcard();

    expect(FakeTag::$lastTag)->toBe('fake:index')
        ->and(FakeTag::$lastMethod)->toBe('index');
});

it('maps the schema tag to the Schema.org tag', function (): void {
    $script = makeJuiceboxTag('juicebox::schema', instance: resolve(Juicebox::class))->wildcard();

    expect($script)->toBeString()->toStartWith('<script type="application/ld+json">');
});

it('throws for an unknown tag when discovering from the feature modules', function (): void {
    expect(fn (): mixed => makeJuiceboxTag('juicebox::unknown', instance: resolve(Juicebox::class))->wildcard())
        ->toThrow(TagNotFoundException::class, 'Tag [unknown] could not be found.');
});
