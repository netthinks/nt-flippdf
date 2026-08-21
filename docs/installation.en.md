# Installation

```bash
composer require netthinks/nt-flippdf
vendor/bin/typo3 extension:setup
```

Without Composer: download the extension from the TYPO3 Extension Repository and
activate it in the extension manager.

## What has to be on the server

| Program | For what | Required |
|---|---|---|
| `gs` (Ghostscript) | renders the PDF pages into images | yes |
| `magick` or `convert` (ImageMagick) | thumbnails, watermark, splitting pages | yes |
| `pdfinfo` (poppler) | counts pages quickly | no |
| `pdftotext` (poppler) | text for the full-text search | no, but recommended |

Both required programs have to be callable **for the web server user**, not only
in your own shell. The backend module under *File → Page-flip editions* lists
what it found and what is missing.

## Where the editions end up

By default in `public/blaetterbar`, reachable under `/blaetterbar/`. Both can be
changed in the extension configuration — `basePath` and `baseUrl`. The directory
is created on the first build.

!!! tip "Keep the storage out of the deployment"
    An edition is data, not code. If your deployment wipes `public/` on every
    release, put the storage somewhere that survives, or exclude the directory.

## Search engines

While building, an `.htaccess` carrying `X-Robots-Tag: noindex, nofollow` is
written into the storage directory, and every start page gets a `noindex` of its
own. The page that embeds an edition is what should be found — not the edition
itself, which would compete with it for the same words.

On nginx there is no `.htaccess`; add the header to the server configuration for
that directory.
