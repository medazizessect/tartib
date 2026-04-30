<?php
require 'config.php';
requireLogin();
require 'db.php';
require '_steps_config.php';

$id = intval($_GET['id'] ?? 0);
$type = trim($_GET['type'] ?? '');
if (!$id || !isset(STEPS[$type]) || $type === 'step1_reclamation') { header("Location: index.php"); exit; }
requireStepAccess($type);

$bq = $pdo->prepare("SELECT * FROM batiments WHERE id=?");
$bq->execute([$id]);
$case = $bq->fetch(PDO::FETCH_ASSOC);
if (!$case) { header("Location: index.php"); exit; }

$cfg = STEPS[$type];
if (!empty($cfg['requires'])) {
    if ($cfg['requires'] === 'step1_reclamation') {
        if (empty($case['bureau_ordre_id'])) die('المرحلة 1 غير مكتملة');
    } else {
        $rq = $pdo->prepare("SELECT id FROM documents_officiels WHERE batiment_id=? AND type=?");
        $rq->execute([$id, $cfg['requires']]);
        $requiredDocId = $rq->fetchColumn();
        if (!$requiredDocId) die('المرحلة السابقة غير مكتملة');
    }
}

$stmt = $pdo->prepare("SELECT * FROM documents_officiels WHERE batiment_id=? AND type=?");
$stmt->execute([$id, $type]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

function saveUploadIfAny($field) {
    if (empty($_FILES[$field]['name'])) return '';
    if (($_FILES[$field]['size'] ?? 0) > 10 * 1024 * 1024) return null;
    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0775, true);
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf','jpg','jpeg','png','doc','docx'];
    if (!in_array($ext, $allowed, true)) return null;
    $mimeAllowed = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $_FILES[$field]['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);
    if (!in_array($mime, $mimeAllowed, true)) return null;
    $name = $field . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $rel = 'uploads/' . $name;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], __DIR__ . '/' . $rel)) return null;
    return $rel;
}

function nextPvNumber($pdo) {
    $yy = date('y');
    $stmt = $pdo->prepare("SELECT numero_doc FROM documents_officiels WHERE type='step2_pv' AND numero_doc LIKE ?");
    $stmt->execute(["%/$yy"]);
    $max = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $num) {
        if (preg_match('/^(\d+)\/\d{2}$/', (string)$num, $m)) $max = max($max, (int)$m[1]);
    }
    return ($max + 1) . '/' . $yy;
}

