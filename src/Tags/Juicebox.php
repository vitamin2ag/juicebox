<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Tags;

use Illuminate\Support\Str;
use Statamic\Tags\TagNotFoundException;
use Statamic\Tags\Tags;
use Vitamin2\Juicebox\FeatureServiceProvider;

class Juicebox extends Tags
{
    public function wildcard(): mixed
    {
        if (Str::startsWith($this->tag, 'juicebox::')) {
            return $this->proxy();
        }

        return null;
    }

    /**
     * @throws TagNotFoundException
     */
    protected function proxy(): mixed
    {
        $signature = Str::of($this->tag)
            ->after('juicebox::')
            ->toString();

        $parts = explode(':', $signature);

        $tag = $parts[0];
        $method = $parts[1] ?? 'index';

        $class = $this->tagClass($tag) ?? throw new TagNotFoundException("Tag [$tag] could not be found.");

        $instance = app()->build($class);

        $instance->setProperties([
            'parser' => $this->parser,
            'content' => $this->content,
            'context' => $this->context,
            'params' => $this->params,
            'tag' => "$tag:$method",
            'tag_method' => $method,
        ]);

        if (method_exists($instance, $method)) {
            return $instance->{$method}();
        }

        if (method_exists($class, $wildcard = $instance->wildcardMethod)) {
            return $instance->{$wildcard}($method);
        }

        throw new TagNotFoundException("Tag [$tag] could not be found.");
    }

    /**
     * Finds `Features\<Name>\Tags\<Tag>` in the registered feature modules, so core never names a feature.
     *
     * @return class-string<Tags>|null
     */
    protected function tagClass(string $tag): ?string
    {
        return collect(app()->getProviders(FeatureServiceProvider::class))
            ->map(fn (FeatureServiceProvider $provider): string => Str::beforeLast($provider::class, '\\').'\\Tags\\'.Str::studly($tag))
            ->map(fn (string $class): ?string => is_subclass_of($class, Tags::class) ? $class : null)
            ->filter()
            ->first();
    }
}
