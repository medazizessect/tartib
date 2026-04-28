<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/address_import_utils.php';

$defaultFile = __DIR__ . '/VOIE_Nom_Rues_Arabe_2026.xlsx';
$xlsxPath = $defaultFile;

if (PHP_SAPI === 'cli' && !empty($argv[1])) {
    $xlsxPath = $argv[1];
}

$addresses = readArabicAddressesFromXlsx($xlsxPath, 'NomAr');
if (!$addresses) {
    $msg = "❌ Aucune adresse importable trouvée (colonne NomAr) dans: $xlsxPath";
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    die("<div style='font-family:Arial;padding:20px;color:#b00020'>$msg</div>");
}

$inserted = 0;
$stmt = $pdo->prepare("INSERT IGNORE INTO adresses (libelle) VALUES (?)");
foreach ($addresses as $libelle) {
    $stmt->execute([$libelle]);
    $inserted += $stmt->rowCount();
}

$total = count($addresses);
$msg = "✅ Import terminé: $inserted nouvelles adresses ajoutées sur $total lignes lues.";

if (PHP_SAPI === 'cli') {
    echo $msg . PHP_EOL;
    exit(0);
}

echo "<!doctype html><html lang='fr'><meta charset='utf-8'><body style='font-family:Arial;padding:20px'>$msg</body></html>";
?>