$addresses = $pdo->query("SELECT id, libelle FROM adresses ORDER BY libelle ASC LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
$msg = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_correspondence']) && in_array($type, ['step3_expert_request','step4_expert_report'], true)) {
        $corrFile = saveUploadIfAny('corr_attachment');
        $pdo->prepare("
            INSERT INTO correspondences
            (batiment_id,step_type,bureau_ordre_id,subject,administration,direction_io,attachment_path)
            VALUES(?,?,?,?,?,?,?)
        ")->execute([
            $id, $type,
            trim($_POST['corr_bureau'] ?? ''),
            trim($_POST['corr_subject'] ?? ''),
            trim($_POST['corr_admin'] ?? ''),
            ($_POST['corr_direction'] ?? 'sader') === 'wared' ? 'wared' : 'sader',
            $corrFile ?: null
        ]);
        header("Location: document.php?id=$id&type=$type&corr=1");
        exit;
    }

    $attachment = saveUploadIfAny('attachment');
    if ($attachment === null) $errors[] = "صيغة الملف غير مدعومة";

    $statut = ($_POST['statut'] ?? 'brouillon') === 'finalise' ? 'finalise' : 'brouillon';
    $numeroDoc = trim($_POST['numero_doc'] ?? '');
    if ($type === 'step2_pv' && $numeroDoc === '') $numeroDoc = nextPvNumber($pdo);

    if ($type === 'step2_pv' && empty($doc['attachment_path']) && !$attachment) {
        $errors[] = "نسخة المحضر الممسوحة أو القابلة للطباعة مطلوبة";
    }
    if ($type === 'step2_pv' && ($_POST['exploite_by'] ?? '') === 'oui' && trim($_POST['occupied_by'] ?? '') === '') {
        $errors[] = "حقل «المشغول» إجباري عند اختيار مستغلة من = نعم";
    }

    if (empty($errors)) {
        $preceding = null;
        if (!empty($cfg['requires']) && $cfg['requires'] !== 'step1_reclamation') {
            $x = $pdo->prepare("SELECT id FROM documents_officiels WHERE batiment_id=? AND type=?");
            $x->execute([$id, $cfg['requires']]);
            $preceding = $x->fetchColumn() ?: null;
        }

        $payload = [
            ':bid' => $id, ':type' => $type, ':statut' => $statut, ':numero_doc' => ($numeroDoc ?: null),
            ':date_doc' => (($_POST['date_doc'] ?? '') ?: null), ':cin' => trim($_POST['cin'] ?? '') ?: null,
            ':owner_name' => trim($_POST['owner_name'] ?? '') ?: null,
            ':exploite_by' => in_array($_POST['exploite_by'] ?? '', ['oui','non'], true) ? $_POST['exploite_by'] : null,
            ':occupied_by' => trim($_POST['occupied_by'] ?? '') ?: null,
            ':confirmation_degree' => trim($_POST['confirmation_degree'] ?? '') ?: null,
            ':commission_members' => trim($_POST['commission'] ?? '') ?: null,
            ':address_id' => (($_POST['address_id'] ?? '') !== '' ? intval($_POST['address_id']) : null),
            ':pv_state_id' => null,
            ':forward_to_ministry' => !empty($_POST['forward_to_ministry']) ? 1 : 0,
            ':subject' => trim($_POST['subject'] ?? '') ?: null,
            ':administration' => trim($_POST['administration'] ?? '') ?: null,
            ':direction_io' => in_array($_POST['direction_io'] ?? '', ['sader','wared'], true) ? $_POST['direction_io'] : null,
            ':expert_name' => trim($_POST['expert_name'] ?? '') ?: null,
            ':report_type' => in_array($_POST['report_type'] ?? '', ['initial','final'], true) ? $_POST['report_type'] : null,
            ':heritage_needed' => !empty($_POST['heritage_needed']) ? 1 : 0,
            ':heritage_direction' => in_array($_POST['heritage_direction'] ?? '', ['sader','wared'], true) ? $_POST['heritage_direction'] : null,
            ':appointment_date' => (($_POST['appointment_date'] ?? '') ?: null),
            ':decision_type' => in_array($_POST['decision_type'] ?? '', ['evacuation','demolition'], true) ? $_POST['decision_type'] : null,
            ':attachment_path' => $attachment ?: ($doc['attachment_path'] ?? null),
            ':observations' => trim($_POST['observations'] ?? '') ?: null,
            ':preceding' => $preceding,
        ];

        if ($doc) {
            $sql = "UPDATE documents_officiels SET
                preceding_document_id=:preceding, statut=:statut, numero_doc=:numero_doc, date_doc=:date_doc,
                cin=:cin, owner_name=:owner_name, exploite_by=:exploite_by, occupied_by=:occupied_by,
                confirmation_degree=:confirmation_degree, commission_members=:commission_members, address_id=:address_id, pv_state_id=:pv_state_id,
                forward_to_ministry=:forward_to_ministry, subject=:subject, administration=:administration,
                direction_io=:direction_io, expert_name=:expert_name, report_type=:report_type,
                heritage_needed=:heritage_needed, heritage_direction=:heritage_direction,
                appointment_date=:appointment_date, decision_type=:decision_type, attachment_path=:attachment_path,
                observations=:observations WHERE batiment_id=:bid AND type=:type";
        } else {
            $sql = "INSERT INTO documents_officiels
                (batiment_id,type,preceding_document_id,statut,numero_doc,date_doc,cin,owner_name,exploite_by,occupied_by,
                confirmation_degree,commission_members,address_id,pv_state_id,forward_to_ministry,subject,administration,direction_io,
                expert_name,report_type,heritage_needed,heritage_direction,appointment_date,decision_type,attachment_path,observations)
                VALUES(:bid,:type,:preceding,:statut,:numero_doc,:date_doc,:cin,:owner_name,:exploite_by,:occupied_by,
                :confirmation_degree,:commission_members,:address_id,:pv_state_id,:forward_to_ministry,:subject,:administration,:direction_io,
                :expert_name,:report_type,:heritage_needed,:heritage_direction,:appointment_date,:decision_type,:attachment_path,:observations)";
        }
        $pdo->prepare($sql)->execute($payload);
        if ($type === 'step2_pv') {
            $addressLabel = null;
            if (!empty($_POST['address_id'])) {
                $addrStmt = $pdo->prepare("SELECT libelle FROM adresses WHERE id=?");
                $addrStmt->execute([(int)$_POST['address_id']]);
                $addressLabel = $addrStmt->fetchColumn() ?: null;
            }
            $pdo->prepare("UPDATE batiments SET commission=? WHERE id=?")
                ->execute([trim($_POST['commission'] ?? '') ?: null, $id]);
            if ($addressLabel !== null) {
                $pdo->prepare("UPDATE batiments SET lieu=? WHERE id=?")->execute([$addressLabel, $id]);
            }
        }
        header("Location: document.php?id=$id&type=$type&saved=1");
        exit;
    }
}

