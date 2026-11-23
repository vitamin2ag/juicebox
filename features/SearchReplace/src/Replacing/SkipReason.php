<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Replacing;

/**
 * Why the command left an item of a batch as it is, as a code the control panel translates.
 */
enum SkipReason: string
{
    /**
     * The entry, term or global set was deleted, or its site removed.
     */
    case Missing = 'missing';

    /**
     * The user of the batch, or the user who asked for its undo, may no longer edit the item.
     */
    case NotPermitted = 'not_permitted';

    /**
     * The entry has unpublished changes, which would hide the replacement until they are published.
     */
    case WorkingCopy = 'working_copy';

    /**
     * A field of the item no longer holds the value the batch was made from.
     */
    case Changed = 'changed';

    /**
     * Saving the item threw an error, which is reported to the log.
     */
    case Failed = 'failed';
}
