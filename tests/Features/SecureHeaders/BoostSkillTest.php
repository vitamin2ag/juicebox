<?php

declare(strict_types=1);

function secureHeadersSkill(): string
{
    return (string) file_get_contents(__DIR__.'/../../../resources/boost/skills/juicebox-secure-headers/SKILL.md');
}

it('documents every config key in the Boost skill', function (): void {
    $keys = array_keys(config()->array('juicebox.secure_headers'));

    foreach ($keys as $key) {
        expect(secureHeadersSkill())->toContain("`$key`");
    }
});

it('has Boost skill front matter matching its folder', function (): void {
    expect(secureHeadersSkill())->toStartWith("---\nname: juicebox-secure-headers\ndescription: ");
});
