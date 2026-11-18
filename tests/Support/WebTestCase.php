<?php
/**
 * Base class for feature tests.
 *
 * Before the tests in a class run, it:
 *   1. rebuilds a separate TEST database from schema.sql and seed.sql;
 *   2. starts PHP's built-in web server, pointed at that test database.
 *
 * Each test then behaves like a browser: it requests pages, submits forms
 * (with the CSRF token taken from the page) and keeps cookies between requests.
 * The real development database is never touched.
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

    /** Starts `php -S` once for the whole test run. */
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
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . self::PORT, '-t', realpath(__DIR__ . '/../../public')];
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
     * A tiny browser
     * ------------------------------------------------------------------ */

    /**
     * Sends a request and returns ['status' => 200, 'headers' => [...], 'body' => '...', 'location' => '...'].
     * Redirects are NOT followed automatically, so tests can check them.
     */
    protected function request(string $method, string $path, array $form = []): array
    {
        $curl = curl_init('http://127.0.0.1:' . self::PORT . '/' . ltrim($path, '/'));
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($method === 'POST') {
            // A form with a file (CURLFile) is sent as multipart/form-data, like a browser upload.
            $hasFile = array_filter($form, fn ($value) => $value instanceof CURLFile) !== [];
            curl_setopt($curl, CURLOPT_POSTFIELDS, $hasFile ? $form : http_build_query($form));
        }
        $response = curl_exec($curl);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $headerText = substr($response, 0, $headerSize);
        preg_match('/^Location:\s*(.+)$/mi', $headerText, $location);
        return [
            'status' => $status,
            'headers' => $headerText,
            'body' => substr($response, $headerSize),
            'location' => isset($location[1]) ? trim($location[1]) : null,
        ];
    }

    protected function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** Opens $formPage, copies its CSRF token, then POSTs $form to $action. Follows one redirect. */
    protected function submit(string $formPage, string $action, array $form, bool $followRedirect = true): array
    {
        $page = $this->get($formPage);
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $page['body'], $token);
        $response = $this->request('POST', $action, $form + ['csrf_token' => $token[1] ?? '']);

        if ($followRedirect && $response['location'] !== null) {
            return $this->get($response['location']);
        }
        return $response;
    }

    protected function signIn(string $email, string $password = 'CampusBridge2026!'): array
    {
        return $this->submit('login.php', 'login.php', ['email' => $email, 'password' => $password]);
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

    protected function assertSeeText(string $text, array $response): void
    {
        $this->assertStringContainsString(htmlspecialchars($text, ENT_QUOTES), $response['body'], "Expected to see: $text");
    }

    protected function assertDontSeeText(string $text, array $response): void
    {
        $this->assertStringNotContainsString(htmlspecialchars($text, ENT_QUOTES), $response['body'], "Did not expect to see: $text");
    }
}
