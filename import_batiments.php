<?php
/**
 * Script d'importation des bâtiments menacés de ruine depuis le fichier Excel.
 * Peut être exécuté depuis le CLI ou depuis le navigateur (réservé à l'admin).
 *
 * Fonctionnement :
 *  1. Lit le fichier Excel "liste des batiments menacés ruine (1).xlsx"
 *  2. Insère les données dans la table `batiments` (ignore les doublons)
 *  3. Génère aléatoirement des étapes/documents pour simuler un historique
 *     en utilisant les données réelles de l'Excel quand elles sont disponibles.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ─── Authentification web ──────────────────────────────────────────────── */
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/config.php';
    requireRole('admin');
    require_once __DIR__ . '/db.php';
} else {
    $host     = 'localhost';
    $dbname   = 'batiments_ruine';
    $username = 'root';
    $password = '';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        fwrite(STDERR, "❌ DB Error: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

/* ─── Chemin du fichier Excel ───────────────────────────────────────────── */
$xlsxPath = __DIR__ . '/liste des batiments menacés ruine (1).xlsx';
if (PHP_SAPI === 'cli' && !empty($argv[1])) {
    $xlsxPath = $argv[1];
}

/* ─── Fonctions utilitaires ─────────────────────────────────────────────── */

/**
 * Convertit un numéro de série Excel en date MySQL (YYYY-MM-DD).
 * Excel epoch = 1899-12-30, donc offset = 25569 jours avant l'epoch Unix.
 */
function excelSerialToDate($serial): ?string
{
    if (!is_numeric($serial) || (float)$serial <= 0) {
        return null;
    }
    $unixTimestamp = ((float)$serial - 25569) * 86400;
    if ($unixTimestamp < 0 || $unixTimestamp > 4102444800) {
        return null;
    }
    return date('Y-m-d', (int)$unixTimestamp);
}

/**
 * Extrait une date DD/MM/YYYY depuis un texte mixte arabe/numérique.
 */
function extractDateFromText(string $text): ?string
{
    if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $text, $m)) {
        $day   = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $year  = $m[3];
        if (checkdate((int)$month, (int)$day, (int)$year)) {
            return "$year-$month-$day";
        }
    }
    return null;
}

/**
 * Convertit une valeur Excel (numérique série ou texte) en date MySQL.
 */
function toMysqlDate($val): ?string
{
    $val = trim((string)$val);
    if ($val === '') {
        return null;
    }
    if (is_numeric($val)) {
        return excelSerialToDate((float)$val);
    }
    return extractDateFromText($val);
}

/**
 * Retourne une date MySQL aléatoire entre deux dates.
 */
function randomDate(string $from, string $to): string
{
    $fromTs = strtotime($from);
    $toTs   = strtotime($to);
    if ($toTs <= $fromTs) {
        $toTs = $fromTs + 86400 * 30;
    }
    return date('Y-m-d', mt_rand($fromTs, $toTs));
}

/**
 * Détermine les étapes à créer pour un bâtiment importé.
 *
 * Retourne un tableau d'étapes avec ['type', 'statut', 'date', 'extra'].
 * Utilise les données réelles de l'Excel quand elles sont présentes,
 * et simule aléatoirement le reste.
 *
 * @param string|null $pvDate        Date du PV (colonne G)
 * @param string|null $expertDate    Date de l'expertise (colonne P)
 * @param string|null $decisionEvac  Décision d'évacuation (colonne Q)
 * @param string|null $decisionDemo  Décision de démolition (colonne R)
 * @param string      $baseDate      Date de réclamation (base)
 * @param string|null $proprietaire  Propriétaire
 * @param string|null $commission    Membres de la commission
 * @param string|null $commissionDefault  Membres par défaut (depuis la DB)
 * @param string|null $observations  Observations
 * @param int|null    $exploiteOui   Exploité (oui)
 * @return array  Liste des étapes à insérer
 */
