# CryptoCon Brella

WordPress plugin that syncs [Brella](https://www.brella.io/) event schedule data via the Integration API, caches it in `wp_options`, and exposes it to [Bricks Builder](https://bricksbuilder.io/) through a custom query loop and dynamic tags.

## Requirements

- WordPress 6.x+
- PHP 7.4+
- Bricks theme (for query loop and dynamic tags)
- Brella Integration API access (API key, Organization ID, Event ID)

## Installation

1. Copy `cryptocon-brella` into `wp-content/plugins/`.
2. Activate **CryptoCon Brella** in the WordPress admin.
3. Go to **Settings → Brella Schedule** and enter your credentials.
4. Click **Test connection**, then **Refresh now**.

## Bricks setup

See [docs/bricks-brella.md](docs/bricks-brella.md).

1. Add a Query Loop to your Agenda page.
2. Set query type to **Brella Schedule**.
3. Map elements to `{brella_*}` dynamic tags (e.g. `{brella_session_title}`, `{brella_session_time_range}`).

## License

Proprietary — CryptoCon / Chunky Duck.
