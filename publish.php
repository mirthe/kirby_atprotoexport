<?php

$kirby = kirby();

$repo = 'did:plc:5qithb452axywln42dxt3ani';
$collection = 'site.standard.document';
$publication = "at://{$repo}/site.standard.publication/3mn5seojh6x2l";

$file = __DIR__ . '/standard-site-export.json';


/**
 * Toon een eenvoudige resultaatpagina
 */
function standardSiteResult(string $title, string $message): void
{
    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html>';
    echo '<html lang="nl">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '</head>';
    echo '<body>';

    echo '<h1>' . htmlspecialchars($title) . '</h1>';
    echo '<p>' . nl2br(htmlspecialchars($message)) . '</p>';
    echo '<p><a href="/standard-site/publish">Terug</a></p>';

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
        throw new RuntimeException('Verbindingsfout: ' . $error);
    }

    $data = json_decode($response, true);

    return [
        'status' => $status,
        'data' => is_array($data) ? $data : [],
    ];
}


/**
 * Exportbestand uitlezen en controleren
 */
if (!is_file($file) || !is_readable($file)) {
    standardSiteResult(
        'Exportbestand ontbreekt',
        'Genereer eerst een export via standard-site/export.'
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
        'Ongeldige export',
        'Het exportbestand heeft niet de verwachte structuur.'
    );
}

if (count($export['records']) === 0) {
    standardSiteResult(
        'Geen records',
        'Er staan geen records in het exportbestand.'
    );
}


/**
 * Records valideren
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
            'Ongeldig record',
            'Een of meer records bevatten ongeldige gegevens.'
        );
    }

    if (isset($seen[$rkey])) {
        standardSiteResult(
            'Dubbele record key',
            'De export bevat een dubbele rkey: ' . $rkey
        );
    }

    $seen[$rkey] = true;

    $records[] = [
        'rkey' => $rkey,
        'record' => $record,
    ];
}


/**
 * Eerst alleen een overzicht tonen
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html>';
    echo '<html lang="nl">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Standard.site publiceren</title>';
    echo '</head>';
    echo '<body>';

    echo '<h1>Standard.site publiceren</h1>';

    echo '<p>Het exportbestand is gevonden.</p>';
    echo '<p>Aantal records: <strong>' . count($records) . '</strong></p>';

    echo '<p>Repo: ' . htmlspecialchars($repo) . '</p>';
    echo '<p>Collection: ' . htmlspecialchars($collection) . '</p>';

    echo '<form method="post">';
    echo '<button type="submit">Publiceer naar PDS</button>';
    echo '</form>';

    echo '</body>';
    echo '</html>';

    exit;
}


/**
 * Configuratie uitlezen
 */
$pds = $kirby->option('standard-site.pds');
$identifier = $kirby->option('standard-site.identifier');
$password = $kirby->option('standard-site.password');

if (!$pds || !$identifier || !$password) {
    standardSiteResult(
        'Configuratie ontbreekt',
        'Controleer de Standard.site-instellingen in config.php.'
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
            'Inloggen mislukt',
            'De PDS heeft de inloggegevens niet geaccepteerd. Controleer je configuratie en app-wachtwoord.'
        );
    }

    if (($login['data']['did'] ?? null) !== $repo) {
        standardSiteResult(
            'Verkeerd account',
            'Het ingelogde account heeft niet de verwachte DID.'
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
                'Controle mislukt',
                'Kon record ' . $rkey . ' niet controleren: ' .
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
            'Publiceren mislukt',
            json_encode($result['data'], JSON_PRETTY_PRINT)
        );
    }


    /**
     * Resultaat tonen
     */
    standardSiteResult(
        'Publiceren geslaagd',
        count($writes) . ' records verwerkt.' . "\n\n" .
        'Nieuw aangemaakt: ' . $created . "\n" .
        'Bijgewerkt: ' . $updated
    );

} catch (Throwable $e) {

    standardSiteResult(
        'Er is iets misgegaan',
        $e->getMessage()
    );
}
