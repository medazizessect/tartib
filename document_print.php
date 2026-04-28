<?php
$numDoc = $v['numero_doc'] ?? '';
$dateDoc = !empty($v['date_doc']) ? date('d/m/Y', strtotime($v['date_doc'])) : date('d/m/Y');
$commissionMembers = trim((string)($v['commission_members'] ?? $case['commission'] ?? ''));
$ownerLabel = trim((string)($v['owner_name'] ?? ''));
if ($ownerLabel === '') $ownerLabel = trim((string)($case['proprietaire'] ?? ''));
$printTitle = $label;
if (($type ?? '') === 'step2_pv') {
    $printTitle = 'محضر معاينة للبناية المتداعية للسقوط';
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($printTitle) ?></title>
<style>
*{box-sizing:border-box} body{font-family:'Times New Roman',serif;direction:rtl;padding:14mm;font-size:15px;line-height:1.9}
.top{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:4mm}
.logo img{width:70px;height:70px;object-fit:contain}
.blk{font-size:11pt;line-height:1.9}
.title{text-align:center;margin:7mm 0 5mm} .title h1{font-size:18pt;text-decoration:underline;margin:0}
.meta{display:flex;justify-content:space-between;margin-bottom:3mm}
.r{margin:1.5mm 0}
.line{border-bottom:1px dotted #666;display:inline-block;min-width:150px;padding:0 4px}
@media print{.noprint{display:none}}
</style>
</head>
<body>
<div class="noprint" style="position:fixed;top:10px;left:10px"><button onclick="window.print()">🖨️ طباعة</button></div>
<div class="top">
  <div class="blk">الجمهورية التونسية<br>وزارة الداخلية<br>بلدية سوسة <br><b>عدد:</b> <?= htmlspecialchars($numDoc ?: '...') ?></div>
  <div class="logo"><img src="Logo_commune_Sousse.svg" alt="logo"></div>
  <div class="blk" style="text-align:left"></div>

</div>
<div class="title"><h1><?= htmlspecialchars($printTitle) ?></h1></div>
عملا بالفصل (6) و الفصل (41) من القانون عدد 33 لسنة 2024 مؤرخ في 28 جوان 2024 يتعلق بالبنايات المتداعية للسقوط <br>
<div class="meta"><div><b>عدد:</b> <?= htmlspecialchars($numDoc ?: '...') ?></div><div><b>في:</b> <?= $dateDoc ?></div></div>
<div class="r"><b> تبعا للإشعار  </b> <span class="line"><?= htmlspecialchars($case['bureau_ordre_id'] ?? '') ?></span></div>
<div class="r"><b>المالك:</b> <span class="line"><?= htmlspecialchars($ownerLabel) ?></span></div>
<?php if (!empty($addressLabel)): ?><div class="r"><b>الكائن بــ:</b> <span class="line"><?= htmlspecialchars($addressLabel) ?></span></div><?php endif; ?>
<?php if (!empty($v['cin'])): ?><div class="r"><b>ب.ت.و:</b> <span class="line"><?= htmlspecialchars($v['cin']) ?></span></div><?php endif; ?>
<?php if ($commissionMembers !== ''): ?><div class="r"><b>توجــهنا نحـن الممضون أسفـله </b> <?= htmlspecialchars($commissionMembers) ?></div><?php endif; ?>
<?php if (!empty($v['occupied_by'])): ?><div class="r"><b>والذي هو على ملك / والمشغول من طرف السيد(ة) :</b> <span class="line"><?= htmlspecialchars($v['occupied_by']) ?></span></div><?php endif; ?>
<?php if (!empty($v['subject'])): ?><div class="r"><b>الموضوع:</b> <span class="line"><?= htmlspecialchars($v['subject']) ?></span></div><?php endif; ?>
<?php if (!empty($v['expert_name'])): ?><div class="r"><b>الخبير:</b> <span class="line"><?= htmlspecialchars($v['expert_name']) ?></span></div><?php endif; ?>
<?php if (!empty($v['report_type'])): ?><div class="r"><b>نوع التقرير:</b> <span class="line"><?= $v['report_type'] === 'initial' ? 'أولي' : 'نهائي' ?></span></div><?php endif; ?>
<?php if (!empty($v['decision_type'])): ?><div class="r"><b>القرار:</b> <span class="line"><?= $v['decision_type'] === 'evacuation' ? 'إخلاء' : 'هدم' ?></span></div><?php endif; ?>
<?php if (!empty($v['observations'])): ?><div class="r"><b>حيث  تبيّن حسب المعاينة والتشخيص الأوّلي ما يلي :</b><br><?= nl2br(htmlspecialchars($v['observations'])) ?></div><?php endif; ?><br>
 * درجة التأكد : 1 <br>
1/ الخطر الوشيك و المؤكد (خطر حتمي الوقوع في أجل قريب) <br>
2/ الخطر الوشيك (خطر موجود إلاّ أنّ لحظة وقوعه غير معلومة بصفة قطعية)
<br>
    * نسخة هذا تحال على كلّ من: <br>
السيّد الكاتب العام (للإعلام)،<br>
- إدارة الشؤون القانونية،<br>
- إدارة الأشغال البلدية،<br>
- الإدارة الجهوية للتجهيز و الإسكان،<br>
- التفقديّة الجهوية للتراث بالساحل، كــــلّ فيما يخــصّه.<br>

</body>
</html>
