# MaVo Cookie Banner — Implementation Plan

Version target: 1.0.0
Purpose: review document — describes exactly what the plugin does so you can spot anything you want implemented differently before coding begins.

---

## What the plugin does

Displays an implicit cookie consent banner on first visit. The banner is dismissed by any click anywhere on the page, or by scrolling 300 px. On dismissal, a consent cookie is written that lasts one year. No page reload is required.

All tracking (GA4, Statcounter, Jetpack Stats) and all third-party cookies set by other plugins are withheld until consent is given. The plugin is designed to produce identical HTML for every visitor so that a page-caching plugin caches one version and serves it correctly to everyone — the distinction between first-time and returning visitors is made entirely in JavaScript by reading the consent cookie client-side.

---

## File structure

```
mavo-cookie-consent/
├── mavo-cookie-consent.php                        # Bootstrap
├── includes/
│   ├── class-mavo-cookie-consent.php              # Core: enqueue, banner
│   ├── class-mavo-cookie-consent-settings.php     # Admin settings page
│   ├── class-mavo-cookie-consent-suppression.php  # Cookie suppression
│   └── class-mavo-cookie-consent-jetpack.php      # Jetpack Stats deferral
├── assets/
│   ├── css/cookie-consent.css                     # Banner styles
│   └── js/cookie-consent.js                       # Banner + tracking logic
└── languages/
    └── mavo-cookie-consent.pot                    # Translation template
```

---

## Bootstrap (`mavo-cookie-consent.php`)

- Defines four constants: `MAVO_CC_VERSION` (`1.2.0`), `MAVO_CC_FILE`, `MAVO_CC_DIR`, `MAVO_CC_URL`
- `require_once`s all four class files in this order: Settings →Suppressiong → Jetpack → Core
- Registers `load_plugin_textdomain` on `init`, skipped when `is_admin()` — so the admin UI stays in English, only front-end strings are translated
- Calls `mavo_cookie_consent_init()` immediately (not on a hook) which instantiates all four singletons

---

## Core class (`class-mavo-cookie-consent.php`)

**Consent cookie:** `mavo_cookie_consent`, value `1`, 1-year expiry, path `/`, `SameSite=Lax`

**On every front-end page load:**
- Enqueues `assets/css/cookie-consent.css` and `assets/js/cookie-consent.js` (footer, no dependencies)
- Passes a `mavoCookieConsent` JS config object via `wp_localize_script` containing:
  - `cookieName` — the consent cookie name
  - `scrollThreshold` — `300` (px)
  - `ga4Id` — GA4 Measurement ID from settings (empty string if not configured)
  - `scProject` — Statcounter Project ID from settings (`0` if not configured)
  - `scSecurity` — Statcounter Security Code from settings (empty string if not configured)
  - `pendingCookies` — array of `{name, value}` objects captured from suppressed Set-Cookie headers (see suppression class); empty array for returning visitors
- Renders the banner HTML in `wp_footer` **only when the consent cookie is absent** (first-time visitors)

**Banner HTML** (rendered in `wp_footer` for first-time visitors only):
```html
<div id="mavo-cookie-banner"
     class="mavo-cookie-banner mavo-cookie-banner--hidden"
     role="region"
     aria-label="[translated: Cookie notice]">
  <p class="mavo-cookie-banner__text">
    [translated: By using this site you accept the use of cookies and anonymous analytics.]
  </p>
  <button type="button" class="mavo-cookie-banner__ok">
    [translated: OK]
  </button>
</div>
```

The banner starts with the `--hidden` modifier class (translated off-screen via CSS). JavaScript removes it on DOMContentLoaded to trigger the slide-up entrance animation. This means the banner is never visible without JS, and returning visitors served a cached page with the banner HTML see it slide up briefly — but the JS reads the consent cookie immediately and simply never removes `--hidden`, so it stays invisible.

> **Note:** Because the page is cached, returning visitors receive HTML that includes the banner `<div>`. The JS prevents it from ever appearing by not calling `init()`'s banner-reveal code when the consent cookie is present.

---

## Admin settings class (`class-mavo-cookie-consent-settings.php`)

Adds a **Settings → Cookie Consent** submenu page. No translations loaded for admin (intentional).

**Stored options (all in `wp_options`):**

| Option key | Type | Description |
|---|---|---|
| `mavo_cc_ga4_id` | string | GA4 Measurement ID, e.g. `G-XXXXXXXXXX`. Validated against `/^G-[A-Z0-9]+$/i`. Stored uppercase. |
| `mavo_cc_statcounter_project` | int | Statcounter Project ID. Sanitized with `absint`. |
| `mavo_cc_statcounter_security` | string | Statcounter Security Code. Sanitized with `sanitize_text_field`. |

