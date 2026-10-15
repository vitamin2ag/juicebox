# Adding a feature

A feature is a self-contained module in `src/Features/<Name>/` with its own service provider, optional config, and a Laravel Boost skill for consuming apps. `src/Features/SecureHeaders/` is the reference implementation.

## How wiring works

`FeatureServiceProvider` (base class) derives everything from the **second-to-last namespace segment** of the provider class:

| Provider class | Config file | Config key | Publish tag |
|---|---|---|---|
| `Vitamin2\Juicebox\Features\SecureHeaders\ServiceProvider` | `config/secure_headers.php` | `juicebox.secure_headers` | `statamic-juicebox-secure-headers-config` |

It merges and publishes the config file if it exists (skipped silently otherwise). Keep the `Features\<Name>\ServiceProvider` layout or the derivation breaks. Do not hand-roll `mergeConfigFrom` or `publishes`.

## Checklist

Steps marked **(enforced)** fail `composer test` when forgotten.

1. Create `src/Features/<Name>/ServiceProvider.php` extending `FeatureServiceProvider` **(enforced)**. Register via Statamic's `AddonServiceProvider` properties (`$middlewareGroups`, `$commands`, ...). Override `bootFeature()` only for custom boot logic; never `bootAddon()`.
2. Put feature code next to the provider (`Http/Middleware/`, `Concerns/`, ...). Nothing outside `src/Features/<Name>/`.
3. If configurable, add `config/<snake_name>.php`. Read env vars here and only here. `false` means "off"; never `null`.
4. Add the provider to `composer.json` under `extra.laravel.providers` **(enforced)**. The test case reads this list, so no test wiring is needed.
5. Write Pest tests in `tests/Features/<Name>/` (see the `package-testing` skill): happy path, `false` path, invalid input. Declare the class under test with `mutates(<Class>::class)` **(enforced)**. `tests/Pest.php` binds everything under `tests/Features` automatically.
6. Add the Boost skill `resources/boost/skills/juicebox-<kebab-name>/SKILL.md` **(enforced)**: front matter `name` equals the folder name, plus `description`; every config key appears in backticks **(enforced)**. Write it for apps that install the package, not for maintainers. This folder ships with the package; `.agents/` does not.
7. Document the feature in the README `Features` section under a `###` heading containing the feature's name in words (`SecureHeaders` → "Secure Headers"). For features with config the section must contain the publish command and every config key in backticks **(enforced)**.
8. Run `composer test`, then `composer test:coverage` and `composer test:mutate` (100% required each; both need a coverage driver).

## Conventions

- Every PHP file declares `strict_types=1`; use `#[Override]` when overriding parent members.
- Read config with `config('juicebox.<snake_name>.<key>')`. Values are `mixed`; narrow them at the point of use (PHPStan runs at `max`; no `@phpstan-ignore`, `assert()` or casts).
- Add only the files the behaviour needs, nothing "because a feature might need it later".

## Worked example: `RemovePoweredBy`

Strips the `X-Powered-By` response header. Config: a master `enabled` toggle.

`src/Features/RemovePoweredBy/ServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\RemovePoweredBy;

use Override;
use Vitamin2\Juicebox\Features\FeatureServiceProvider;
use Vitamin2\Juicebox\Features\RemovePoweredBy\Http\Middleware\RemovePoweredHeader;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    protected $middlewareGroups = [
        'web' => [
            RemovePoweredHeader::class,
        ],
    ];
}
```

`src/Features/RemovePoweredBy/Http/Middleware/RemovePoweredHeader.php`

```php
<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\RemovePoweredBy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RemovePoweredHeader
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('juicebox.remove_powered_by.enabled')) {
            $response->headers->remove('X-Powered-By');
        }

        return $response;
    }
}
```

`config/remove_powered_by.php`

```php
<?php

declare(strict_types=1);

return [

    /*
    |----------------------------------------------------------------------
    | Enable Remove Powered By
    |----------------------------------------------------------------------
    |
    | Accepted values: true, false
    |
    */

    'enabled' => env('JUICEBOX_REMOVE_POWERED_BY_ENABLED', true),

];
```

`composer.json`

```json
"providers": [
    "Vitamin2\\Juicebox\\ServiceProvider",
    "Vitamin2\\Juicebox\\Features\\SecureHeaders\\ServiceProvider",
    "Vitamin2\\Juicebox\\Features\\RemovePoweredBy\\ServiceProvider"
]
```

`tests/Features/RemovePoweredBy/Http/Middleware/RemovePoweredHeaderTest.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Vitamin2\Juicebox\Features\RemovePoweredBy\Http\Middleware\RemovePoweredHeader;

mutates(RemovePoweredHeader::class);

function runRemovePoweredBy(): Response
{
    $response = new Response;
    $response->headers->set('X-Powered-By', 'PHP');

    $result = (new RemovePoweredHeader)->handle(Request::create('/'), fn (): Response => $response);

    return $result instanceof Response ? $result : $response;
}

it('removes X-Powered-By', function (): void {
    expect(runRemovePoweredBy()->headers->has('X-Powered-By'))->toBeFalse();
});

it('keeps X-Powered-By when disabled', function (): void {
    config(['juicebox.remove_powered_by.enabled' => false]);

    expect(runRemovePoweredBy()->headers->get('X-Powered-By'))->toBe('PHP');
});
```

`resources/boost/skills/juicebox-remove-powered-by/SKILL.md`

```markdown
---
name: juicebox-remove-powered-by
description: Disable or verify the removal of the X-Powered-By response header done by the vitamin2/juicebox addon.
---

# Juicebox Remove Powered By

## Configure

| Key | Accepted values |
| --- | --- |
| `enabled` | `true`, `false`. Also set by `JUICEBOX_REMOVE_POWERED_BY_ENABLED`. |
```

README `Features` entry: a `### Remove Powered By` section with the middleware class, a config table, and the publish command `php artisan vendor:publish --tag=statamic-juicebox-remove-powered-by-config`.
