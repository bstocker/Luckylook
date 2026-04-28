<?php
declare(strict_types=1);
session_name('ll_admin');
session_start();

// ── Config ────────────────────────────────────────────────────────────────────
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(503);
    die('<pre style="font-family:monospace;padding:32px">Créez admin/config.php depuis admin/config.example.php</pre>');
}
require $configFile;

if (!defined('ADMIN_PASSWORD_HASH') || ADMIN_PASSWORD_HASH === 'REMPLACEZ_PAR_VOTRE_HASH') {
    http_response_code(503);
    die('<pre style="font-family:monospace;padding:32px">Mot de passe non configuré dans admin/config.php</pre>');
}

// Base de données dans www/data/ (protégée par .htaccess)
$DB_PATH = dirname(__DIR__) . '/data/messages.db';
$error   = '';

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// ── Déconnexion ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ── Connexion ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['ll_admin'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Mot de passe incorrect.';
}

// ── Actions (authentifié uniquement) ─────────────────────────────────────────
if (isset($_SESSION['ll_admin']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrf'] ?? '') !== $csrf) {
        http_response_code(403);
        die('Jeton CSRF invalide.');
    }
    if (file_exists($DB_PATH)) {
        $db = new PDO('sqlite:' . $DB_PATH, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if (isset($_POST['mark_read'])) {
            $db->prepare('UPDATE messages SET read_at = datetime("now") WHERE id = ? AND read_at IS NULL')
               ->execute([(int)$_POST['mark_read']]);
        } elseif (isset($_POST['delete'])) {
            $db->prepare('DELETE FROM messages WHERE id = ?')
               ->execute([(int)$_POST['delete']]);
        }
    }
    header('Location: index.php');
    exit;
}

// ── Chargement des messages ───────────────────────────────────────────────────
$messages = [];
$unread   = 0;
$dbError  = '';

if (isset($_SESSION['ll_admin'])) {
    if (!file_exists($DB_PATH)) {
        $dbError = 'Aucun message reçu pour l\'instant.';
    } else {
        try {
            $db       = new PDO('sqlite:' . $DB_PATH, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $messages = $db->query('SELECT * FROM messages ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
            $unread   = (int)$db->query('SELECT COUNT(*) FROM messages WHERE read_at IS NULL')->fetchColumn();
        } catch (Throwable $e) {
            $dbError = 'Erreur base de données : ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Messages – Lucky Look</title>
<style>
  :root{--ink:#1a1008;--bark:#2e1f0e;--leather:#5c3a1e;--rust:#8b4513;--sand:#c4956a;--gold:#c8973b;--parchment:#f2e8d5;--cream:#faf5ec;}
  *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--cream);color:var(--ink);min-height:100vh;}

  /* ── Login ── */
  .login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bark);}
  .login-box{background:var(--parchment);padding:40px;width:340px;border-top:3px solid var(--gold);}
  .login-box h1{font-size:20px;margin-bottom:6px;color:var(--bark);}
  .login-box p{font-size:13px;color:var(--leather);margin-bottom:24px;}
  .login-box input[type=password]{width:100%;padding:11px 14px;border:1px solid rgba(92,58,30,.3);font-size:14px;outline:none;margin-bottom:12px;background:#fff;}
  .login-box input:focus{border-color:var(--gold);}
  .login-box button{width:100%;padding:12px;background:var(--bark);color:var(--gold);font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;border:none;cursor:pointer;transition:background .2s,color .2s;}
  .login-box button:hover{background:var(--gold);color:var(--ink);}
  .login-error{color:#c0392b;font-size:13px;margin-bottom:12px;}

  /* ── Admin header ── */
  .admin-header{background:var(--bark);border-bottom:2px solid var(--gold);padding:14px 32px;display:flex;justify-content:space-between;align-items:center;}
  .brand{color:var(--parchment);font-size:16px;font-weight:700;}
  .brand span{color:var(--gold);}
  .header-right{display:flex;align-items:center;gap:14px;}
  .badge{background:var(--rust);color:#fff;font-size:11px;font-weight:700;padding:3px 8px;}
  .logout-btn{background:none;border:1px solid rgba(200,151,59,.4);color:var(--sand);font-size:11px;letter-spacing:.1em;text-transform:uppercase;padding:6px 14px;cursor:pointer;transition:background .2s,color .2s,border-color .2s;}
  .logout-btn:hover{background:var(--gold);color:var(--ink);border-color:var(--gold);}

  /* ── Admin body ── */
  .admin-body{max-width:900px;margin:40px auto;padding:0 24px;}
  .section-title{font-size:12px;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:var(--sand);margin-bottom:20px;padding-bottom:10px;border-bottom:1px solid rgba(200,151,59,.2);}
  .empty{font-size:14px;color:var(--sand);padding:32px 0;}

  /* ── Message card ── */
  .msg-card{background:#fff;border:1px solid rgba(92,58,30,.12);border-left:4px solid var(--rust);margin-bottom:16px;padding:20px 24px;transition:opacity .2s;}
  .msg-card.is-read{border-left-color:rgba(92,58,30,.15);opacity:.65;}
  .msg-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px;}
  .msg-sender{font-size:15px;font-weight:600;color:var(--bark);}
  .msg-email{font-size:13px;color:var(--rust);margin-top:3px;}
  .msg-phone{font-size:13px;color:var(--leather);margin-top:2px;}
  .msg-meta{text-align:right;flex-shrink:0;}
  .msg-date{font-size:12px;color:var(--sand);}
  .msg-new{display:inline-block;background:var(--rust);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;letter-spacing:.06em;text-transform:uppercase;margin-bottom:4px;}
  .msg-text{font-size:14px;line-height:1.75;color:#4a3828;white-space:pre-wrap;word-break:break-word;border-top:1px solid rgba(92,58,30,.08);padding-top:12px;}
  .msg-actions{display:flex;gap:8px;margin-top:14px;}
  .btn-read{background:var(--bark);color:var(--gold);font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:7px 16px;border:none;cursor:pointer;transition:background .2s,color .2s;}
  .btn-read:hover{background:var(--gold);color:var(--ink);}
  .btn-del{background:none;color:#c0392b;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:7px 16px;border:1px solid rgba(192,57,43,.35);cursor:pointer;transition:background .2s,color .2s,border-color .2s;}
  .btn-del:hover{background:#c0392b;color:#fff;border-color:#c0392b;}
</style>
</head>
<body>

<?php if (!isset($_SESSION['ll_admin'])): ?>

<div class="login-wrap">
  <div class="login-box">
    <h1>Lucky Look</h1>
    <p>Administration des messages</p>
    <?php if ($error): ?>
      <p class="login-error"><?= htmlspecialchars($error) ?></p>
    <?php endif ?>
    <form method="post" autocomplete="off">
      <input type="password" name="password" placeholder="Mot de passe" autofocus autocomplete="current-password">
      <button type="submit">Accéder</button>
    </form>
  </div>
</div>

<?php else: ?>

<header class="admin-header">
  <div class="brand">Lucky Look &mdash; <span>Messages</span></div>
  <div class="header-right">
    <?php if ($unread > 0): ?>
      <span class="badge"><?= $unread ?> non lu<?= $unread > 1 ? 's' : '' ?></span>
    <?php endif ?>
    <form method="post" style="display:inline">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <button type="submit" name="logout" value="1" class="logout-btn">Déconnexion</button>
    </form>
  </div>
</header>

<div class="admin-body">
  <p class="section-title"><?= count($messages) ?> message<?= count($messages) !== 1 ? 's' : '' ?> au total</p>

  <?php if ($dbError): ?>
    <p class="empty"><?= htmlspecialchars($dbError) ?></p>
  <?php elseif (empty($messages)): ?>
    <p class="empty">Aucun message pour le moment.</p>
  <?php else: ?>

    <?php foreach ($messages as $m): ?>
      <?php $isRead = !empty($m['read_at']); ?>
      <div class="msg-card <?= $isRead ? 'is-read' : '' ?>">
        <div class="msg-card-head">
          <div>
            <div class="msg-sender">
              <?= htmlspecialchars(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: 'Anonyme') ?>
            </div>
            <div class="msg-email"><?= htmlspecialchars($m['email']) ?></div>
            <?php if (!empty($m['phone'])): ?>
              <div class="msg-phone">📞 <?= htmlspecialchars($m['phone']) ?></div>
            <?php endif ?>
          </div>
          <div class="msg-meta">
            <?php if (!$isRead): ?>
              <div class="msg-new">Nouveau</div>
            <?php endif ?>
            <div class="msg-date"><?= htmlspecialchars($m['created_at']) ?></div>
          </div>
        </div>
        <div class="msg-text"><?= htmlspecialchars($m['message']) ?></div>
        <div class="msg-actions">
          <?php if (!$isRead): ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="mark_read" value="<?= (int)$m['id'] ?>">
              <button type="submit" class="btn-read">Marquer comme lu</button>
            </form>
          <?php endif ?>
          <form method="post" onsubmit="return confirm('Supprimer définitivement ce message ?')">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="delete" value="<?= (int)$m['id'] ?>">
            <button type="submit" class="btn-del">Supprimer</button>
          </form>
        </div>
      </div>
    <?php endforeach ?>

  <?php endif ?>
</div>

<?php endif ?>

</body>
</html>
