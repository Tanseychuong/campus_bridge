<?php
/**
 * Base class for feature (API) tests.
 *
 * Before the tests in a class run, it:
 *   1. rebuilds a separate TEST database from schema.sql and seed.sql;
 *   2. starts PHP's built-in web server with public/index.php as the router,
 *      pointed at that test database.
 *
 * Each test then behaves like the React app: it calls /api/... addresses,
 * sends the CSRF token in the X-CSRF-Token header and keeps the session
 * cookie between requests. The real development database is never touched.
 */

use PHPUnit\Framework\TestCase;

abstract class WebTestCase extends TestCase
{
    private const PORT = 8765;

    /** @var resource|null the running web server process */
    private static $server = null;

    /** Cookies for the current test ("one browser per test"). */
    private string $cookieFile;

    public static function setUpBeforeClass(): void
    {
        self::resetTestDatabase();
        self::startServer();
    }

    protected function setUp(): void
    {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'cb_cookies_');
    }

    protected function tearDown(): void
    {
        @unlink($this->cookieFile);
    }

    private static function databaseSettings(): array
    {
        return [
            'host' => getenv('TEST_DB_HOST'), 'port' => getenv('TEST_DB_PORT'), 'name' => getenv('TEST_DB_NAME'),
            'user' => getenv('TEST_DB_USER'), 'password' => getenv('TEST_DB_PASSWORD'),
        ];
    }

    /** Drops and re-creates the test database with fresh sample data. */
    private static function resetTestDatabase(): void
    {
        $db = self::databaseSettings();
        try {
            $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset=utf8mb4", $db['user'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $error) {
            self::markTestSkipped('Feature tests need MySQL (see README, "Testing"): ' . $error->getMessage());
        }
        $pdo->exec("DROP DATABASE IF EXISTS `{$db['name']}`");
        $pdo->exec("CREATE DATABASE `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$db['name']}`");
        $pdo->exec(file_get_contents(__DIR__ . '/../../database/schema.sql'));
        $pdo->exec(file_get_contents(__DIR__ . '/../../database/seed.sql'));
    }

    /** Starts `php -S` once for the whole test run, with public/index.php handling every request. */
    private static function startServer(): void
    {
        if (self::$server !== null) {
            return;
        }
        $db = self::databaseSettings();
        $environment = array_merge(getenv(), [
            'DB_HOST' => $db['host'], 'DB_PORT' => $db['port'], 'DB_NAME' => $db['name'],
            'DB_USER' => $db['user'], 'DB_PASSWORD' => $db['password'], 'APP_DEBUG' => '1',
            'UPLOAD_DIR' => sys_get_temp_dir() . '/campusbridge_test_uploads', // keep test uploads out of the project
        ]);
        $public = realpath(__DIR__ . '/../../public');
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . self::PORT, '-t', $public, $public . DIRECTORY_SEPARATOR . 'index.php'];
        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        self::$server = proc_open($command, [1 => ['file', $nullDevice, 'w'], 2 => ['file', $nullDevice, 'w']], $pipes, null, $environment);

        // Wait until the server answers (at most ~5 seconds).
        for ($attempt = 0; $attempt < 50; $attempt++) {
            if (@fsockopen('127.0.0.1', self::PORT)) {
                break;
            }
            usleep(100_000);
        }
        register_shutdown_function(function () {
            if (self::$server !== null) {
                proc_terminate(self::$server);
            }
        });
    }

    /** Runs the background scan worker once against the test database. Returns its output. */
    protected function runScanWorker(): string
    {
        $db = self::databaseSettings();
        $environment = array_merge(getenv(), [
            'DB_HOST' => $db['host'], 'DB_PORT' => $db['port'], 'DB_NAME' => $db['name'],
            'DB_USER' => $db['user'], 'DB_PASSWORD' => $db['password'],
            'UPLOAD_DIR' => sys_get_temp_dir() . '/campusbridge_test_uploads',
        ]);
        $command = [PHP_BINARY, realpath(__DIR__ . '/../../bin/scan-worker.php'), '--once'];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);
        $this->assertSame(0, $exitCode, "Scan worker failed:\n$output");
        return $output;
    }

    /* ------------------------------------------------------------------
     * A tiny API client, behaving like the React app
     * ------------------------------------------------------------------ */

    /**
     * Sends a request and returns ['status' => 200, 'headers' => '...', 'body' => '...', 'json' => [...] or null].
     * $data is sent as JSON, or as a multipart form when it contains a file (CURLFile).
     * $csrfToken: null = fetch the real token first; a string = send that exact token.
     */
    protected function request(string $method, string $path, array $data = [], ?string $csrfToken = null): array
    {
        $headers = [];
        if ($method !== 'GET') {
            $headers[] = 'X-CSRF-Token: ' . ($csrfToken ?? $this->csrfToken());
        }

        $curl = curl_init('http://127.0.0.1:' . self::PORT . '/' . ltrim($path, '/'));
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($method !== 'GET') {
            $hasFile = array_filter($data, fn ($value) => $value instanceof CURLFile) !== [];
            if ($hasFile) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // multipart/form-data, like a browser upload
            } else {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $body = substr($response, $headerSize);
        $json = json_decode($body, true);
        return [
            'status' => $status,
            'headers' => substr($response, 0, $headerSize),
            'body' => $body,
            'json' => is_array($json) ? $json : null,
        ];
    }

    /** The session's CSRF token, as the React app gets it. */
    protected function csrfToken(): string
    {
        return $this->request('GET', 'api/session')['json']['csrf_token'];
    }

    protected function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    protected function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, $data);
    }

    protected function signIn(string $email, string $password = 'CampusBridge2026!'): array
    {
        return $this->post('api/login', ['email' => $email, 'password' => $password]);
    }

    protected function signOut(): void
    {
        $this->post('api/logout');
    }

    /** Database id of a seeded item, looked up by title (seed ids are stable but titles read better in tests). */
    protected function idOf(string $table, string $title): int
    {
        $db = self::databaseSettings();
        $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['name']}", $db['user'], $db['password']);
        $column = $table === 'organizations' ? 'name' : 'title';
        $statement = $pdo->prepare("SELECT id FROM $table WHERE $column = ?");
        $statement->execute([$title]);
        return (int) $statement->fetchColumn();
    }

    /** Asserts the JSON answer contains $text somewhere (in any value). */
    protected function assertSeeText(string $text, array $response): void
    {
        $this->assertStringContainsString($text, $this->allText($response), "Expected to see: $text");
    }

    protected function assertDontSeeText(string $text, array $response): void
    {
        $this->assertStringNotContainsString($text, $this->allText($response), "Did not expect to see: $text");
    }

    /** Every string in the JSON answer, joined, so tests can search it like a page. */
    private function allText(array $response): string
    {
        $strings = [];
        $json = $response['json'] ?? [];
        array_walk_recursive($json, function ($value) use (&$strings) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        });
        return implode("\n", $strings);
    }

    /** The titles of a list of items in the answer, e.g. titlesIn($response, 'results'). */
    protected function titlesIn(array $response, string $key): array
    {
        return array_map(fn ($item) => $item['title'] ?? $item['name'], $response['json'][$key] ?? []);
    }
}
