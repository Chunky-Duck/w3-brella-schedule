# Brella Agenda element

A track by time agenda grid built into W3 Brella Integration. It reads the schedule this plugin syncs from Brella's Integration API and caches in WordPress, so the browser never talks to Brella.

- X axis: tracks (Brella track order, or your own order)
- Y axis: start time, in the event's own timezone
- Overlapping sessions in the same track sit side by side
- Day tabs for multi-day events (opens on today when the event is live)
- Speaker avatars on each card, using the photo from the speaker's Brella profile (initials when there is no photo)
- Filters on the same row as the day tabs: theatre (track), speaker, tags and session type
- Details popup per session (description, speakers, tags)
- "Live" badge on sessions running now
- Optional break out: above the tablet breakpoint the grid can run to the right edge of the window
- Calendar / List switch in the top bar; below a set width the list view is automatic

## Setup

1. Go to **Settings > W3 Brella Integration**, enter the API key, Organization ID and Event ID, click **Test connection** then **Refresh now**.
2. In Bricks, add the **Brella Agenda** element (under General) to your page. The element's **Brella data** panel shows the connection status and links straight to the settings page.

## Updating from the standalone plugin

If the separate "Brella Agenda for Bricks" plugin is installed, W3 Brella Integration switches it off the next time an admin loads the dashboard, and its element and shortcode take over straight away. Agenda elements already on pages keep their settings and pick up avatars, filters and horizontal scroll automatically; untick them with the **Hide ...** / **Turn off ...** options.

## Data and columns

The agenda uses the same cache as the `{brella_*}` tags and the query loop, so cache length and **Refresh now** are managed on the settings page. Logged in editors can also add `?brella_refresh=1` to a page URL to re-sync straight away.

Each column (theatre) comes from, in order of preference:

1. The Brella track, when the Integration API returns one for the session
2. The session location / room
3. The session's first tag

Force one with the element's **Columns** setting (Auto, Brella track, Location, First tag), or `group_by` on the shortcode.

## Shortcode

Everything the element does is also available as a shortcode, handy for a Bricks Shortcode element or a non-Bricks page:

```
[brella_agenda theme="dark" step="5" label_interval="30" tracks="Main Stage|Hall A"]
```

| Attribute | Default | Notes |
| --- | --- | --- |
| group_by | auto | Columns from: auto, track, location or tag |
| tracks | all | Pipe separated names or IDs; also sets column order |
| step | 5 | Minutes per grid row |
| label_interval | 30 | Minutes between time labels |
| time_format | g:i a | PHP date format |
| day_format | D j M | PHP date format for tabs |
| theme | dark | dark or light |
| include_networking | false | Show Brella 1:1 meeting slots |
| hide_empty_tracks | true | Per day |
| show_subtitle / show_location / show_speakers | true | |
| show_avatars | true | Speaker photos from Brella, initials when none (Bricks: *Hide speaker avatars*) |
| max_avatars | 3 | Extra speakers show as "+N" |
| show_filters | true | Filter dropdowns beside the day tabs (Bricks: *Hide all filters*, plus one *Hide ... filter* per dropdown) |
| filters | track,speaker,tag,type | Which dropdowns to show, in order. Session type is the session subtitle in Brella (Keynote, Panel Discussion...) |
| track_label | Theatre | Word used for a track in the filter |
| show_excerpt | false | Description on the card itself |
| details | modal | modal or none |
| mobile | list | list or scroll |
| breakpoint | 768 | Element width in px where list mode kicks in |
| height | auto | Fixed grid height, e.g. `80vh`. The grid scrolls inside it with pinned headers |
| hscroll | true | Off makes tracks shrink to fit instead of scrolling sideways (Bricks: *Turn off horizontal scroll*) |
| default_view | calendar | calendar or list. Visitors can switch with the Calendar / List buttons (remembered in their browser) |
| view_toggle | true | Show the Calendar / List switch (Bricks: *Hide Calendar / List switch*) |
| freeze | both | Keep fixed while scrolling: both (time column and theatre headers), time, headers or none |
| freeze_offset | auto | Gap above the pinned headers in px, e.g. for a sticky site header. Blank detects a sticky Bricks header |
| breakout | false | Grid runs past its container to the right edge of the window |
| breakout_min | 991 | Only break out above this window width (Bricks element uses your tablet breakpoint) |

## Styling

Everything is driven by CSS custom properties on `.brella-agenda`, so ACSS variables drop straight in:

```css
.brella-agenda {
	--ba-slot-h: 1.5rem;          /* height of one step */
	--ba-track-min: 16rem;        /* min column width */
	--ba-max-h: 80vh;             /* scroll inside the grid, headers stay sticky */
	--ba-bg: var(--base-ultra-dark);
	--ba-c-green: var(--primary); /* Brella's "green" track colour */
}
```

Brella track colours arrive as names (magenta, green, blue, cyan, yellow and so on) and map to `--ba-c-{name}`.
