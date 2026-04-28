<?php
require 'config.php';
requireRole('admin');
require 'db.php';

$stepLabels = [
    'step1_reclamation'    => 'شكاوي (مرحلة 1)',
    'step2_pv'             => 'محضر (مرحلة 2)',
    'step3_expert_request' => 'تكليف خبير (مرحلة 3)',
    'step4_expert_report'  => 'رجوع التقرير (مرحلة 4)',
    'step5_decision'       => 'القرار النهائي (مرحلة 5)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $stepPermissionsInput = array_filter($_POST['step_permissions'] ?? [], 'is_string');
    $selectedSteps = array_values(array_unique(array_intersect(stepTypes(), $stepPermissionsInput)));
    $stepPermissionsJson = json_encode($selectedSteps, JSON_UNESCAPED_UNICODE);

    if ($action === 'add_user') {
        $role = !empty($_POST['is_admin']) ? 'admin' : 'haifa';
        $pdo->prepare("INSERT INTO membres (nom, username, role, step_permissions, password, actif) VALUES (?,?,?,?,?,1)")
            ->execute([
                trim($_POST['nom'] ?? ''),
                trim($_POST['username'] ?? ''),
                $role,
                $stepPermissionsJson,
                password_hash(trim($_POST['password'] ?? ''), PASSWORD_DEFAULT),
            ]);
    } elseif ($action === 'toggle_user') {
        $pdo->prepare("UPDATE membres SET actif = 1 - actif WHERE id=?")->execute([intval($_POST['id'] ?? 0)]);
    } elseif ($action === 'update_user') {
        $id = intval($_POST['id'] ?? 0);
        $currentRoleStmt = $pdo->prepare("SELECT role FROM membres WHERE id=?");
        $currentRoleStmt->execute([$id]);
        $currentRole = (string)$currentRoleStmt->fetchColumn();
        $newRole = !empty($_POST['is_admin']) ? 'admin' : ($currentRole === 'admin' ? 'haifa' : $currentRole);

        $sql = "UPDATE membres SET nom=?, role=?, step_permissions=?";
        $params = [trim($_POST['nom'] ?? ''), $newRole, $stepPermissionsJson];
        if (trim($_POST['password'] ?? '') !== '') {
            $sql .= ", password=?";
            $params[] = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        }
        $sql .= " WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
    } elseif ($action === 'delete_user') {
        $id = intval($_POST['id'] ?? 0);
        $uStmt = $pdo->prepare("SELECT username, role FROM membres WHERE id=?");
        $uStmt->execute([$id]);
        $toDelete = $uStmt->fetch(PDO::FETCH_ASSOC);
        if ($toDelete) {
            $currentUsername = $_SESSION['user']['username'] ?? '';
            if ((string)$toDelete['username'] !== $currentUsername) {
                $canDelete = false;
                if ((string)$toDelete['role'] !== 'admin') {
                    $canDelete = true;
                } else {
                    $remainingAdminsStmt = $pdo->prepare("SELECT COUNT(*) FROM membres WHERE role='admin' AND id<>?");
                    $remainingAdminsStmt->execute([$id]);
                    $remainingAdmins = (int)$remainingAdminsStmt->fetchColumn();
                    $canDelete = ($remainingAdmins >= 1);
                }
                if ($canDelete) $pdo->prepare("DELETE FROM membres WHERE id=?")->execute([$id]);
            }
        }
    } elseif ($action === 'add_address') {
        $lib = trim($_POST['libelle'] ?? '');
        if ($lib !== '') $pdo->prepare("INSERT IGNORE INTO adresses (libelle) VALUES (?)")->execute([$lib]);
    } elseif ($action === 'delete_address') {
        $pdo->prepare("DELETE FROM adresses WHERE id=?")->execute([intval($_POST['id'] ?? 0)]);
    } elseif ($action === 'add_commission_member') {
        $nom = trim($_POST['nom'] ?? '');
        $titre = trim($_POST['titre'] ?? '');
        $ordreInput = (int)($_POST['ordre'] ?? 0);
        if ($nom !== '' && $titre !== '') {
            $nextOrder = $ordreInput > 0
                ? $ordreInput
                : (int)$pdo->query("SELECT COALESCE(MAX(ordre), 0) + 1 FROM commission_members")->fetchColumn();
            $pdo->prepare("INSERT INTO commission_members (titre, nom, ordre, actif) VALUES (?,?,?,1)")
                ->execute([$titre, $nom, $nextOrder]);
        }
    } elseif ($action === 'update_commission_member') {
        $id = intval($_POST['id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $titre = trim($_POST['titre'] ?? '');
        $ordre = max(1, intval($_POST['ordre'] ?? 1));
        if ($id > 0 && $nom !== '' && $titre !== '') {
            $pdo->prepare("UPDATE commission_members SET titre=?, nom=?, ordre=? WHERE id=?")
                ->execute([$titre, $nom, $ordre, $id]);
        }
    } elseif ($action === 'delete_commission_member') {
        $pdo->prepare("DELETE FROM commission_members WHERE id=?")->execute([intval($_POST['id'] ?? 0)]);
    }
    header("Location: membres.php?ok=1");
    exit;
}

$users = $pdo->query("SELECT * FROM membres ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$addresses = $pdo->query("SELECT * FROM adresses ORDER BY libelle ASC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
$commissionMembers = $pdo->query("SELECT * FROM commission_members ORDER BY ordre ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>الإدارة</title>
<style>
*{box-sizing:border-box} body{font-family:Segoe UI,Arial;background:#f0f2f5;direction:rtl;margin:0}
header{background:linear-gradient(135deg,#6f42c1,#9b59b6);color:#fff;padding:18px 26px}
.wrap{max-width:1000px;margin:20px auto;padding:0 15px}.card{background:#fff;border-radius:12px;padding:18px;margin-bottom:14px;box-shadow:0 3px 12px rgba(0,0,0,.08)}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #e9ecef;padding:8px;font-size:12px}th{background:#f8f9fa}
input,select{padding:7px 9px;border:1px solid #ddd;border-radius:7px;width:100%;font-family:inherit}
.btn{padding:7px 10px;border:none;border-radius:7px;cursor:pointer}.b1{background:#28a745;color:#fff}.b2{background:#17a2b8;color:#fff}.b3{background:#dc3545;color:#fff}
</style>
</head>
<body>
<?php include '_menu.php'; ?>
<header><h2 style="margin:0">الإدارة</h2></header>
<div class="wrap">
<?php if (!empty($_GET['ok'])): ?><div style="background:#d4edda;padding:10px;border:1px solid #c3e6cb;border-radius:8px;margin-bottom:10px">✅ تم الحفظ</div><?php endif; ?>

<div class="card">
    <h3>أعضاء اللجنة</h3>
    <form method="POST" style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap">
        <input type="hidden" name="action" value="add_commission_member">
        <input name="titre" placeholder="الصفة" required style="max-width:220px">
        <input name="nom" placeholder="الاسم واللقب" required style="max-width:260px">
        <input type="number" name="ordre" min="1" placeholder="الترتيب" style="max-width:120px">
        <button class="btn b1">➕</button>
    </form>
    <div style="max-height:260px;overflow:auto">
        <table>
            <tr><th>#</th><th>الاسم واللقب</th><th>الصفة</th><th>إجراءات</th></tr>
            <?php foreach($commissionMembers as $cm): ?>
            <?php $formId = 'cm-update-' . (int)$cm['id']; ?>
            <tr>
                <td>
                    <input type="number" min="1" name="ordre" value="<?= (int)$cm['ordre'] ?>" style="max-width:80px" form="<?= $formId ?>">
                </td>
                <td><input name="titre" value="<?= htmlspecialchars($cm['titre']) ?>" style="min-width:160px" form="<?= $formId ?>"></td>
                <td><input name="nom" value="<?= htmlspecialchars($cm['nom']) ?>" style="min-width:220px" form="<?= $formId ?>"></td>
                <td style="display:flex;gap:6px">
                    <form method="POST" id="<?= $formId ?>">
                        <input type="hidden" name="action" value="update_commission_member">
                        <input type="hidden" name="id" value="<?= (int)$cm['id'] ?>">
                    </form>
                    <button class="btn b1" type="submit" form="<?= $formId ?>">💾</button>
                    <form method="POST" onsubmit="return confirm('حذف عضو اللجنة؟')">
                        <input type="hidden" name="action" value="delete_commission_member">
                        <input type="hidden" name="id" value="<?= (int)$cm['id'] ?>">
                        <button class="btn b3" type="submit">🗑️</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<div class="card">
    <h3>التصرف في المستعملين</h3>
    <table>
        <tr><th>الاسم</th><th>Username</th><th>صلاحيات المراحل</th><th>كلمة مرور جديدة</th><th>الحالة</th><th>إجراءات</th></tr>
        <?php foreach($users as $u): ?>
        <?php $uPerms = normalizeStepPermissions($u['step_permissions'] ?? null, $u['role'] ?? ''); ?>
        <tr>
            <form method="POST">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <td><input name="nom" value="<?= htmlspecialchars($u['nom']) ?>"></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td>
                    <label style="display:block;margin-bottom:6px"><input type="checkbox" name="is_admin" value="1" <?= ($u['role'] ?? '') === 'admin' ? 'checked' : '' ?>> مدير</label>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <?php foreach($stepLabels as $stepKey => $stepLabel): ?>
                            <label style="display:flex;align-items:center;gap:3px"><input type="checkbox" name="step_permissions[]" value="<?= $stepKey ?>" <?= !empty($uPerms[$stepKey]) ? 'checked' : '' ?>> <?= $stepLabel ?></label>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td><input name="password" placeholder="اتركه فارغًا بدون تغيير"></td>
                <td><?= $u['actif'] ? 'نشط' : 'معطل' ?></td>
                <td style="display:flex;gap:6px">
                    <button class="btn b1" type="submit">💾</button>
            </form>
            <form method="POST">
                <input type="hidden" name="action" value="toggle_user">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="btn b2" type="submit"><?= $u['actif'] ? '🔕' : '🔔' ?></button>
            </form>
            <form method="POST" onsubmit="return confirm('حذف المستخدم؟')">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="btn b3" type="submit">🗑️</button>
            </form>
                </td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <td><input name="nom" required></td>
                <td><input name="username" required></td>
                <td>
                    <label style="display:block;margin-bottom:6px"><input type="checkbox" name="is_admin" value="1"> مدير</label>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <?php foreach($stepLabels as $stepKey => $stepLabel): ?>
                            <label style="display:flex;align-items:center;gap:3px"><input type="checkbox" name="step_permissions[]" value="<?= $stepKey ?>"> <?= $stepLabel ?></label>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td><input name="password" required></td>
                <td>جديد</td>
                <td><button class="btn b1">➕</button></td>
            </form>
        </tr>
    </table>
</div>

<div class="card">
    <h3>العنوان</h3>
    <form method="POST" style="display:flex;gap:8px;margin-bottom:10px">
        <input type="hidden" name="action" value="add_address">
        <input name="libelle" placeholder="أضف عنوانًا جديدًا" required>
        <button class="btn b1">➕</button>
    </form>
    <div style="max-height:360px;overflow:auto">
        <table>
            <tr><th>ID</th><th>العنوان</th><th></th></tr>
            <?php foreach($addresses as $a): ?>
            <tr><td><?= $a['id'] ?></td><td><?= htmlspecialchars($a['libelle']) ?></td><td>
                <form method="POST"><input type="hidden" name="action" value="delete_address"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn b3" onclick="return confirm('حذف؟')">🗑️</button></form>
            </td></tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
</div>
</body>
</html>
