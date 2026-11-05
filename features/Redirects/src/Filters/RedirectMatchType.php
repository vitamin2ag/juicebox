<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Filters;

use Illuminate\Support\Fluent;
use Override;

class RedirectMatchType extends RedirectFilter
{
    #[Override]
    public static function title(): string
    {
        return __('Match type');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    #[Override]
    public function apply(mixed $query, mixed $values): void
    {
        $query->where('match_type', new Fluent($values)->string('match_type')->toString());
    }

    #[Override]
    protected function field(): string
    {
        return 'match_type';
    }

    #[Override]
    protected function options(): array
    {
        return ['exact' => __('Exact match'), 'regex' => __('Regex match')];
    }
}
