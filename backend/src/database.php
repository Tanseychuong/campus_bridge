<?php
/**
 * The database connection.
 *
 * We use PDO (PHP Data Objects), PHP's built-in way to talk to databases.
 * Every query in this project uses PREPARED STATEMENTS:
 *
 *     $statement = db()->prepare('SELECT * FROM users WHERE email = ?');
 *     $statement->execute([$email]);
 *
 * The "?" is a placeholder. MySQL receives the SQL and the value separately,
 * so a value can never change the meaning of the SQL. This is what protects
 * us from SQL injection attacks.
 */

/**
 * Returns the shared database connection, creating it the first time.
 */
function db(): PDO
{
    static $connection = null;   // "static" keeps the value between calls

    if ($connection === null) {
        $settings = app_config()['db'];
        $dsn = "mysql:host={$settings['host']};port={$settings['port']};dbname={$settings['name']};charset=utf8mb4";

        $connection = new PDO($dsn, $settings['user'], $settings['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw an error if a query fails
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows come back as ['column' => value]
            PDO::ATTR_EMULATE_PREPARES   => false,                  // use real prepared statements
        ]);

        // Store and read all dates in UTC.
        $connection->exec("SET time_zone = '+00:00'");
    }

    return $connection;
}

/**
 * Runs a query and returns all rows.
 * Example: fetch_all('SELECT * FROM events WHERE id = ?', [5])
 */
function fetch_all(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

/**
 * Runs a query and returns the first row, or null if there is none.
 */
function fetch_one(string $sql, array $params = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/**
 * Runs an INSERT, UPDATE or DELETE and returns how many rows changed.
 */
function execute(string $sql, array $params = []): int
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->rowCount();
}
