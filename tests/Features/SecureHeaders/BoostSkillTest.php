<?php

declare(strict_types=1);

it('documents every config key in the Boost skill', function (): void {
    $skill = (string) file_get_contents(__DIR__.'/../../../resources/boost/skills/juicebox-secure-headers/SKILL.md');

    foreach (array_keys(config()->array('juicebox.secure_headers')) as $key) {
        expect($skill)->toContain("`$key`");
    }
});

it('has Boost skill front matter matching its folder', function (): void {
    $skill = (string) file_get_contents(__DIR__.'/../../../resources/boost/skills/juicebox-secure-headers/SKILL.md');

    expect($skill)->toStartWith("---\nname: juicebox-secure-headers\ndescription: ");
});
