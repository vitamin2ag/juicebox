<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http;

use Statamic\Fieldtypes\Entries;

/**
 * The form a destination entry opens in a stack with: the one Statamic's entries fieldtype opens its entries with.
 * It reads only the form getters of the fieldtype, since preloading it needs a collection to exist.
 * It lives outside `src/Fieldtypes`, so it is not registered as a fieldtype.
 */
class EntryForm extends Entries
{
    /**
     * @return array{formComponent: string, formComponentProps: array<string, string>, formStackSize: string|null}
     */
    public function toArray(): array
    {
        return [
            'formComponent' => $this->getFormComponent(),
            'formComponentProps' => $this->getFormComponentProps(),
            'formStackSize' => $this->getFormStackSize(),
        ];
    }
}
