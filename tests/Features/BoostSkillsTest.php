<?php

declare(strict_types=1);

use Illuminate\Support\Str;

dataset('boost skills', function (): array {
    $skills = [];

    foreach (glob(__DIR__.'/../../resources/boost/skills/*/SKILL.md') ?: [] as $path) {
        $skills[basename(dirname($path))] = [basename(dirname($path)), (string) file_get_contents($path)];
    }

    return $skills;
});

it('has Boost skill front matter matching its folder', function (string $name, string $skill): void {
    expect($skill)->toStartWith("---\nname: $name\ndescription: ");
})->with('boost skills');

it('documents every config key in the Boost skill', function (string $name, string $skill): void {
    $feature = Str::snake(Str::studly(Str::after($name, 'juicebox-')));

    foreach (array_keys(config()->array("juicebox.$feature")) as $key) {
        expect($skill)->toContain("`$key`");
    }
})->with('boost skills');
