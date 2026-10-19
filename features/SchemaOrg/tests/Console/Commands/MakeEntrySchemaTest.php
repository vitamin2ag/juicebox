<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Vitamin2\Juicebox\Features\SchemaOrg\Console\Commands\MakeEntrySchema;

mutates(MakeEntrySchema::class);

const MAKE_SCHEMA_COMMAND = 'statamic:juicebox:schema-org:make-schema';

function generatedSchemaPath(string $name): string
{
    return app_path("Schemas/Entries/$name.php");
}

beforeEach(function (): void {
    $this->appPath = sys_get_temp_dir().'/juicebox-make-schema-'.getmypid();
    app()->useAppPath($this->appPath);
});

afterEach(function (): void {
    File::deleteDirectory($this->appPath);
});

it('scaffolds a class for a Schema.org type named after the class', function (): void {
    expect(Artisan::call(MAKE_SCHEMA_COMMAND, ['name' => 'Article']))->toBe(0);

    $source = (string) file_get_contents(generatedSchemaPath('Article'));

    expect($source)
        ->toContain('namespace App\Schemas\Entries;')
        ->toContain('use Spatie\SchemaOrg\Article as ArticleType;')
        ->toContain('class Article extends EntrySchema')
        ->toContain("return 'Article';")
        ->toContain('Schema::article()')
        ->toContain("Ids::forEntry(\$this->entry, 'article')");
});

it('falls back to a Thing node when the name is not a Schema.org type', function (): void {
    Artisan::call(MAKE_SCHEMA_COMMAND, ['name' => 'BlogPost']);

    expect((string) file_get_contents(generatedSchemaPath('BlogPost')))
        ->toContain('use Spatie\SchemaOrg\Thing as ThingType;')
        ->toContain("return 'Blog Post';")
        ->toContain('Schema::thing()')
        ->toContain("Ids::forEntry(\$this->entry, 'thing')");
});

it('uses the Schema.org type given with --type', function (): void {
    expect(Artisan::call(MAKE_SCHEMA_COMMAND, ['name' => 'BlogPost', '--type' => 'article']))->toBe(0)
        ->and((string) file_get_contents(generatedSchemaPath('BlogPost')))->toContain('class BlogPost extends EntrySchema')
        ->toContain("return 'Blog Post';")
        ->toContain('use Spatie\SchemaOrg\Article as ArticleType;')
        ->toContain('Schema::article()')
        ->toContain("Ids::forEntry(\$this->entry, 'article')");
});

it('rejects a --type that is not a Schema.org type', function (): void {
    expect(Artisan::call(MAKE_SCHEMA_COMMAND, ['name' => 'BlogPost', '--type' => 'Nope']))->toBe(1)
        ->and(Artisan::output())->toContain('[Nope] is not a Schema.org type.')
        ->and(generatedSchemaPath('BlogPost'))->not->toBeFile();
});

it('describes its options in the command help', function (): void {
    $definition = Artisan::all()[MAKE_SCHEMA_COMMAND]->getDefinition();

    expect($definition->getOption('type')->getDescription())->toContain('Schema.org type')
        ->and($definition->getOption('force')->getDescription())->toContain('Overwrite');
});

it('generates syntactically valid PHP', function (): void {
    Artisan::call(MAKE_SCHEMA_COMMAND, ['name' => 'Event']);

    expect(Process::run(['php', '-l', generatedSchemaPath('Event')])->successful())->toBeTrue();
});

it('does not overwrite an existing class', function (): void {
    Artisan::call(MAKE_SCHEMA_COMMAND, ['name' => 'Article']);
    file_put_contents(generatedSchemaPath('Article'), 'custom');

    Artisan::call(MAKE_SCHEMA_COMMAND, ['name' => 'Article']);

    expect(file_get_contents(generatedSchemaPath('Article')))->toBe('custom');
});

it('overwrites an existing class with --force', function (): void {
    Artisan::call(MAKE_SCHEMA_COMMAND, ['name' => 'BlogPost']);

    expect(Artisan::call(MAKE_SCHEMA_COMMAND, ['name' => 'BlogPost', '--type' => 'Article', '--force' => true]))->toBe(0)
        ->and((string) file_get_contents(generatedSchemaPath('BlogPost')))->toContain('Schema::article()')->not->toContain('Schema::thing()');
});
