<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Statamic\Dictionaries\Item;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema as SchemaContract;
use Vitamin2\Juicebox\Features\SchemaOrg\Dictionaries\Schemas;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Organization;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\Schemas\Entries\FakeCollectionSchemaA;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\Schemas\Entries\FakeCollectionSchemaB;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\Schemas\Globals\FakeSchemaA;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\Schemas\Globals\FakeSchemaB;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\SchemasOverride\Entries\FakeCollectionSchemaA as FakeCollectionSchemaAOverride;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\SchemasOverride\Globals\FakeSchemaA as FakeSchemaAOverride;

mutates(Schemas::class);

const SCHEMAS_FIXTURE_ROOT = 'Vitamin2\\Juicebox\\Features\\SchemaOrg\\Tests\\__Fixtures__\\Schemas';

const SCHEMAS_FIXTURE_OVERRIDE_ROOT = 'Vitamin2\\Juicebox\\Features\\SchemaOrg\\Tests\\__Fixtures__\\SchemasOverride';

dataset('schema categories', [
    'Globals' => ['Globals', FakeSchemaA::class, FakeSchemaB::class, 'NotASchema', FakeSchemaAOverride::class],
    'Entries' => ['Entries', FakeCollectionSchemaA::class, FakeCollectionSchemaB::class, 'NotACollectionSchema', FakeCollectionSchemaAOverride::class],
]);

beforeEach(function (): void {
    Cache::flush();
});

/**
 * @param  array<int, string>  $roots
 */
function schemasItemsCacheKey(string $category, array $roots = [SCHEMAS_FIXTURE_ROOT]): string
{
    return 'juicebox-schema-dictionary-schemas-'.hash('xxh128', implode('|', $roots))."-$category-items";
}

/**
 * @param  array<int, string>  $roots
 */
function schemasDictionary(?string $category = null, array $roots = [SCHEMAS_FIXTURE_ROOT]): Schemas
{
    $dictionary = new class($roots) extends Schemas
    {
        /** @param  array<int, string>  $rootList */
        public function __construct(private readonly array $rootList) {}

        /** @return array<int, string> */
        protected function roots(): array
        {
            return $this->rootList;
        }
    };

    $dictionary->setConfig($category ? ['category' => $category] : []);

    return $dictionary;
}

it('returns schema classes from namespace', function (string $category, string $classA, string $classB): void {
    $options = schemasDictionary($category)->options();

    expect($options)->toHaveKey(class_basename($classA))
        ->and($options)->toHaveKey(class_basename($classB));
})->with('schema categories');

it('uses the short class name as option value and the schema label as label', function (string $category, string $classA, string $classB): void {
    $options = schemasDictionary($category)->options();

    expect($options[class_basename($classA)])->toBe($classA::label())
        ->and($options[class_basename($classB)])->toBe($classB::label());
})->with('schema categories');

it('sorts options alphabetically', function (string $category): void {
    $options = collect(schemasDictionary($category)->options());
    $labels = $options->values()->all();

    expect($labels)->toBe(collect($labels)->sort()->values()->all());
})->with('schema categories');

it('excludes classes not implementing the schema contract', function (string $category, string $classA, string $classB, string $notASchema): void {
    $dictionary = schemasDictionary($category);
    $options = $dictionary->options();

    foreach (array_keys($options) as $name) {
        $class = $dictionary->resolveClass($name);

        expect($class)->toImplement(SchemaContract::class);
    }

    expect($options)->not->toHaveKey($notASchema);
})->with('schema categories');

it('returns an item for a known class', function (string $category, string $classA): void {
    $item = schemasDictionary($category)->get(class_basename($classA));

    expect($item)->toBeInstanceOf(Item::class)
        ->and($item?->value())->toBe(class_basename($classA))
        ->and($item?->label())->toBe($classA::label());
})->with('schema categories');

it('returns null for an unknown class', function (): void {
    expect(schemasDictionary('Globals')->get('NonExistent\\Schema'))->toBeNull();
});

it('caches results', function (string $category): void {
    schemasDictionary($category)->options();

    expect(Cache::has(schemasItemsCacheKey($category)))->toBeTrue();
})->with('schema categories');

it('reuses the cache while source files are unchanged', function (string $category): void {
    $dictionary = schemasDictionary($category);
    $dictionary->options();

    $cached = Cache::get(schemasItemsCacheKey($category));
    Cache::forever(schemasItemsCacheKey($category), [
        'fingerprint' => is_array($cached) ? $cached['fingerprint'] : null,
        'data' => [['label' => 'Stub', 'value' => 'Stub']],
    ]);

    expect($dictionary->options())->toHaveKey('Stub');
})->with('schema categories');

it('invalidates the cache when a source file changes', function (string $category): void {
    $dictionary = schemasDictionary($category);
    $dictionary->options();

    Cache::forever(schemasItemsCacheKey($category), [
        'fingerprint' => 'stale-fingerprint',
        'data' => [['label' => 'Stub', 'value' => 'Stub']],
    ]);

    expect($dictionary->options())->not->toHaveKey('Stub');
})->with('schema categories');

it('lets a later root overwrite an earlier one for the same class name', function (string $category, string $classA, string $classB, string $notASchema, string $override): void {
    $dictionary = schemasDictionary($category, [SCHEMAS_FIXTURE_ROOT, SCHEMAS_FIXTURE_OVERRIDE_ROOT]);

    expect($dictionary->resolveClass(class_basename($classA)))->toBe($override)
        ->and($dictionary->resolveClass(class_basename($classA)))->not->toBe($classA)
        ->and($dictionary->options())->toHaveKey(class_basename($classA));
})->with('schema categories');

