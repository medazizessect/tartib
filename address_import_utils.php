<?php
function readArabicAddressesFromXlsx($path, $targetHeader = 'NomAr') {
    if (!is_file($path) || !class_exists('ZipArchive')) return [];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [];
    $xml = $zip->getFromName('xl/worksheets/sheet.xml');
    if (!$xml) $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$xml) return [];

    $targetCol = '';
    if (preg_match_all('/r="([A-Z]+)1"[^>]*?>\s*<[^>]*?v>([^<]*)<\/[^>]*?v>/u', $xml, $headerMatches, PREG_SET_ORDER)) {
        foreach ($headerMatches as $hm) {
            if (strcasecmp(trim(html_entity_decode($hm[2], ENT_QUOTES | ENT_XML1, 'UTF-8')), $targetHeader) === 0) {
                $targetCol = $hm[1];
                break;
            }
        }
    }
    if ($targetCol === '') return [];

    $labels = [];
    if (preg_match_all('/r="' . preg_quote($targetCol, '/') . '(\d+)"[^>]*?>\s*<[^>]*?v>([^<]*)<\/[^>]*?v>/u', $xml, $valueMatches, PREG_SET_ORDER)) {
        foreach ($valueMatches as $vm) {
            if ((int)$vm[1] === 1) continue;
            $nomAr = trim(html_entity_decode($vm[2], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            if ($nomAr !== '') $labels[$nomAr] = true;
        }
    }
    return array_keys($labels);
}
?>
