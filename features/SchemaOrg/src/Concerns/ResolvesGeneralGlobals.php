<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Concerns;

use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;

trait ResolvesGeneralGlobals
{
    /**
     * Not blinked here: `GlobalSet::localizations()` already does, keyed by
     * the set's own id, with identity preserved — see
     * `Statamic\Globals\GlobalSet::in()`.
     */
    protected function generalGlobals(): ?Variables
    {
        $variables = GlobalSet::findByHandle('general')?->inCurrentSite();

        return $variables instanceof Variables ? $variables : null;
    }

    /**
     * Short class name of the site's configured entity schema (e.g.
     * `Organization`, `Person`), as stored on `json_ld_entity`.
     */
    protected function entityType(): ?string
    {
        $name = $this->generalGlobals()?->json_ld_entity?->value();

        return is_string($name) && $name !== '' ? $name : null;
    }
}