function buildStepsForBatiment(
    ?string $pvDate,
    ?string $expertDate,
    ?string $decisionEvac,
    ?string $decisionDemo,
    string  $baseDate,
    ?string $proprietaire,
    ?string $commission,
    ?string $commissionDefault,
    ?string $observations,
    int     $exploiteOui
): array {
    $steps = [];

    /* ── Étape 2 : محضر المعاينة ────────────────────────────────────────── */
    $hasRealPv = ($pvDate !== null);
    if ($hasRealPv) {
        $step2Statut = 'finalise';
        $step2Date   = $pvDate;
    } else {
        $rand = mt_rand(1, 10);
        if ($rand <= 4) {
            return $steps; // 40 % → s'arrête à l'étape 1
        }
        $step2Statut = ($rand <= 7) ? 'brouillon' : 'finalise';
        $step2Date   = randomDate($baseDate, date('Y-m-d', strtotime($baseDate . ' +90 days')));
    }

    $steps[] = [
        'type'         => 'step2_pv',
        'statut'       => $step2Statut,
        'date'         => $step2Date,
        'commission'   => $commission ?: $commissionDefault,
        'owner_name'   => $proprietaire,
        'exploite_by'  => $exploiteOui ? 'oui' : null,
        'decision_type'=> null,
        'observations' => null,
    ];

    if ($step2Statut !== 'finalise') {
        return $steps;
    }

    /* ── Étape 3 : تكليف خبير ───────────────────────────────────────────── */
    $hasRealExpert = ($expertDate !== null);
    if ($hasRealExpert) {
        $step3Statut = 'finalise';
        $step3Date   = randomDate($step2Date, date('Y-m-d', strtotime($step2Date . ' +60 days')));
    } else {
        $rand = mt_rand(1, 10);
        if ($rand <= 5) {
            return $steps; // 50 % → s'arrête à l'étape 2
        }
        $step3Statut = ($rand <= 8) ? 'finalise' : 'brouillon';
        $step3Date   = randomDate($step2Date, date('Y-m-d', strtotime($step2Date . ' +60 days')));
    }

    $steps[] = [
        'type'         => 'step3_expert_request',
        'statut'       => $step3Statut,
        'date'         => $step3Date,
        'commission'   => null,
        'owner_name'   => null,
        'exploite_by'  => null,
        'decision_type'=> null,
        'observations' => null,
    ];

    if ($step3Statut !== 'finalise') {
        return $steps;
    }

    /* ── Étape 4 : رجوع تقرير الخبير ───────────────────────────────────── */
    if ($hasRealExpert) {
        $step4Statut = 'finalise';
        $step4Date   = $expertDate;
    } else {
        $rand = mt_rand(1, 10);
        if ($rand <= 3) {
            return $steps;
        }
        $step4Statut = ($rand <= 7) ? 'finalise' : 'brouillon';
        $step4Date   = randomDate($step3Date, date('Y-m-d', strtotime($step3Date . ' +90 days')));
    }

    $steps[] = [
        'type'         => 'step4_expert_report',
        'statut'       => $step4Statut,
        'date'         => $step4Date,
        'commission'   => null,
        'owner_name'   => null,
        'exploite_by'  => null,
        'decision_type'=> null,
        'observations' => $observations,
    ];

    if ($step4Statut !== 'finalise') {
        return $steps;
    }

    /* ── Étape 5 : قرار الإخلاء / الهدم ───────────────────────────────── */
    $hasDecision = ($decisionEvac !== null || $decisionDemo !== null);
    if ($hasDecision) {
        $step5Statut  = 'finalise';
        $step5Date    = randomDate($step4Date, date('Y-m-d', strtotime($step4Date . ' +30 days')));
        $step5DecType = ($decisionDemo !== null) ? 'demolition' : 'evacuation';
        $step5Obs     = $decisionDemo ?: $decisionEvac;
    } else {
        $rand = mt_rand(1, 10);
        if ($rand <= 4) {
            return $steps;
        }
        $step5Statut  = ($rand <= 7) ? 'finalise' : 'brouillon';
        $step5Date    = randomDate($step4Date, date('Y-m-d', strtotime($step4Date . ' +45 days')));
        $step5DecType = (mt_rand(0, 1) === 0) ? 'demolition' : 'evacuation';
        $step5Obs     = null;
    }

    $steps[] = [
        'type'         => 'step5_decision',
        'statut'       => $step5Statut,
        'date'         => $step5Date,
        'commission'   => null,
        'owner_name'   => null,
        'exploite_by'  => null,
        'decision_type'=> $step5DecType,
        'observations' => $step5Obs,
    ];

    return $steps;
}

