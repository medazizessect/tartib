<?php
require 'config.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$users = DEFAULT_USERS;
try {
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: 'batiments_ruine';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASSWORD') ?: '';
    $pdoTmp = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8", $dbUser, $dbPass);
    $pdoTmp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $hasTable = $pdoTmp->query("SHOW TABLES LIKE 'membres'")->fetchColumn();
    if ($hasTable) {
        $hasStepPermissions = $pdoTmp->query("SHOW COLUMNS FROM membres LIKE 'step_permissions'")->fetchColumn();
        $sql = $hasStepPermissions
            ? "SELECT username, nom, role, password, step_permissions FROM membres WHERE actif=1"
            : "SELECT username, nom, role, password, NULL AS step_permissions FROM membres WHERE actif=1";
        $rows = $pdoTmp->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $users = [];
            foreach ($rows as $r) {
                $users[$r['username']] = [
                    'password' => $r['password'],
                    'role' => $r['role'],
                    'nom' => $r['nom'],
                    'step_permissions' => normalizeStepPermissions($r['step_permissions'] ?? null, $r['role'] ?? ''),
                ];
            }
        }
    }
} catch (Throwable $e) {
    error_log('Login DB fallback activated.');
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $valid = false;
    if (isset($users[$username])) {
        $stored = (string)$users[$username]['password'];
        if (preg_match('/^\$2y\$/', $stored)) {
            $valid = password_verify($password, $stored);
        } else {
            $valid = hash_equals($stored, $password);
        }
    }

    if ($valid) {
        $_SESSION['user'] = [
            'username' => $username,
            'nom'      => $users[$username]['nom'],
            'role'     => $users[$username]['role'],
            'step_permissions' => $users[$username]['step_permissions'] ?? normalizeStepPermissions(null, $users[$username]['role'] ?? ''),
        ];
        header("Location: index.php");
        exit;
    }

    $error = 'بيانات الدخول غير صحيحة';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول — بلدية سوسة</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:'Segoe UI',Arial,sans-serif;
            background:linear-gradient(135deg,#1a3c5e,#2e6da4);
            min-height:100vh;display:flex;align-items:center;justify-content:center;direction:rtl;
        }
        .login-wrap{
            background:white;border-radius:16px;padding:34px 30px;
            width:100%;max-width:430px;box-shadow:0 16px 50px rgba(0,0,0,.25);
        }
        .login-logo{text-align:center;margin-bottom:18px}
        .login-logo img{width:96px;height:96px;object-fit:contain}
        .login-logo h1{font-size:20px;color:#1a3c5e}
        .login-logo p{font-size:13px;color:#888;margin-top:5px}
        .error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;padding:10px 12px;border-radius:8px;margin-bottom:12px;font-size:13px}
        .fg{margin-bottom:12px}
        .fg label{display:block;margin-bottom:6px;font-size:13px;color:#666;font-weight:600}
        input{
            width:100%;padding:11px 12px;border:2px solid #e9ecef;border-radius:8px;
            font-family:inherit;font-size:14px;
        }
        input:focus{outline:none;border-color:#2e6da4}
        .users{
        .btn{
            width:100%;padding:12px;border:none;border-radius:8px;cursor:pointer;
            background:linear-gradient(135deg,#1a3c5e,#2e6da4);color:white;font-weight:700;font-family:inherit;
        }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-logo">
        <img src="Logo_commune_Sousse.svg" alt="Logo commune de Sousse">
        <h1>بلدية سوسة</h1>
        <p>نظام متابعة مسار البنايات المتداعية</p>
    </div>

    <?php if ($error): ?><div class="error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
        <div class="fg">
            <label>اسم المستخدم</label>
            <input type="text" name="username" autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
        </div>

        <div class="fg">
            <label>كلمة المرور</label>
            <input type="password" name="password" autocomplete="current-password" required>
        </div>
        <button class="btn" type="submit">🚀 تسجيل الدخول</button>
    </form>
</div>
</body>
</html>