it('returns null when resolving an unknown name', function (): void {
    expect(schemasDictionary('Globals')->resolveClass('Unknown'))->toBeNull();
});

it('excludes abstract classes', function (): void {
    expect(schemasDictionary('Entries')->options())->not->toHaveKey('AbstractFakeSchema');
});

it('exposes a category select with discovered options in the field items', function (): void {
    $dictionary = schemasDictionary(roots: [SCHEMAS_FIXTURE_ROOT]);

    $fields = $dictionary->fields();
    $field = $fields instanceof Fields ? $fields->all()->get('category') : null;
    $config = $field instanceof Field ? $field->config() : [];

    expect($config)->toMatchArray([
        'type' => 'select',
        'display' => 'Category',
        'clearable' => false,
        'options' => ['Entries' => 'Entries', 'Globals' => 'Globals'],
    ])->and($config['instructions'] ?? null)->toBeString()->not->toBeEmpty();
});

it('caches categories independently', function (): void {
    $dictionary = new class([SCHEMAS_FIXTURE_ROOT]) extends Schemas
    {
        /** @param  array<int, string>  $rootList */
        public function __construct(private readonly array $rootList) {}

        /** @return array<int, string> */
        protected function roots(): array
        {
            return $this->rootList;
        }
    };

    $globalOptions = (clone $dictionary)->setConfig(['category' => 'Globals'])->options();
    $collectionOptions = (clone $dictionary)->setConfig(['category' => 'Entries'])->options();

    expect($globalOptions)->toHaveKey('FakeSchemaA')
        ->and($collectionOptions)->not->toHaveKey('FakeSchemaA')
        ->and($collectionOptions)->toHaveKey('FakeCollectionSchemaA')
        ->and($globalOptions)->not->toHaveKey('FakeCollectionSchemaA');
});

it('lists no options until a category is set', function (): void {
    expect(schemasDictionary()->options())->toBeEmpty();
});

it('refuses to resolve a class until a category is set', function (): void {
    schemasDictionary()->resolveClass('FakeSchemaA');
})->throws(LogicException::class, 'requires a category');

it('fingerprints the category files by name, size and mtime', function (): void {
    schemasDictionary('Entries')->options();

    $files = glob(__DIR__.'/../__Fixtures__/Schemas/Entries/*.php') ?: [];
    $expected = collect($files)
        ->map(fn (string $file): string => basename($file).':'.filesize($file).':'.filemtime($file))
        ->sort()
        ->implode('|');

    $cached = Cache::get(schemasItemsCacheKey('Entries'));

    expect($files)->not->toBeEmpty()
        ->and(is_array($cached) ? $cached['fingerprint'] : null)->toBe($expected)
        ->and(is_array($cached) ? $cached['data'] : null)->toBeArray()->not->toBeEmpty();
});

it('ignores malformed cached options', function (): void {
    $dictionary = schemasDictionary('Globals');
    $dictionary->options();

    $cached = Cache::get(schemasItemsCacheKey('Globals'));
    Cache::forever(schemasItemsCacheKey('Globals'), [
        'fingerprint' => is_array($cached) ? $cached['fingerprint'] : null,
        'data' => [
            'not an array',
            ['label' => 'No value'],
            ['value' => 'NoLabel'],
            ['label' => 'Valid', 'value' => 'Valid'],
        ],
    ]);

    expect($dictionary->options())->toBe(['Valid' => 'Valid']);
});

it('ignores malformed cached classes', function (): void {
    $dictionary = schemasDictionary('Globals');
    $dictionary->options();

    $key = str_replace('-items', '-classes', schemasItemsCacheKey('Globals'));
    $cached = Cache::get($key);
    Cache::forever($key, [
        'fingerprint' => is_array($cached) ? $cached['fingerprint'] : null,
        'data' => [
            'NotAClass' => stdClass::class,
            0 => FakeSchemaA::class,
            'FakeSchemaA' => FakeSchemaA::class,
        ],
    ]);

    expect($dictionary->resolveClass('NotAClass'))->toBeNull()
        ->and($dictionary->resolveClass('0'))->toBeNull()
        ->and($dictionary->resolveClass('FakeSchemaA'))->toBe(FakeSchemaA::class);
});

it('discovers the bundled schemas by default', function (): void {
    $dictionary = new Schemas;
    $dictionary->setConfig(['category' => 'Globals']);

    expect($dictionary->resolveClass('Organization'))->toBe(Organization::class);
});

it('also discovers the apps own schemas by default', function (): void {
    $directory = sys_get_temp_dir().'/juicebox-app-schemas-'.uniqid();
    mkdir("$directory/Entries", recursive: true);

    file_put_contents("$directory/Entries/AppOwnSchema.php", <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Schemas\Entries;

        use Spatie\SchemaOrg\BaseType;
        use Spatie\SchemaOrg\Contracts\ThingContract;
        use Spatie\SchemaOrg\Schema;
        use Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema as Contract;

        class AppOwnSchema implements Contract
        {
            public static function label(): string
            {
                return 'App Own';
            }

            public function schema(): BaseType&ThingContract
            {
                return Schema::thing();
            }
        }
        PHP);

    require_once "$directory/Entries/AppOwnSchema.php";

    foreach (ClassLoader::getRegisteredLoaders() as $loader) {
        $loader->addPsr4('App\\Schemas\\', $directory);
    }

    $dictionary = new Schemas;
    $dictionary->setConfig(['category' => 'Entries']);

    expect($dictionary->resolveClass('AppOwnSchema'))->toBe('App\\Schemas\\Entries\\AppOwnSchema');

    File::deleteDirectory($directory);
});
