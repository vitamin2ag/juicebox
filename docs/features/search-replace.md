# Search & Replace

Finds a term in the text of the content and replaces it with another one, from the control panel.

**Utilities > Search & Replace** searches entries of every collection and site, taxonomy terms and globals, but only their text fields: `text`, `textarea`, `markdown`, the items of a `list`, the cells of a `table` and the text of `bard`, nested sets, grids and groups included, as well as fields imported from a fieldset. Slugs, links, relations, select values and the like are never searched, nor is the `bard_table` fieldtype of vitamin2/bardtable, whose cells hold Bard content instead of text. Each match is listed with its entry, term or global, its field and the text around it. The selected matches are not replaced in the request: they are saved as a batch, one JSON file on the `local` disk, and an artisan command that Laravel's scheduler runs applies the pending batches, without a queue.

The feature is off by default; switch it on with `JUICEBOX_SEARCH_REPLACE_ENABLED=true`. While it is off, nothing is scheduled, the command stops with an error and the config can still be published.

## Control panel

**Utilities > Search & Replace** (`/cp/utilities/search-replace`) has a form with the term, its replacement and `Match case`. The term is searched as it is typed, spaces at its start or end included. The page lists up to 500 matches by entry, term or global set: its title, linked to its edit page, the site when there is more than one, and for each match a checkbox, its field and the text around it, with the match struck through and the replacement highlighted as it is typed. When nothing matches, the page says that no text field contains the term; when the search finds more than 500, it says so; narrow the search to see the rest.

In Bard, the text nodes that follow each other in a paragraph or heading are searched as one text, so the context of a match in a bold word shows the words around it. A match inside one text node is listed like any other. A match across formatting, such as `Acme Corp` where only `Acme` is bold or a link, cannot be replaced, as its formatting would have to change: it is listed with its item, field and context, highlighted, without a checkbox and with an `Edit by hand` link to the edit page, and it never goes into a batch. Such matches count toward the 500. A hard break or any other node between two text nodes ends the text, so a match across it is not found.

Select the matches, or all of them with `Select All`, and confirm `Replace selected`. The selected matches and the replacement, which may be empty to delete them, are saved as a batch, and the page says that they will be replaced at the next scheduled run. The batch is made from the term the listed matches were found with, so after changing the term or `Match case`, search again before replacing. Before it saves the batch, the search runs again: a selected match that is gone, or whose field changed since the search, is left out, and when none is left, no batch is saved and the page asks to search again.

Below the search, the page lists the latest 10 batches: when and by whom, the search and the replacement, whether the batch is pending or applied, how many matches it replaced, and the items it skipped with the reason (`Deleted`, `Not allowed to edit`, `Has unpublished changes`, `Changed since the search`, `Could not be saved`), linked to their edit page. An item deleted since is shown as missing, such as `Missing entry`. While a batch or its undo is pending, the page reloads the list every 5 seconds, so it shows the result once the command has run.

An applied batch that wrote at least one item can be undone, once, by the user who made it or by a super user; other users do not see its `Undo` button. After confirming `Undo`, the page says that the batch will be undone at the next scheduled run; the undo is not done in the request either. The list then shows the batch as `Undo pending` until the command has done it, then as `Undone`, or `Partly undone` when it skipped items, with the time. The items the undo skipped are listed under `Not undone` with the reason (`Deleted`, `Not allowed to edit`, `Has unpublished changes`, `Changed since the replacement`, `Could not be saved`), apart from those the replacement skipped.

Users need the permission `access search_replace utility`, which Statamic lists with the other utilities in the role editor; super users have it. The search lists, and a batch holds, only the entries, terms and global sets the user may edit.

## Command

Apply the pending batches by hand:

``` bash
php please juicebox:search-replace:apply
```

It applies every pending batch, the oldest first, and saves each one as applied right after it. Then it undoes every batch whose undo was asked for, the oldest request first, and saves each one as undone right after it, so an undo sees what the batches of the same run changed. Last, it deletes the batches it applied or undid more than `keep_days` days ago (see Batches). It prints a summary such as:

``` text
Applied 1 batch, replaced 12 matches, skipped 1 item.
Undid 1 batch, restored 3 items, skipped 0 items.
Deleted 2 old batches.
```

The last line is left out while `keep_days` keeps every batch.

Without a pending batch or undo it changes no content. While the feature is off it prints an error and exits with `1`. The same command runs as `php artisan statamic:juicebox:search-replace:apply`.

## Scheduler

The pending batches are applied by Laravel's scheduler, at the time `schedule` sets (every minute by default). A scheduled run is skipped while the previous one still runs. The scheduler runs only when the server calls it every minute, which Statamic already needs for scheduled entries. Add this cron entry on the server if it is missing:

``` bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Where the scheduler cannot run, call the command from the hosting's cron instead, and set `JUICEBOX_SEARCH_REPLACE_SCHEDULE=false` so a scheduler set up later does not run it as well:

``` bash
* * * * * cd /path/to/project && php please juicebox:search-replace:apply >> /dev/null 2>&1
```

## Batches

Each batch is one JSON file, `juicebox/search-replace/<uuid>.json` on the `local` disk, which is `storage/app/private/juicebox/search-replace/` in a default Laravel app. It holds the search, the replacement, who asked for it and when, and the whole value of every field it changes, before and after the replacement. Once applied, it also holds when, how many matches and items it replaced, the items it skipped, and for every item it wrote the whole value of each top-level field it set, before and after the write. Once its undo is asked for, it holds who asked and when, and once undone, when, how many items were restored and the items skipped. The files stay out of `content/`, so they make no git commits, and out of the cache, which a deploy clears.

A batch is kept for `keep_days` days (30 by default) after the command applied it, or, once undone, after it undid it; then the command deletes it at its next run. A pending batch, or one whose undo is pending, is never deleted. A batch can be undone only while it is kept, so after `keep_days` the replacement stays. Set `JUICEBOX_SEARCH_REPLACE_KEEP_DAYS=false` to keep every batch.

``` json
{
    "id": "<uuid>",
    "user": "<user id>",
    "search": "Team",
    "replacement": "Praxis",
    "match_case": false,
    "created_at": "2026-11-19T10:00:00+00:00",
    "patches": [
        {"type": "entry", "id": "<entry id>", "site": "default", "path": "title", "before": "Our Team", "after": "Our Praxis", "occurrences": 1}
    ],
    "result": {
        "applied_at": "2026-11-19T10:01:00+00:00",
        "replaced": 1,
        "items": 1,
        "skipped": [],
        "written": [
            {"type": "entry", "id": "<entry id>", "site": "default", "fields": [{"handle": "title", "before": "Our Team", "after": "Our Praxis"}]}
        ]
    },
    "undo": {
        "user": "<user id>",
        "requested_at": "2026-11-19T11:00:00+00:00",
        "result": {"undone_at": "2026-11-19T11:01:00+00:00", "restored": 1, "skipped": []}
    }
}
```

`result` is `null` while the batch is pending, `undo` is `null` until its undo is asked for, and the `result` of the undo is `null` while the undo is pending. A skipped item is `{"type", "id", "site", "reason"}`, with the reason `missing`, `not_permitted`, `working_copy`, `changed` or `failed`. The `id` of a term is `<taxonomy>::<slug>`, and that of a global set its handle.

Before an item is written, it is read again. It is left as it is, and listed in the batch with the reason, when:

- it was deleted, or its site removed (`missing`);
- the user who asked for the batch may no longer edit it (`not_permitted`); a batch without a user, or whose user was deleted, is not checked again;
- it is an entry with a working copy, whose unpublished changes would hide the replacement until they are published (`working_copy`);
- one of its fields no longer holds the value the batch was made from, because it was edited after the search (`changed`). Then none of its fields is written.

Every other item gets all its replacements and is saved once, as a save in the control panel is: events, the search index and the static cache see it. Entries and terms record the user who asked for the batch as their last editor, while that user still exists. An entry of a collection with revisions (Statamic Pro with `STATAMIC_REVISIONS_ENABLED` and `revisions: true` on the collection) gets a revision with that user and the same message as the Git commit below, so the replacement shows in its revision history. When the save of an item throws an error, such as a file that cannot be written or a failing listener, the error goes to the log and the item is listed as `failed`; it keeps its values from before, and the other items and the later batches go on, so the batch is still saved as applied. An error that a listener of the saved event, or the revision, throws comes after the file was written, so such a `failed` item can hold the replacement, and the undo does not restore it.

With Statamic's automatic Git commits (Statamic Pro with `STATAMIC_GIT_ENABLED` and `STATAMIC_GIT_AUTOMATIC`), the items of a batch are not committed one by one: once the batch is applied, one commit holds every item it wrote, as the user who asked for the batch, with a message such as `Search & Replace: replaced "Team" with "Praxis"`, in the locale of Statamic's own commit messages (`STATAMIC_GIT_LOCALE`). A batch that wrote no item makes no commit.

A Bard text node that a replacement leaves empty, such as a bold word replaced with nothing, is removed, as Bard does not open a field with an empty text node; its paragraph stays. A list item or table cell it leaves empty stays as an empty item or cell. The item is saved without the validation of the control panel, so a required field can end up empty and a character limit is not checked. In a collection with revisions, the replacement is published at once.

## Undo

A batch can be undone while it is kept (`keep_days`). An undo uses only what the batch recorded when it wrote each item: the whole value of every top-level field it set, before and after. So it also brings back a Bard text node the replacement removed. Each item is read again and left as it is, and listed with the reason, when:

- it was deleted, or its site removed (`missing`);
- the user who asked for the undo may not edit it (`not_permitted`); an undo without a user is not checked;
- it is an entry with a working copy (`working_copy`);
- one of the fields the batch wrote no longer holds what the batch wrote, because it was edited, or changed by a later batch (`changed`). Then none of its fields is restored. A field the batch emptied counts as unchanged while it is still empty, as Statamic leaves empty fields out of the file.

Every other item gets the values of those fields from before the replacement back and is saved once, like a save in the control panel. Entries and terms record the user who asked for the undo as their last editor, while that user still exists. Other fields, and changes made to other items since, stay as they are. An entry of a collection with revisions gets a revision with the user who asked for the undo and a message such as `Search & Replace: undid replacing "Team" with "Praxis"`. An item whose save throws is listed as `failed`, as when a batch is applied. With automatic Git commits, the undo makes one commit as well, as the user who asked for it, with a message such as `Search & Replace: undid replacing "Team" with "Praxis"`.

## Configuration

Publish the config file:

``` bash
php artisan vendor:publish --tag=juicebox-search-replace-config
```

| Key | Default | Accepted values |
|---|---|---|
| `enabled` | `false` | `true`, `false` |
| `schedule` | `'* * * * *'` | a cron expression, or `false` for no scheduled run |
| `keep_days` | `30` | the days a batch is kept after it was applied or undone, or `false` (or `0`) to keep every batch |

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `JUICEBOX_SEARCH_REPLACE_ENABLED` | `false` | Master toggle for the feature. |
| `JUICEBOX_SEARCH_REPLACE_SCHEDULE` | `* * * * *` | When the scheduler applies the pending batches; `false` schedules nothing. |
| `JUICEBOX_SEARCH_REPLACE_KEEP_DAYS` | `30` | How many days a batch is kept after it was applied or undone; `false` keeps every batch. |
