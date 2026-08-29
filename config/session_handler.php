<?php
// ============================================================================
// Database-backed Session Handler for Vercel Serverless (Stateless PHP)
// Stores sessions in TiDB/MySQL `sessions` table instead of the filesystem
// ============================================================================

require_once __DIR__ . '/db.php';

class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $lifetime;

    public function __construct(PDO $pdo, int $lifetime = 86400)
    {
        $this->pdo = $pdo;
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT data FROM sessions WHERE id = ? AND last_access >= ?'
            );
            $stmt->execute([$id, time() - $this->lifetime]);
            $row = $stmt->fetch();
            return $row ? $row['data'] : '';
        } catch (Throwable $e) {
            error_log('Session read error: ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'REPLACE INTO sessions (id, data, last_access) VALUES (?, ?, ?)'
            );
            return $stmt->execute([$id, $data, time()]);
        } catch (Throwable $e) {
            error_log('Session write error: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            error_log('Session destroy error: ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE last_access < ?');
            $stmt->execute([time() - $max_lifetime]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('Session gc error: ' . $e->getMessage());
            return 0;
        }
    }
}

// Register the database session handler
$handler = new DbSessionHandler($pdo);
session_set_save_handler($handler, true);

// Ensure the session cookie settings work cross-request on Vercel
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isSecure,
    'httponly'  => true,
    'samesite'  => 'Lax',
]);

// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
