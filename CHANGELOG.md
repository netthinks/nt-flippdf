# Changelog

Version numbers follow [Semantic Versioning](https://semver.org/).
The German translation of every entry follows in
**[CHANGELOG.de.md](CHANGELOG.de.md)**.

## [1.8.0] – 2026-08-21

First public release.

A PDF becomes a page-flip edition in a self-contained directory: page images,
thumbnails, full-text search, table of contents, page overview, zoom, print, a
smaller version for download, and a viewer that needs nothing but the files in
that directory.

- **Backend module** under *File → Page-flip editions*: build editions, view
  them, maintain the contents, refresh the viewer, rebuild, delete. It shows
  page count, size and build date, and marks editions whose source file is
  newer.
- **Content element "page-flip edition"** – embedded or as a button. Without a
  column of its own in `tt_content`; the settings live in the FlexForm field,
  and the page module shows a preview with the cover and the details.
- **Commands** `flippdf:build` and `flippdf:refresh`; refreshing brings existing
  editions up to a new viewer without rendering the pages again.
- **Every control can be switched off** – as a default, per edition and per
  content element. The header buttons come spelled out, as icons, or both.
- **Labels in German and English**, following the language of the backend user
  in the module and set per edition in the viewer.
- **Extension points**: four events around a build, three in the backend
  module, and a handful of public building blocks – that is where
  [nt_flippdf_pro](https://www.netthinks.com) hangs itself in.
- Editions are kept out of search engines: an `.htaccess` with
  `X-Robots-Tag: noindex, nofollow` and a `noindex` in every start page.