$stmt->execute([$id, $type]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (isset($_GET['print'])) {
    if (($doc['statut'] ?? '') !== 'finalise') {
        header("Location: document.php?id=$id&type=$type");
        exit;
    }
    $v = $doc;
    $label = $cfg['label'];
    $addressLabel = '';
    if (!empty($v['address_id'])) {
        $addrStmt = $pdo->prepare("SELECT libelle FROM adresses WHERE id=?");
        $addrStmt->execute([(int)$v['address_id']]);
        $addressLabel = (string)($addrStmt->fetchColumn() ?: '');
    }
    include 'document_print.php';
    exit;
}
$corr = [];
if (in_array($type, ['step3_expert_request','step4_expert_report'], true)) {
    $c = $pdo->prepare("SELECT * FROM correspondences WHERE batiment_id=? AND step_type=? ORDER BY id DESC");
    $c->execute([$id, $type]);
    $corr = $c->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($cfg['label']) ?></title>
    <style>
        *{box-sizing:border-box} body{font-family:Segoe UI,Arial;background:#f0f2f5;direction:rtl;margin:0}
        .wrap{max-width:980px;margin:20px auto;padding:0 15px} .card{background:#fff;border-radius:12px;padding:20px;margin-bottom:14px;box-shadow:0 3px 11px rgba(0,0,0,.08)}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px} .full{grid-column:1/-1}
        label{font-size:12px;color:#555;font-weight:700;display:block;margin-bottom:4px}
        input,select,textarea{width:100%;padding:9px 10px;border:2px solid #e9ecef;border-radius:8px;font-family:inherit}
        textarea{min-height:80px;resize:vertical} input:focus,select:focus,textarea:focus{outline:none;border-color:#2e6da4}
        .btn{padding:10px 14px;border:none;border-radius:8px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
        .b1{background:#ffc107}.b2{background:#28a745;color:#fff}.b3{background:#2e6da4;color:#fff}.b4{background:#6c757d;color:#fff}
        .ok{background:#d4edda;border:1px solid #c3e6cb;padding:10px;border-radius:8px;margin-bottom:10px}
        .err{background:#f8d7da;border:1px solid #f5c6cb;padding:10px;border-radius:8px;margin-bottom:10px}
        .io-sader{background:#fff3cd;color:#856404;padding:2px 8px;border-radius:12px;font-size:11px}
        .io-wared{background:#d4edda;color:#155724;padding:2px 8px;border-radius:12px;font-size:11px}
        .commission-wrap{border:2px solid #e0d4c0;border-radius:10px;padding:12px;background:#fffaf4}
        .tags-box{min-height:44px;border:2px solid #e9ecef;border-radius:8px;padding:7px 10px;background:#fff;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
        .tag{padding:4px 8px;border-radius:20px;font-size:12px;display:inline-flex;gap:4px;align-items:center;background:#2e6da4;color:#fff}
        .tag-remove{cursor:pointer;border:none;background:none;color:#fff;font-size:15px;line-height:1}
        .tag-input{border:none;outline:none;font-size:13px;min-width:130px;flex:1;background:transparent}
        .membres-predefs{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px}
        .predef-btn{padding:5px 12px;border-radius:20px;border:2px solid #2e6da4;background:#fff;color:#2e6da4;cursor:pointer}
        .predef-btn.selected{background:#2e6da4;color:#fff}
        .commission-hint{font-size:11px;color:#777;margin-top:8px}
        @media(max-width:700px){.grid{grid-template-columns:1fr}}
        #map-container{grid-column:1/-1;margin-top:4px}
        #intervention-map{height:340px;border-radius:10px;border:2px solid #e9ecef;width:100%;z-index:0}
        .map-hint{font-size:11px;color:#777;margin-top:5px}
    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLs=" crossorigin=""></script>
</head>
<body>
<?php include '_menu.php'; ?>
<div class="wrap">
    <a href="modifier.php?id=<?= $id ?>" class="btn b4">↩️ رجوع للملف</a>
    <div class="card">
        <h3 style="margin-top:0"><?= $cfg['icon'] ?> <?= htmlspecialchars($cfg['label']) ?></h3>
        <p style="color:#666;font-size:12px">ID bureau d'ordre: <?= htmlspecialchars($case['bureau_ordre_id']) ?></p>
    </div>

    <?php if (!empty($_GET['saved'])): ?><div class="ok">✅ تم الحفظ</div><?php endif; ?>
    <?php if (!empty($_GET['corr'])): ?><div class="ok">✅ تمت إضافة المراسلة</div><?php endif; ?>
    <?php if ($errors): ?><div class="err"><?php foreach ($errors as $e) echo '• '.htmlspecialchars($e).'<br>'; ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="card">
        <div class="grid">
            <div><label>رقم المحضر</label><input type="text" name="numero_doc" value="<?= htmlspecialchars($doc['numero_doc'] ?? '') ?>"></div>
            <div><label>تاريخ المحضر</label><input type="date" name="date_doc" value="<?= htmlspecialchars($doc['date_doc'] ?? '') ?>"></div>

            <?php if ($type === 'step2_pv'): ?>
                <div><label>CIN (اختياري)</label><input type="text" name="cin" value="<?= htmlspecialchars($doc['cin'] ?? '') ?>"></div>
                <div><label>مالك</label><input type="text" name="owner_name" value="<?= htmlspecialchars($doc['owner_name'] ?? $case['proprietaire'] ?? '') ?>"></div>
                <div><label>مستغلة</label><select id="exploite_by" name="exploite_by"><option value="">--</option><option value="oui" <?= (($doc['exploite_by'] ?? '') === 'oui') ? 'selected' : '' ?>>نعم</option><option value="non" <?= (($doc['exploite_by'] ?? '') === 'non') ? 'selected' : '' ?>>لا</option></select></div>
                <div id="occupiedWrap" style="display:none"><label>الشاغل</label><input type="text" name="occupied_by" value="<?= htmlspecialchars($doc['occupied_by'] ?? '') ?>"></div>
                <div><label>درجة التأكيد</label><input type="text" name="confirmation_degree" value="<?= htmlspecialchars($doc['confirmation_degree'] ?? '') ?>"></div>
                <div>
                    <label>المكان</label>
                    <?php
                    $currentAddrLabel = '';
                    if (!empty($doc['address_id'])) {
                        foreach($addresses as $a) {
                            if ((int)$a['id'] === (int)$doc['address_id']) { $currentAddrLabel = $a['libelle']; break; }
                        }
                    }
                    ?>
                    <input type="text" id="address-search" list="address-datalist"
                           placeholder="ابحث عن العنوان..."
                           value="<?= htmlspecialchars($currentAddrLabel) ?>"
                           autocomplete="off">
                    <input type="hidden" name="address_id" id="address-id"
                           value="<?= (int)($doc['address_id'] ?? 0) ?: '' ?>">
                    <datalist id="address-datalist">
                        <?php foreach($addresses as $a): ?>
                        <option data-id="<?= $a['id'] ?>" value="<?= htmlspecialchars($a['libelle']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div><label><input type="checkbox" name="forward_to_ministry" value="1" <?= !empty($doc['forward_to_ministry']) ? 'checked' : '' ?>> توجيه وزارة التجهيز (اختياري)</label></div>
                <div id="map-container" class="full">
                    <label>🗺️ خريطة موقع التدخل</label>
                    <div id="intervention-map"></div>
                    <p class="map-hint">📍 تحديد العنوان أعلاه يُحدّد تلقائياً موقع التدخل على الخريطة.</p>
                </div>
                <div class="full">
                    <label>أعضاء اللجنة</label>
                    <?php $commissionValue = $doc['commission_members'] ?? ($case['commission'] ?? ''); include '_commission.php'; ?>
                </div>
            <?php endif; ?>

            <?php if ($type === 'step3_expert_request'): ?>
                <div><label>الموضوع</label><input type="text" name="subject" value="<?= htmlspecialchars($doc['subject'] ?? '') ?>"></div>
                <div><label>الإدارة</label><input type="text" name="administration" value="<?= htmlspecialchars($doc['administration'] ?? '') ?>"></div>
                <div><label>اتجاه</label><select name="direction_io"><option value="sader" <?= (($doc['direction_io'] ?? '') === 'sader') ? 'selected' : '' ?>>صادر</option><option value="wared" <?= (($doc['direction_io'] ?? '') === 'wared') ? 'selected' : '' ?>>وارد</option></select></div>
                <div><label>تسمية الخبير بعد رجوع المحكمة</label><input type="text" name="expert_name" value="<?= htmlspecialchars($doc['expert_name'] ?? '') ?>"></div>
            <?php endif; ?>

            <?php if ($type === 'step4_expert_report'): ?>
                <div><label>نوع التقرير</label><select name="report_type"><option value="initial" <?= (($doc['report_type'] ?? '') === 'initial') ? 'selected' : '' ?>>تقرير اختيار اولي</option><option value="final" <?= (($doc['report_type'] ?? '') === 'final') ? 'selected' : '' ?>>تقرير اختبار نهائي</option></select></div>
                <div><label><input type="checkbox" name="heritage_needed" value="1" <?= !empty($doc['heritage_needed']) ? 'checked' : '' ?>> المرور على إدارة التراث (اختياري)</label></div>
                <div><label>اتجاه التراث</label><select name="heritage_direction"><option value="">--</option><option value="sader" <?= (($doc['heritage_direction'] ?? '') === 'sader') ? 'selected' : '' ?>>صادر</option><option value="wared" <?= (($doc['heritage_direction'] ?? '') === 'wared') ? 'selected' : '' ?>>وارد</option></select></div>
                <div><label>موعد التوجه</label><input type="date" name="appointment_date" value="<?= htmlspecialchars($doc['appointment_date'] ?? '') ?>"></div>
            <?php endif; ?>

            <?php if ($type === 'step5_decision'): ?>
                <div class="full"><label>القرار النهائي</label><select name="decision_type"><option value="evacuation" <?= (($doc['decision_type'] ?? '') === 'evacuation') ? 'selected' : '' ?>>قرار إخلاء</option><option value="demolition" <?= (($doc['decision_type'] ?? '') === 'demolition') ? 'selected' : '' ?>>قرار هدم</option></select></div>
            <?php endif; ?>

            <div class="full"><label>ملاحظات</label><textarea name="observations"><?= htmlspecialchars($doc['observations'] ?? '') ?></textarea></div>
            <div class="full"><label>ملف مرفق</label><input type="file" name="attachment"><?php if (!empty($doc['attachment_path'])): ?><div style="margin-top:5px"><a target="_blank" href="<?= htmlspecialchars($doc['attachment_path']) ?>">📎 الملف الحالي</a></div><?php endif; ?></div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn b1" type="submit" name="statut" value="brouillon">💾 حفظ مسودة</button>
            <button class="btn b2" type="submit" name="statut" value="finalise">✅ حفظ نهائي</button>
            <?php if (($doc['statut'] ?? '') === 'finalise'): ?><a class="btn b3" target="_blank" href="document.php?id=<?= $id ?>&type=<?= $type ?>&print=1">🖨️ PDF</a><?php endif; ?>
        </div>
    </form>

    <?php if (in_array($type, ['step3_expert_request','step4_expert_report'], true)): ?>
    <div class="card">
        <h4>مراسلة (صادر / وارد)</h4>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="add_correspondence" value="1">
            <div class="grid">
                <div><label>ID bureau d'ordre</label><input name="corr_bureau" value="<?= htmlspecialchars($case['bureau_ordre_id']) ?>"></div>
                <div><label>الموضوع</label><input name="corr_subject"></div>
                <div><label>الإدارة</label><input name="corr_admin"></div>
                <div><label>الاتجاه</label><select name="corr_direction"><option value="sader">صادر</option><option value="wared">وارد</option></select></div>
                <div class="full"><label>مرفق</label><input type="file" name="corr_attachment"></div>
            </div>
            <div style="margin-top:10px"><button class="btn b3" type="submit">➕ إضافة مراسلة</button></div>
        </form>
        <hr style="margin:14px 0;border:none;border-top:1px solid #eee">
        <?php if (!$corr): ?><div style="color:#888">لا توجد مراسلات.</div><?php endif; ?>
        <?php foreach ($corr as $c): ?>
            <div style="padding:8px;border:1px solid #eee;border-radius:8px;margin-bottom:8px">
                <b><?= htmlspecialchars($c['bureau_ordre_id']) ?></b> — <?= htmlspecialchars($c['subject']) ?> — <?= htmlspecialchars($c['administration']) ?>
                <span class="<?= $c['direction_io'] === 'wared' ? 'io-wared' : 'io-sader' ?>"><?= $c['direction_io'] === 'wared' ? 'وارد' : 'صادر' ?></span>
                <?php if (!empty($c['attachment_path'])): ?> <a target="_blank" href="<?= htmlspecialchars($c['attachment_path']) ?>">📎</a><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<script>
function toggleOccupied(){var s=document.getElementById('exploite_by');var w=document.getElementById('occupiedWrap');if(!s||!w)return;w.style.display=(s.value==='oui')?'block':'none';}
document.addEventListener('DOMContentLoaded',function(){
    var s=document.getElementById('exploite_by');
    if(s){s.addEventListener('change',toggleOccupied);toggleOccupied();}

    // Address autocomplete: map selected label to ID via hidden field
    var addrInput = document.getElementById('address-search');
    var addrHidden = document.getElementById('address-id');
    if (addrInput && addrHidden) {
        var addrMap = {};
        document.querySelectorAll('#address-datalist option').forEach(function(opt){
            addrMap[opt.value] = opt.getAttribute('data-id');
        });
        addrInput.addEventListener('input', function(){
            var id = addrMap[this.value];
            addrHidden.value = id ? id : '';
        });
        addrInput.addEventListener('change', function(){
            var id = addrMap[this.value];
            addrHidden.value = id ? id : '';
        });
    }

    // Interactive map with Leaflet.js + OpenStreetMap
    var mapEl = document.getElementById('intervention-map');
    if (mapEl) {
        // Default center: Sousse, Tunisia
        var defaultLat = 35.8256, defaultLon = 10.6369;
        var map = L.map('intervention-map').setView([defaultLat, defaultLon], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(map);

        var marker = null;

        function placeMarker(lat, lon, label) {
            if (marker) { map.removeLayer(marker); }
            marker = L.marker([lat, lon]).addTo(map);
            if (label) { marker.bindPopup('<b>' + label + '</b>').openPopup(); }
            map.setView([lat, lon], 16);
        }

        // Load street coordinates from shapefile-derived JSON
        fetch('streets_coords.json')
            .then(function(r){ return r.json(); })
            .then(function(streetsCoords) {
                function updateMapByAddress(label) {
                    if (!label) return;
                    // Exact match first
                    if (streetsCoords[label]) {
                        var coords = streetsCoords[label];
                        placeMarker(coords[0], coords[1], label);
                        return;
                    }
                    // Partial match fallback (only on explicit selection/change)
                    var keys = Object.keys(streetsCoords);
                    for (var i = 0; i < keys.length; i++) {
                        if (keys[i].indexOf(label) !== -1 || label.indexOf(keys[i]) !== -1) {
                            placeMarker(streetsCoords[keys[i]][0], streetsCoords[keys[i]][1], label);
                            return;
                        }
                    }
                }

                // If address already selected on page load, show on map
                if (addrInput && addrInput.value.trim()) {
                    updateMapByAddress(addrInput.value.trim());
                }

                // Update map only on explicit selection (change event) to avoid per-keystroke searches
                if (addrInput) {
                    addrInput.addEventListener('change', function(){ updateMapByAddress(this.value.trim()); });
                }
            })
            .catch(function(){
                // If streets_coords.json fails to load, map still shows Sousse
            });
    }
});
</script>
</body>
</html>
