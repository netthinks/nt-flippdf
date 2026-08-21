# Configuration

The defaults live in the extension configuration under *Settings → Extension configuration → nt_flippdf*. They apply to new editions; what an edition makes of them is then kept inside it and can be changed in the backend module.

## Storage and basics

Where the editions live and under which address they are reachable.

| Key | Default | Meaning |
|---|---|---|
| `basePath` | `public/blaetterbar` | Where the finished editions are written |
| `baseUrl` | `/blaetterbar/` | Under which address that directory is reachable |
| `protectFromIndexing` | `1` | Write an .htaccess with X-Robots-Tag: noindex |
| `pdfFolders` | `—` | Which folders offer PDF files in the module (empty = all) |

## Appearance

How the pages are rendered and how the edition looks.

| Key | Default | Meaning |
|---|---|---|
| `pageWidth` | `1240` | Width of the page images in pixels — sharpness and weight |
| `jpegQuality` | `80` | JPEG quality of the page images (1–100) |
| `thumbWidth` | `200` | Width of the thumbnails in the page overview |
| `zoom` | `1` | Offer zoom |
| `zoomMax` | `3` | Highest zoom level |
| `extractLinks` | `1` | Make links from the PDF clickable |

## Download

The version offered for download.

| Key | Default | Meaning |
|---|---|---|
| `buildDownloadVersion` | `1` | Write a smaller version for download |
| `downloadResolution` | `120` | Resolution of that version in dpi |

## Preview

How many pages the content element may show at most.

| Key | Default | Meaning |
|---|---|---|
| `teaserPages` | `5` | How many pages the content element may show in preview mode |

## Page flipping

Movement and behaviour while flipping.

| Key | Default | Meaning |
|---|---|---|
| `flipDuration` | `700` | Duration of one flip in milliseconds |
| `flipShadows` | `1` | Draw shadows while flipping |
| `flipCover` | `1` | Show the cover on its own |
| `flipDrag` | `1` | Allow dragging with the mouse |

## Controls

What the viewer offers. Every default here can be overridden per edition and per content element.

| Key | Default | Meaning |
|---|---|---|
| `uiButtonStyle` | `text` | Header buttons: text, icons only, or both |
| `uiSearch` | `1` | Offer the full-text search |
| `uiToc` | `1` | Offer the table of contents |
| `uiThumbs` | `1` | Offer the page overview |
| `uiDownload` | `1` | Offer the download button |
| `uiZoom` | `1` | Offer the zoom button |
| `uiPrint` | `1` | Offer printing |
| `uiFullscreen` | `1` | Offer full screen |
| `uiLanguages` | `1` | Offer the language switch |
| `uiNav` | `1` | Arrows beside the book |
| `uiSlider` | `1` | Page slider in the footer |
| `uiMarks` | `1` | Chapter marks below the slider |
| `uiIndicator` | `1` | Page number in the header |
| `uiHint` | `1` | Usage hint in the footer |
| `uiExtern` | `1` | Offer "open in its own window" |
| `uiSound` | `1` | Offer the page-turn sound |
| `uiSoundOn` | `1` | Page-turn sound on from the start |

## Which level does what

Three levels, from coarse to fine:

1. **Extension configuration** — the default for everything new.
2. **The edition itself** — in the backend module under *Edit*. What is set here
   applies everywhere this edition is embedded.
3. **The content element** — applies to this one place only.

What a finer level switches off stays off; it cannot switch on what is off
further up.

## Settings of the add-on package

Watermark, background images, logo folders, the extent of the preview edition
and the counting live in the configuration of `nt_flippdf_pro`, not here.
