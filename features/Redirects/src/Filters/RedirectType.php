<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Filters;

use Illuminate\Support\Fluent;
use Override;

class RedirectType extends RedirectFilter
{
    #[Override]
    public static function title(): string
    {
        return __('Type');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    #[Override]
    public function apply(mixed $query, mixed $values): void
    {
        $query->where('type', new Fluent($values)->integer('type'));
    }

    #[Override]
    protected function field(): string
    {
        return 'type';
    }

    #[Override]
    protected function options(): array
    {
        return ['301' => __('Permanent (301)'), '302' => __('Temporary (302)'), '410' => __('Gone (410)')];
    }
}
