# Changelog

Version numbers follow [Semantic Versioning](https://semver.org/).
The German translation of every entry follows in
**[CHANGELOG.de.md](CHANGELOG.de.md)**.

## [1.9.0] – 2026-08-22

### Added

- **A click in the middle of a page opens the zoom.** That is the reflex most
  readers have, and it was the one thing the viewer did not answer. Clicks near
  the outer edges keep flipping — that is where one reaches to turn a page.
- **Swiping works on a tablet.** The flip library only counts a swipe if it is
  over within 250 milliseconds; a comfortable swipe on an iPad takes longer, and
  the page simply stayed put. Touches are now taken up by the viewer itself: a
  swipe flips, a tap in the middle zooms, a tap near an edge flips. Vertical
  swipes are left alone so the page underneath keeps scrolling.
- **Two fingers open the zoom** and scale it, on the page as well as inside the
  zoom.

---

## [1.8.5] – 2026-08-21

### Fixed

- **The zoom no longer jumps on the first notch.** It began at a level where the
  page fills the whole width — coming from a book that takes up a third of it,
  that is a leap, and the corner of the page was cut off. The wheel now opens
  the zoom at exactly the size the book has on the stage and grows from there,
  each notch about a tenth larger than the one before. Proportional steps
  instead of fixed ones, so it feels the same close up and far out. The page is
  centred while it is narrower than the surface.

---

## [1.8.4] – 2026-08-21

### Added

- **TYPO3 14 support.** Tested through on 14.3: building on the command line and
  in the module, the viewer, the content element, the preview in the page
  module, the backend module in both languages. Three places had to give way —
  `StandaloneView` is gone in 14, the page module hands over a record object
  instead of an array, and the FlexForm of an element arrives already resolved.
  All three are handled for 12.4, 13.4 and 14 alike.

### Changed

- **The zoom grows step by step.** One notch of the wheel used to jump to 140 %.
  It now starts where the book left off and grows with every further notch; how
  far one notch takes is derived from what the device reports, so a mouse wheel
  and a trackpad both move at the same calm pace.

---

## [1.8.3] – 2026-08-21

### Fixed

- **Zooming out again.** Inside the zoom the wheel still asked for the Ctrl key
  – it had been the only way in there before. Whoever opened the zoom with a
  plain wheel turn could get closer but not back out. The wheel now works in
  both directions inside the zoom, without the key; turning back past the
  smallest step closes it and the book is there again.

---

## [1.8.2] – 2026-08-21

### Changed

- **The mouse wheel alone zooms where there is nothing to scroll.** Standing on
  its own – in its own window or in full screen – the viewer takes a plain
  wheel turn as "zoom in". Embedded on a page it does not: there the wheel
  scrolls the page, and a viewer that swallows it holds the visitor captive –
  the same trap that makes embedded maps ask for the Ctrl key. Whoever tries it
  without the key gets a short note saying so, and the page keeps scrolling.

---

## [1.8.1] – 2026-08-21

### Added

- **Ctrl and the mouse wheel over the book open the zoom.** That gesture is the
  first reflex for many readers, and without us the browser zooms the whole
  page instead – embedded on a landing page that means header and footer grow
  while the book stays as it is. The gesture now opens the viewer's own zoom;
  turning further scales inside it. Two fingers on a trackpad arrive as the
  same event and are covered as well. Zooming out keeps working as before.

---

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
