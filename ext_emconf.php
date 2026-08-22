<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Page-flip editions from PDF files',
    'description' => 'Turns a PDF into a page-flip edition held in a self-contained directory: page images, full-text search, table of contents, page overview and download - static files, with no TYPO3 at runtime.',
    'category' => 'plugin',
    'author' => 'netthinks',
    'author_email' => 'info@netthinks.com',
    'state' => 'stable',
    'version' => '1.9.2',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