/* ─── Lecture du fichier Excel ──────────────────────────────────────────── */
if (!is_file($xlsxPath)) {
    $msg = "❌ Fichier introuvable : $xlsxPath";
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $msg . PHP_EOL); exit(1); }
    die("<div style='font-family:Arial;padding:20px;color:red'>$msg</div>");
}

if (!class_exists('ZipArchive')) {
    $msg = "❌ Extension PHP ZipArchive requise.";
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $msg . PHP_EOL); exit(1); }
    die("<div style='font-family:Arial;padding:20px;color:red'>$msg</div>");
}

$zip = new ZipArchive();
if ($zip->open($xlsxPath) !== true) {
    $msg = "❌ Impossible d'ouvrir le fichier Excel.";
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $msg . PHP_EOL); exit(1); }
    die("<div style='font-family:Arial;padding:20px;color:red'>$msg</div>");
}

/* Shared strings */
$sharedStrings = [];
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
if ($ssXml) {
    $ss = simplexml_load_string($ssXml);
    foreach ($ss->si as $si) {
        if (isset($si->t)) {
            $sharedStrings[] = (string)$si->t;
        } else {
            $t = '';
            foreach ($si->r as $r) {
                $t .= (string)$r->t;
            }
            $sharedStrings[] = $t;
        }
    }
}

$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
$zip->close();

if (!$sheetXml) {
    $msg = "❌ Feuille sheet1.xml introuvable dans le fichier Excel.";
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $msg . PHP_EOL); exit(1); }
    die("<div style='font-family:Arial;padding:20px;color:red'>$msg</div>");
}

$dom = new DOMDocument();
$dom->loadXML($sheetXml);
$xp = new DOMXPath($dom);
$xp->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
$rowNodes = $xp->query('//s:sheetData/s:row');

/* ─── Extraction des lignes de données ──────────────────────────────────── */
$dataRows = [];
for ($ri = 0; $ri < $rowNodes->length; $ri++) {
    $row    = $rowNodes->item($ri);
    $rowNum = (int)$row->getAttribute('r');
    if ($rowNum < 10) {
        continue;
    }

    $rowData = [];
    $cells   = $xp->query('s:c', $row);
    foreach ($cells as $cell) {
        $ref   = $cell->getAttribute('r');
        $type  = $cell->getAttribute('t');
        $vList = $xp->query('s:v', $cell);
        $val   = $vList->length > 0 ? $vList->item(0)->textContent : '';
        if ($type === 's') {
            $val = $sharedStrings[(int)$val] ?? '';
        }
        // Extract column letter(s) by stripping trailing digits
        $col = rtrim($ref, '0123456789');
        $rowData[$col] = trim($val);
    }

    if (!isset($rowData['A']) || !is_numeric($rowData['A']) || (int)$rowData['A'] <= 0) {
        continue;
    }
    $rowData['_rowNum'] = $rowNum;
    $dataRows[] = $rowData;
}

/* ─── Préparation SQL ───────────────────────────────────────────────────── */
$checkExisting = $pdo->prepare(
    "SELECT id FROM batiments WHERE bureau_ordre_id = ? LIMIT 1"
);

