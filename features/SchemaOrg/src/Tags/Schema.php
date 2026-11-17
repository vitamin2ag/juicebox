<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tags;

use Spatie\SchemaOrg\Graph;
use Statamic\Facades\Blink;
use Statamic\Tags\Tags;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\GraphBuilder;

class Schema extends Tags
{
    public function index(): string
    {
        if (! config('juicebox.schema_org.enabled')) {
            return '';
        }

        $graph = Blink::once('juicebox-schema-graph', fn (): ?Graph => resolve(GraphBuilder::class)->build());

        if (! $graph instanceof Graph) {
            return '';
        }

        $nonce = $this->params->get('nonce');

        if (is_string($nonce)) {
            $graph->setNonce($nonce);
        }

        return $graph->toScript();
    }
}
