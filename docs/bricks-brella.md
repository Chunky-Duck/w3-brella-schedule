# Brella schedule — Bricks builder wiring

W3 Brella Integration exposes a **W3 Brella Schedule** query type and `{brella_*}` dynamic tags for the agenda page.

## Prerequisites

1. Activate **W3 Brella Integration** plugin.
2. Enter API key, Organization ID, and Event ID under **Settings → W3 Brella Integration**.
3. Click **Test connection**, then **Refresh now** to populate the cache.

## Agenda query loop

1. Open the Agenda page (or Bricks template) in the builder.
2. Add a **Query Loop** (or use an existing loop container).
3. In the query settings, set **Query type** to **W3 Brella Schedule**.
4. Build the loop item layout with text/heading elements.

## Dynamic tags (inside the loop)

Group: **Brella · Schedule**

| Tag | Use for |
|-----|---------|
| `{brella_session_title}` | Session title |
| `{brella_session_subtitle}` | Subtitle / description line |
| `{brella_session_time_range}` | e.g. `4:20 PM – 5:20 PM` |
| `{brella_session_day_label}` | e.g. `Monday 10 Apr` |
| `{brella_session_location}` | Room / stage |
| `{brella_session_tracks}` | Comma-separated track/tag names |
| `{brella_session_speakers}` | Comma-separated speaker names |
| `{brella_session_speakers_list}` | HTML `<ul>` with optional roles |

Outside the loop (page-level):

| Tag | Use for |
|-----|---------|
| `{brella_schedule_last_sync}` | When cache was last refreshed |
| `{brella_schedule_count}` | Number of cached sessions |

## Section visibility (Event Control Panel)

Use existing ECP conditions to show/hide the agenda block:

- **Agenda state** = `live` (or `teaser` for placeholder)
- `{cc_agenda_page_url}` for nav links

## Filtering behaviour

By default, empty-title **networking** timeslots are excluded. Toggle under **Settings → W3 Brella Integration → Exclude networking slots**.

## Cache

- Schedule data is stored in `wp_options` (no custom post types).
- Cache refreshes automatically when expired (default 30 minutes).
- Use **Refresh now** on the settings page after Brella schedule changes.

## Example loop structure

```
Query Loop (W3 Brella Schedule)
├── Heading     → {brella_session_time_range}
├── Heading     → {brella_session_title}
├── Text        → {brella_session_subtitle}
├── Text        → {brella_session_location}
└── Text        → {brella_session_speakers}
```

For multi-day agendas, group visually by `{brella_session_day_label}` or add separate loops filtered in a future version.


## Agenda grid (Brella Agenda element)

The **Brella Agenda** element (Bricks, under General) renders this cache as a track by time grid with day tabs, filters, speaker avatars and a details popup. No query loop or extra credentials needed. Full options: [agenda.md](agenda.md).

Since 1.1.0 each cached session also carries:

| Key | Contents |
|-----|----------|
| `track_id`, `track`, `track_color`, `track_position` | Brella track (the agenda column), when the Integration API returns one |
| `tags_detail` | Tags with id, name and colour |
| `color` | Session colour |
| `cover_image` | Session cover image URL |

Existing keys and `{brella_*}` tags are unchanged. Click **Refresh now** after updating so the cache picks up the new keys.
