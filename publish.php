<?php

$kirby = kirby();

$repo = 'did:plc:5qithb452axywln42dxt3ani';
$collection = 'site.standard.document';
$publication = "at://{$repo}/site.standard.publication/3mn5seojh6x2l";

$file = __DIR__ . '/atprotoexport-export.json';


/**
 * Display a simple result page
 */
function standardSiteResult(string $title, string $message): void
{
    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '</head>';
    echo '<body>';

    echo '<h1>' . htmlspecialchars($title) . '</h1>';
    echo '<p>' . nl2br(htmlspecialchars($message)) . '</p>';
    echo '<p><a href="/atprotoexport/publish">Back</a></p>';

    echo '</body>';
    echo '</html>';

    exit;
}


/**
 * AT Protocol API request
 */
function standardSiteRequest(
    string $pds,
    string $endpoint,
    string $method = 'POST',
    ?array $payload = null,
    ?string $token = null
): array {

    $ch = curl_init(rtrim($pds, '/') . '/xrpc/' . $endpoint);

    $headers = [
        'Accept: application/json',
    ];

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($payload !== null) {
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Connection error: ' . $error);
    }

    $data = json_decode($response, true);

    return [
        'status' => $status,
        'data' => is_array($data) ? $data : [],
    ];
}


/**
 * Read and validate the export file
 */
if (!is_file($file) || !is_readable($file)) {
    standardSiteResult(
        'Export file not found',
        'Generate an export first at atprotoexport/export.'
    );
}

$json = file_get_contents($file);
$export = json_decode($json, true);

if (
    !is_array($export) ||
    ($export['repo'] ?? null) !== $repo ||
    ($export['collection'] ?? null) !== $collection ||
    !isset($export['records']) ||
    !is_array($export['records'])
) {
    standardSiteResult(
        'Invalid export',
        'The export file does not have the expected structure.'
    );
}

if (count($export['records']) === 0) {
    standardSiteResult(
        'No records',
        'The export file contains no records.'
    );
}


/**
 * Validate records
 */
$records = [];
$seen = [];

foreach ($export['records'] as $item) {

    $rkey = $item['rkey'] ?? null;
    $record = $item['record'] ?? null;

    if (
        !is_string($rkey) ||
        $rkey === '' ||
        !is_array($record) ||
        ($record['$type'] ?? null) !== $collection ||
        ($record['site'] ?? null) !== $publication
    ) {
        standardSiteResult(
            'Invalid record',
            'One or more records contain invalid data.'
        );
    }

    if (isset($seen[$rkey])) {
        standardSiteResult(
            'Duplicate record key',
            'The export contains a duplicate record key: ' . $rkey
        );
    }

    $seen[$rkey] = true;

    $records[] = [
        'rkey' => $rkey,
        'record' => $record,
    ];
}


/**
 * Show a summary before publishing
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Publish to AT Protocol</title>';
    echo '</head>';
    echo '<body>';

    echo '<h1>Publish to AT Protocol</h1>';

    echo '<p>Export file found.</p>';
    echo '<p>Number of records: <strong>' . count($records) . '</strong></p>';

    echo '<p>Repo: ' . htmlspecialchars($repo) . '</p>';
    echo '<p>Collection: ' . htmlspecialchars($collection) . '</p>';

    echo '<form method="post">';
    echo '<button type="submit">Publish to PDS</button>';
    echo '</form>';

    echo '</body>';
    echo '</html>';

    exit;
}


/**
 * Configuratie uitlezen
 */
$pds = $kirby->option('atprotoexport.pds');
$identifier = $kirby->option('atprotoexport.identifier');
$password = $kirby->option('atprotoexport.password');

if (!$pds || !$identifier || !$password) {
    standardSiteResult(
        'Configuration missing',
        'Check the AT Protocol settings in config.php.'
    );
}


/**
 * Inloggen bij PDS
 */
try {

    $login = standardSiteRequest(
        $pds,
        'com.atproto.server.createSession',
        'POST',
        [
            'identifier' => $identifier,
            'password' => $password,
        ]
    );

    if (
        $login['status'] !== 200 ||
        empty($login['data']['accessJwt'])
    ) {
        standardSiteResult(
            'Login failed',
            'The PDS rejected the login credentials. Check your configuration and app password.'
        );
    }

    if (($login['data']['did'] ?? null) !== $repo) {
        standardSiteResult(
            'Incorrect account',
            'The signed-in account does not have the expected DID.'
        );
    }

    $token = $login['data']['accessJwt'];


    /**
     * Bestaande records controleren
     */
    $writes = [];
    $created = 0;
    $updated = 0;

    foreach ($records as $item) {

        $rkey = $item['rkey'];
        $record = $item['record'];

        $query = http_build_query([
            'repo' => $repo,
            'collection' => $collection,
            'rkey' => $rkey,
        ]);

        $existing = standardSiteRequest(
            $pds,
            'com.atproto.repo.getRecord?' . $query,
            'GET',
            null,
            $token
        );

        if ($existing['status'] === 200) {

            $action = 'update';
            $updated++;

        } elseif (
            in_array($existing['status'], [400, 404], true) &&
            ($existing['data']['error'] ?? '') === 'RecordNotFound'
        ) {

            $action = 'create';
            $created++;

        } else {

            standardSiteResult(
                'Record check failed',
                'Could not check record ' . $rkey . ': ' .
                json_encode($existing['data'])
            );
        }

        $writes[] = [
            '$type' => 'com.atproto.repo.applyWrites#' . $action,
            'collection' => $collection,
            'rkey' => $rkey,
            'value' => $record,
        ];
    }


    /**
     * Batch publiceren
     */
    $result = standardSiteRequest(
        $pds,
        'com.atproto.repo.applyWrites',
        'POST',
        [
            'repo' => $repo,
            'writes' => $writes,
        ],
        $token
    );

    if ($result['status'] !== 200) {

        standardSiteResult(
            'Publishing failed',
            json_encode($result['data'], JSON_PRETTY_PRINT)
        );
    }


    /**
     * Resultaat tonen
     */
    standardSiteResult(
        'Publishing succeeded',
        count($writes) . ' records processed.' . "\n\n" .
        'Created: ' . $created . "\n" .
        'Updated: ' . $updated
    );

} catch (Throwable $e) {

    standardSiteResult(
        'Something went wrong',
        $e->getMessage()
    );
}
