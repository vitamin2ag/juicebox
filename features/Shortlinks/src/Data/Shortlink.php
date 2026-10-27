<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Data;

use Statamic\Data\ExistsAsFile;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkStore;

class Shortlink
{
    use ExistsAsFile;

    public function __construct(
        private readonly string $slug,
        private readonly ?string $destination = null,
        private readonly ?string $description = null,
        private readonly bool $utm = false,
        private readonly ?string $utmSource = null,
        private readonly ?string $utmMedium = null,
        private readonly ?string $utmCampaign = null,
        private readonly bool $enabled = true,
    ) {}

    /**
     * The Stache keys its items by id; the slug never changes, so it is the id.
     */
    public function id(): string
    {
        return $this->slug;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function destination(): ?string
    {
        return $this->destination;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function utm(): bool
    {
        return $this->utm;
    }

    public function utmSource(): ?string
    {
        return $this->utmSource;
    }

    public function utmMedium(): ?string
    {
        return $this->utmMedium;
    }

    public function utmCampaign(): ?string
    {
        return $this->utmCampaign;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function withValues(?string $destination, ?string $description, bool $utm, ?string $utmSource, ?string $utmMedium, ?string $utmCampaign, bool $enabled): self
    {
        return clone ($this, [
            'destination' => $destination,
            'description' => $description,
            'utm' => $utm,
            'utmSource' => $utmSource,
            'utmMedium' => $utmMedium,
            'utmCampaign' => $utmCampaign,
            'enabled' => $enabled,
        ]);
    }

    public function path(): string
    {
        return resolve(ShortlinkStore::class)->directory()."{$this->slug}.yaml";
    }

    /**
     * The slug is the file name, so the file does not repeat it.
     *
     * @return array<string, mixed>
     */
    public function fileData(): array
    {
        return [
            'destination' => $this->destination,
            'description' => $this->description,
            'utm' => $this->utm,
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
            'enabled' => $this->enabled,
        ];
    }
}
