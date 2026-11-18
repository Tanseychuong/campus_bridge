<?php
/**
 * The resource scanner worker: checks uploaded resources for possible
 * privacy and copyright problems, in the background.
 *
 * Run it from the project folder:
 *     php bin/scan-worker.php           keep running, check the queue every few seconds
 *     php bin/scan-worker.php --once    scan everything queued now, then stop
 *
 * With Docker it runs automatically as the "worker" service (docker-compose.yml).
 *
 * Why a separate worker? Reading a 10 MB PDF can take a moment. Doing it in
 * the background keeps the upload page fast, and a crash while reading a
 * strange file can never break the website itself.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php'; // on the command line this only loads our functions

$once = in_array('--once', $argv, true);
$pauseSeconds = 3;

$log = function (string $message): void {
    fwrite(STDOUT, '[' . gmdate('Y-m-d H:i:s') . "] $message\n");
};

$log($once ? 'Scanning queued resources once.' : 'Scan worker started. Waiting for uploads…');

while (true) {
    try {
        $result = scan_next_resource();
    } catch (PDOException $error) {
        // The database may still be starting (Docker) or briefly unavailable.
        $log('Database not reachable: ' . $error->getMessage());
        if ($once) {
            exit(1);
        }
        sleep(5);
        continue;
    }

    if ($result !== null) {
        if ($result['flags'] !== null) {
            $critical = count(array_filter($result['flags'], fn ($flag) => $flag['severity'] === 'critical'));
            $log("Resource #{$result['id']}: " . count($result['flags']) . " finding(s), $critical critical.");
        }
        continue; // look for the next one straight away
    }

    if ($once) {
        break;
    }
    sleep($pauseSeconds);
}
