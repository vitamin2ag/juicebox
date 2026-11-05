<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Filters;

use Illuminate\Support\Fluent;
use Override;

class RedirectEnabled extends RedirectFilter
{
    #[Override]
    public static function title(): string
    {
        return __('Enabled');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    #[Override]
    public function apply(mixed $query, mixed $values): void
    {
        $query->where('enabled', new Fluent($values)->boolean('enabled'));
    }

    #[Override]
    protected function field(): string
    {
        return 'enabled';
    }

    #[Override]
    protected function options(): array
    {
        return ['1' => __('Enabled'), '0' => __('Disabled')];
    }
}
