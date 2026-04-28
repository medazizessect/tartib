<?php
require 'config.php';
requireLogin();
require 'db.php';
require '_steps_config.php';

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $q = $pdo->prepare("
        SELECT DISTINCT b.*
        FROM batiments b
        LEFT JOIN documents_officiels d ON d.batiment_id = b.id AND d.type='step2_pv'
        LEFT JOIN adresses a ON a.id = d.address_id
        WHERE b.bureau_ordre_id LIKE :s OR b.proprietaire LIKE :s OR b.lieu LIKE :s OR a.libelle LIKE :s
        ORDER BY b.id DESC
    ");
    $q->execute([':s' => "%$search%"]);
} else {
    $q = $pdo->query("SELECT * FROM batiments ORDER BY id DESC");
}
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

$docs = [];
if ($rows) {
    $ids = array_column($rows, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $d = $pdo->prepare("SELECT batiment_id, type, statut FROM documents_officiels WHERE batiment_id IN ($ph)");
    $d->execute($ids);
    foreach ($d->fetchAll(PDO::FETCH_ASSOC) as $r) $docs[$r['batiment_id']][$r['type']] = $r['statut'];
}

$corr = [];
if ($rows) {
    $ids = array_column($rows, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $c = $pdo->prepare("SELECT * FROM correspondences WHERE batiment_id IN ($ph) ORDER BY id DESC");
    $c->execute($ids);
    foreach ($c->fetchAll(PDO::FETCH_ASSOC) as $r) $corr[$r['batiment_id']][] = $r;
}

function rowStatus($docSet) {
    if (!empty($docSet['step5_decision'])) return ['done', 'مكتمل (مرحلة 5)', '#d4edda'];
    if (!empty($docSet['step2_pv']) || !empty($docSet['step3_expert_request']) || !empty($docSet['step4_expert_report'])) return ['progress', 'قيد المعالجة', '#fff3cd'];
    return ['new', 'في الشكاية (مرحلة 1)', '#f8d7da'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>متابعة الملفات</title>
<style>
*{box-sizing:border-box} body{margin:0;font-family:Segoe UI,Arial;background:#f0f2f5;direction:rtl}
header{background:linear-gradient(135deg,#1a3c5e,#2e6da4);color:white;padding:16px 22px;position:sticky;top:0;z-index:100}
.wrap{padding:18px}
.toolbar{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;background:#fff;padding:12px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:12px}
.btn{padding:8px 12px;border-radius:7px;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.b-add{background:#28a745;color:#fff}.b-cancel{background:#6c757d;color:#fff}
.search{display:flex}.search input{border:1px solid #ddd;padding:8px 10px;border-radius:0 8px 8px 0}.search button{border:1px solid #ddd;background:#2e6da4;color:white;padding:8px 12px;border-radius:8px 0 0 8px}
.table-wrap{max-height:calc(100vh - 200px);overflow:auto;border-radius:12px;box-shadow:0 3px 14px rgba(0,0,0,.09)}
table{width:100%;border-collapse:collapse;background:#fff;min-width:1200px}
thead th{position:sticky;top:0;z-index:2;background:linear-gradient(135deg,#1a3c5e,#2e6da4);color:#fff;padding:11px;border:1px solid rgba(255,255,255,.2);font-size:12px}
tbody td{padding:8px;border:1px solid #e9ecef;font-size:12px;vertical-align:top}
.stepper{display:flex;gap:4px;flex-wrap:wrap}
.st{padding:4px 7px;border-radius:6px;text-decoration:none;font-size:11px;background:#f5f5f5;color:#777}
.st.done{background:#2e6da4;color:#fff}.st.lock{background:#ececec;color:#bbb}
.io-sader{background:#fff3cd;color:#856404;border-radius:10px;padding:1px 6px;font-size:10px}
.io-wared{background:#d4edda;color:#155724;border-radius:10px;padding:1px 6px;font-size:10px}
</style>
</head>
<body>
<?php include '_menu.php'; ?>
<header><h2 style="margin:0">متابعة الملفات</h2></header>
<div class="wrap">
    <?php if (!empty($_GET['msg'])): ?><div style="background:#d4edda;border:1px solid #c3e6cb;padding:10px;border-radius:8px;margin-bottom:10px">✅ تمت العملية بنجاح</div><?php endif; ?>
    <div class="toolbar">
        <div>
            <?php if (hasStepAccess('step1_reclamation')): ?><a class="btn b-add" href="ajouter.php">➕ إضافة شكاية</a><?php endif; ?>
        </div>
        <div style="display:flex;gap:6px;align-items:center">
            <form method="GET" class="search"><button aria-label="بحث">🔍</button><input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="بحث شامل: رقم / مالك / عنوان"></form>
            <a class="btn b-cancel" href="export_excel.php?search=<?= urlencode($search) ?>">⬇️ Excel</a>
            <a class="btn b-cancel" target="_blank" href="export_pdf.php?search=<?= urlencode($search) ?>">⬇️ PDF</a>
            <?php if ($search !== ''): ?><a href="index.php" class="btn b-cancel">✖</a><?php endif; ?>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>#</th><th>ID bureau d'ordre</th><th>التاريخ</th><th>المالك</th><th>الحالة</th>
                <th>مراسلة</th><th>المراحل</th><th>إجراءات</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="8" style="text-align:center;color:#888;padding:26px">لا توجد بيانات</td></tr><?php endif; ?>
            <?php foreach ($rows as $i => $r):
                $docSet = $docs[$r['id']] ?? [];
                [$statusKey,$statusTxt,$bg] = rowStatus($docSet);
            ?>
                <tr style="background:<?= $bg ?>">
                    <td><?= $i+1 ?></td>
                    <td><b><?= htmlspecialchars($r['bureau_ordre_id']) ?></b></td>
                    <td><?= !empty($r['date_reclamation']) ? date('d/m/Y', strtotime($r['date_reclamation'])) : '—' ?></td>
                    <td><?= htmlspecialchars($r['proprietaire'] ?: '—') ?></td>
                    <td>
                        <?php if ($statusKey === 'new'): ?><span style="background:#dc3545;color:#fff;padding:3px 8px;border-radius:12px">أحمر</span><?php endif; ?>
                        <?php if ($statusKey === 'progress'): ?><span style="background:#f39c12;color:#fff;padding:3px 8px;border-radius:12px">برتقالي</span><?php endif; ?>
                        <?php if ($statusKey === 'done'): ?><span style="background:#28a745;color:#fff;padding:3px 8px;border-radius:12px">أخضر</span><?php endif; ?>
                        <div style="font-size:11px;color:#666;margin-top:3px"><?= $statusTxt ?></div>
                    </td>
                    <td>
                        <?php if (empty($corr[$r['id']])): ?>—<?php endif; ?>
                        <?php foreach (($corr[$r['id']] ?? []) as $c): ?>
                            <div style="margin-bottom:4px;padding:4px;border:1px solid #eee;border-radius:6px;background:#fff">
                                <?= htmlspecialchars($c['bureau_ordre_id']) ?> | <?= htmlspecialchars($c['subject']) ?> | <?= htmlspecialchars($c['administration']) ?>
                                <span class="<?= $c['direction_io'] === 'wared' ? 'io-wared' : 'io-sader' ?>"><?= $c['direction_io'] === 'wared' ? 'وارد' : 'صادر' ?></span>
                                <?php if (!empty($c['attachment_path'])): ?><a target="_blank" href="<?= htmlspecialchars($c['attachment_path']) ?>">📎</a><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <div class="stepper">
                            <?php foreach (STEPS as $type => $cfg):
                                if ($type === 'step1_reclamation') { echo '<span class="st done">🧾 شكاية</span>'; continue; }
                                $locked = !empty($cfg['requires']) && $cfg['requires'] !== 'step1_reclamation' && empty($docSet[$cfg['requires']]);
                                $can = hasStepAccess($type);
                                if ($locked || !$can): ?>
                                    <span class="st lock"><?= $cfg['icon'] ?> <?= htmlspecialchars($cfg['label']) ?></span>
                                <?php else: ?>
                                    <a class="st <?= !empty($docSet[$type]) ? 'done' : '' ?>" href="document.php?id=<?= $r['id'] ?>&type=<?= $type ?>"><?= $cfg['icon'] ?> <?= htmlspecialchars($cfg['label']) ?></a>
                                <?php endif;
                            endforeach; ?>
                        </div>
                    </td>
                    <td><a class="btn b-cancel" href="modifier.php?id=<?= $r['id'] ?>">فتح الملف</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
