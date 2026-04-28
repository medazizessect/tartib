<?php
error_reporting(0);
ini_set('display_errors', 0);

$host     = 'localhost';
$dbname   = 'batiments_ruine';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $hasMembres = $pdo->query("SHOW TABLES LIKE 'membres'")->fetchColumn();
    if ($hasMembres) {
        $hasStepPermissions = $pdo->query("SHOW COLUMNS FROM membres LIKE 'step_permissions'")->fetchColumn();
        if (!$hasStepPermissions) {
            $pdo->exec("ALTER TABLE membres ADD COLUMN step_permissions TEXT NULL AFTER role");
        }

        // Migrate role column from ENUM to VARCHAR if needed (to support new user roles)
        $roleColInfo = $pdo->query("SHOW COLUMNS FROM membres LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
        if ($roleColInfo && strpos((string)($roleColInfo['Type'] ?? ''), 'enum') !== false) {
            $pdo->exec("ALTER TABLE membres MODIFY COLUMN role VARCHAR(30) NOT NULL");
        }

        $adminPwdStmt = $pdo->prepare("SELECT id, password FROM membres WHERE username='admin' LIMIT 1");
        $adminPwdStmt->execute();
        $adminRow = $adminPwdStmt->fetch(PDO::FETCH_ASSOC);
        if ($adminRow) {
            $stored = (string)($adminRow['password'] ?? '');
            $isHashed = !empty(password_get_info($stored)['algo']);
            $needsUpdate = $isHashed ? password_verify('admin123', $stored) : hash_equals($stored, 'admin123');
            if ($needsUpdate) {
                $pdo->prepare("UPDATE membres SET password=? WHERE id=?")
                    ->execute([password_hash('admin1912', PASSWORD_DEFAULT), (int)$adminRow['id']]);
            }
        }

        // Add new users if they don't exist
        $newUsers = [
            ['ALI',    'ali',    'ali',    json_encode(['step1_reclamation','step2_pv'], JSON_UNESCAPED_UNICODE), 'ali123'],
            ['MOURAD', 'mourad', 'mourad', json_encode(['step1_reclamation','step2_pv'], JSON_UNESCAPED_UNICODE), 'mourad123'],
            ['AHMED',  'ahmed',  'ahmed',  json_encode(['step1_reclamation','step2_pv'], JSON_UNESCAPED_UNICODE), 'ahmed123'],
        ];
        $checkUser = $pdo->prepare("SELECT COUNT(*) FROM membres WHERE username=?");
        $addUser   = $pdo->prepare("INSERT IGNORE INTO membres (nom, username, role, step_permissions, password, actif) VALUES (?,?,?,?,?,1)");
        foreach ($newUsers as [$nom, $uname, $role, $perms, $pwd]) {
            $checkUser->execute([$uname]);
            if (!(int)$checkUser->fetchColumn()) {
                $addUser->execute([$nom, $uname, $role, $perms, password_hash($pwd, PASSWORD_DEFAULT)]);
            }
        }
    }

    $hasCommissionMembers = $pdo->query("SHOW TABLES LIKE 'commission_members'")->fetchColumn();
    if (!$hasCommissionMembers) {
        $pdo->exec("
            CREATE TABLE commission_members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titre VARCHAR(120) NOT NULL,
                nom VARCHAR(120) NOT NULL,
                ordre INT NOT NULL DEFAULT 0,
                actif TINYINT(1) DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
        ");
        $pdo->exec("
            INSERT INTO commission_members (titre, nom, ordre, actif) VALUES
            ('رئيس اللجنة', 'ممثل البلدية', 1, 1),
            ('عضو', 'ممثل الحماية المدنية', 2, 1),
            ('عضو', 'ممثل الشرطة البلدية', 3, 1)
        ");
    }

    $hasCommissionMembersColumn = $pdo->query("SHOW COLUMNS FROM documents_officiels LIKE 'commission_members'")->fetchColumn();
    if (!$hasCommissionMembersColumn) {
        $pdo->exec("ALTER TABLE documents_officiels ADD COLUMN commission_members TEXT NULL AFTER confirmation_degree");
    }

    // Create calendar_events table if not exists
    $hasCalendar = $pdo->query("SHOW TABLES LIKE 'calendar_events'")->fetchColumn();
    if (!$hasCalendar) {
        $pdo->exec("
            CREATE TABLE calendar_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                batiment_id INT NULL,
                titre VARCHAR(255) NOT NULL,
                date_evenement DATE NOT NULL,
                notes TEXT NULL,
                created_by VARCHAR(80) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (batiment_id) REFERENCES batiments(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
        ");
    }
} catch (PDOException $e) {
    die("
    <div style='font-family:Arial;padding:30px;text-align:center'>
        <h2 style='color:red'>❌ خطأ في الاتصال بقاعدة البيانات</h2>
        <p style='color:#666;margin:10px 0'>" . $e->getMessage() . "</p>
        <a href='init_db.php'
           style='background:#1a3c5e;color:white;padding:10px 20px;
                  border-radius:6px;text-decoration:none'>
            🔧 إنشاء قاعدة البيانات
        </a>
    </div>");
}
?>
