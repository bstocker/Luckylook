<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false]));
}

$c     = $_POST['contact'] ?? [];
$fn    = trim(strip_tags((string)($c['firstName'] ?? '')));
$ln    = trim(strip_tags((string)($c['lastName']  ?? '')));
$email = filter_var(trim((string)($c['email']     ?? '')), FILTER_VALIDATE_EMAIL);
$phone = trim(strip_tags((string)($c['phone']     ?? '')));
$msg   = trim(strip_tags((string)($c['message']   ?? '')));

if (!$email || $msg === '') {
    http_response_code(422);
    exit(json_encode(['ok' => false, 'error' => 'Email et message requis.']));
}

// Base de données dans www/data/ (protégée par .htaccess)
$dbDir  = __DIR__ . '/data';
$dbPath = $dbDir . '/messages.db';

if (!is_dir($dbDir) && !mkdir($dbDir, 0750, true)) {
    error_log('[LuckyLook] Impossible de créer le répertoire data/');
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'Erreur configuration serveur.']));
}

try {
    $db = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $db->exec('CREATE TABLE IF NOT EXISTS messages (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        first_name TEXT,
        last_name  TEXT,
        email      TEXT NOT NULL,
        phone      TEXT,
        message    TEXT NOT NULL,
        ip         TEXT,
        created_at TEXT DEFAULT (datetime("now")),
        read_at    TEXT
    )');

    $db->prepare(
        'INSERT INTO messages (first_name, last_name, email, phone, message, ip) VALUES (?,?,?,?,?,?)'
    )->execute([$fn ?: null, $ln ?: null, $email, $phone ?: null, $msg, $_SERVER['REMOTE_ADDR'] ?? null]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('[LuckyLook] contact.php : ' . $e->getMessage());
    http_response_code(500);
    // DEBUG TEMPORAIRE — à supprimer après diagnostic
    exit(json_encode(['ok' => false, 'error' => $e->getMessage()]));
}