**Page layout:** Two sections — "Google Analytics 4" and "Statcounter" — each with their respective fields. Standard WordPress Settings API form, submitted to `options.php`.

**Static helper:** `get_tracking_config()` returns all three values as a typed array; used by the core class during asset enqueueing.

---

## Suppression / cookie suppression class (`class-mavo-cookie-consent-suppression.php`)

**Purpose:** Prevent any third-party cookies from being sent to first-time visitors (those without the consent cookie). Also captures their name/value/attribute values so JavaScript can restore them client-side after consent.

**For returning visitors** (consent cookie present): constructor returns immediately, all cookies are sent normally.

**For first-time visitors:**
1. Hooks `suppress_all_cookies()` on `send_headers` at `PHP_INT_MAX` priority — runs after all plugins have had a chance to call `setcookie()` during `init`
2. `suppress_all_cookies()`:
   - Calls `headers_list()` to read all pending response headers
   - Parses every `Set-Cookie:` header, extracting name, value and attributes (URL-decoded)
   - Skips cookies with the `HttpOnly` flag — JavaScript cannot write these anyway
   - Stores the captured `{name, value, attributes}` sets in `static $pending_cookies`
   - Calls `header_remove('Set-Cookie')` to drop all pending Set-Cookie headers from the response
3. `get_pending_cookies()` (static) returns the captured list; called during `enqueue_assets()` to populate `mavoCookieConsent.pendingCookies`

On consent, the JavaScript re-sets each captured cookie client-side.

> **Note:** `HttpOnly` cookies that were suppressed are permanently lost for the first-time visit. They will be set on the next page load (returning visitor, PHP sends them normally). This is acceptable since `HttpOnly` cookies are typically auth/session cookies that shouldn't be written by JS anyway.

---

## Jetpack Statistics class (`class-mavo-cookie-consent-jetpack.php`)

**Purpose:** Prevent Jetpack Stats from firing immediately and instead defer it to JavaScript so it respects consent and does not affect cache integrity.

**If Jetpack Stats is inactive** (`has_action('wp_footer', 'stats_footer')` returns false): constructor returns immediately, no hooks registered.

**If Jetpack Stats is active:**
1. `remove_action('wp_footer', 'stats_footer', 101)` — prevents Jetpack's automatic output
2. Hooks `capture_and_defer_stats()` on `wp_footer` at priority 102

`capture_and_defer_stats()`:
- Calls `stats_footer()` inside `ob_start()` / `ob_get_clean()` to capture its HTML output without sending it
- If the captured string is empty, returns without output
- Otherwise emits an inline `<script>` that sets `mavoCookieConsent.jetpackStatsMarkup = '...'` (JSON-encoded HTML string, unescaped slashes/unicode)

**Timing:** `cookie-consent.js` is output early in `wp_footer` (WordPress footer scripts); the Jetpack inline script fires at priority 102, after the main JS has run. Since `config` in the JS IIFE is a reference to the `mavoCookieConsent` object, the `jetpackStatsMarkup` property is visible when `loadTracking()` runs on user interaction.

---

## JavaScript (`assets/js/cookie-consent.js`)

Single IIFE, no dependencies, loaded in footer.

**Config:** reads `window.mavoCookieConsent` into a local `config` variable at startup.

**Helpers:**
- `getCookie(name)` — reads a cookie from `document.cookie` by name; returns value string or `null`
- `setCookie(name, value)` — writes a cookie with 1-year expiry, path `/`, `SameSite=Lax`

**`init()` — called on DOMContentLoaded (or immediately if DOM is already ready):**

- If `getCookie(config.cookieName)` returns a value → **returning visitor path**: call `loadTracking()` and return. No banner interaction.
- Otherwise → **first-time visitor path**: find `#mavo-cookie-banner`, remove the `--hidden` class to reveal it, attach `click` listener on `document` and `scroll` listener on `window` (passive)

**`loadTracking()` — fires GA4, Statcounter, Jetpack Stats:**

- **GA4:** if `config.ga4Id` is set, creates an async `<script>` loading `googletagmanager.com/gtag/js?id=...`, then sets up `window.dataLayer`, defines `window.gtag`, and calls `gtag('js', new Date())` and `gtag('config', ga4Id)`
- **Statcounter:** if `config.scProject` and `config.scSecurity` are set, sets `window.sc_project`, `window.sc_invisible = 1`, `window.sc_security`, then creates an async `<script>` loading `statcounter.com/counter/counter.js`
- **Jetpack Stats:** if `config.jetpackStatsMarkup` is set, creates a temporary `<div>`, sets its `innerHTML` to the markup, then iterates all `<script>` elements found within it; for each one creates a new `<script>` element (external: copies `src`/`async`/`defer`; inline: copies `textContent`) and appends it to `<head>`. `innerHTML` does not execute scripts — re-creating them does.

