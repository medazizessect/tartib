<?php
error_reporting(0);
ini_set('display_errors', 0);
require 'config.php';
requireLogin();
require 'db.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT DISTINCT b.*
        FROM batiments b
        LEFT JOIN documents_officiels d ON d.batiment_id = b.id AND d.type='step2_pv'
        LEFT JOIN adresses a ON a.id = d.address_id
        WHERE b.numero_rapport LIKE :s1 OR b.lieu LIKE :s2
           OR b.proprietaire LIKE :s3 OR b.observations LIKE :s4
           OR b.bureau_ordre_id LIKE :s5 OR a.libelle LIKE :s6
        ORDER BY b.id ASC
    ");
    $like = "%$search%";
    $stmt->execute([':s1' => $like, ':s2' => $like, ':s3' => $like, ':s4' => $like, ':s5' => $like, ':s6' => $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM batiments ORDER BY id ASC");
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// Helpers
// ============================================================
function esc($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
function dt($v) {
    return $v ? date('d/m/Y', strtotime($v)) : '';
}

$filename = 'batiments_ruine_' . date('Y-m-d_H-i-s') . '.xlsx';

// ============================================================
// Shared Strings
// ============================================================
$strings      = [];
$stringIndex  = [];

function si($s) {
    global $strings, $stringIndex;
    $s = (string)($s ?? '');
    if (!array_key_exists($s, $stringIndex)) {
        $stringIndex[$s] = count($strings);
        $strings[]       = $s;
    }
    return $stringIndex[$s];
}

// Pré-charger toutes les chaînes nécessaires
$headers = [
    '#',
    'عدد المحضر',
    'المكان',
    'المالك / المشغول',
    'تاريخ المعاينة',
    'مستغلة',
    'اللجنة',
    'توجيه التراتيب',
    'توجيه الوزارة',
    'توجيه التراث',
    'توجيه الشؤون القانونية',
    'إجابة التراث',
    'تاريخ الخبير',
    'قرار إخلاء',
    'قرار هدم',
    'ملاحظات',
];

si('جدول بياني للبنايات المتداعية للسقوط');
si('بلدية سوسة — إدارة الشؤون التقنية — تاريخ التصدير: ' . date('d/m/Y H:i'));
foreach ($headers as $h) si($h);

// Préparer les lignes de données
$dataRows = [];
foreach ($rows as $i => $b) {
    $dataRows[] = [
        $i + 1,
        $b['numero_rapport']      ?? '',
        $b['lieu']                ?? '',
        $b['proprietaire']        ?? '',
        dt($b['date_rapport']),
        $b['exploite_oui'] ? 'نعم' : ($b['exploite_non'] ? 'لا' : ''),
        $b['commission']          ?? '',
        dt($b['date_envoi_tratiib']),
        dt($b['date_envoi_wiz']),
        dt($b['date_envoi_turat']),
        dt($b['date_envoi_juridique']),
        $b['reponse_turat']       ?? '',
        dt($b['date_expert']),
        $b['decision_evacuation'] ?? '',
        $b['decision_demolition'] ?? '',
        $b['observations']        ?? '',
    ];
}
foreach ($dataRows as $row) {
    foreach ($row as $ci => $cell) {
        if ($ci !== 0) si((string)$cell);
    }
}

// ============================================================
// [Content_Types].xml
// ============================================================
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels"
    ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

// ============================================================
// _rels/.rels
// ============================================================
$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>
</Relationships>';

// ============================================================
// xl/_rels/workbook.xml.rels
// ============================================================
$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
    Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"
    Target="sharedStrings.xml"/>
  <Relationship Id="rId3"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
    Target="styles.xml"/>
</Relationships>';

// ============================================================
// xl/workbook.xml
// ============================================================
$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <fileVersion appName="xl" lastEdited="5" lowestEdited="5"/>
  <workbookPr date1904="0"/>
  <bookViews>
    <workbookView xWindow="0" yWindow="0" windowWidth="22260" windowHeight="12645"/>
  </bookViews>
  <sheets>
    <sheet name="البيانات" sheetId="1" r:id="rId1"/>
  </sheets>
  <calcPr calcId="144523"/>
</workbook>';

// ============================================================
// xl/styles.xml  —  styles simplifiés et validés
// ============================================================
$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">

  <fonts count="5">
    <!-- 0 : normal -->
    <font>
      <sz val="10"/>
      <name val="Calibri"/>
    </font>
    <!-- 1 : titre (grand, gras, blanc) -->
    <font>
      <b/>
      <sz val="16"/>
      <color rgb="FFFFFFFF"/>
      <name val="Calibri"/>
    </font>
    <!-- 2 : en-tête colonne (gras, blanc) -->
    <font>
      <b/>
      <sz val="10"/>
      <color rgb="FFFFFFFF"/>
      <name val="Calibri"/>
    </font>
    <!-- 3 : données gras bleu foncé -->
    <font>
      <b/>
      <sz val="10"/>
      <color rgb="FF1A3C5E"/>
      <name val="Calibri"/>
    </font>
    <!-- 4 : données normal -->
    <font>
      <sz val="10"/>
      <color rgb="FF333333"/>
      <name val="Calibri"/>
    </font>
  </fonts>

  <fills count="6">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <!-- 2 : bleu foncé (titre / header) -->
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FF1A3C5E"/>
      </patternFill>
    </fill>
    <!-- 3 : bleu très clair (lignes paires) -->
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FFE8F0FB"/>
      </patternFill>
    </fill>
    <!-- 4 : vert clair (نعم) -->
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FFD4EDDA"/>
      </patternFill>
    </fill>
    <!-- 5 : rouge clair (قرار هدم) -->
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FFF8D7DA"/>
      </patternFill>
    </fill>
  </fills>

  <borders count="2">
    <!-- 0 : pas de bordure -->
    <border>
      <left/><right/><top/><bottom/><diagonal/>
    </border>
    <!-- 1 : bordure fine grise -->
    <border>
      <left   style="thin"><color rgb="FFCCCCCC"/></left>
      <right  style="thin"><color rgb="FFCCCCCC"/></right>
      <top    style="thin"><color rgb="FFCCCCCC"/></top>
      <bottom style="thin"><color rgb="FFCCCCCC"/></bottom>
      <diagonal/>
    </border>
  </borders>

  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>

  <cellXfs count="8">
    <!-- 0 : cellule normale blanche -->
    <xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0">
      <alignment horizontal="center" vertical="center"
                 wrapText="1" readingOrder="2"/>
    </xf>
    <!-- 1 : titre fusionné -->
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0">
      <alignment horizontal="center" vertical="center" readingOrder="2"/>
    </xf>
    <!-- 2 : en-tête colonne -->
    <xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0">
      <alignment horizontal="center" vertical="center"
                 wrapText="1" readingOrder="2"/>
    </xf>
    <!-- 3 : ligne paire (bleu clair) -->
    <xf numFmtId="0" fontId="4" fillId="3" borderId="1" xfId="0">
      <alignment horizontal="center" vertical="center"
                 wrapText="1" readingOrder="2"/>
    </xf>
    <!-- 4 : texte droit blanc -->
    <xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0">
      <alignment horizontal="right" vertical="center"
                 wrapText="1" readingOrder="2"/>
    </xf>
    <!-- 5 : texte droit bleu clair -->
    <xf numFmtId="0" fontId="4" fillId="3" borderId="1" xfId="0">
      <alignment horizontal="right" vertical="center"
                 wrapText="1" readingOrder="2"/>
    </xf>
    <!-- 6 : badge vert (نعم) -->
    <xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0">
      <alignment horizontal="center" vertical="center" readingOrder="2"/>
    </xf>
    <!-- 7 : badge rouge (هدم) -->
    <xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0">
      <alignment horizontal="center" vertical="center" readingOrder="2"/>
    </xf>
  </cellXfs>

  <cellStyles count="1">
    <cellStyle name="Normal" xfId="0" builtinId="0"/>
  </cellStyles>

</styleSheet>';

// ============================================================
// xl/worksheets/sheet1.xml
// ============================================================
$colLetters = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P'];
$nbCols     = count($colLetters);
$lastCol    = $colLetters[$nbCols - 1];

// Largeurs des colonnes (en caractères)
$colWidths = [5, 12, 32, 22, 14, 10, 36, 14, 14, 14, 22, 14, 14, 14, 14, 36];

$ws  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
$ws .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"' . "\n";
$ws .= '           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";

// Vue RTL + volet figé
$ws .= '<sheetViews>
  <sheetView rightToLeft="1" workbookViewId="0">
    <pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/>
    <selection pane="bottomLeft" activeCell="A4" sqref="A4"/>
  </sheetView>
</sheetViews>' . "\n";

$ws .= '<sheetFormatPr defaultRowHeight="18" customHeight="1"/>' . "\n";

// Colonnes
$ws .= '<cols>' . "\n";
foreach ($colWidths as $ci => $w) {
    $n = $ci + 1;
    $ws .= '<col min="' . $n . '" max="' . $n . '" width="' . $w . '" customWidth="1"/>' . "\n";
}
$ws .= '</cols>' . "\n";

$ws .= '<sheetData>' . "\n";

// ── Ligne 1 : Titre ──────────────────────────────────────────
$ws .= '<row r="1" ht="38" customHeight="1">' . "\n";
$ws .= '<c r="A1" t="s" s="1"><v>' . si('جدول بياني للبنايات المتداعية للسقوط') . '</v></c>' . "\n";
// Cellules vides pour les colonnes fusionnées
for ($c = 1; $c < $nbCols; $c++) {
    $ws .= '<c r="' . $colLetters[$c] . '1" s="1"/>' . "\n";
}
$ws .= '</row>' . "\n";

// ── Ligne 2 : Sous-titre ─────────────────────────────────────
$ws .= '<row r="2" ht="20" customHeight="1">' . "\n";
$ws .= '<c r="A2" t="s" s="1"><v>' . si('بلدية سوسة — إدارة الشؤون التقنية — تاريخ التصدير: ' . date('d/m/Y H:i')) . '</v></c>' . "\n";
for ($c = 1; $c < $nbCols; $c++) {
    $ws .= '<c r="' . $colLetters[$c] . '2" s="1"/>' . "\n";
}
$ws .= '</row>' . "\n";

// ── Ligne 3 : En-têtes ───────────────────────────────────────
$ws .= '<row r="3" ht="36" customHeight="1">' . "\n";
foreach ($colLetters as $ci => $col) {
    $ws .= '<c r="' . $col . '3" t="s" s="2"><v>' . si($headers[$ci]) . '</v></c>' . "\n";
}
$ws .= '</row>' . "\n";

// ── Lignes de données ─────────────────────────────────────────
foreach ($dataRows as $ri => $row) {
    $excelRow = $ri + 4;
    $isEven   = ($ri % 2 === 1);

    $ws .= '<row r="' . $excelRow . '" ht="22" customHeight="1">' . "\n";

    foreach ($row as $ci => $cell) {
        $col = $colLetters[$ci];
        $ref = $col . $excelRow;

        // Choisir le style
        if ($ci === 5 && $cell === 'نعم') {
            $sty = 6;                                      // vert
        } elseif ($ci === 14 && $cell !== '') {
            $sty = 7;                                      // rouge
        } elseif (in_array($ci, [2, 3, 6, 15])) {
            $sty = $isEven ? 5 : 4;                        // texte droit
        } else {
            $sty = $isEven ? 3 : 0;                        // normal / bleu clair
        }

        if ($ci === 0) {
            // Colonne # → valeur numérique
            $ws .= '<c r="' . $ref . '" s="' . $sty . '"><v>' . intval($cell) . '</v></c>' . "\n";
        } else {
            $ws .= '<c r="' . $ref . '" t="s" s="' . $sty . '"><v>' . si((string)$cell) . '</v></c>' . "\n";
        }
    }

    $ws .= '</row>' . "\n";
}

$ws .= '</sheetData>' . "\n";

// Fusionner titre et sous-titre sur toute la largeur
$ws .= '<mergeCells count="2">' . "\n";
$ws .= '  <mergeCell ref="A1:' . $lastCol . '1"/>' . "\n";
$ws .= '  <mergeCell ref="A2:' . $lastCol . '2"/>' . "\n";
$ws .= '</mergeCells>' . "\n";

// Mise en page paysage A4
$ws .= '<pageSetup paperSize="9" orientation="landscape"
         fitToPage="1" fitToWidth="1" fitToHeight="0"/>' . "\n";

$ws .= '</worksheet>' . "\n";

// ============================================================
// xl/sharedStrings.xml  (construit APRÈS avoir tout ajouté)
// ============================================================
$ssXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
$ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
$ssXml .= ' count="' . count($strings) . '" uniqueCount="' . count($strings) . '">' . "\n";
foreach ($strings as $s) {
    $ssXml .= '  <si><t xml:space="preserve">' . esc($s) . '</t></si>' . "\n";
}
$ssXml .= '</sst>';

// ============================================================
// Assemblage du ZIP → .xlsx
// ============================================================
$tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
@unlink($tmpFile);
$tmpFile .= '.xlsx';

$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die('Impossible de créer le fichier ZIP');
}

$zip->addFromString('[Content_Types].xml',          $contentTypes);
$zip->addFromString('_rels/.rels',                  $rels);
$zip->addFromString('xl/workbook.xml',              $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels',   $wbRels);
$zip->addFromString('xl/styles.xml',                $stylesXml);
$zip->addFromString('xl/sharedStrings.xml',         $ssXml);
$zip->addFromString('xl/worksheets/sheet1.xml',     $ws);

$zip->close();

// ============================================================
// Envoi du fichier au navigateur
// ============================================================
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');

readfile($tmpFile);
unlink($tmpFile);
exit;
?>
