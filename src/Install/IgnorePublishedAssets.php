<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Install;

use Edalzell\Features\Feature;
use Edalzell\Features\FeatureRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Keeps the assets that statamic-features publishes for a feature out of the site's git repository. It runs
 * after `statamic:install`, which composer starts on every install and update, the first one included.
 *
 * Git decides what the site already ignores, so any equivalent pattern counts. Where git cannot say (a site
 * that is no repository, or no git binary), nothing is written.
 */
class IgnorePublishedAssets
{
    public function __invoke(): void
    {
        $missing = $this->publishedPaths()->filter(fn (string $path): bool => $this->isNotIgnored($path));

        if ($missing->isEmpty()) {
            return;
        }

        $file = base_path('.gitignore');
        $contents = File::exists($file) ? File::get($file) : '';
        $separator = $contents === '' || str_ends_with($contents, "\n") ? '' : "\n";
        $lines = $missing->map(fn (string $path): string => '/'.$path)->implode("\n");

        File::put($file, $contents.$separator.$lines."\n");
    }

    /**
     * statamic-features publishes the built control panel assets of a feature, `resources/dist`, to
     * `public/vendor/<slug>`.
     *
     * @return Collection<string, non-falsy-string>
     */
    private function publishedPaths(): Collection
    {
        return resolve(FeatureRegistry::class)
            ->all()
            ->filter(fn (Feature $feature): bool => $feature->has('resources/dist'))
            ->map(fn (Feature $feature): string => 'public/vendor/'.str($feature->name)->kebab().'/');
    }

    /**
     * `git check-ignore` exits with 1 for a path that is not ignored, and with 0 for one that is.
     */
    private function isNotIgnored(string $path): bool
    {
        return Process::path(base_path())->run(['git', 'check-ignore', $path])->exitCode() === 1;
    }
}
