# PDF fonts

TCPDF (via `tc-lib-pdf-font`) renders text from pre-compiled font definition
files, not raw `.ttf` files. This directory holds:

| File | Purpose |
| --- | --- |
| `arial.ttf` | Source TrueType font (bundled; contains Arabic glyphs). |
| `arial.json` / `arial.z` / `arial.ctg.z` | Generated definition + compressed program + character-to-glyph map. |
| `helvetica.json` / `courier.json` / `times.json` | Copies of `arial.json` so the engine's built-in core-font names resolve to the Arabic font instead of a Latin-only fallback. |

`config/pdf.php` points `K_PATH_FONTS` here (see `AppServiceProvider::register()`).

## Regenerating

After replacing `arial.ttf` (or adding another `<family>.ttf`):

```
php artisan pdf:import-fonts
```

Commit the regenerated `.json` / `.z` / `.ctg.z` files.
