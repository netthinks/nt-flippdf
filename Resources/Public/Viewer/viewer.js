/**
 * Betrachter für die blätterbaren Ausgaben.
 *
 * Liest book.json (Seitenbilder) und search.json (Volltext je Seite) aus dem
 * eigenen Verzeichnis; läuft ohne TYPO3 und ohne externe Dienste.
 */
(function () {
    'use strict';

    var book = null;
    var pageFlip = null;
    var searchIndex = null;

    /**
     * Beschriftungen kommen aus book.json, damit derselbe Betrachter fuer
     * Ausgaben in verschiedenen Sprachen taugt. Die Vorgaben greifen nur,
     * wenn eine aeltere Ausgabe sie noch nicht mitbringt.
     */
    var beschriftung = {
        pageOf: 'Seite %1$s von %2$s',
        failed: 'Die Ausgabe konnte nicht geladen werden.',
        noResults: 'Kein Treffer',
        page: 'Seite'
    };

    function label(key, one, two) {
        var value = beschriftung[key] || '';
        return value.replace('%1$s', one === undefined ? '' : one)
                    .replace('%2$s', two === undefined ? '' : two);
    }

    var el = {
        book: document.getElementById('book'),
        loading: document.getElementById('loading'),
        indicator: document.getElementById('pageIndicator'),
        slider: document.getElementById('pageSlider'),
        prev: document.getElementById('prevPage'),
        next: document.getElementById('nextPage'),
        thumbs: document.getElementById('thumbs'),
        thumbsToggle: document.getElementById('thumbsToggle'),
        toc: document.getElementById('toc'),
        tocToggle: document.getElementById('tocToggle'),
        zoomToggle: document.getElementById('zoomToggle'),
        zoom: document.getElementById('zoom'),
        zoomBilder: document.getElementById('zoomImages'),
        zoomEin: document.getElementById('zoomIn'),
        zoomAus: document.getElementById('zoomOut'),
        zoomZu: document.getElementById('zoomClose'),
        drucken: document.getElementById('printButton'),
        sprachen: document.getElementById('languages'),
        marken: document.getElementById('sliderMarks'),
        hinweis: document.querySelector('.hint'),
        ton: document.getElementById('soundToggle'),
        extern: document.getElementById('externLink'),
        fullscreen: document.getElementById('fullscreenToggle'),
        search: document.getElementById('searchInput'),
        results: document.getElementById('searchResults'),
        download: document.getElementById('downloadLink')
    };

    /**
     * Bedienelemente. Was book.json vorgibt, laesst sich je Einbindung ueber
     * die Adresse veraendern: ?stil=icon|text|both blendet die Beschriftungen
     * um, ?ohne=print,search schaltet einzelne Elemente ab. Damit kann
     * dasselbe gebaute Buch an einer Stelle vollstaendig und an einer anderen
     * abgespeckt stehen, ohne es zweimal zu bauen.
     */
    var BEDIEN_VORGABE = {
        buttonStyle: 'text',
        search: true,
        toc: true,
        thumbs: true,
        download: true,
        zoom: true,
        print: true,
        fullscreen: true,
        extern: true,
        sound: true,
        soundOn: true,
        logo: true,
        languages: true,
        nav: true,
        slider: true,
        marks: true,
        indicator: true,
        hint: true
    };

    var bedienung = null;

    function adressWert(name) {
        var treffer = new RegExp('[?&]' + name + '=([^&]*)').exec(window.location.search);

        return treffer ? decodeURIComponent(treffer[1].replace(/\+/g, ' ')) : null;
    }

    function ermittleBedienung(angaben) {
        var werte = {};
        var name;
        for (name in BEDIEN_VORGABE) {
            if (Object.prototype.hasOwnProperty.call(BEDIEN_VORGABE, name)) {
                werte[name] = BEDIEN_VORGABE[name];
            }
        }
        var ausBuch = (angaben && angaben.ui) || {};
        for (name in ausBuch) {
            if (Object.prototype.hasOwnProperty.call(werte, name)) {
                werte[name] = ausBuch[name];
            }
        }

        var stil = adressWert('stil');
        if (stil === 'icon' || stil === 'text' || stil === 'both') {
            werte.buttonStyle = stil;
        }
        var ohne = adressWert('ohne');
        if (ohne) {
            ohne.split(',').forEach(function (eintrag) {
                var schluessel = eintrag.trim();
                if (schluessel && typeof werte[schluessel] === 'boolean') {
                    werte[schluessel] = false;
                }
            });
        }
        // In der Vorschau gibt es keinen Download - unabhaengig von allem
        // anderen. Das ist ihr Zweck.
        if (vorschau) { werte.download = false; }

        return werte;
    }

    /**
     * Entfernt, was abgeschaltet ist, und setzt die Art der Beschriftung.
     * Entfernt statt versteckt: Was nicht da ist, laesst sich auch nicht mit
     * der Tabulatortaste erreichen.
     */
    function wendeBedienungAn() {
        document.body.setAttribute('data-knopfstil', bedienung.buttonStyle);
        Array.prototype.forEach.call(document.querySelectorAll('[data-ui]'), function (knoten) {
            var name = knoten.getAttribute('data-ui');
            if (bedienung[name] === false) {
                knoten.remove();
                Object.keys(el).forEach(function (schluessel) {
                    if (el[schluessel] === knoten) { el[schluessel] = null; }
                });
            }
        });
        // Bleibt in der Kopfleiste nichts uebrig, faellt auch der Platz dafuer weg.
        var leiste = document.querySelector('.bar-actions');
        if (leiste && leiste.children.length === 0) { leiste.remove(); }
    }

    /**
     * Vorschau: Mit ?teaser=N laedt der Betrachter eine eigene Angabendatei,
     * die nur die ersten Seiten kennt und keinen Download anbietet. Die
     * uebrigen Seitenbilder tauchen darin nicht auf und tragen eine
     * Zufallskennung im Namen - sie sind also auch nicht zu erraten.
     */
    var vorschau = (function () {
        var treffer = /[?&]teaser=(\d*)/.exec(window.location.search);
        if (!treffer) { return 0; }

        return treffer[1] === '' ? -1 : Math.max(1, parseInt(treffer[1], 10));
    })();

    /**
     * Wurde die Ausgabe ohne Vorschau gebaut, gibt es keine teaser.json. Dann
     * wird die vollstaendige Angabendatei genommen und hier im Browser gekuerzt
     * - der Download bleibt in diesem Fall trotzdem aus. Sicher verborgen sind
     * die uebrigen Seiten damit nicht; dafuer muss die Ausgabe mit Vorschau
     * gebaut werden.
     */
    function ladeAngaben() {
        if (!vorschau) {
            return hole('book.json');
        }

        return hole('teaser.json').catch(function () {
            return hole('book.json').then(function (data) {
                data.downloadUrl = '';
                data.teaser = true;

                return data;
            });
        });
    }

    /**
     * Holt eine Angabendatei.
     *
     * Mit Stand der Ausgabe in der Adresse und ohne Zwischenspeicher: Nach
     * einem Neubau lag sonst die alte Datei im Browser - und wer die Seite
     * einmal aufgerufen hatte, als es die Vorschau noch nicht gab, bekam
     * dauerhaft deren 404 serviert.
     */
    function hole(datei) {
        var stand = document.documentElement.getAttribute('data-stand') || '';
        var adresse = datei + (stand ? '?v=' + encodeURIComponent(stand) : '');

        return fetch(adresse, { cache: 'no-cache' }).then(function (r) {
            if (!r.ok) { throw new Error(datei + ': ' + r.status); }

            return r.json();
        });
    }

    ladeAngaben()
        .then(function (data) {
            book = data;
            if (book.labels) {
                Object.keys(book.labels).forEach(function (key) { beschriftung[key] = book.labels[key]; });
                // "Seite" fuer die Trefferliste aus der Seitenanzeige ableiten
                beschriftung.page = (beschriftung.pageOf || '').split(' ')[0] || 'Seite';
            }
            var grenze = vorschau > 0 ? vorschau : book.pages.length;
            if (grenze > 0 && book.pages.length > grenze) {
                book.pages = book.pages.slice(0, grenze);
                book.pageCount = book.pages.length;
                book.toc = (book.toc || []).filter(function (e) { return e.page <= book.pageCount; });
            }
            // Der Regler steht in der Vorlage auf der vollen Seitenzahl; in der
            // Vorschau sind es weniger.
            bedienung = ermittleBedienung(book);
            wendeBedienungAn();
            wendeHintergrundAn();
            // Die endgueltige Grenze setzt reglerAusrichten, sobald das Buch
            // steht und die Ausrichtung feststeht.
            if (el.slider) { el.slider.max = String(book.pageCount); }
            if (!book.downloadUrl && el.download) {
                el.download.remove();
                el.download = null;
            }
            start();
        })
        .catch(melde);

    /**
     * Zeigt und protokolliert, woran es lag.
     *
     * Eine Meldung wie "konnte nicht geladen werden" allein hilft niemandem
     * weiter - weder dem Leser noch dem, der es reparieren soll. Deshalb steht
     * der Grund klein darunter und ausfuehrlich in der Konsole.
     */
    function melde(fehler) {
        var grund = fehler && fehler.message ? fehler.message : String(fehler);
        if (window.console && console.error) {
            console.error('nt_flippdf:', grund, fehler);
        }
        if (!el.loading || !el.loading.parentNode) {
            el.loading = document.createElement('p');
            el.loading.className = 'loading';
            document.querySelector('.stage').appendChild(el.loading);
        }
        el.loading.textContent = label('failed');
        var klein = document.createElement('small');
        klein.className = 'loading-grund';
        klein.textContent = grund;
        el.loading.appendChild(document.createElement('br'));
        el.loading.appendChild(klein);
    }

    /**
     * Größe einer einzelnen Seite.
     *
     * Maßgeblich ist der kleinere der beiden Grenzwerte: die verfügbare Breite
     * (im Querformat geteilt durch zwei, weil zwei Seiten nebeneinander stehen)
     * und die verfügbare Höhe. Nur so passt die Doppelseite immer vollständig
     * ins Fenster, statt unten herauszulaufen.
     */
    function stageSize() {
        var first = book.pages[0] || { w: 1240, h: 1754 };
        var ratio = first.h / first.w;
        var portrait = window.innerWidth < 900;
        // Im Hochformat wird gewischt statt geklickt – dort brauchen die
        // Pfeiltasten keinen Platz am Rand.
        var sideMargin = portrait ? 16 : 96;

        // Höhe aus dem Fenster abzüglich der Leisten statt aus der Bühne:
        // Ein zu großes Buch dehnt die Bühne, und die nächste Messung würde
        // den Fehler übernehmen und weiter aufschaukeln.
        var bars = 0;
        Array.prototype.forEach.call(document.querySelectorAll('.bar'), function (bar) {
            bars += bar.offsetHeight;
        });
        // Der Mindestwert darf nicht zu hoch liegen: Im Querformat eines
        // Telefons bleiben nach den beiden Leisten kaum 250 Pixel uebrig -
        // eine Untergrenze von 280 hatte das Buch in die Fussleiste geschoben.
        var available = {
            width: Math.max(200, window.innerWidth - sideMargin),
            height: Math.max(160, window.innerHeight - bars - 28)
        };
        var byWidth = portrait ? available.width : available.width / 2;
        var byHeight = available.height / ratio;
        var width = Math.floor(Math.min(byWidth, byHeight));

        return { width: width, height: Math.floor(width * ratio), portrait: portrait };
    }

    var lastLayout = null;

    function start() {
        if (typeof St === 'undefined' || !St.PageFlip) {
            throw new Error('page-flip.browser.js wurde nicht geladen');
        }
        buildFlipbook(1);
        el.loading.remove();
        buildThumbs();
        buildToc();
        buildSliderMarks();
        buildLanguages();
        ton.vorbereiten();
        richteExternEin();
        zeigeHinweis();
        bindControls();
        bindZoom();
        syncPage(1);
        applyDeepLink();
        zaehle('view');
    }

    /**
     * Baut den Betrachter in der aktuellen Größe auf.
     *
     * Wird bei deutlichen Größenänderungen erneut aufgerufen: StPageFlip
     * übernimmt über update() zwar die neuen Maße, behält aber die einmal
     * berechnete Darstellung bei – nach dem Verkleinern des Fensters ragte die
     * Seite dadurch rechts heraus.
     */
    function buildFlipbook(startPage) {
        // Erst den alten Container entfernen, dann messen
        ensureBookElement();
        var size = stageSize();
        lastLayout = size;

        // Im Hochformat richtet sich StPageFlip nach der Breite des Containers
        // und ignoriert die übergebene Breite. Deshalb wird der Container selbst
        // auf das Maß gesetzt, das zur verfügbaren Höhe passt – sonst wird die
        // Seite so hoch, dass sie unten aus dem Fenster läuft.
        setBookWidth(size);
        // Für die Sichtprüfung nachvollziehbar machen, womit gerechnet wurde
        el.book.dataset.computed = size.width + 'x' + size.height + (size.portrait ? ' hoch' : ' quer');

        // Umblättern: Vorgaben, die sich je Ausgabe überschreiben lassen.
        // Wer weniger Bewegung möchte, stellt die Dauer klein oder schaltet
        // die Schatten ab; das Betriebssystem kann das ohnehin überstimmen.
        var flip = book.flip || {};
        var ruhig = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        pageFlip = new St.PageFlip(el.book, {
            width: size.width,
            height: size.height,
            // "fixed" statt "stretch": bei "stretch" leitet StPageFlip die Höhe
            // allein aus der Breite ab und läuft aus dem Fenster heraus.
            size: 'fixed',
            drawShadow: flip.shadows !== false,
            maxShadowOpacity: typeof flip.shadowOpacity === 'number' ? flip.shadowOpacity : 0.5,
            // Nicht 0: StPageFlip lehnt jede Dauer bis einschliesslich null mit
            // "Invalid flipping time" ab und bricht den Aufbau ab. Bei
            // gewuenschter Bewegungsarmut also die kleinste erlaubte Dauer -
            // sichtbar ist davon nichts.
            flippingTime: ruhig ? 1 : Math.max(1, typeof flip.duration === 'number' ? flip.duration : 700),
            usePortrait: flip.portrait !== false,
            startPage: 0,
            showCover: flip.cover !== false,
            useMouseEvents: flip.drag !== false,
            mobileScrollSupport: flip.mobileScroll !== false
        });

        pageFlip.loadFromHTML(buildPages());

        pageFlip.on('flip', function (event) { syncPage(event.data + 1); });
        // 'flip' meldet erst das Ende der Bewegung; 'changeState' meldet ihren
        // Beginn - dort gehoert das Geraeusch hin.
        pageFlip.on('changeState', function (event) {
            if (event.data === 'flipping') { ton.spielen(); }
        });
        pageFlip.on('changeOrientation', function () { syncPage(pageFlip.getCurrentPageIndex() + 1); });

        if (startPage > 1) {
            pageFlip.turnToPage(startPage - 1);
        }
    }

    /**
     * StPageFlip entfernt beim destroy() auch den übergebenen Container.
     * Für den Neuaufbau wird er deshalb frisch angelegt.
     */
    function ensureBookElement() {
        var oldHolder = document.querySelector('.book-holder');
        if (oldHolder) {
            oldHolder.remove();
        }
        var existing = document.getElementById('book');
        if (existing) {
            existing.remove();
        }

        // Der Rahmen begrenzt die Breite; StPageFlip überschreibt die Maße des
        // eigenen Containers im Hochformat, den Rahmen aber nicht.
        var holder = document.createElement('div');
        holder.className = 'book-holder';

        var fresh = document.createElement('div');
        fresh.id = 'book';
        fresh.className = 'book';
        holder.appendChild(fresh);
        document.querySelector('.stage').insertBefore(holder, el.thumbs || null);

        el.book = fresh;
        el.holder = holder;
    }

    function setBookWidth(size) {
        var width = size.portrait ? size.width : size.width * 2;
        if (el.holder) {
            el.holder.style.width = width + 'px';
        }
    }

    function rebuildFlipbook() {
        var current = pageFlip ? pageFlip.getCurrentPageIndex() + 1 : 1;
        try { pageFlip.destroy(); } catch (e) { /* bereits abgeräumt */ }
        el.book.innerHTML = '';
        buildFlipbook(current);
        syncPage(current);
    }

    function buildPages() {
        var fragment = document.createDocumentFragment();
        book.pages.forEach(function (page, index) {
            var wrapper = document.createElement('div');
            wrapper.className = 'page';
            wrapper.setAttribute('data-density', index === 0 || index === book.pages.length - 1 ? 'hard' : 'soft');

            var image = document.createElement('img');
            // Nur die ersten Seiten sofort laden, der Rest beim Blättern
            image.loading = index < 4 ? 'eager' : 'lazy';
            image.src = page.src;
            image.alt = beschriftung.page + ' ' + page.n;
            wrapper.appendChild(image);
            addLinks(wrapper, page);
            fragment.appendChild(wrapper);
        });
        el.book.appendChild(fragment);

        return el.book.querySelectorAll('.page');
    }

    /**
     * Legt die Verweise des PDFs als Flächen über das Seitenbild.
     *
     * Die Lage steht in book.json auf 0 bis 1 bezogen, deshalb genügen
     * Prozentangaben - sie stimmen in jeder Größe.
     */
    function addLinks(wrapper, page) {
        (page.links || []).forEach(function (verweis) {
            var a = document.createElement('a');
            a.className = 'page-link';
            a.href = verweis.href;
            a.target = '_blank';
            a.rel = 'noopener';
            a.title = verweis.href;
            a.style.left = (verweis.x * 100) + '%';
            a.style.top = (verweis.y * 100) + '%';
            a.style.width = (verweis.w * 100) + '%';
            a.style.height = (verweis.h * 100) + '%';
            // Sonst nimmt StPageFlip den Klick als Blätterbewegung
            a.addEventListener('mousedown', function (e) { e.stopPropagation(); });
            a.addEventListener('touchstart', function (e) { e.stopPropagation(); }, { passive: true });
            a.addEventListener('click', function () { zaehle('link'); });
            wrapper.appendChild(a);
        });
    }

    function buildThumbs() {
        if (!el.thumbs) {
            if (el.thumbsToggle) { el.thumbsToggle.remove(); el.thumbsToggle = null; }

            return;
        }
        book.pages.forEach(function (page) {
            var button = document.createElement('button');
            button.type = 'button';
            button.dataset.page = String(page.n);
            button.innerHTML = '<img src="' + page.thumb + '" alt="" loading="lazy"><span>' + page.n + '</span>';
            button.addEventListener('click', function () { goTo(page.n, true); });
            el.thumbs.appendChild(button);
        });
    }

    /**
     * Das Inhaltsverzeichnis kommt aus book.json. Gibt es keines, verschwindet
     * die Schaltflaeche - ein leeres Fach waere nur im Weg.
     */
    function buildToc() {
        if (!el.toc || !el.tocToggle) {
            if (el.tocToggle) { el.tocToggle.remove(); el.tocToggle = null; }
            if (el.toc) { el.toc.remove(); el.toc = null; }

            return;
        }
        var eintraege = (book.toc || []).filter(function (e) { return e && e.title && e.page; });
        if (eintraege.length === 0) {
            if (el.tocToggle) { el.tocToggle.remove(); }
            if (el.toc) { el.toc.remove(); }
            el.toc = null;
            el.tocToggle = null;

            return;
        }
        eintraege.forEach(function (eintrag) {
            var button = document.createElement('button');
            button.type = 'button';
            button.dataset.page = String(eintrag.page);
            var titel = document.createElement('span');
            titel.className = 'toc-title';
            titel.textContent = eintrag.title;
            var seite = document.createElement('span');
            seite.className = 'toc-page';
            seite.textContent = String(eintrag.page);
            button.appendChild(titel);
            button.appendChild(seite);
            button.addEventListener('click', function () {
                goTo(eintrag.page, true);
                el.toc.hidden = true;
                el.tocToggle.setAttribute('aria-pressed', 'false');
            });
            el.toc.appendChild(button);
        });
    }

    /**
     * Immer nur eines der beiden Fächer offen - sie liegen an derselben Stelle.
     */
    function togglePanel(panel, toggle, other, otherToggle) {
        var open = panel.hasAttribute('hidden');
        panel.toggleAttribute('hidden', !open);
        toggle.setAttribute('aria-pressed', String(open));
        if (open && other) {
            other.setAttribute('hidden', '');
            if (otherToggle) { otherToggle.setAttribute('aria-pressed', 'false'); }
        }
    }

    /**
     * Hintergrund je Einbindung.
     *
     * Das Inhaltselement kann einen anderen Hintergrund vorgeben als die
     * Ausgabe selbst: ?hg=<Adresse>, ?hgdim=<0-90>, ?hgfit=cover|contain|tile.
     * Ein Bindestrich bei hg nimmt den Hintergrund an dieser Stelle ganz weg.
     *
     * Angenommen werden nur Adressen vom eigenen Server - eine fremde Adresse
     * aus der Aufrufzeile hat im Stylesheet nichts verloren.
     */
    function wendeHintergrundAn() {
        var bild = adressWert('hg');
        var wurzel = document.documentElement;
        if (bild === '-') {
            wurzel.style.setProperty('--stage-image', 'none');
            wurzel.style.setProperty('--stage-dim', '0');
        } else if (bild && /^\/[A-Za-z0-9/_.,%()-]+\.(webp|jpg|jpeg|png|avif)$/i.test(bild)) {
            wurzel.style.setProperty('--stage-image', "url('" + bild + "')");
        }
        var abdunklung = adressWert('hgdim');
        if (abdunklung !== null && abdunklung !== '' && bild !== '-') {
            var wert = Math.max(0, Math.min(90, parseInt(abdunklung, 10) || 0));
            wurzel.style.setProperty('--stage-dim', String(wert / 100));
        }
        var passung = adressWert('hgfit');
        if (passung === 'cover' || passung === 'contain' || passung === 'tile') {
            wurzel.style.setProperty('--stage-size', passung === 'tile' ? 'auto' : passung);
            wurzel.style.setProperty('--stage-repeat', passung === 'tile' ? 'repeat' : 'no-repeat');
        }
    }

    /**
     * Blaettergeraeusch.
     *
     * Die Datei liegt neben dem Betrachter und ist rund vier Kilobyte gross.
     * Bei jedem Blaettern wird sie mit leicht verschobener Geschwindigkeit
     * abgespielt - sonst klingt es nach dem immer gleichen Klick. Der Schalter
     * in der Leiste merkt sich seinen Zustand im Browser.
     *
     * Autoplay-Sperren sind hier kein Thema: Gespielt wird nur nach einer
     * Eingabe des Lesers, und genau die verlangen die Browser.
     */
    var ton = {
        an: true,
        datei: null,
        vorbereiten: function () {
            if (!bedienung.sound) {
                if (el.ton) { el.ton.remove(); el.ton = null; }

                return;
            }
            this.an = (book.ui && book.ui.soundOn) !== false;
            try {
                var gemerkt = window.localStorage && window.localStorage.getItem('ntflippdf-ton');
                if (gemerkt !== null && gemerkt !== undefined) { this.an = gemerkt === '1'; }
            } catch (e) { /* ohne Gedaechtnis bleibt die Vorgabe */ }

            this.datei = new Audio('assets/blaettern.mp3?v=' + (book.built || 0));
            this.datei.preload = 'auto';
            this.datei.volume = 0.5;

            if (el.ton) {
                el.ton.hidden = false;
                this.anzeigen();
                var selbst = this;
                el.ton.addEventListener('click', function () {
                    selbst.an = !selbst.an;
                    selbst.anzeigen();
                    try {
                        if (window.localStorage) { window.localStorage.setItem('ntflippdf-ton', selbst.an ? '1' : '0'); }
                    } catch (e) { /* siehe oben */ }
                    if (selbst.an) { selbst.spielen(); }
                });
            }
        },
        anzeigen: function () {
            if (!el.ton) { return; }
            el.ton.setAttribute('aria-pressed', this.an ? 'true' : 'false');
            var text = this.an ? beschriftung.soundOff : beschriftung.soundOn;
            if (text) {
                el.ton.title = text;
                el.ton.setAttribute('aria-label', text);
            }
        },
        spielen: function () {
            if (!this.an || !this.datei) { return; }
            try {
                var klang = this.datei.cloneNode();
                klang.volume = 0.5;
                // Ein bisschen Streuung, damit es nicht wie ein Tastendruck klingt
                klang.playbackRate = 0.92 + Math.random() * 0.16;
                var versuch = klang.play();
                if (versuch && versuch.catch) { versuch.catch(function () {}); }
            } catch (e) { /* Ton ist Beiwerk */ }
        }
    };

    /**
     * "In eigenem Fenster oeffnen".
     *
     * Sinnvoll nur, solange der Betrachter in einer Seite steckt: Das eigene
     * Fenster gibt ihm die volle Groesse und eine Adresse, die sich
     * weitergeben laesst - mitsamt der Seite, die gerade aufgeschlagen ist.
     * Steht er ohnehin allein, verschwindet der Knopf.
     */
    function richteExternEin() {
        if (!el.extern) { return; }
        var eingebettet = window.self !== window.top;
        if (!eingebettet) {
            el.extern.remove();
            el.extern = null;

            return;
        }
        el.extern.hidden = false;
        el.extern.addEventListener('click', function () {
            el.extern.href = window.location.href;
        });
        el.extern.href = window.location.href;
    }

    /**
     * Bedienhinweis: einmal je Ausgabe, dann nicht mehr.
     *
     * Wer schon weiss, wie man blaettert, braucht den Satz nicht - und in der
     * Fussleiste stand er sonst dauerhaft im Weg. Gemerkt wird das im Browser
     * des Lesers; ist das nicht moeglich (etwa in einem Rahmen mit strengen
     * Einstellungen), zeigt er sich eben jedes Mal kurz.
     */
    function zeigeHinweis() {
        if (!el.hinweis) { return; }
        var schluessel = 'ntflippdf-hinweis-' + (book.slug || 'ausgabe');
        try {
            if (window.localStorage && window.localStorage.getItem(schluessel)) {
                el.hinweis.remove();
                el.hinweis = null;

                return;
            }
        } catch (e) { /* ohne Gedaechtnis eben jedes Mal */ }

        el.hinweis.classList.add('hint-an');
        var weg = function () {
            if (!el.hinweis) { return; }
            el.hinweis.classList.remove('hint-an');
            window.setTimeout(function () {
                if (el.hinweis) { el.hinweis.remove(); el.hinweis = null; }
            }, 500);
            try {
                if (window.localStorage) { window.localStorage.setItem(schluessel, '1'); }
            } catch (e) { /* siehe oben */ }
        };
        window.setTimeout(weg, 6000);
        // Wer blaettert, hat es verstanden.
        document.addEventListener('keydown', weg, { once: true });
        if (el.book) { el.book.addEventListener('mousedown', weg, { once: true }); }
    }

    /**
     * Kapitelmarken unter dem Seitenregler.
     *
     * Bei 44 Seiten sagt der Regler allein wenig; die Striche zeigen, wo die
     * Kapitel anfangen, und lassen sich anklicken.
     */
    function buildSliderMarks() {
        if (!el.marken) { return; }
        var eintraege = (book.toc || []).filter(function (e) { return e && e.page > 1; });
        if (eintraege.length === 0 || book.pageCount < 2) {
            el.marken.remove();
            el.marken = null;

            return;
        }
        eintraege.forEach(function (eintrag) {
            var marke = document.createElement('button');
            var name = eintrag.title + ' (' + beschriftung.page + ' ' + eintrag.page + ')';
            marke.type = 'button';
            marke.className = 'slider-mark';
            marke.dataset.page = String(eintrag.page);
            marke.title = name;
            marke.setAttribute('aria-label', name);
            // Eigene Fahne statt des Browser-Tooltips: der kommt erst nach
            // einer Sekunde, und bis dahin hat der Leser weitergeklickt.
            var fahne = document.createElement('span');
            fahne.className = 'mark-title';
            fahne.textContent = eintrag.title;
            marke.appendChild(fahne);
            marke.addEventListener('click', function () { goTo(eintrag.page); });
            el.marken.appendChild(marke);
        });
        reglerAusrichten();
    }

    /**
     * Die rechte Seite der aufgeschlagenen Doppelseite - oder dieselbe Seite,
     * wenn nur eine zu sehen ist.
     *
     * Der Betrachter zaehlt in linken Seiten. Wer aufgeschlagen 2 und 3 vor
     * sich hat und "Seite 2" liest, haelt die Zaehlung fuer falsch; deshalb
     * steht dort jetzt "Seite 2-3".
     */
    function zweiteSeite(seite) {
        if (!pageFlip || pageFlip.getOrientation() === 'portrait') {
            return seite;
        }
        var mitDeckel = (book.flip || {}).cover !== false;
        if (mitDeckel && seite === 1) {
            return seite;
        }
        // Mit eigenem Deckel bilden 2/3, 4/5 ... die Paare, ohne Deckel 1/2, 3/4 ...
        var linksImPaar = mitDeckel ? (seite % 2 === 0) : (seite % 2 === 1);
        if (!linksImPaar) {
            return seite;
        }

        return seite + 1 <= book.pageCount ? seite + 1 : seite;
    }

    /**
     * Groesster Wert, den der Regler annehmen kann.
     *
     * Im Querformat stehen zwei Seiten nebeneinander, und die Seitenzahl meint
     * immer die linke davon. Auf der letzten Doppelseite ist das nicht die
     * letzte Seite - der Anfasser blieb deshalb vor dem Ende stehen, obwohl
     * nichts mehr kommt. Bei fuenf Seiten mit eigenem Deckel etwa endet er bei
     * vier. Der Regler laeuft daher bis zur linken Seite der letzten
     * Doppelseite; im Hochformat, wo einzeln geblaettert wird, bis zur letzten.
     */
    function reglerGrenze() {
        if (!pageFlip || pageFlip.getOrientation() === 'portrait') {
            return book.pageCount;
        }
        // Mit eigenem Deckel steht Seite 1 allein, danach bilden 2/3, 4/5 ...
        // Paare; ohne Deckel beginnen die Paare schon bei 1/2.
        var mitDeckel = (book.flip || {}).cover !== false;
        var gerade = book.pageCount % 2 === 0;
        var grenze = (mitDeckel ? gerade : !gerade) ? book.pageCount : book.pageCount - 1;

        return Math.max(1, grenze);
    }

    /**
     * Haelt Regler und Kapitelmarken auf derselben Skala - die Grenze aendert
     * sich, wenn das Fenster zwischen ein- und zweiseitiger Ansicht wechselt.
     */
    function reglerAusrichten() {
        var grenze = reglerGrenze();
        if (grenze === reglerMax) { return; }
        reglerMax = grenze;
        if (el.slider) { el.slider.max = String(grenze); }
        if (el.marken) {
            Array.prototype.forEach.call(el.marken.children, function (marke) {
                var seite = Math.min(parseInt(marke.dataset.page, 10) || 1, grenze);
                marke.style.left = (grenze > 1 ? (seite - 1) / (grenze - 1) * 100 : 0) + '%';
            });
        }
    }

    /**
     * Verweise auf dieselbe Ausgabe in anderen Sprachen.
     */
    function buildLanguages() {
        if (!el.sprachen) { return; }
        var andere = book.siblings || [];
        if (andere.length === 0) {
            el.sprachen.remove();
            el.sprachen = null;

            return;
        }
        andere.forEach(function (eintrag) {
            var a = document.createElement('a');
            a.href = eintrag.url;
            a.textContent = eintrag.label || (eintrag.language || '').toUpperCase();
            a.title = eintrag.title || a.textContent;
            el.sprachen.appendChild(a);
        });
    }

    /**
     * Zählt Aufrufe, Downloads und Klicks auf Verweise.
     *
     * Ohne Cookie und ohne Kennung des Lesers: gezählt wird nur, dass etwas
     * geschehen ist. Fällt der Zähler aus, merkt der Leser davon nichts.
     */
    function zaehle(art) {
        if (!book.counter) { return; }
        try {
            var url = book.counter
                + (book.counter.indexOf('?') === -1 ? '?' : '&')
                + 'ausgabe=' + encodeURIComponent(book.slug)
                + '&art=' + encodeURIComponent(art);
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url);
            } else {
                fetch(url, { method: 'GET', keepalive: true, credentials: 'omit' }).catch(function () {});
            }
        } catch (e) { /* Zählen ist Beiwerk */ }
    }

    function bindControls() {
        if (el.prev) { el.prev.addEventListener('click', function () { pageFlip.flipPrev(); }); }
        if (el.next) { el.next.addEventListener('click', function () { pageFlip.flipNext(); }); }
        if (el.slider) {
            el.slider.addEventListener('input', function () { goTo(parseInt(el.slider.value, 10)); });
        }

        if (el.thumbsToggle) {
            el.thumbsToggle.addEventListener('click', function () {
                togglePanel(el.thumbs, el.thumbsToggle, el.toc, el.tocToggle);
            });
        }

        if (el.tocToggle) {
            el.tocToggle.addEventListener('click', function () {
                togglePanel(el.toc, el.tocToggle, el.thumbs, el.thumbsToggle);
            });
        }

        if (el.drucken) {
            el.drucken.addEventListener('click', drucke);
        }

        if (el.download) {
            el.download.addEventListener('click', function () { zaehle('download'); });
        }

        // Zurück- und Vorwärts-Knopf des Browsers
        window.addEventListener('popstate', applyDeepLink);
        window.addEventListener('hashchange', applyDeepLink);

        if (el.fullscreen) { el.fullscreen.addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                document.documentElement.requestFullscreen();
            }
        }); }

        document.addEventListener('keydown', function (event) {
            if (event.target === el.search) { return; }
            if (event.key === 'ArrowLeft') { pageFlip.flipPrev(); }
            if (event.key === 'ArrowRight') { pageFlip.flipNext(); }
            if (event.key === 'Home') { goTo(1); }
            if (event.key === 'End') { goTo(book.pageCount); }
        });

        if (el.search && el.results) {
            var timer = null;
            el.search.addEventListener('input', function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(runSearch, 200);
            });
            document.addEventListener('click', function (event) {
                if (!el.results.contains(event.target) && event.target !== el.search) {
                    el.results.hidden = true;
                }
            });
        }

        var resizeTimer = null;
        window.addEventListener('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(function () {
                var next = stageSize();
                var changedOrientation = !lastLayout || next.portrait !== lastLayout.portrait;
                var changedSize = !lastLayout || Math.abs(next.width - lastLayout.width) > lastLayout.width * 0.1;

                if (changedOrientation || changedSize) {
                    rebuildFlipbook();
                } else {
                    lastLayout = next;
                    setBookWidth(next);
                    pageFlip.update({ width: next.width, height: next.height });
                }
            }, 200);
        });
    }

    /**
     * Springt auf eine Seite.
     *
     * merken=true legt einen Eintrag im Verlauf an, damit der Zurück-Knopf des
     * Browsers zur vorigen Stelle führt. Beim Blättern und beim Ziehen des
     * Reglers waere das laestig - dort wird die Adresse nur ersetzt.
     */
    function goTo(pageNumber, merken) {
        var target = Math.min(Math.max(pageNumber, 1), book.pageCount);
        sprungMerken = merken === true;
        pageFlip.flip(target - 1);
        syncPage(target);
        sprungMerken = false;
    }

    var sprungMerken = false;
    var reglerMax = 0;

    function syncPage(pageNumber) {
        markSingleSpread(pageNumber);
        if (el.indicator) {
            var bis = zweiteSeite(pageNumber);
            el.indicator.textContent = label(
                'pageOf',
                bis > pageNumber ? pageNumber + '\u2013' + bis : pageNumber,
                book.pageCount
            );
        }
        reglerAusrichten();
        if (el.slider) { el.slider.value = String(Math.min(pageNumber, reglerMax)); }
        updateHash(pageNumber, sprungMerken);
        if (el.zoom && !el.zoom.hidden) { el.zoom.dispatchEvent(new CustomEvent('seitenwechsel')); }
        if (el.thumbs) {
            Array.prototype.forEach.call(el.thumbs.children, function (button) {
                button.setAttribute('aria-current', button.dataset.page === String(pageNumber) ? 'true' : 'false');
            });
        }
    }

    /**
     * Titel- und Rückseite werden als Einzelseite gezeigt; dann wird das Buch
     * verschoben, damit es mittig steht statt am rechten Rand zu kleben.
     */
    function markSingleSpread(pageNumber) {
        var stage = document.querySelector('.stage');
        var portrait = pageFlip.getOrientation() === 'portrait';
        var isFirst = !portrait && pageNumber === 1;
        // Die letzte Seite steht in der linken Hälfte, die erste in der rechten –
        // sie müssen deshalb in entgegengesetzte Richtungen gerückt werden.
        var isLast = !portrait && pageNumber === book.pageCount;

        stage.classList.toggle('is-single', isFirst || isLast);
        stage.classList.toggle('is-first', isFirst);
        stage.classList.toggle('is-last', isLast);
        stage.classList.toggle('is-portrait', portrait);
    }

    function applyDeepLink() {
        var match = /^#seite-(\d+)$/.exec(window.location.hash);
        if (match) { goTo(parseInt(match[1], 10)); }
    }

    /**
     * Haelt die Adresse auf der aktuellen Seite.
     *
     * replaceState statt pushState beim Blättern: Sonst sammelt der
     * Zurück-Knopf jede einzelne Seite. Nur Sprünge - Inhaltsverzeichnis,
     * Suche, Seitenübersicht - kommen in den Verlauf.
     */
    function updateHash(pageNumber, merken) {
        var hash = '#seite-' + pageNumber;
        if (window.location.hash === hash) { return; }
        try {
            if (merken) {
                history.pushState(null, '', hash);
            } else {
                history.replaceState(null, '', hash);
            }
        } catch (e) {
            window.location.hash = hash;
        }
    }

    /**
     * Vergrößern: eine eigene Ebene über dem Buch statt einer Skalierung des
     * Buches selbst. StPageFlip rechnet mit festen Maßen; würde man daran
     * drehen, geriete das Blättern durcheinander.
     */
    function bindZoom() {
        if (!el.zoom || !el.zoomToggle) { return; }
        if (book.zoom === false) {
            el.zoomToggle.remove();
            el.zoom.remove();
            el.zoom = null;

            return;
        }

        var stufe = 1;
        var maximum = typeof book.zoomMax === 'number' ? book.zoomMax : 3;

        function zeige(an) {
            el.zoom.hidden = !an;
            el.zoomToggle.setAttribute('aria-pressed', String(an));
            if (an) {
                fuelle();
                setzeStufe(1.4);
            } else {
                el.zoomBilder.innerHTML = '';
            }
        }

        function fuelle() {
            el.zoomBilder.innerHTML = '';
            aktuelleSeiten().forEach(function (nummer) {
                var seite = book.pages[nummer - 1];
                if (!seite) { return; }
                var img = document.createElement('img');
                img.src = seite.src;
                img.alt = beschriftung.page + ' ' + seite.n;
                el.zoomBilder.appendChild(img);
            });
        }

        function setzeStufe(neu) {
            stufe = Math.min(maximum, Math.max(1, neu));
            el.zoomBilder.style.setProperty('--zoom', String(stufe));
        }

        el.zoom.addEventListener('seitenwechsel', fuelle);
        el.zoomToggle.addEventListener('click', function () { zeige(el.zoom.hidden); });
        el.zoomZu.addEventListener('click', function () { zeige(false); });
        el.zoomEin.addEventListener('click', function () { setzeStufe(stufe + 0.4); });
        el.zoomAus.addEventListener('click', function () { setzeStufe(stufe - 0.4); });
        el.zoom.addEventListener('wheel', function (event) {
            if (!event.ctrlKey && !event.metaKey) { return; }
            event.preventDefault();
            setzeStufe(stufe + (event.deltaY < 0 ? 0.2 : -0.2));
        }, { passive: false });

        /* Strg und Mausrad ueber dem Buch: Der erste Griff zum Vergroessern ist
           bei den meisten nicht die Lupe, sondern genau diese Geste. Ohne uns
           zoomt der Browser die ganze Seite - eingebettet also Kopf- und
           Fussbereich der Website, waehrend das Buch gleich gross bleibt.
           Deshalb fangen wir sie ab und oeffnen die Vergroesserung.
           Zwei Finger auf dem Trackpad melden sich als dasselbe Ereignis und
           sind damit ebenfalls abgedeckt. */
        var buehne = document.querySelector('.stage') || document.body;
        buehne.addEventListener('wheel', function (event) {
            if (!event.ctrlKey && !event.metaKey) { return; }
            if (!el.zoom.hidden) { return; }
            if (event.deltaY > 0) { return; }
            event.preventDefault();
            // Beim Oeffnen nicht zusaetzlich vergroessern: Die Vergroesserung
            // startet ohnehin bei 1,4 - wer weiter hinein will, dreht einfach
            // weiter, das Rad wirkt dann in der Vergroesserung selbst.
            zeige(true);
        }, { passive: false });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !el.zoom.hidden) { zeige(false); }
        });
    }

    /**
     * Die Seiten, die gerade zu sehen sind - im Querformat zwei, sonst eine.
     */
    function aktuelleSeiten() {
        var nummer = pageFlip ? pageFlip.getCurrentPageIndex() + 1 : 1;
        if (!pageFlip || pageFlip.getOrientation() === 'portrait') {
            return [nummer];
        }
        // Die linke Seite einer Doppelseite ist immer die gerade Nummer
        var links = nummer % 2 === 0 ? nummer : nummer - 1;

        return [links, links + 1].filter(function (n) { return n >= 1 && n <= book.pageCount; });
    }

    /**
     * Druckt die Seiten, die gerade zu sehen sind.
     */
    function drucke() {
        var bilder = aktuelleSeiten().map(function (nummer) {
            var seite = book.pages[nummer - 1];

            return seite ? '<img src="' + seite.src + '" alt="">' : '';
        }).join('');
        var fenster = window.open('', '_blank');
        if (!fenster) { return; }
        fenster.document.write(
            '<!DOCTYPE html><html lang="' + (book.language || 'de') + '"><head><meta charset="utf-8">'
            + '<title>' + (book.title || '') + '</title><style>'
            + 'body{margin:0;display:flex;gap:0}img{width:100%;height:auto}'
            + '@page{size:landscape;margin:8mm}</style></head><body>' + bilder + '</body></html>'
        );
        fenster.document.close();
        fenster.focus();
        fenster.addEventListener('load', function () { fenster.print(); });
    }

    function runSearch() {
        var term = el.search.value.trim();
        if (term.length < 3) {
            el.results.hidden = true;

            return;
        }
        loadSearchIndex().then(function (index) {
            var hits = [];
            var needle = term.toLowerCase();
            Object.keys(index).forEach(function (pageNumber) {
                var text = index[pageNumber] || '';
                var position = text.toLowerCase().indexOf(needle);
                if (position !== -1) {
                    hits.push({ page: parseInt(pageNumber, 10), excerpt: excerpt(text, position, term.length) });
                }
            });
            renderResults(hits, term);
        });
    }

    function excerpt(text, position, length) {
        var start = Math.max(0, position - 40);
        var snippet = text.substring(start, position + length + 60);

        return (start > 0 ? '… ' : '') + snippet + ' …';
    }

    function renderResults(hits, term) {
        el.results.innerHTML = '';
        el.results.hidden = false;

        if (hits.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'empty';
            empty.textContent = label('noResults') + ': \u201e' + term + '\u201c';
            el.results.appendChild(empty);

            return;
        }

        hits.sort(function (a, b) { return a.page - b.page; });
        hits.slice(0, 25).forEach(function (hit) {
            var button = document.createElement('button');
            button.type = 'button';
            var auszug = document.createTextNode(hit.excerpt);
            var marke = document.createElement('strong');
            marke.textContent = beschriftung.page + ' ' + hit.page + ': ';
            button.appendChild(marke);
            button.appendChild(auszug);
            button.addEventListener('click', function () {
                goTo(hit.page, true);
                el.results.hidden = true;
            });
            el.results.appendChild(button);
        });
    }

    function loadSearchIndex() {
        if (searchIndex) { return Promise.resolve(searchIndex); }

        return hole(vorschau ? 'teaser-search.json' : 'search.json')
            .then(function (data) { searchIndex = data; return data; })
            .catch(function () { return {}; });
    }
})();