$insertBatiment = $pdo->prepare("
    INSERT INTO batiments
        (bureau_ordre_id, numero_rapport, date_reclamation, proprietaire,
         notification_pending, lieu, date_rapport, mise_a_jour, notification,
         exploite_oui, exploite_non, commission,
         date_envoi_tratiib, date_envoi_wiz, date_envoi_turat, date_envoi_juridique,
         date_expert, decision_evacuation, decision_demolition, observations)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$insertDoc = $pdo->prepare("
    INSERT IGNORE INTO documents_officiels
        (batiment_id, type, preceding_document_id, statut, numero_doc, date_doc,
         commission_members, owner_name, exploite_by, decision_type, observations)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$getDocId = $pdo->prepare(
    "SELECT id FROM documents_officiels WHERE batiment_id = ? AND type = ? LIMIT 1"
);

/* Membres de commission par défaut */
$commissionDefault = null;
try {
    $cmRows = $pdo->query(
        "SELECT titre, nom FROM commission_members WHERE actif=1 ORDER BY ordre ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($cmRows) {
        $parts = [];
        foreach ($cmRows as $cm) {
            $parts[] = $cm['titre'] . ': ' . $cm['nom'];
        }
        $commissionDefault = implode(' | ', $parts);
    }
} catch (PDOException $e) {
    // Table inexistante, continuer sans membres
}

/* ─── Import ────────────────────────────────────────────────────────────── */
$inserted  = 0;
$skipped   = 0;
$docsAdded = 0;
$errors    = [];

foreach ($dataRows as $r) {
    $rowNum = (int)($r['_rowNum'] ?? 0);

    /* Extraction des champs */
    $bureauOrdreId   = trim($r['B'] ?? '');
    if ($bureauOrdreId === '') {
        $bureauOrdreId = 'IMP-' . $rowNum;
    }
    $lieu            = trim($r['C'] ?? '') ?: null;
    $proprietaire    = trim($r['D'] ?? '') ?: null;
    $miseAJour       = trim($r['E'] ?? '') ?: null;
    $notificationRaw = trim($r['F'] ?? '');
    $pvDateRaw       = trim($r['G'] ?? '');
    $exploiteOui     = (trim($r['H'] ?? '') !== '') ? 1 : 0;
    $exploiteNon     = (trim($r['I'] ?? '') !== '') ? 1 : 0;
    $commission      = trim($r['J'] ?? '') ?: null;
    $dateTratiib     = toMysqlDate(trim($r['K'] ?? ''));
    $dateWiz         = toMysqlDate(trim($r['L'] ?? ''));
    $dateTurat       = toMysqlDate(trim($r['M'] ?? ''));
    $dateJuridique   = toMysqlDate(trim($r['N'] ?? ''));
    $expertDateRaw   = trim($r['P'] ?? '');
    $decisionEvac    = trim($r['Q'] ?? '') ?: null;
    $decisionDemo    = trim($r['R'] ?? '') ?: null;
    $observations    = trim($r['S'] ?? '') ?: null;

    $pvDate     = toMysqlDate($pvDateRaw);
    $expertDate = toMysqlDate($expertDateRaw);
    $notifDate  = toMysqlDate($notificationRaw) ?: ($notificationRaw ?: null);

    /* Date de réclamation ≈ 2 semaines avant le PV */
    if ($pvDate) {
        $dateReclamation = date('Y-m-d', strtotime($pvDate . ' -14 days'));
    } else {
        $dateReclamation = date('Y-m-d', mktime(0, 0, 0, mt_rand(1, 12), mt_rand(1, 28), 2024));
    }

    /* Vérification doublon */
    $checkExisting->execute([$bureauOrdreId]);
    if ($checkExisting->fetchColumn()) {
        $skipped++;
        continue;
    }

    /* Insertion du bâtiment */
    try {
        $insertBatiment->execute([
            $bureauOrdreId, $bureauOrdreId, $dateReclamation, $proprietaire,
            0, $lieu, $pvDate, $miseAJour, $notifDate,
            $exploiteOui, $exploiteNon, $commission,
            $dateTratiib, $dateWiz, $dateTurat, $dateJuridique,
            $expertDate, $decisionEvac, $decisionDemo, $observations,
        ]);
        $batimentId = (int)$pdo->lastInsertId();
        $inserted++;
    } catch (PDOException $e) {
        $errors[] = "Ligne $bureauOrdreId : " . $e->getMessage();
        continue;
    }

    /* Génération des étapes simulées */
    $steps = buildStepsForBatiment(
        $pvDate, $expertDate, $decisionEvac, $decisionDemo,
        $dateReclamation, $proprietaire, $commission, $commissionDefault,
        $observations, $exploiteOui
    );

    $prevDocId = null;
    foreach ($steps as $step) {
        try {
            $insertDoc->execute([
                $batimentId,
                $step['type'],
                $prevDocId,
                $step['statut'],
                ($step['type'] === 'step2_pv') ? $bureauOrdreId : null,
                $step['date'],
                $step['commission'],
                $step['owner_name'],
                $step['exploite_by'],
                $step['decision_type'],
                $step['observations'],
            ]);
            $getDocId->execute([$batimentId, $step['type']]);
            $docId = (int)($getDocId->fetchColumn() ?: 0);
            if ($docId > 0) {
                $prevDocId = $docId;
                $docsAdded++;
            }
        } catch (PDOException $e) {
            $errors[] = "{$step['type']} $bureauOrdreId : " . $e->getMessage();
        }
    }
}

/* ─── Résultats ─────────────────────────────────────────────────────────── */
$summary = "✅ Import terminé : $inserted bâtiments ajoutés, $skipped ignorés (doublon), $docsAdded documents/étapes créés.";
if ($errors) {
    $summary .= "\n⚠️ " . count($errors) . " erreur(s) : " . implode(' | ', array_slice($errors, 0, 5));
}

if (PHP_SAPI === 'cli') {
    echo $summary . PHP_EOL;
    exit(empty($errors) ? 0 : 2);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد البنايات</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; display: flex;
               align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; padding: 40px 50px; border-radius: 14px;
                box-shadow: 0 4px 20px rgba(0,0,0,.12); text-align: center; max-width: 600px; }
        h2 { color: #1a3c5e; }
        .stat { font-size: 18px; margin: 8px 0; }
        .ok  { color: #28a745; }
        .skip{ color: #6c757d; }
        .doc { color: #007bff; }
        .err { color: #dc3545; font-size: 13px; text-align: right; margin-top: 12px;
               background: #fff3cd; padding: 10px; border-radius: 6px; }
        a { display: inline-block; margin-top: 20px; padding: 10px 24px;
            background: #1a3c5e; color: #fff; text-decoration: none; border-radius: 8px; }
    </style>
</head>
<body>
<div class="card">
    <h2>📥 نتيجة الاستيراد</h2>
    <p class="stat ok">✅ بنايات مضافة : <strong><?= $inserted ?></strong></p>
    <p class="stat skip">⏭️ تم تجاهلها (موجودة مسبقاً) : <strong><?= $skipped ?></strong></p>
    <p class="stat doc">📄 وثائق / مراحل أُنشئت : <strong><?= $docsAdded ?></strong></p>
    <?php if ($errors): ?>
        <div class="err">
            <strong>⚠️ أخطاء (<?= count($errors) ?>) :</strong><br>
            <?= nl2br(htmlspecialchars(implode("\n", array_slice($errors, 0, 10)))) ?>
        </div>
    <?php endif; ?>
    <a href="index.php">↩️ العودة للقائمة</a>
</div>
</body>
</html>
