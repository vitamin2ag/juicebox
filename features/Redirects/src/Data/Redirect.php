<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Data;

use Illuminate\Support\Facades\File;
use Statamic\Data\ExistsAsFile;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;

class Redirect
{
    use ExistsAsFile;

    public function __construct(
        private readonly string $id,
        private readonly string $site,
        private readonly ?string $source = null,
        private readonly ?string $destination = null,
        private readonly int $type = 301,
        private readonly string $matchType = 'exact',
        private readonly bool $enabled = true,
        private readonly ?string $description = null,
        private readonly ?int $order = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function site(): string
    {
        return $this->site;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function source(): ?string
    {
        return $this->source;
    }

    public function destination(): ?string
    {
        return $this->destination;
    }

    public function type(): int
    {
        return $this->type;
    }

    public function matchType(): string
    {
        return $this->matchType;
    }

    public function order(): ?int
    {
        return $this->order;
    }

    public function withDestination(?string $destination): self
    {
        return clone ($this, ['destination' => $destination]);
    }

    public function withEnabled(bool $enabled): self
    {
        return clone ($this, ['enabled' => $enabled]);
    }

    public function path(): string
    {
        return resolve(RedirectStore::class)->directory()."{$this->site}/{$this->id}.yaml";
    }

    /**
     * rias puts the order in front of the id in the file name, so a file it wrote is not at path().
     */
    public function deleteFile(): void
    {
        $file = $this->initialPath();

        File::delete(is_string($file) ? $file : $this->path());
    }

    /**
     * @return array<string, mixed>
     */
    public function fileData(): array
    {
        return [
            'id' => $this->id,
            'enabled' => $this->enabled,
            'source' => $this->source,
            'destination' => $this->destination,
            'type' => $this->type,
            'match_type' => $this->matchType,
            'description' => $this->description,
            'order' => $this->order,
        ];
    }
}
