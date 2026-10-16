<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox;

use Edalzell\Features\Features;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Override;
use Statamic\Providers\AddonServiceProvider;

abstract class FeatureServiceProvider extends AddonServiceProvider
{
    private readonly Features $features;

    public function __construct(Application $app)
    {
        parent::__construct($app);

        $name = Str::of(static::class)->beforeLast('\\')->classBasename();

        $this->features = new Features($this)
            ->configGroup('juicebox')
            ->configFileName($name->snake()->toString())
            ->configPublishHandle('juicebox-'.$name->kebab());
    }

    #[Override]
    public function boot(): void
    {
        parent::boot();

        $this->features->bootFeature();
    }

    #[Override]
    public function register(): void
    {
        $this->features->registerFeature();
    }
}
