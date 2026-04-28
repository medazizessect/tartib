<?php
define('DEFAULT_USERS', [
    'admin'   => ['password' => 'admin1912',  'role' => 'admin',   'nom' => 'المدير'],
    'haifa'   => ['password' => 'haifa123',   'role' => 'haifa',   'nom' => 'HAIFA'],
    'khaoula' => ['password' => 'khaoula123', 'role' => 'khaoula', 'nom' => 'KHAOULA'],
    'mohamed' => ['password' => 'mohamed123', 'role' => 'mohamed', 'nom' => 'MOHAMED'],
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return !empty($_SESSION['user']);
}

function currentRole() {
    return $_SESSION['user']['role'] ?? '';
}

function stepTypes() {
    return [
        'step1_reclamation',
        'step2_pv',
        'step3_expert_request',
        'step4_expert_report',
        'step5_decision',
    ];
}

function normalizeStepPermissions($raw, $role = '') {
    $all = stepTypes();
    if ($role === 'admin') {
        return array_fill_keys($all, true);
    }

    $normalized = array_fill_keys($all, false);

    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $raw = $decoded;
    }

    if (is_array($raw)) {
        foreach ($all as $step) {
            if ((array_key_exists($step, $raw) && !empty($raw[$step])) || in_array($step, $raw, true)) {
                $normalized[$step] = true;
            }
        }
        return $normalized;
    }

    $legacyMap = [
        'haifa' => ['step1_reclamation', 'step2_pv'],
        'khaoula' => ['step3_expert_request', 'step4_expert_report'],
        'mohamed' => ['step5_decision'],
    ];
    foreach ($legacyMap[$role] ?? [] as $step) {
        $normalized[$step] = true;
    }
    return $normalized;
}

function hasAnyRole($roles) {
    if (!isLoggedIn()) return false;
    return in_array(currentRole(), $roles, true);
}

function hasRole($role) {
    if (!isLoggedIn()) return false;

    $current = currentRole();
    $legacyRanks = [
        'viewer'  => 1,
        'mohamed' => 2,
        'khaoula' => 3,
        'haifa'   => 4,
        'admin'   => 5,
    ];

    if ($role === 'agent') {
        return in_array($current, ['admin','haifa','khaoula','mohamed','ali','mourad','ahmed'], true);
    }
    if ($role === 'viewer') {
        return true;
    }

    return ($legacyRanks[$current] ?? 0) >= ($legacyRanks[$role] ?? 0);
}

function hasStepAccess($stepType) {
    if (!isLoggedIn()) return false;
    if (currentRole() === 'admin') return true;
    $permissions = normalizeStepPermissions($_SESSION['user']['step_permissions'] ?? null, currentRole());
    return !empty($permissions[$stepType]);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

function denyAccess() {
    die("
    <div style='font-family:Arial;text-align:center;padding:60px;direction:rtl'>
        <div style='font-size:48px;margin-bottom:20px'>🚫</div>
        <h2 style='color:#dc3545'>غير مصرح لك بهذه العملية</h2>
        <a href='index.php'
           style='background:#2e6da4;color:white;padding:10px 24px;
                  border-radius:8px;text-decoration:none;margin-top:20px;
                  display:inline-block'>↩️ رجوع</a>
    </div>");
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) denyAccess();
}

function requireStepAccess($stepType) {
    requireLogin();
    if (!hasStepAccess($stepType)) denyAccess();
}

function roleLabel($role) {
    return [
        'admin'   => 'مدير',
        'haifa'   => 'HAIFA',
        'khaoula' => 'KHAOULA',
        'mohamed' => 'MOHAMED',
        'ali'     => 'ALI',
        'mourad'  => 'MOURAD',
        'ahmed'   => 'AHMED',
    ][$role] ?? $role;
}
?>
