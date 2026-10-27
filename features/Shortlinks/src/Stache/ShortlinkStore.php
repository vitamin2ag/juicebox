<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Stache;

use Override;
use Statamic\Stache\Stores\BasicStore;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;

class ShortlinkStore extends BasicStore
{
    #[Override]
    public function key(): string
    {
        return 'shortlinks';
    }

    /**
     * The slug comes from the file name, and an empty or broken file becomes a disabled
     * shortlink without a destination.
     *
     * @param  string  $path
     * @param  string|null  $contents
     */
    #[Override]
    public function makeItemFromFile(mixed $path, mixed $contents): Shortlink
    {
        $data = $this->parse($contents);

        return new Shortlink(
            slug: basename($path, '.yaml'),
            destination: $this->string($data, 'destination'),
            description: $this->string($data, 'description'),
            utm: filter_var($data['utm'] ?? false, FILTER_VALIDATE_BOOL),
            utmSource: $this->string($data, 'utm_source'),
            utmMedium: $this->string($data, 'utm_medium'),
            utmCampaign: $this->string($data, 'utm_campaign'),
            enabled: filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOL),
        );
    }

    /**
     * @return array<mixed>
     */
    private function parse(?string $contents): array
    {
        if ($contents === null) {
            return [];
        }

        try {
            $data = Yaml::parse($contents);
        } catch (ParseException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<mixed>  $data
     */
    private function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
