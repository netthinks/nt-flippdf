# Changelog

Version numbers follow [Semantic Versioning](https://semver.org/).
The German translation of every entry follows in
**[CHANGELOG.de.md](CHANGELOG.de.md)**.

## [1.9.7] – 2026-08-22

### Changed

- **In the zoom the wheel reads, Ctrl and the wheel zoom.** As soon as the page
  is taller than the screen, the wheel scrolls it — the only way down was
  blocked before, because the wheel zoomed there without the key. When the page
  fits entirely, there is nothing to scroll: then the wheel alone zooms as
  before, and one turn back closes the zoom. The hint about the Ctrl key now
  appears in the zoom as well, once per visit.

---

## [1.9.6] – 2026-08-22

### Fixed

- **The zoom buttons wandered off to the left.** Plus, minus and close were
  pinned to the top of the layer but not to its side, so as soon as the view
  grew wider than the screen they slid away with the content. They now stay in
  the top right corner at every level and in every scroll position.

---

## [1.9.5] – 2026-08-22

### Fixed

- **Refreshing an edition threw away what the add-on package contributes.**
  `flippdf:refresh` wrote `book.json` before asking the extension points, not
  after — the build does it the other way round. French and Chinese editions
  therefore fell back to English labels with every refresh. Now the same order
  applies in both places.

---

## [1.9.4] – 2026-08-22

### Added

- **A word at the first and the last page.** Whoever keeps turning where the
  edition ends used to get nothing at all and could not tell whether this was
  the end or a viewer that had stopped working. A short message now appears at
  the outer edge — through every door: the buttons, the arrow keys, a click on
  the outer margin and a swipe on a touch screen. It shows once and fades on its
  own; turning again does not stack up messages.

---

## [1.9.3] – 2026-08-22

### Fixed

- **Ctrl and the wheel zoomed the whole browser page.** Zooming out that way
  shrank everything, not just the edition — and once the window fell below 900
  pixels, the viewer switched to single pages, so the neighbouring page seemed
  to have gone. Inside the viewer the gesture now belongs to the viewer, in both
  directions and everywhere on the page. (A browser page zoom already set stays
  in place; **Ctrl + 0** puts it back, and the spread returns by itself.)

### Changed

- **The zoom button opens a double page whole**, instead of pushing the second
  page out of view. A single page still gets the familiar jump.
- **Beyond full width the view stays centred** on the gutter, and it can be
  pushed around with the mouse — the wheel zooms here, so dragging does the
  moving.
- **The + and – buttons work proportionally**, like the wheel. A fixed amount
  was a leap on a double page, which is fully visible at half a level already.

---

## [1.9.2] – 2026-08-22

### Fixed

- **A click or tap on a double page made it smaller instead of larger.** The
  starting size was read from the first sheet the flip library holds, and on a
  double page that one is put aside with no width at all — so the zoom opened at
  its lower limit, about a fifth of the screen. It now measures the sheet
  actually on the stage.
- **A double page in the zoom layer ran off to the right.** The layer now knows
  how many pages it shows and gives them the room they need, so a spread stays
  centred and each page comes out the same size as a single one would.
- **A click or tap now opens at full width** instead of the size the page
  already had on the stage: reading is what the click is for. The wheel and two
  fingers keep starting where the book stands, so their first step stays a step.

---

## [1.9.1] – 2026-08-22

### Fixed

- **On an iPad the whole window slid out of view.** Safari treats two fingers as
  a page zoom of its own; with the viewer doing the same thing at the same time,
  the page ended up zoomed and could be pushed around. Safari's gesture is now
  turned down — the viewer scales the pages instead. Horizontal swipes are
  claimed by the viewer as well, vertical ones stay with the page underneath,
  and nothing can drift sideways any more.

---

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
