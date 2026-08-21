# Putting an edition on a page

The content element **Blätterbare Ausgabe** (page-flip edition) brings an
edition onto any page — embedded in the text flow or as a button that opens it
in a window of its own.

It needs no column of its own in `tt_content`: its settings live in the FlexForm
field that is there anyway. In a grown installation that matters — `tt_content`
runs into the row size limit of InnoDB at some point, and every extra column
brings that day closer.

## The settings

| Setting | What it does |
|---|---|
| **Edition** | which of the built editions is shown |
| **Appearance** | embedded on the page, or a button opening its own window |
| **Height** | of the embedded viewer, 300 to 2000 pixels |
| **Button label** | the text on the button |
| **Preview only** | shows only the first pages and offers no download |
| **Pages in the preview** | at most as many as the edition allows |
| **Button labels** | text, icons only, or both — for this element |
| **Hide here** | switches single controls off for this element only |

The page module shows a preview of the element with the cover of the edition,
its extent, language, build date, size and the settings of this element.

## Preview: what it does and what it does not

With *preview only* the viewer loads a shortened description of the edition and
shows the first pages, without download. The remaining page images carry a
random string in their names, so they cannot be reached by counting up the
address.

!!! warning "Not a protection for gated content"
    The complete description file stays next to it in the same directory, and it
    lists every page. Whoever knows the address of the edition has the whole
    document. For a whitepaper that is only handed out after a registration, the
    preview has to be an **edition of its own** — that is what the add-on
    package builds.

## Several elements, one edition

The same edition can appear on several pages, each with its own settings: one
page shows it embedded and complete, another as a button, a third as a preview
with three pages. The settings travel in the address of the viewer; the built
edition stays untouched.
