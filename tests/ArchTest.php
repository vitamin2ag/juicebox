<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema;

arch()->preset()->php();

// Pest only treats Vitamin2\Juicebox\Tests as test code, so the colocated
// features/<Name>/tests namespaces have to be excluded by hand.
arch()->preset()->security()->ignoring(array_map(
    fn (string $directory): string => 'Vitamin2\\Juicebox\\Features\\'.basename($directory).'\\Tests',
    glob(__DIR__.'/../features/*', GLOB_ONLYDIR) ?: [],
));

arch('the package declares strict types')
    ->expect('Vitamin2\Juicebox')
    ->toUseStrictTypes();

arch('features do not depend on test code')
    ->expect('Vitamin2\Juicebox\Features')
    ->not->toUse('Vitamin2\Juicebox\Tests');

// GraphBuilder constructs a schema with an entry only if it extends
// EntrySchema, so a Globals class extending it would be built without one.
arch('global schemas do not extend EntrySchema')
    ->expect('Vitamin2\\Juicebox\\Features\\SchemaOrg\\Schemas\\Globals')
    ->not->toExtend(EntrySchema::class);
