<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Override;
use Spatie\SchemaOrg\Schema as SpatieSchema;
use Statamic\Console\RunsInPlease;
use Symfony\Component\Console\Input\InputOption;

class MakeEntrySchema extends GeneratorCommand
{
    use RunsInPlease;

    #[Override]
    protected $name = 'statamic:juicebox:schema-org:make-schema';

    #[Override]
    protected $description = 'Create a Schema.org entry schema class in App\Schemas\Entries';

    #[Override]
    protected $type = 'Schema';

    #[Override]
    protected function getStub(): string
    {
        return dirname(__DIR__, 3).'/resources/stubs/entry-schema.php.stub';
    }

    #[Override]
    protected function getNameInput(): string
    {
        return Str::studly(parent::getNameInput());
    }

    /**
     * The `Schemas` dictionary scans the fixed `App\Schemas` root, so the
     * class always lands there regardless of the app's own namespace.
     */
    #[Override]
    protected function rootNamespace(): string
    {
        return 'App\\';
    }

    #[Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Schemas\\Entries';
    }

    #[Override]
    public function handle(): bool|int|null
    {
        $type = $this->option('type');

        if (is_string($type) && ! $this->isSchemaType(Str::studly($type))) {
            $this->components->error("[$type] is not a Schema.org type.");

            return self::FAILURE;
        }

        return parent::handle();
    }

    /** @return array<int, array<int, mixed>> */
    #[Override]
    protected function getOptions(): array
    {
        return [
            ['type', 't', InputOption::VALUE_REQUIRED, 'The Schema.org type to start from (default: the class name, else Thing)'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the class if it already exists'],
        ];
    }

    /**
     * The node type is `--type`, else the class name when it names a
     * Schema.org type (`Article`), else a plain `Thing` (`BlogPost`).
     */
    #[Override]
    protected function buildClass($name): string
    {
        $class = class_basename($name);
        $requested = $this->option('type');
        $type = is_string($requested) ? Str::studly($requested) : ($this->isSchemaType($class) ? $class : 'Thing');

        return str_replace(
            ['{{ type }}', '{{ factory }}', '{{ label }}', '{{ fragment }}'],
            [$type, lcfirst($type), Str::headline($class), Str::kebab($type)],
            parent::buildClass($name),
        );
    }

    /** Every Schema.org type has a static factory of its own name on Spatie's `Schema` (method names are case-insensitive). */
    private function isSchemaType(string $type): bool
    {
        return method_exists(SpatieSchema::class, $type);
    }
}
