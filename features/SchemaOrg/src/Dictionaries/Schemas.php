<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Dictionaries;

use Closure;
use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use LogicException;
use Override;
use ReflectionClass;
use Statamic\Dictionaries\BasicDictionary;
use Statamic\Support\Str;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema;

class Schemas extends BasicDictionary
{
    /** @return class-string<Schema>|null */
    public function resolveClass(?string $name): ?string
    {
        return $this->classesByName()->get($name);
    }

    /** @return array<int, string> */
    protected function roots(): array
    {
        return [
            'Vitamin2\\Juicebox\\Features\\SchemaOrg\\Schemas',
            'App\\Schemas',
        ];
    }

    /** @return array<string, array<string, mixed>> */
    #[Override]
    protected function fieldItems(): array
    {
        return [
            'category' => [
                'display' => __('Category'),
                'instructions' => __('The Schemas subdirectory/subnamespace to pick a Schema.org type from.'),
                'type' => 'select',
                'options' => $this->categories(),
                'clearable' => false,
            ],
        ];
    }

    /** @return array<int, array{label: string, value: string}> */
    #[Override]
    protected function getItems(): array
    {
        if (! $this->category()) {
            return [];
        }

        return $this->classesByName()
            ->map(fn (string $class, string $name): array => ['label' => $class::label(), 'value' => $name])
            ->sortBy('label')
            ->values()
            ->all();
    }

    private function category(): ?string
    {
        $category = $this->config()['category'] ?? null;

        return is_string($category) ? $category : null;
    }

    /** @return array<string, string> */
    private function categories(): array
    {
        return collect($this->roots())
            ->flatMap(fn (string $root): array => $this->subdirectoriesOf($root))
            ->unique()
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $category): array => [$category => $category])
            ->all();
    }

    /** @return array<int, string> */
    private function subdirectoriesOf(string $namespace): array
    {
        $names = [];

        foreach ($this->resolveNamespaceDirectories($namespace) as $directory) {
            foreach (Finder::create()->directories()->depth(0)->in($directory) as $path) {
                $names[] = $path->getFilename();
            }
        }

        return $names;
    }

    /** @return Collection<string, class-string<Schema>> */
    private function classesByName(): Collection
    {
        throw_unless($this->category(), LogicException::class, 'Schemas dictionary requires a category to be set via setConfig([\'category\' => ...]) before resolving classes.');

        $classes = [];

        foreach ($this->cached($this->cacheKey('classes'), $this->fingerprint(), $this->discoverClasses(...)) as $name => $class) {
            if (is_string($name) && is_string($class) && is_a($class, Schema::class, true)) {
                $classes[$name] = $class;
            }
        }

        return collect($classes);
    }

    /** @return array<string, class-string<Schema>> */
    private function discoverClasses(): array
    {
        return $this->categoryFiles()
            ->flatMap(fn (Collection $files, string $namespace): array => $this->classesFromFiles($files, $namespace))
            ->all();
    }

    /**
     * Every `.php` file under the current category's namespace directories,
     * grouped by namespace.
     *
     * @return Collection<string, Collection<int|string, SplFileInfo>>
     */
    private function categoryFiles(): Collection
    {
        return collect($this->roots())
            ->map(fn (string $root): string => "$root\\{$this->category()}")
            ->mapWithKeys(fn (string $namespace): array => [
                $namespace => $this
                    ->resolveNamespaceDirectories($namespace)
                    ->flatMap(fn (string $directory): Collection => collect($this->files()->files($directory))),
            ]);
    }

    /**
     * Fingerprints the category's files by name, size and mtime rather than
     * mtime alone, so a deploy method that preserves file timestamps (e.g.
     * `rsync -t`) still busts the cache when a file's contents actually
     * changed.
     */
    private function fingerprint(): string
    {
        return $this->categoryFiles()
            ->flatMap(fn (Collection $files): Collection => $files)
            ->map(fn (SplFileInfo $file): string => $file->getFilename().':'.$file->getSize().':'.$file->getMTime())
            ->sort()
            ->implode('|');
    }

    /**
     * @param  Collection<int|string, SplFileInfo>  $files
     * @return array<string, class-string<Schema>>
     */
    private function classesFromFiles(Collection $files, string $namespace): array
    {
        return $files
            ->mapWithKeys(function (SplFileInfo $file) use ($namespace): array {
                $name = $file->getBasename('.php');

                return [$name => "$namespace\\$name"];
            })
            ->filter(fn (string $class): bool => is_a($class, Schema::class, true) && new ReflectionClass($class)->isInstantiable())
            ->all();
    }

    /** @return Collection<int, non-falsy-string> */
    private function resolveNamespaceDirectories(string $namespace): Collection
    {
        $loader = current(ClassLoader::getRegisteredLoaders());

        return collect($loader ? $loader->getPrefixesPsr4() : [])
            ->filter(fn (array $directories, string $prefix): bool => str_starts_with($namespace, $prefix))
            ->map(fn (array $directories, string $prefix): ?string => $this->resolveDirectory($directories, $prefix, $namespace))
            ->filter()
            ->filter(fn (string $directory): bool => $this->files()->isDirectory($directory))
            ->values();
    }

    /** @param  array<int, string>  $directories */
    private function resolveDirectory(array $directories, string $prefix, string $namespace): ?string
    {
        $path = Str::of($namespace)
            ->after($prefix)
            ->replace('\\', DIRECTORY_SEPARATOR)
            ->start(DIRECTORY_SEPARATOR)
            ->toString();

        $first = $directories[0] ?? null;

        return $first === null ? null : $first.$path;
    }

    private function files(): Filesystem
    {
        return resolve(Filesystem::class);
    }

    private function cacheKey(string $suffix): string
    {
        return 'juicebox-schema-dictionary-schemas-'.hash('xxh128', implode('|', $this->roots()))."-{$this->category()}-$suffix";
    }

    /**
     * Caches $resolver's result under $key, invalidated by $fingerprint
     * instead of a request lifetime or a manual cache clear: a schema class
     * added, removed or edited changes the fingerprint, which busts the
     * cache on its own on the next resolution.
     *
     * @param  Closure(): array<mixed>  $resolver
     * @return array<mixed>
     */
    private function cached(string $key, string $fingerprint, Closure $resolver): array
    {
        $cached = Cache::get($key);

        if (is_array($cached) && ($cached['fingerprint'] ?? null) === $fingerprint && is_array($cached['data'] ?? null)) {
            return $cached['data'];
        }

        $data = $resolver();

        Cache::forever($key, ['fingerprint' => $fingerprint, 'data' => $data]);

        return $data;
    }
}
