<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$username = 'root';
$password = '';
require_once __DIR__ . '/address_import_utils.php';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS batiments_ruine CHARACTER SET utf8 COLLATE utf8_unicode_ci");
    $pdo->exec("USE batiments_ruine");

    $pdo->exec("DROP TABLE IF EXISTS correspondences");
    $pdo->exec("DROP TABLE IF EXISTS calendar_events");
    $pdo->exec("DROP TABLE IF EXISTS documents_officiels");
    $pdo->exec("DROP TABLE IF EXISTS batiments");
    $pdo->exec("DROP TABLE IF EXISTS adresses");
    $pdo->exec("DROP TABLE IF EXISTS commission_members");
    $pdo->exec("DROP TABLE IF EXISTS pv_states");
    $pdo->exec("DROP TABLE IF EXISTS membres");
    $pdo->exec("DROP TABLE IF EXISTS modeles_documents");

    $pdo->exec("
        CREATE TABLE batiments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bureau_ordre_id VARCHAR(60) NOT NULL,
            date_reclamation DATE NULL,
            proprietaire VARCHAR(255) NULL,
            reclamation_scan_path VARCHAR(255) NULL,
            notification_pending TINYINT(1) DEFAULT 0,
            numero_rapport VARCHAR(20) NULL,
            lieu TEXT NULL,
            date_rapport DATE NULL,
            mise_a_jour VARCHAR(100) NULL,
            notification VARCHAR(100) NULL,
            exploite_oui TINYINT(1) DEFAULT 0,
            exploite_non TINYINT(1) DEFAULT 0,
            commission TEXT NULL,
            date_envoi_tratiib DATE NULL,
            date_envoi_wiz DATE NULL,
            date_envoi_turat DATE NULL,
            date_envoi_juridique DATE NULL,
            date_expert DATE NULL,
            decision_evacuation TEXT NULL,
            decision_demolition TEXT NULL,
            observations TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE adresses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            libelle VARCHAR(255) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ");

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
        CREATE TABLE pv_states (
            id INT AUTO_INCREMENT PRIMARY KEY,
            libelle VARCHAR(120) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE documents_officiels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batiment_id INT NOT NULL,
            type ENUM('step2_pv','step3_expert_request','step4_expert_report','step5_decision') NOT NULL,
            preceding_document_id INT NULL,
            statut ENUM('brouillon','finalise') DEFAULT 'brouillon',
            numero_doc VARCHAR(50) NULL,
            date_doc DATE NULL,
            cin VARCHAR(30) NULL,
            owner_name VARCHAR(255) NULL,
            exploite_by ENUM('oui','non') NULL,
            occupied_by VARCHAR(255) NULL,
            confirmation_degree VARCHAR(80) NULL,
            commission_members TEXT NULL,
            address_id INT NULL,
            pv_state_id INT NULL,
            forward_to_ministry TINYINT(1) DEFAULT 0,
            subject VARCHAR(255) NULL,
            administration VARCHAR(120) NULL,
            direction_io ENUM('sader','wared') NULL,
            expert_name VARCHAR(150) NULL,
            report_type ENUM('initial','final') NULL,
            heritage_needed TINYINT(1) DEFAULT 0,
            heritage_direction ENUM('sader','wared') NULL,
            appointment_date DATE NULL,
            decision_type ENUM('evacuation','demolition') NULL,
            attachment_path VARCHAR(255) NULL,
            observations TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_doc (batiment_id, type),
            FOREIGN KEY (batiment_id) REFERENCES batiments(id) ON DELETE CASCADE,
            FOREIGN KEY (preceding_document_id) REFERENCES documents_officiels(id) ON DELETE SET NULL,
            FOREIGN KEY (address_id) REFERENCES adresses(id) ON DELETE SET NULL,
            FOREIGN KEY (pv_state_id) REFERENCES pv_states(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE correspondences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batiment_id INT NOT NULL,
            step_type ENUM('step3_expert_request','step4_expert_report') NOT NULL,
            bureau_ordre_id VARCHAR(60) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            administration VARCHAR(120) NOT NULL,
            direction_io ENUM('sader','wared') NOT NULL,
            attachment_path VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (batiment_id) REFERENCES batiments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE membres (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(120) NOT NULL,
            username VARCHAR(80) NOT NULL UNIQUE,
            role VARCHAR(30) NOT NULL,
            step_permissions TEXT NULL,
            password VARCHAR(120) NOT NULL,
            actif TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ");
    $adminPassword = getenv('ADMIN_PASSWORD') ?: 'admin1912';
    $haifaPassword = getenv('HAIFA_PASSWORD') ?: 'haifa123';
    $khaoulaPassword = getenv('KHAOULA_PASSWORD') ?: 'khaoula123';
    $mohamedPassword = getenv('MOHAMED_PASSWORD') ?: 'mohamed123';
    $insUser = $pdo->prepare("INSERT INTO membres (nom, username, role, step_permissions, password) VALUES (?,?,?,?,?)");
    $insUser->execute(['المدير', 'admin', 'admin', json_encode(['step1_reclamation','step2_pv','step3_expert_request','step4_expert_report','step5_decision'], JSON_UNESCAPED_UNICODE), password_hash($adminPassword, PASSWORD_DEFAULT)]);
    $insUser->execute(['HAIFA', 'haifa', 'haifa', json_encode(['step1_reclamation','step2_pv'], JSON_UNESCAPED_UNICODE), password_hash($haifaPassword, PASSWORD_DEFAULT)]);
    $insUser->execute(['KHAOULA', 'khaoula', 'khaoula', json_encode(['step3_expert_request','step4_expert_report'], JSON_UNESCAPED_UNICODE), password_hash($khaoulaPassword, PASSWORD_DEFAULT)]);
    $insUser->execute(['MOHAMED', 'mohamed', 'mohamed', json_encode(['step5_decision'], JSON_UNESCAPED_UNICODE), password_hash($mohamedPassword, PASSWORD_DEFAULT)]);
    $insUser->execute(['ALI', 'ali', 'ali', json_encode(['step1_reclamation','step2_pv'], JSON_UNESCAPED_UNICODE), password_hash('ali123', PASSWORD_DEFAULT)]);
    $insUser->execute(['MOURAD', 'mourad', 'mourad', json_encode(['step1_reclamation','step2_pv'], JSON_UNESCAPED_UNICODE), password_hash('mourad123', PASSWORD_DEFAULT)]);
    $insUser->execute(['AHMED', 'ahmed', 'ahmed', json_encode(['step1_reclamation','step2_pv'], JSON_UNESCAPED_UNICODE), password_hash('ahmed123', PASSWORD_DEFAULT)]);

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

    $pdo->exec("
        CREATE TABLE modeles_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL UNIQUE,
            intro LONGTEXT,
            contenu LONGTEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ");
    $pdo->prepare("
        INSERT INTO modeles_documents (type, intro, contenu) VALUES
        ('step2_pv', :s2, :s2),
        ('step3_expert_request', :s3, :s3),
        ('step4_expert_report', :s4, :s4),
        ('step5_decision', :s5, :s5)
    ")->execute([
        ':s2' => 'محضر معاينة للبناية المتداعية للسقوط.',
        ':s3' => 'مراسلة المحكمة/الإدارة لتكليف خبير.',
        ':s4' => 'رجوع تقرير الخبير وتحديد موعد التوجه.',
        ':s5' => 'قرار نهائي: إخلاء أو هدم.',
    ]);

    $states = ['مسودة', 'نهائي', 'قيد المعالجة'];
    $insState = $pdo->prepare("INSERT INTO pv_states (libelle) VALUES (?)");
    foreach ($states as $st) $insState->execute([$st]);

    $insCommission = $pdo->prepare("INSERT INTO commission_members (titre, nom, ordre, actif) VALUES (?,?,?,1)");
    $insCommission->execute(['رئيس اللجنة', 'ممثل البلدية', 1]);
    $insCommission->execute(['عضو', 'ممثل الحماية المدنية', 2]);
    $insCommission->execute(['عضو', 'ممثل الشرطة البلدية', 3]);

    $addresses = readArabicAddressesFromXlsx(__DIR__ . '/VOIE_Nom_Rues_Arabe_2026.xlsx');
    $insAdr = $pdo->prepare("INSERT IGNORE INTO adresses (libelle) VALUES (?)");
    foreach ($addresses as $a) $insAdr->execute([$a]);
    if (empty($addresses)) {
        foreach (['نهج الحبيب بورقيبة','نهج الجمهورية','حي الرياض'] as $fallback) {
            $insAdr->execute([$fallback]);
        }
    }

    echo "<!doctype html><html lang='ar' dir='rtl'><head><meta charset='utf-8'><title>تهيئة</title>
    <style>body{font-family:Arial;background:#f0f2f5;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
    .b{background:#fff;padding:35px 42px;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.12);text-align:center}
    a{display:inline-block;margin-top:15px;padding:10px 20px;background:#1a3c5e;color:#fff;text-decoration:none;border-radius:8px}</style>
    </head><body><div class='b'><h2>✅ تم تحديث قاعدة البيانات</h2>
    <p>خطوات جديدة + أدوار + عناوين + حالات محضر</p>
    <p>عدد العناوين: ".(int)$pdo->query("SELECT COUNT(*) FROM adresses")->fetchColumn()."</p>
    <a href='index.php'>الدخول للتطبيق</a></div></body></html>";
} catch (PDOException $e) {
    die("<div style='font-family:Arial;color:red;padding:20px'>❌ ".$e->getMessage()."</div>");
}
?>
