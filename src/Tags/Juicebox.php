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

        throw_unless($class = $this->tagClass($tag), TagNotFoundException::class, "Tag [$tag] could not be found.");

        /** @var Tags $instance */
        $instance = resolve($class);

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
        foreach (collect(app()->getProviders(FeatureServiceProvider::class))->whereInstanceOf(FeatureServiceProvider::class) as $provider) {
            $class = Str::beforeLast($provider::class, '\\').'\\Tags\\'.Str::studly($tag);

            if (is_subclass_of($class, Tags::class)) {
                return $class;
            }
        }

        return null;
    }
}
