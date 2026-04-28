<?php
require 'config.php';
requireLogin();
require 'db.php';

// Filters
$filterYear    = isset($_GET['year'])    ? (int)$_GET['year']             : 0;
$filterAddress = isset($_GET['address']) ? trim($_GET['address'])          : '';

// Build WHERE clause for batiments
$whereClauses = [];
$whereParams  = [];
if ($filterYear > 0) {
    $whereClauses[] = "YEAR(b.date_reclamation) = ?";
    $whereParams[]  = $filterYear;
}
$joinAddress = '';
if ($filterAddress !== '') {
    $joinAddress    = "LEFT JOIN documents_officiels pv2 ON pv2.batiment_id = b.id AND pv2.type='step2_pv' LEFT JOIN adresses a2 ON a2.id = pv2.address_id";
    $whereClauses[] = "a2.libelle LIKE ?";
    $whereParams[]  = '%' . $filterAddress . '%';
}
$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Total cases with filter
$totalStmt = $pdo->prepare("SELECT COUNT(DISTINCT b.id) FROM batiments b $joinAddress $whereSQL");
$totalStmt->execute($whereParams);
$totalCases = (int)$totalStmt->fetchColumn();

// Treated (step5 done)
$treatedStmt = $pdo->prepare("SELECT COUNT(DISTINCT b.id) FROM batiments b $joinAddress
    JOIN documents_officiels d5 ON d5.batiment_id = b.id AND d5.type='step5_decision'
    $whereSQL");
$treatedStmt->execute($whereParams);
$treatedCases = (int)$treatedStmt->fetchColumn();

// In progress
$inProgressStmt = $pdo->prepare("SELECT COUNT(DISTINCT b.id) FROM batiments b $joinAddress
    JOIN documents_officiels dp ON dp.batiment_id = b.id AND dp.type IN ('step2_pv','step3_expert_request','step4_expert_report')
    WHERE b.id NOT IN (SELECT batiment_id FROM documents_officiels WHERE type='step5_decision')
    " . ($whereClauses ? 'AND ' . implode(' AND ', $whereClauses) : ''));
$inProgressStmt->execute($whereParams);
$inProgressCases = (int)$inProgressStmt->fetchColumn();

$newCases = max(0, $totalCases - $treatedCases - $inProgressCases);

// Cases per year (for bar chart)
$yearRows = $pdo->query("
    SELECT YEAR(b.date_reclamation) AS yr, COUNT(*) AS cnt
    FROM batiments b
    WHERE b.date_reclamation IS NOT NULL
    GROUP BY yr ORDER BY yr ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Cases per address (top 10)
$addressRows = $pdo->query("
    SELECT a.libelle, COUNT(DISTINCT d.batiment_id) AS cnt
    FROM adresses a
    JOIN documents_officiels d ON d.address_id = a.id AND d.type='step2_pv'
    GROUP BY a.id ORDER BY cnt DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Available years for filter
$years = $pdo->query("SELECT DISTINCT YEAR(date_reclamation) AS yr FROM batiments WHERE date_reclamation IS NOT NULL ORDER BY yr DESC")->fetchAll(PDO::FETCH_COLUMN);

// Available addresses for filter (all unique)
$allAddresses = $pdo->query("SELECT DISTINCT libelle FROM adresses ORDER BY libelle ASC LIMIT 500")->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>لوحة الإحصائيات</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Segoe UI,Arial;background:#f0f2f5}
header{background:linear-gradient(135deg,#1a3c5e,#2e6da4);color:#fff;padding:16px 22px}
.wrap{padding:18px;max-width:1200px;margin:0 auto}
.grid-kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px}
.card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 3px 12px rgba(0,0,0,.08)}
.label{color:#666;font-size:13px}.num{font-size:32px;font-weight:700;margin-top:6px}
.charts-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:18px;margin-top:18px}
.filter-bar{background:#fff;border-radius:12px;padding:14px 18px;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:16px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end}
.filter-bar label{font-size:12px;color:#555;font-weight:700;display:block;margin-bottom:4px}
.filter-bar select,.filter-bar input{padding:7px 10px;border:2px solid #e9ecef;border-radius:7px;font-family:inherit;font-size:13px}
.btn-filter{padding:9px 16px;border:none;border-radius:8px;background:#2e6da4;color:#fff;cursor:pointer;font-family:inherit}
.btn-reset{padding:9px 14px;border:none;border-radius:8px;background:#6c757d;color:#fff;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center}
.kpi-icon{font-size:28px;margin-bottom:4px}
</style>
</head>
<body>
<?php include '_menu.php'; ?>
<header><h2 style="margin:0">📊 لوحة الإحصائيات</h2></header>
<div class="wrap">

    <!-- Filters -->
    <form method="GET" class="filter-bar">
        <div>
            <label>السنة</label>
            <select name="year">
                <option value="">-- كل السنوات --</option>
                <?php foreach($years as $y): ?>
                <option value="<?= (int)$y ?>" <?= $filterYear === (int)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>العنوان</label>
            <input type="text" name="address" list="addr-list" value="<?= htmlspecialchars($filterAddress) ?>" placeholder="ابحث عن عنوان...">
            <datalist id="addr-list">
                <?php foreach($allAddresses as $adr): ?>
                <option value="<?= htmlspecialchars($adr) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <div>
            <button type="submit" class="btn-filter">🔍 بحث</button>
            <?php if ($filterYear || $filterAddress !== ''): ?>
            <a href="dashboard.php" class="btn-reset">✖ إلغاء</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- KPI Cards -->
    <div class="grid-kpi">
        <div class="card"><div class="kpi-icon">📁</div><div class="label">إجمالي الملفات</div><div class="num" style="color:#2e6da4"><?= $totalCases ?></div></div>
        <div class="card"><div class="kpi-icon">✅</div><div class="label">ملفات مكتملة</div><div class="num" style="color:#28a745"><?= $treatedCases ?></div></div>
        <div class="card"><div class="kpi-icon">⏳</div><div class="label">ملفات قيد المعالجة</div><div class="num" style="color:#f39c12"><?= $inProgressCases ?></div></div>
        <div class="card"><div class="kpi-icon">🆕</div><div class="label">ملفات جديدة</div><div class="num" style="color:#dc3545"><?= $newCases ?></div></div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <div class="card">
            <h3 style="margin-top:0;font-size:15px">توزيع الملفات حسب الحالة</h3>
            <canvas id="pieChart" height="260"></canvas>
        </div>
        <div class="card">
            <h3 style="margin-top:0;font-size:15px">عدد الملفات حسب السنة</h3>
            <canvas id="barChart" height="260"></canvas>
        </div>
        <?php if (!empty($addressRows)): ?>
        <div class="card" style="grid-column:1/-1">
            <h3 style="margin-top:0;font-size:15px">أكثر 10 عناوين من حيث الملفات</h3>
            <canvas id="addressChart" height="180"></canvas>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Pie chart - case status distribution
(function(){
    var ctx = document.getElementById('pieChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['ملفات جديدة','قيد المعالجة','مكتملة'],
            datasets:[{
                data: [<?= $newCases ?>, <?= $inProgressCases ?>, <?= $treatedCases ?>],
                backgroundColor:['#dc3545','#f39c12','#28a745'],
                borderWidth:2, borderColor:'#fff'
            }]
        },
        options:{
            responsive:true,
            plugins:{legend:{position:'bottom',labels:{font:{size:13},padding:14}}}
        }
    });
})();

// Bar chart - cases per year
(function(){
    var ctx = document.getElementById('barChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($yearRows, 'yr'), JSON_UNESCAPED_UNICODE) ?>,
            datasets:[{
                label:'عدد الملفات',
                data: <?= json_encode(array_column($yearRows, 'cnt'), JSON_UNESCAPED_UNICODE) ?>,
                backgroundColor:'rgba(46,109,164,0.75)',
                borderColor:'#2e6da4',
                borderWidth:2,
                borderRadius:6
            }]
        },
        options:{
            responsive:true,
            plugins:{legend:{display:false}},
            scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}
        }
    });
})();

// Horizontal bar chart - top addresses
<?php if (!empty($addressRows)): ?>
(function(){
    var ctx = document.getElementById('addressChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($addressRows, 'libelle'), JSON_UNESCAPED_UNICODE) ?>,
            datasets:[{
                label:'عدد الملفات',
                data: <?= json_encode(array_column($addressRows, 'cnt'), JSON_UNESCAPED_UNICODE) ?>,
                backgroundColor:'rgba(111,66,193,0.72)',
                borderColor:'#6f42c1',
                borderWidth:2,
                borderRadius:5
            }]
        },
        options:{
            indexAxis:'y',
            responsive:true,
            plugins:{legend:{display:false}},
            scales:{x:{beginAtZero:true,ticks:{stepSize:1}}}
        }
    });
})();
<?php endif; ?>
</script>
</body>
</html>
