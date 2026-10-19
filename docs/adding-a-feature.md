# Adding a feature

A feature is a self-contained folder `features/<Name>/` that [`edalzell/laravel-features`](https://github.com/edalzell/laravel-features) discovers and registers, with [`edalzell/statamic-features`](https://github.com/edalzell/statamic-features) adding the Statamic side: its own service provider, config, tests and (optionally) routes, views, translations, commands, listeners, policies and migrations. A Laravel Boost skill for consuming apps ships alongside. `features/SecureHeaders/` is the reference implementation. The generic conventions are in `vendor/edalzell/laravel-features/resources/boost/skills/laravel-features/SKILL.md`; read it when using anything beyond config and a provider.

## Layout

```
features/SecureHeaders/
├── config/secure_headers.php      # merged at juicebox.secure_headers
├── src/
│   ├── ServiceProvider.php        # Vitamin2\Juicebox\Features\SecureHeaders\ServiceProvider
│   └── Http/Middleware/SetSecureHeaders.php
└── tests/                         # Vitamin2\Juicebox\Features\SecureHeaders\Tests
```

laravel-features loads these folders when they exist: `routes/api.php`, `resources/views` (namespace `<slug>::`), `lang`, `database/{migrations,factories,seeders}`, `src/Console/Commands`, `src/Listeners`, `src/Policies`.

statamic-features registers the Statamic folders, one level deep, as Statamic does for an addon:

| Folder | What happens |
|---|---|
| `src/{Actions,Dictionaries,Fieldtypes,Modifiers,Scopes,Tags,Widgets}`, `src/Query/Scopes`, `src/Query/Scopes/Filters` | every concrete class of that type is registered |
| `src/Subscribers` | every class is passed to `Event::subscribe()` |
| `src/UpdateScripts` | registered against `vitamin2/juicebox` |
| `resources/blueprints`, `resources/fieldsets` | namespace `<slug>::`, imported as `<slug>::<handle>` |
| `routes/web.php`, `routes/cp.php`, `routes/actions.php` | Statamic web, control panel and action routes (actions prefixed `/!/<slug>/`) |
| `resources/cp/{js,css}` | control panel scripts and stylesheets, published to `public/vendor/<slug>/` |

Put each file where it is listed; other locations are not loaded. Command stubs go in `resources/stubs`. Two features cannot share a slug: the second throws.

## How wiring works

`Vitamin2\Juicebox\ServiceProvider` uses `HasFeatures` and calls `registerFeatures()` from `register()`. `registerFeatures()` scans `features/*`; every folder with a `src/ServiceProvider.php` is a feature. The Composer plugin generates the PSR-4 entries (`Vitamin2\Juicebox\Features\<Name>\` to `features/<Name>/src`, plus `\Tests\` and `\Database\...`) when the autoloader is dumped. Names derive from the folder name:

| Folder | Slug | Config file | Config key | Publish tag | Published to |
|---|---|---|---|---|---|
| `features/SecureHeaders` | `secure-headers` | `config/secure_headers.php` | `juicebox.secure_headers` | `juicebox-secure-headers-config` | `config/juicebox/secure_headers.php` |

The config file and key use the snake_case of the folder name (the base class sets `configFileName()`); the kebab-case slug is used for the publish tag, the view and lang namespaces and the Boost skill.

`Vitamin2\Juicebox\FeatureServiceProvider` is the base class. It extends laravel-features' `FeatureServiceProvider` and only sets the snake_case config file name and the publish tag prefix `juicebox-`; the config group `juicebox` is the Composer package name. It loads config, routes, views and translations; rely on it instead of calling `mergeConfigFrom`, `publishes`, `loadRoutesFrom` or `loadViewsFrom`. Config is merged in `register()` and offered for publishing in the console only.

Do not register fieldsets, blueprints, tags, fieldtypes and the like by hand; the folders above are enough. Only what has no folder (middleware, say) goes in the provider's `boot()`, after `parent::boot()`.

Read stubs by path from the command that uses them, for example `dirname(__DIR__, 3).'/resources/stubs/<name>.stub'` from `src/Console/Commands`.

## Checklist

Steps marked **(enforced)** fail `composer test` when forgotten.

1. Create `features/<Name>/src/ServiceProvider.php` in the namespace `Vitamin2\Juicebox\Features\<Name>` extending `Vitamin2\Juicebox\FeatureServiceProvider` **(enforced)**. The folder name equals the namespace segment; name it `Ai`, not `AI` (the slug is its kebab-case). When you override `boot()` or `register()`, call the parent first.
2. Run `composer dump-autoload` so the plugin picks up the new folder. Leave `extra.laravel.providers` with `Vitamin2\Juicebox\ServiceProvider` only **(enforced)**.
3. Put feature code in `features/<Name>/src/` (`Http/Middleware/`, `Concerns/`, ...) and fieldsets, blueprints and stubs in `features/<Name>/resources/`. Keep everything inside `features/<Name>/`, except the Boost skill, the feature doc and the README entry below.
4. Put classes in the folders listed under Layout so they register themselves; register the rest in `boot()` (for example `pushMiddlewareToGroup`). Register through methods, not property lists such as `$middlewareGroups`: mutation testing does not cover property defaults.
5. If configurable, add `features/<Name>/config/<snake_name>.php`. Read env vars there only. Use `false` to switch a value off.
6. Write Pest tests in `features/<Name>/tests/` (see the `package-testing` skill): happy path, `false` path, invalid input. Declare the class under test with `mutates(<Class>::class)` **(enforced)**. `tests/Pest.php` binds everything under `features/` automatically. Put fixtures in `features/<Name>/tests/__Fixtures__/` (namespace `Vitamin2\Juicebox\Features\<Name>\Tests\__Fixtures__`).
7. Add the Boost skill `resources/boost/skills/juicebox-<slug>/SKILL.md` **(enforced)**: front matter `name` equals the folder name, plus `description`; every config key appears in backticks **(enforced)**. Write it for apps that install the package, not for maintainers. This folder ships with the package; `.agents/` does not.
8. Document the feature in `docs/features/<slug>.md` and list it in the README `Features` section as `- [<Feature name>](docs/features/<slug>.md): <one-line summary>` **(enforced)**. For features with config the doc contains the publish command (`--tag=juicebox-<slug>-config`) and every config key in backticks **(enforced)**.
9. Run `composer test`, then `composer test:coverage` and `composer test:mutate` (100% required each; both need a coverage driver).

## Conventions

- Every PHP file declares `strict_types=1`; use `#[Override]` when overriding parent members.
- Read config with `config('juicebox.<snake_name>.<key>')`. Values are `mixed`; narrow them at the point of use (PHPStan runs at `max`; fix the type instead of using `@phpstan-ignore`, `assert()` or casts).
- Name artisan commands `statamic:juicebox:<slug>:<action>`.
- Add only the files the behaviour needs.

## Worked example: `RemovePoweredBy`

Strips the `X-Powered-By` response header. Config: a master `enabled` toggle.

`features/RemovePoweredBy/src/ServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\RemovePoweredBy;

use Illuminate\Routing\Router;
use Override;
use Vitamin2\Juicebox\FeatureServiceProvider;
use Vitamin2\Juicebox\Features\RemovePoweredBy\Http\Middleware\RemovePoweredHeader;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    public function boot(): void
    {
        parent::boot();

        $this->app->make(Router::class)->pushMiddlewareToGroup('web', RemovePoweredHeader::class);
    }
}
```

`features/RemovePoweredBy/src/Http/Middleware/RemovePoweredHeader.php`

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

`features/RemovePoweredBy/config/remove_powered_by.php`

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

`features/RemovePoweredBy/tests/Http/Middleware/RemovePoweredHeaderTest.php`

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

`docs/features/remove-powered-by.md`: the middleware class, a config table, and the publish command `php artisan vendor:publish --tag=juicebox-remove-powered-by-config`.

README `Features` entry: `- [Remove Powered By](docs/features/remove-powered-by.md): removes the X-Powered-By response header.`