**`dismiss()` — called on click or scroll threshold:**

- Guards against double-invocation with a `dismissed` flag
- Calls `setCookie(config.cookieName, '1')` — writes the consent cookie
- Iterates `config.pendingCookies` and calls `setCookie()` for each — restores suppressed third-party cookies
- Calls `loadTracking()`
- Adds `mavo-cookie-banner--dismissing` class to the banner element (triggers CSS exit animation)
- On `transitionend`, removes the banner from the DOM
- Removes the `click` and `scroll` event listeners

---

## CSS (`assets/css/cookie-consent.css`)

Fixed bar pinned to the bottom of the viewport (`position: fixed; bottom: 0; left: 0; right: 0`), `z-index: 99999`.

- Background: `rgba(30, 30, 30, 0.95)` (near-black, slightly transparent)
- Text colour: `#f5f5f5`
- Padding: `14px 24px`, centred text
- Drop shadow upward: `0 -2px 8px rgba(0,0,0,0.25)`
- Transition on `transform` and `opacity`, `0.35s ease`

**States:**
- `.mavo-cookie-banner` — visible state (`translateY(0)`, `opacity: 1`)
- `.mavo-cookie-banner--hidden` — start state and return-visitor state (`translateY(100%)`, `opacity: 0`, `pointer-events: none`)
- `.mavo-cookie-banner--dismissing` — exit state, same values as `--hidden`; JS adds this on consent to trigger the slide-down animation

**Inner elements:**
- `.mavo-cookie-banner__text` — `font-size: 13px`, inherits font family, no margin
- `.mavo-cookie-banner__ok` — small button, transparent background, `1px solid rgba(245,245,245,0.5)` border, `border-radius: 3px`; hover/focus: `rgba(245,245,245,0.15)` background fill, no outline

---

## Translations

- Text domain: `mavo-cookie-consent`
- Loaded on `init`, front-end only (skipped in admin)
- Translation files in `languages/`, named `mavo-cookie-consent-{locale}.po` / `.mo`
- POT template at `languages/mavo-cookie-consent.pot`

**Translatable strings (front-end only):**

| String | Context |
|---|---|
| `"Cookie notice"` | `aria-label` on the banner `<div>` |
| `"By using this site you accept the use of cookies and anonymous analytics."` | Banner body text |
| `"OK"` | Dismiss button label |

Admin strings (`"Cookie Consent"`, `"Measurement ID"`, etc.) are intentionally not translated — the settings UI is English-only.

---

## Design decisions to consider

The following choices were made during development. These are the most likely candidates for things you may want done differently:

1. **Implied consent trigger** — any click anywhere on the page, or scrolling 300 px. There is no explicit "I agree" interaction required; the banner is informational only.

2. **Consent duration** — 1 year, written by JavaScript. No server-side expiry control.

3. **Cookie attributes** — `path=/; SameSite=Lax`. No `Secure` flag (would require HTTPS enforcement). No `Domain` attribute (defaults to current host).

4. **Suppressed HttpOnly cookies** — permanently lost for the first-time visit session. These will be re-set on the next page load.

5. n/a

6. **Jetpack Stats output buffer approach** — `stats_footer()` is called inside `ob_start()` and its entire HTML output is passed to JS as a string. If Jetpack ever moves `stats_footer` to a class-based callback (instead of a standalone function), `has_action('wp_footer', 'stats_footer')` will return false and Jetpack Stats will not be deferred — it would fire without consent.

7. **No explicit "reject" option** — there is no way for a visitor to actively refuse cookies. The banner only records consent, never refusal.

8. **No cookie category granularity** — all trackers are treated as a single all-or-nothing group. There is no way to accept analytics but reject other cookies.

9. **Admin settings in English only** — `load_plugin_textdomain` is skipped when `is_admin()`. The admin settings UI cannot be translated.

10. **No bot/crawler detection** — the banner is shown and tracking is deferred for all visitors, including search engine crawlers. Crawlers do not send cookies, so they always see the first-time-visitor HTML (banner present, tracking absent).

11. **Scroll threshold** — hardcoded at 300 px in `wp_localize_script`. Not configurable from the admin UI.

12. **Banner position** — fixed to the bottom of the viewport, full width, centred text with an OK button inline to the right of the text.
