# Fonts

Self-hosted WOFF2 so the prototype opens from `file://` with no network and no CDN.

| Family | Role | Weights | Licence |
| --- | --- | --- | --- |
| Geist | UI, body, tables | 400, 500, 600 | SIL Open Font License 1.1 |
| Geist Mono | batch codes, figures | 400, 500 | SIL Open Font License 1.1 |
| Instrument Serif | editorial display | 400 | SIL Open Font License 1.1 |

Instrument Serif ships a single weight and is drawn for display sizes — set it
large and tracked in slightly, never bolded, or synthetic bold wrecks the thin
strokes.

Two subsets per weight. `latin-ext` is required, not optional — the naira sign
(U+20A6) that the whole product is priced in sits in its `U+20A0-20AB` block.

The matching `@font-face` rules live at the top of `assets/css/base.css`.
Re-download with `python tools/fetch-fonts.py`.
