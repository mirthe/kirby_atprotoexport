<?php

$kirby = kirby();

$repo = 'did:plc:5qithb452axywln42dxt3ani';
$collection = 'site.standard.document';
$publication = "at://{$repo}/site.standard.publication/3mn5seojh6x2l";

// which filter to use: 'date' of 'tag'
$filterMode = 'date';

// settings
$fromDate = '2026-10-01';
$tag = 'uitgelicht';

// get posts
$pages = $kirby->site()->index()
    ->filterBy('template', 'blogpost');
if ($filterMode === 'date') {
    $pages = $pages->filter(function ($page) use ($fromDate) {
        return $page->date()->toDate('Y-m-d') >= $fromDate;
    });
} elseif ($filterMode === 'tag') {
    $pages = $pages->filterBy('tags', $tag, ',');
}
$pages = $pages->sortBy('date', 'desc');
$records = [];

foreach ($pages as $page) {
    $rkey = explode('://', $page->uuid())[1];

    $publishedAt = (new DateTime($page->date()->value()))
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d\TH:i:s.v\Z');

    $records[] = [
        'rkey' => $rkey,
        'record' => [
            '$type' => $collection,
            'site' => $publication,
            'title' => $page->title()->value(),
            'description' => html_entity_decode(
                $page->intro()->excerpt(200)->value(),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ),
            'tags' => array_values(array_filter(array_merge(
                [$page->category()->value()],
                $page->tags()->split(',')
            ))),
            'path' => '/' . $page->id(),
            'publishedAt' => $publishedAt,
        ],
    ];
}


$payload = [
    'repo' => $repo,
    'collection' => $collection,
    'records' => $records,
];

$json = json_encode(
    $payload,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);

if ($json === false) {
    throw new RuntimeException('JSON encoding failed: ' . json_last_error_msg());
}

$file = __DIR__ . '/standard-site-export.json';

if (file_put_contents($file, $json, LOCK_EX) === false) {
    throw new RuntimeException('Could not write export file.');
}

header('Content-Type: application/json; charset=utf-8');

echo $json;

exit();
