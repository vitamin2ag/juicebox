<?php

declare(strict_types=1);

const SECURE_HEADERS_SKILL = __DIR__.'/../../../resources/boost/skills/juicebox-secure-headers/SKILL.md';

it('documents every config key in the Boost skill', function (): void {
    $skill = (string) file_get_contents(SECURE_HEADERS_SKILL);
    $keys = array_keys(config()->array('juicebox.secure_headers'));

    foreach ($keys as $key) {
        expect($skill)->toContain("`$key`");
    }
});

it('has Boost skill front matter matching its folder', function (): void {
    $skill = (string) file_get_contents(SECURE_HEADERS_SKILL);

    expect($skill)->toStartWith("---\nname: juicebox-secure-headers\ndescription: ");
});
