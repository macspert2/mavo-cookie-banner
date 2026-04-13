# Bug Fix: Blank Page (ob_start callback returns null)

**File:** `includes/class-mavo-cookie-consent.php`  
**Method:** `force_script_defer()`  

---

## Root Cause

`preg_replace_callback()` returns `null` on PCRE failure (e.g. backtrack limit exceeded on a large page). When an `ob_start` callback returns null, PHP discards the entire output buffer — blank page. This only affects logged-out visitors (the suppression class short-circuits for anyone with the `mavo_cookie_consent` cookie), so Cloudflare caches the blank response and serves it to all subsequent anonymous visitors until the cache is purged.

---

## What to Change

Replace the `ob_start` callback body so that a null result from `preg_replace_callback` falls back to the original HTML instead of being returned as-is.

### Current code

```php
public function force_script_defer(): void {
    ob_start( function( $html ) {
        return preg_replace_callback(
            '/(<script\b[^>]*\bid=["\']mavo-cookie-consent-js["\'][^>]*?)(\s*>)/i',
            function( $m ) {
                if ( stripos( $m[1], 'defer' ) !== false ) {
                    return $m[0];
                }
                return $m[1] . ' defer' . $m[2];
            },
            $html
        );
    } );
}
```

### Fixed code

```php
public function force_script_defer(): void {
    ob_start( function( $html ) {
        if ( ! is_string( $html ) || $html === '' ) {
            return $html;
        }
        $result = preg_replace_callback(
            '/(<script\b[^>]*\bid=["\']mavo-cookie-consent-js["\'][^>]*?)(\s*>)/i',
            function( $m ) {
                if ( stripos( $m[1], 'defer' ) !== false ) {
                    return $m[0];
                }
                return $m[1] . ' defer' . $m[2];
            },
            $html
        );
        return $result !== null ? $result : $html;
    } );
}
```

---

## Why This Is Safe

- The regex and inner callback are unchanged — behaviour is identical in the normal case.
- If `preg_replace_callback` returns `null`, we return the original `$html`. The page renders without the `defer` attribute on that one script tag — completely harmless. The script still loads; it just won't be deferred.
- The `is_string` guard handles the theoretical case where the ob callback receives a non-string.

---

## Optional: Add Error Logging

To know when PCRE actually fails, replace the final return line with:

```php
if ( $result === null ) {
    error_log( 'mavo-cookie-consent: preg_replace_callback failed in force_script_defer (PCRE error ' . preg_last_error() . ')' );
}
return $result !== null ? $result : $html;
```

---

## After Deploying

1. **Purge the Cloudflare cache** for the entire site immediately. Any blank pages already cached will continue to be served until purged.
2. Monitor the site for a few days. If blank pages were coming from this cause, they should stop entirely.

---

## Long-Term: Consider Removing force_script_defer

This method exists because Autoptimize strips the `defer` attribute from excluded scripts. Worth checking whether the current Autoptimize version and config still exhibit this behaviour. If `wp_script_add_data('mavo-cookie-consent', 'defer', true)` now survives Autoptimize's processing, `force_script_defer` can be removed entirely — eliminating the output buffer and the risk altogether.

To test: temporarily disable the method and inspect the rendered page source to confirm `defer` is still present on the `mavo-cookie-consent-js` script tag.
