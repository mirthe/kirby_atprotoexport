<?php

$kirby = kirby();

$repo = option('mirthe.atprotoexport.repo');
if (!is_string($repo) || $repo === 'did:plc:') {
    throw new RuntimeException('Missing repository ID in atprotoexport.repo configuration.');
}

$collection = 'site.standard.document';
$publication = "at://{$repo}/site.standard.publication/". option('mirthe.atprotoexport.publication');

// Export posts published on or after this date (optional)
$fromDate = '2026-10-01';

// Filter posts by this tag (optional)
$tag = 'uitgelicht';

// get posts
$pages = $kirby->site()->index()
    ->filterBy('template', 'blogpost');
// $pages = $pages->filter(function ($page) use ($fromDate) {
//     return $page->date()->toDate('Y-m-d') >= $fromDate;
// });
// $pages = $pages->filterBy('tags', $tag, ',');
$pages = $pages->sortBy('date', 'desc')->limit(10);
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

$file = __DIR__ . '/atprotoexport-export.json';

if (file_put_contents($file, $json, LOCK_EX) === false) {
    throw new RuntimeException('Could not write export file.');
}

header('Content-Type: application/json; charset=utf-8');

echo $json;

exit();
