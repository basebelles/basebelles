# Belle Directory — setup

A submission to the sign-up form becomes a `belle` post in **Pending** status. Publishing it is
approval; trashing it is spam. Only published Belles appear in the directory block. Nobody gets a
login.

## 1. Build the form

On [/be-a-belle/](https://basebelles.com/be-a-belle/), with these field types:

| Field | WPForms type | Label must contain |
|---|---|---|
| Name | Name | `name` |
| Email | Email | `email` |
| Location | Single Line Text | `location`, `city`, `hometown`, or `where from` |
| Favorite current player | **Dropdown** | `current` |
| Favorite historical player | Single Line Text | `historical`, `all-time`, or `retired` |
| Consent | GDPR Checkbox (required) | `gdpr`, `consent`, `agree`, or `permission` |

Fields are matched on their labels, so you can reword them in the builder without touching code —
just keep the keyword above somewhere in the label. Casing, spaces and punctuation are ignored.
To pin exact field IDs instead, return a `key => field_id` map from the
`basebelles_belles_field_map` filter.

A single-select **Dropdown** is the right field for the current player — it stays compact with a
26-man roster in it. Checkboxes or Multiple Choice work too; intake takes the first selection if a
field somehow returns more than one.

Worth adding while you are in there: WPForms' anti-spam token, and Akismet if you have it.

## 2. Connect it

**Belles → Form Settings**

1. Pick the sign-up form, **Save Settings**.
2. Reload the screen, then pick the current-player field (the picker reads fields from the saved
   form, which is why it takes two passes).
3. **Save and Sync Roster Now.**

That writes the active Guardians roster into the field's choices. A daily cron job repeats it. If a
sync fails, the error shows on this screen and on the Belles list.

The sync rewrites the *whole* form — that is how WPForms' API works — so don't leave the form open
in the builder while a sync lands, or the builder's next save will clobber the fresh roster.

## 3. Make the page

Create a page at `/belles/` and insert the **Belle Directory** block (Base\*Belles category). No
settings; it lists every published Belle alphabetically as a card.

## 4. Moderating

**Belles → All Belles.** New submissions sit in Pending.

- **Publish** = approved, appears on `/belles/`
- **Trash** = spam
- The list shows avatar, location and favorites, so most calls can be made without opening anything
- A repeat email address gets a "possible duplicate" warning on the edit screen rather than being
  dropped — usually it is someone fixing a typo. Correct the address and the warning clears.

## Avatars

Gravatar coverage is low outside dev circles, so every card falls back to initials on a
team-colored circle. The Gravatar lookup happens once, on a cron run about ten seconds after
approval — a newly approved Belle may show a monogram until then, and will fix itself.

## Data notes

Everything is protected post meta (`_bb_belle_*`), and the post type has `show_in_rest` off, so
stored email addresses are not exposed through the REST API.

The raw address is never printed. What does reach the page is a SHA-256 hash of it inside the
Gravatar URL, and only for Belles who have a Gravatar. That hash is not anonymization — anyone who
guesses an address can confirm it by hashing — it is the same exposure WordPress core accepts for
commenter avatars.

`_bb_belle_user_id` is stored as `0` on every Belle. It is the hook for linking a Belle to a real
WP user later, if gated content ever happens.

### Consent

The WPForms entry is the primary evidence that consent was given, and `_bb_belle_entry_id` points
at it. Each Belle also carries its own copy — `_bb_belle_consent` (1 or 0) and
`_bb_belle_consented_at` (a GMT timestamp) — so the record survives entries being pruned or entry
storage being switched off later.

The edit screen shows this as text, not a field. A consent record you can edit is not a record; if
one is wrong, the WPForms entry is the thing to go back to.

Three states, deliberately distinct:

- **Given** — box ticked, with the timestamp
- **Withheld** — the form asked and the box was not ticked (only reachable if the field is ever
  made optional)
- **Not recorded** — the form had no consent field at the time, which is not the same as a refusal

Belles approved before the consent field existed will read "Not recorded". Their consent is in
their WPForms entry if the form asked at the time; otherwise it was never collected.

## Filters

| Filter | Purpose |
|---|---|
| `basebelles_belles_field_map` | Pin exact WPForms field IDs instead of matching labels |
| `basebelles_belles_display_limit` | Cap on Belles rendered at once (default 500) |
| `basebelles_belle_created` | Action fired after a submission is stored as pending |

## Known rough edges

- The daily roster cron is not unscheduled on plugin deactivation. Harmless — the hook stops
  existing so nothing fires — but it leaves an orphan cron entry.
- Roster choices only cover the current active roster. Someone whose favorite was traded last week
  keeps the name already stored on their Belle; new submissions won't see that player.
- Admin strings are not internationalized, matching the rest of the plugin.
