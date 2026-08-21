<?php

declare(strict_types=1);

namespace Netthinks\NtFlippdf\Service;

/**
 * Fehler beim Aufruf eines PDF-Werkzeugs. Die Meldung ist fuer Menschen
 * gedacht und wird unveraendert ausgegeben.
 */
class PdfToolkitException extends \RuntimeException {}
