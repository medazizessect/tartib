<?php
require 'config.php';
requireLogin();
require 'db.php';

$isKhaoula = (currentRole() === 'khaoula');
$msg = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isKhaoula) {
        $errors[] = '';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_event') {
            $titre = trim($_POST['titre'] ?? '');
            $date  = trim($_POST['date_evenement'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $bid   = intval($_POST['batiment_id'] ?? 0) ?: null;
            if ($titre === '' || $date === '') {
                $errors[] = 'العنوان والتاريخ مطلوبان';
            } else {
                $pdo->prepare("INSERT INTO calendar_events (batiment_id, titre, date_evenement, notes, created_by) VALUES (?,?,?,?,?)")
                    ->execute([$bid, $titre, $date, $notes ?: null, $_SESSION['user']['username'] ?? '']);
                header("Location: calendrier.php?saved=1");
                exit;
            }
        } elseif ($action === 'delete_event') {
            $eid = intval($_POST['event_id'] ?? 0);
            if ($eid > 0) {
                $pdo->prepare("DELETE FROM calendar_events WHERE id=?")->execute([$eid]);
            }
            header("Location: calendrier.php?deleted=1");
            exit;
        } elseif ($action === 'edit_event') {
            $eid   = intval($_POST['event_id'] ?? 0);
            $titre = trim($_POST['titre'] ?? '');
            $date  = trim($_POST['date_evenement'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $bid   = intval($_POST['batiment_id'] ?? 0) ?: null;
            if ($titre === '' || $date === '' || $eid <= 0) {
                $errors[] = 'البيانات غير صحيحة';
            } else {
                $pdo->prepare("UPDATE calendar_events SET batiment_id=?, titre=?, date_evenement=?, notes=? WHERE id=?")
                    ->execute([$bid, $titre, $date, $notes ?: null, $eid]);
                header("Location: calendrier.php?saved=1");
                exit;
            }
        }
    }
}

// Month navigation
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }
$prevM = $month - 1; $prevY = $year;
if ($prevM < 1) { $prevM = 12; $prevY--; }
$nextM = $month + 1; $nextY = $year;
if ($nextM > 12) { $nextM = 1; $nextY++; }

// Fetch all events for this month
$eventsStmt = $pdo->prepare("
    SELECT e.*, b.bureau_ordre_id
    FROM calendar_events e
    LEFT JOIN batiments b ON b.id = e.batiment_id
    WHERE YEAR(e.date_evenement) = ? AND MONTH(e.date_evenement) = ?
    ORDER BY e.date_evenement ASC, e.id ASC
");
$eventsStmt->execute([$year, $month]);
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

// Group events by day
$eventsByDay = [];
foreach ($events as $ev) {
    $day = (int)date('j', strtotime($ev['date_evenement']));
    $eventsByDay[$day][] = $ev;
}

// All batiments for the add-event form dropdown
$batimentsForForm = $pdo->query("SELECT id, bureau_ordre_id FROM batiments ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);

// Arabic month names
$arabicMonths = ['','جانفي','فيفري','مارس','افريل','ماي','جوان','جولية','اوت','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
$arabicDays   = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];

// Calendar grid: first day of month (0=Sun ... 6=Sat)
$firstDay = (int)date('w', mktime(0,0,0,$month,1,$year));
$daysInMonth = (int)date('t', mktime(0,0,0,$month,1,$year));
$today = (int)date('j'); $todayM = (int)date('n'); $todayY = (int)date('Y');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>تاريخ التوجية — التقويم</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Segoe UI,Arial;background:#f0f2f5;direction:rtl}
header{background:linear-gradient(135deg,#1a3c5e,#2e6da4);color:#fff;padding:16px 22px}
.wrap{max-width:1100px;margin:18px auto;padding:0 15px}
.card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 3px 12px rgba(0,0,0,.08);margin-bottom:16px}
/* Nav bar */
.cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.cal-nav h3{margin:0;font-size:18px;color:#1a3c5e}
.btn{padding:8px 14px;border:none;border-radius:8px;cursor:pointer;text-decoration:none;font-family:inherit;font-size:13px;display:inline-flex;align-items:center;gap:5px}
.btn-nav{background:#e8f0fb;color:#1a3c5e;font-weight:700}
.btn-primary{background:#2e6da4;color:#fff}
.btn-danger{background:#dc3545;color:#fff;font-size:11px;padding:4px 8px}
.btn-edit{background:#f39c12;color:#fff;font-size:11px;padding:4px 8px}
/* Calendar grid */
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px}
.cal-day-header{padding:8px 4px;text-align:center;font-size:11px;font-weight:700;color:#2e6da4;background:#e8f0fb;border-radius:6px}
.cal-cell{min-height:90px;background:#fafafa;border-radius:8px;padding:6px;border:1px solid #e9ecef;vertical-align:top}
.cal-cell.other-month{background:#f5f5f5;opacity:.5}
.cal-cell.today{border:2px solid #2e6da4;background:#e8f2ff}
.cal-cell-num{font-size:12px;font-weight:700;color:#555;margin-bottom:4px}
.cal-cell.today .cal-cell-num{color:#2e6da4}
.event-pill{font-size:10px;background:#2e6da4;color:#fff;border-radius:12px;padding:2px 7px;margin-bottom:3px;display:flex;align-items:center;justify-content:space-between;gap:4px;word-break:break-word;cursor:pointer}
.event-pill:hover{background:#1a3c5e}
/* Form */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.fg{display:flex;flex-direction:column;gap:5px}
.fg.full{grid-column:1/-1}
.fg label{font-size:12px;font-weight:700;color:#555}
.fg input,.fg select,.fg textarea{padding:8px 10px;border:2px solid #e9ecef;border-radius:7px;font-family:inherit;font-size:13px}
.fg input:focus,.fg select:focus,.fg textarea:focus{outline:none;border-color:#2e6da4}
.fg textarea{min-height:60px;resize:vertical}
.alert-ok{background:#d4edda;border:1px solid #c3e6cb;padding:10px;border-radius:8px;margin-bottom:12px;font-size:13px}
.alert-err{background:#f8d7da;border:1px solid #f5c6cb;padding:10px;border-radius:8px;margin-bottom:12px;font-size:13px}
/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1100;align-items:center;justify-content:center}
.modal-bg.open{display:flex}
.modal-box{background:#fff;border-radius:14px;padding:26px;width:100%;max-width:480px;box-shadow:0 8px 40px rgba(0,0,0,.2)}
.modal-box h4{margin-top:0;color:#1a3c5e}
</style>
</head>
<body>
<?php include '_menu.php'; ?>
<header><h2 style="margin:0">📅 تاريخ التوجيه</h2></header>
<div class="wrap">

<?php if (!empty($_GET['saved'])): ?><div class="alert-ok">✅ تم الحفظ</div><?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?><div class="alert-ok">🗑️ تم الحذف</div><?php endif; ?>
<?php if ($errors): ?><div class="alert-err"><?php foreach($errors as $e) echo htmlspecialchars($e).'<br>'; ?></div><?php endif; ?>

<div class="card">
    <div class="cal-nav">
        <a href="calendrier.php?year=<?= $prevY ?>&month=<?= $prevM ?>" class="btn btn-nav">◀ السابق</a>
        <h3><?= $arabicMonths[$month] ?> <?= $year ?></h3>
        <a href="calendrier.php?year=<?= $nextY ?>&month=<?= $nextM ?>" class="btn btn-nav">التالي ▶</a>
    </div>

    <div class="cal-grid">
        <?php foreach($arabicDays as $dname): ?>
        <div class="cal-day-header"><?= $dname ?></div>
        <?php endforeach; ?>

        <?php
        // Blank cells before first day
        for ($i = 0; $i < $firstDay; $i++) {
            echo '<div class="cal-cell other-month"></div>';
        }
        // Day cells
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $isToday = ($d === $today && $month === $todayM && $year === $todayY);
            echo '<div class="cal-cell ' . ($isToday ? 'today' : '') . '">';
            echo '<div class="cal-cell-num">' . $d . '</div>';
            if (!empty($eventsByDay[$d])) {
                foreach ($eventsByDay[$d] as $ev) {
                    echo '<div class="event-pill" onclick="openViewModal(' . (int)$ev['id'] . ',' . htmlspecialchars(json_encode($ev, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . ')">';
                    echo '<span>' . htmlspecialchars($ev['titre']) . '</span>';
                    echo '</div>';
                }
            }
            echo '</div>';
        }
        // Trailing blank cells
        $total = $firstDay + $daysInMonth;
        $trail = (7 - ($total % 7)) % 7;
        for ($i = 0; $i < $trail; $i++) {
            echo '<div class="cal-cell other-month"></div>';
        }
        ?>
    </div>
</div>

<?php if ($isKhaoula): ?>
<div class="card">
    <h3 style="margin-top:0;font-size:15px">➕ إضافة موعد جديد (تاريخ التوجية)</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add_event">
        <div class="form-grid">
            <div class="fg">
                <label><span style="color:#dc3545">*</span> العنوان</label>
                <input type="text" name="titre" required placeholder="عنوان الموعد">
            </div>
            <div class="fg">
                <label><span style="color:#dc3545">*</span> التاريخ</label>
                <input type="date" name="date_evenement" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="fg">
                <label>الملف المرتبط (اختياري)</label>
                <select name="batiment_id">
                    <option value="">-- بدون ملف --</option>
                    <?php foreach($batimentsForForm as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['bureau_ordre_id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg">
                <label>ملاحظات</label>
                <textarea name="notes" placeholder="ملاحظات..."></textarea>
            </div>
        </div>
        <div style="margin-top:12px">
            <button type="submit" class="btn btn-primary">💾 إضافة موعد</button>
        </div>
    </form>
</div>
<?php else: ?>
<div class="card" style="background:#e8f0fb;border:2px solid #2e6da4">
    <p style="margin:0;color:#1a3c5e;font-size:13px">🔒 .</p>
</div>
<?php endif; ?>

<!-- Events list for this month -->
<?php if ($events): ?>
<div class="card">
    <h3 style="margin-top:0;font-size:15px">قائمة المواعيد — <?= $arabicMonths[$month] ?> <?= $year ?></h3>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr style="background:#f8f9fa">
            <th style="padding:8px;border:1px solid #e9ecef;text-align:right">التاريخ</th>
            <th style="padding:8px;border:1px solid #e9ecef;text-align:right">العنوان</th>
            <th style="padding:8px;border:1px solid #e9ecef;text-align:right">الملف</th>
            <th style="padding:8px;border:1px solid #e9ecef;text-align:right">ملاحظات</th>
            <?php if ($isKhaoula): ?><th style="padding:8px;border:1px solid #e9ecef">إجراءات</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach($events as $ev): ?>
        <tr>
            <td style="padding:8px;border:1px solid #e9ecef"><?= date('d/m/Y', strtotime($ev['date_evenement'])) ?></td>
            <td style="padding:8px;border:1px solid #e9ecef;font-weight:700"><?= htmlspecialchars($ev['titre']) ?></td>
            <td style="padding:8px;border:1px solid #e9ecef">
                <?php if (!empty($ev['bureau_ordre_id'])): ?>
                <a href="modifier.php?id=<?= (int)$ev['batiment_id'] ?>"><?= htmlspecialchars($ev['bureau_ordre_id']) ?></a>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td style="padding:8px;border:1px solid #e9ecef;color:#666"><?= htmlspecialchars($ev['notes'] ?? '') ?></td>
            <?php if ($isKhaoula): ?>
            <td style="padding:8px;border:1px solid #e9ecef;display:flex;gap:5px">
                <button class="btn btn-edit" type="button"
                    onclick="openEditModal(<?= (int)$ev['id'] ?>,'<?= htmlspecialchars(addslashes($ev['titre']),ENT_QUOTES) ?>','<?= htmlspecialchars($ev['date_evenement'],ENT_QUOTES) ?>',<?= intval($ev['batiment_id'] ?? 0) ?>,'<?= htmlspecialchars(addslashes($ev['notes'] ?? ''),ENT_QUOTES) ?>')">✏️</button>
                <form method="POST" onsubmit="return confirm('حذف الموعد؟')">
                    <input type="hidden" name="action" value="delete_event">
                    <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                    <button class="btn btn-danger" type="submit">🗑️</button>
                </form>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

</div><!-- .wrap -->

<!-- Edit Modal -->
<?php if ($isKhaoula): ?>
<div class="modal-bg" id="editModal">
    <div class="modal-box">
        <h4>✏️ تعديل الموعد</h4>
        <form method="POST">
            <input type="hidden" name="action" value="edit_event">
            <input type="hidden" name="event_id" id="edit-event-id">
            <div class="form-grid">
                <div class="fg">
                    <label>العنوان</label>
                    <input type="text" name="titre" id="edit-titre" required>
                </div>
                <div class="fg">
                    <label>التاريخ</label>
                    <input type="date" name="date_evenement" id="edit-date" required>
                </div>
                <div class="fg">
                    <label>الملف المرتبط</label>
                    <select name="batiment_id" id="edit-batiment">
                        <option value="">-- بدون ملف --</option>
                        <?php foreach($batimentsForForm as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['bureau_ordre_id']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg">
                    <label>ملاحظات</label>
                    <textarea name="notes" id="edit-notes"></textarea>
                </div>
            </div>
            <div style="margin-top:14px;display:flex;gap:8px">
                <button type="submit" class="btn btn-primary">💾 حفظ</button>
                <button type="button" class="btn" style="background:#6c757d;color:#fff" onclick="closeEditModal()">إلغاء</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openEditModal(id, titre, date, batimentId, notes) {
    document.getElementById('edit-event-id').value = id;
    document.getElementById('edit-titre').value = titre;
    document.getElementById('edit-date').value = date;
    document.getElementById('edit-notes').value = notes;
    var sel = document.getElementById('edit-batiment');
    if (sel) {
        for (var i=0; i<sel.options.length; i++) {
            sel.options[i].selected = (parseInt(sel.options[i].value) === parseInt(batimentId));
        }
    }
    document.getElementById('editModal').classList.add('open');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}
document.getElementById('editModal') && document.getElementById('editModal').addEventListener('click', function(e){
    if (e.target === this) closeEditModal();
});
</script>
</body>
</html>
