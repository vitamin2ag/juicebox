<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Filters;

use Illuminate\Support\Fluent;
use Override;
use Statamic\Query\Scopes\Filter;

/**
 * A filter of the redirect list with one radio field, named like the redirect value it filters.
 */
abstract class RedirectFilter extends Filter
{
    abstract protected function field(): string;

    /**
     * @return array<int|string, string>
     */
    abstract protected function options(): array;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function fieldItems(): array
    {
        return [$this->field() => ['type' => 'radio', 'options' => $this->options()]];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    #[Override]
    public function badge(mixed $values): string
    {
        return $this->options()[new Fluent($values)->string($this->field())->toString()];
    }

    #[Override]
    public function visibleTo(mixed $key): bool
    {
        return $key === 'redirects';
    }
}
