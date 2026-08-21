# For developers

## Anatomy of an edition

```
<slug>/index.html      the viewer
<slug>/book.json       everything the viewer knows about the edition
<slug>/search.json     the text of each page
<slug>/pages/…         the page images, named with a random string
<slug>/thumbs/…        the thumbnails
<slug>/assets/…        viewer files, copied in
<slug>/<name>.pdf      the file offered for download
<slug>/teaser.json     the shortened description for preview mode
```

Nothing in there points back into TYPO3. Copy the directory to another server
and it runs there — that is the point of the whole construction.

`book.json` carries title, slug, language, page count, labels, table of
contents, colours, flipping behaviour, the list of controls and the pages
themselves. An add-on package can put more in there; the viewer ignores what it
does not know.

## Extension points

The build dispatches four events, the backend module three. They are the seam
along which `nt_flippdf_pro` is attached — and along which anything else can be.

| Event | When | Good for |
|---|---|---|
| `BeforeBuildEvent` | before the run | change the settings, even the slug |
| `AfterPagesRenderedEvent` | pages rendered, nothing derived yet | change the page images or their number |
| `BeforeBookWrittenEvent` | before `book.json` is written | add anything the viewer should know |
| `AfterBuildEvent` | the edition is in place | put something beside it |
| `ModuleFormEvent` | the module renders a form | add form sections |
| `ModuleSaveEvent` | the module builds or saves | add settings |
| `ModuleColumnsEvent` | the module lists editions | add columns |

`AfterPagesRenderedEvent` carries a list saying which page of the source PDF a
book page came from. Without it, text and links would drift apart the moment the
number of pages changes — which is what happens when double pages are split.

### An example

```php
final class MeinListener
{
    public function __invoke(BeforeBookWrittenEvent $ereignis): void
    {
        $buch = $ereignis->getBuch();
        $buch['theme']['accent'] = '#ec6602';
        $ereignis->setBuch($buch);
    }
}
```

```yaml
services:
  Vendor\Ext\EventListener\MeinListener:
    tags:
      - name: event.listener
        event: Netthinks\NtFlippdf\Event\BeforeBookWrittenEvent
```

### Building blocks

For an add-on that wants to put a second edition beside the first, a few methods
of `FlipbookBuilder` are public: `writeViewer()`, `swapDirectory()`,
`clearDir()`, `directoryBytes()` and `sanitizeSlug()`.

## Counting

The base package does not count. `nt_flippdf_pro` writes an address into
`book.json`, the viewer pings it on a view, a download or a click on a link —
without a cookie and without any identity of the reader, aggregated per day.
