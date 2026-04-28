<?php
error_reporting(0);
ini_set('display_errors', 0);
require 'db.php';

$types = [
    'step2_pv'             => ['📋 محضر',                    '#f39c12'],
    'step3_expert_request' => ['⚖️ تكليف خبير',             '#f39c12'],
    'step4_expert_report'  => ['🧪 رجوع تقرير الخبير',      '#f39c12'],
    'step5_decision'       => ['✅ قرار إخلاء أو هدم',      '#28a745'],
];

$msg  = '';
$type = $_GET['type'] ?? 'step2_pv';
if (!isset($types[$type])) $type = 'step2_pv';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("UPDATE modeles_documents SET contenu = :c WHERE type = :t")
        ->execute([':c' => $_POST['contenu'], ':t' => $type]);
    $msg = 'saved';
}

$modele = $pdo->prepare("SELECT contenu FROM modeles_documents WHERE type = ?");
$modele->execute([$type]);
$modele_contenu = $modele->fetchColumn();

$color = $types[$type][1];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة نماذج الوثائق</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f2f5;direction:rtl}
        header{
            background:linear-gradient(135deg,#1a3c5e,#2e6da4);
            color:white;padding:18px 30px;text-align:center;
        }
        header h1{font-size:21px}
        .wrap{max-width:1000px;margin:25px auto;padding:0 15px}
        .back{display:inline-flex;align-items:center;gap:6px;
              margin-bottom:16px;color:#1a3c5e;text-decoration:none;
              font-size:14px;font-weight:600}
        .back:hover{text-decoration:underline}
        .tabs{display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap}
        .tab{
            padding:9px 16px;border-radius:8px;text-decoration:none;
            font-size:13px;border:2px solid transparent;
            transition:all .18s;color:white;
        }
        .tab:hover{opacity:.85}
        .tab.active{border-color:white;box-shadow:0 2px 8px rgba(0,0,0,.2)}
        .card{background:white;border-radius:12px;padding:24px;
              box-shadow:0 3px 12px rgba(0,0,0,.09)}
        .card-title{font-size:16px;font-weight:bold;color:#1a3c5e;
                    margin-bottom:16px;padding-bottom:10px;
                    border-bottom:2px solid #e8f0fb}
        .editor-vars{background:#fffbf0;border:1px solid #f0c060;
                     border-bottom:none;padding:8px 12px;
                     font-size:12px;color:#856404;
                     border-radius:8px 8px 0 0}
        .var-tag{display:inline-block;background:#ffc107;color:#333;
                 padding:2px 8px;border-radius:10px;font-size:11px;
                 cursor:pointer;margin:2px}
        .var-tag:hover{background:#e0a800}
        textarea{
            width:100%;min-height:500px;padding:16px;
            border:1px solid #ddd;border-radius:0 0 8px 8px;
            font-size:14px;font-family:'Segoe UI',Arial,sans-serif;
            line-height:2;resize:vertical;direction:rtl;
        }
        textarea:focus{outline:none;border-color:<?= $color ?>;
                       box-shadow:0 0 0 3px <?= $color ?>22}
        .actions{display:flex;gap:10px;margin-top:16px}
        .btn{padding:10px 22px;border:none;border-radius:8px;
             cursor:pointer;font-size:14px;font-family:inherit;
             display:inline-flex;align-items:center;gap:6px;
             transition:opacity .2s;text-decoration:none}
        .btn:hover{opacity:.85}
        .btn-save{background:#28a745;color:white}
        .btn-secondary{background:#6c757d;color:white}
        .alert{padding:11px 16px;border-radius:7px;margin-bottom:14px;font-size:14px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
    </style>
</head>
<body>
<header><h1>📝 إدارة نماذج الوثائق الرسمية</h1></header>
<div class="wrap">
    <a href="index.php" class="back">↩️ رجوع للقائمة</a>

    <!-- Onglets -->
    <div class="tabs">
        <?php foreach ($types as $t => $tl): ?>
        <a href="?type=<?= $t ?>"
           class="tab <?= $t === $type ? 'active' : '' ?>"
           style="background:<?= $tl[1] ?>">
            <?= $tl[0] ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($msg === 'saved'): ?>
        <div class="alert alert-success">✅ تم حفظ النموذج بنجاح!</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">
            تعديل نموذج: <?= $types[$type][0] ?>
        </div>

        <!-- Variables -->
        <div class="editor-vars">
            <strong>📌 المتغيرات المتاحة:</strong><br>
            <?php
            $vars = [
                '{{numero_rapport}}'     => 'عدد المحضر',
                '{{lieu}}'               => 'المكان',
                '{{proprietaire}}'       => 'المالك',
                '{{date_rapport}}'       => 'تاريخ المعاينة',
                '{{date_expert}}'        => 'تاريخ الخبير',
                '{{nom_expert}}'         => 'اسم الخبير',
                '{{nom_juge}}'           => 'اسم القاضي',
                '{{date_izn_tribunal}}'  => 'تاريخ الإذن',
                '{{contenu_specifique}}' => 'المحتوى التفصيلي',
            ];
            foreach ($vars as $v => $l): ?>
                <span class="var-tag"
                      onclick="insertVar('<?= $v ?>')"><?= $l ?></span>
            <?php endforeach; ?>
        </div>

        <form method="POST">
            <textarea name="contenu"
                      id="editor"><?= htmlspecialchars($modele_contenu) ?></textarea>
            <div class="actions">
                <button type="submit" class="btn btn-save">💾 حفظ النموذج</button>
                <a href="index.php" class="btn btn-secondary">❌ إلغاء</a>
            </div>
        </form>
    </div>
</div>
<script>
function insertVar(v) {
    var ta = document.getElementById('editor');
    var s  = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.substring(0,s) + v + ta.value.substring(e);
    ta.selectionStart = ta.selectionEnd = s + v.length;
    ta.focus();
}
</script>
</body>
</html>
