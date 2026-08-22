# Page-flip editions from PDF files

`nt_flippdf` turns a PDF into a **page-flip edition inside a self-contained
directory**: page images, thumbnails, a full-text index, a viewer and a file to
download. Once built, an edition is nothing but static files. It keeps working
whatever happens to the website around it – after a relaunch, after a TYPO3
upgrade, even with the extension uninstalled.

## Requirements

* TYPO3 12.4, 13.4 or 14.x, PHP 8.2 or newer
* **Ghostscript** (`gs`) renders the pages
* **ImageMagick** (`magick` or `convert`) makes the thumbnails
* `pdfinfo`, `pdftotext` from the poppler package are optional; they speed up
  counting pages and make the full-text search better

The backend module checks for these and says what is missing.

## Installation

```bash
composer require netthinks/nt-flippdf
vendor/bin/typo3 extension:setup
```

Where editions are written and under which address they are reachable is set in
the extension configuration – by default `public/blaetterbar` and
`/blaetterbar/`.

## Building an edition

### In the backend

![The backend module](Images/modul-uebersicht.webp)

**File → Page-flip editions.** Pick a PDF, give it a slug, choose a language,
press *Build edition*. Rendering takes from half a minute to several minutes,
and a progress bar shows what is happening. The list then holds the edition with
page count, size, build date and the actions: view, edit, table of contents,
refresh the viewer, rebuild, delete.

Which folders offer their PDF files is limited by `pdfFolders` in the extension
configuration. Without it, everything in the file storage is offered – in a
grown installation that includes job applications from the user upload.

### On the command line

```bash
vendor/bin/typo3 flippdf:build <path/to.pdf> <slug> \
    --titel "Title above the viewer" \
    --sprache de \
    [--ohne-download] \
    [--download https://example.org/file.pdf] \
    [--farbe-leiste "#1f2933"] [--farbe-akzent "#ec6602"]
```

After a change to the viewer, existing editions catch up without re-rendering
every page:

```bash
vendor/bin/typo3 flippdf:refresh [slug]
```

![Editing an edition in the backend module](Images/modul-bearbeiten.webp)

## Putting an edition on a page

The content element **Blätterbare Ausgabe** (page-flip edition) embeds it or
offers a button that opens it in a window of its own. It needs no column of its
own in `tt_content`; its settings live in the existing FlexForm field, and the
page module shows a preview with the cover and the details of the edition.

## What the viewer offers

![The viewer with an edition open](Images/betrachter-doppelseite.webp)

Page flipping with the arrow keys, the buttons, by dragging a corner with the
mouse or by swiping on a touch screen; a page slider with chapter marks;
full-text search; table of contents; page overview; zoom; print; download; full
screen; opening in its own window; a page-turn sound; and a switch between
linked language editions.

**At the first and the last page** the viewer says so briefly when someone
tries to turn further — otherwise nothing at all would happen there.

**Getting closer** takes a click in the middle of a page, the wheel, two fingers
on a touch screen, or the button. Clicks near the outer edges keep flipping —
that is where one reaches to turn a page.

**Every one of these can be switched off** – as a default in the extension
configuration, per edition in the backend module, and per content element. The
header buttons come in three flavours: spelled out, icons only, or both.

The viewer needs no framework at runtime; everything it needs lies in the
edition's directory.

## Anatomy of an edition

```
<slug>/index.html      the viewer
<slug>/book.json       everything the viewer knows about the edition
<slug>/search.json     the text of each page
<slug>/pages/…         the page images
<slug>/thumbs/…        the thumbnails
<slug>/assets/…        viewer files, copied in
<slug>/<name>.pdf      the file offered for download
```

Nothing here points back into TYPO3. Copy the directory somewhere else, and it
runs there.

## Settings

The extension configuration holds the defaults: storage path and address, page
width and JPEG quality, thumbnail width, whether a smaller download version is
written and at what resolution, protection from search engines, which folders
offer PDF files, flipping behaviour (duration, shadows, single cover page,
dragging), zoom, link extraction, and which controls the viewer offers.

Search engines are kept out by an `.htaccess` carrying
`X-Robots-Tag: noindex, nofollow`, plus a `noindex` in every start page: the
page embedding the edition is what should be found, not the edition itself.

## Extension points

The build dispatches four events, the backend module three:

| Event | When | What it is for |
|---|---|---|
| `BeforeBuildEvent` | before the run | change the settings, even the slug |
| `AfterPagesRenderedEvent` | pages rendered, nothing derived yet | change the page images or their number |
| `BeforeBookWrittenEvent` | before `book.json` is written | add anything the viewer should know |
| `AfterBuildEvent` | edition is in place | put something beside it |
| `ModuleFormEvent` | module renders a form | add form sections |
| `ModuleSaveEvent` | module builds or saves | add settings |
| `ModuleColumnsEvent` | module lists editions | add columns |

A few building blocks of the builder are public for that purpose:
`writeViewer()`, `swapDirectory()`, `clearDir()`, `directoryBytes()` and
`sanitizeSlug()`.

## The add-on package

**[nt_flippdf_pro](https://www.netthinks.com)** hangs itself into those points
and adds what a gated whitepaper needs: a preview edition beside the full one,
an unguessable slug for the full edition, splitting double pages, watermark,
logo and background images, French and Chinese labels, linked language editions,
and counting of views and downloads. None of it is needed here – this package is
complete on its own. Details and licence: [netthinks.com](https://www.netthinks.com).

**In use:** [ystral.com](https://ystral.com/wissen/wissensportal/whitepaper/die-vielseitige-welt-der-pulverdispergierung/)
shows a preview of the first pages on the landing page and hands out the full
edition after registration.

## When something does not work

**The viewer stays empty.** Open the browser console; the viewer writes the
reason there. Most often `book.json` cannot be read because the edition was
built into a different directory than the one the address points at.

**Pages look blurred.** Raise `pageWidth`; 1240 pixels suit an A4 page on a
normal screen, 1600 on a large one. The edition grows accordingly.

**Building aborts.** The backend module lists the tools it found. Ghostscript
and ImageMagick have to be callable for the web server user, not only in your
shell.

**An edition is unreachable for a moment during a rebuild.** It should not be:
building happens in a directory beside the old one, and only the last step
swaps them. If it does happen, the storage directory is likely on a file system
where `rename` is not atomic.
