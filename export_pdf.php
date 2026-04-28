<?php
require 'config.php';
requireLogin();
require 'db.php';

$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT DISTINCT b.*
        FROM batiments b
        LEFT JOIN documents_officiels d ON d.batiment_id = b.id AND d.type='step2_pv'
        LEFT JOIN adresses a ON a.id = d.address_id
        WHERE b.bureau_ordre_id LIKE :s OR b.proprietaire LIKE :s OR b.lieu LIKE :s OR a.libelle LIKE :s
        ORDER BY b.id ASC
    ");
    $stmt->execute([':s' => "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM batiments ORDER BY id ASC");
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>تقرير البنايات</title>
<style>
body{font-family:Arial,sans-serif;direction:rtl;padding:14mm}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px;font-size:12px}
th{background:#f3f3f3}
@media print{.noprint{display:none}}
</style>
</head>
<body>
<div class="noprint" style="position:fixed;top:10px;left:10px"><button onclick="window.print()">🖨️ PDF / طباعة</button></div>
<h3 style="text-align:center">تقرير البنايات المتداعية</h3>
<table>
    <thead><tr><th>#</th><th>ID</th><th>المالك</th><th>المكان</th><th>تاريخ الشكاية</th><th>اللجنة</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" style="text-align:center">لا توجد نتائج</td></tr><?php endif; ?>
    <?php foreach ($rows as $i => $r): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($r['bureau_ordre_id'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['proprietaire'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['lieu'] ?? '') ?></td>
            <td><?= !empty($r['date_reclamation']) ? date('d/m/Y', strtotime($r['date_reclamation'])) : '' ?></td>
            <td><?= htmlspecialchars($r['commission'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
