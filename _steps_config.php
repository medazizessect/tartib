<?php
/**
 * Configuration centralisée des étapes
 * NE PAS utiliser $b ici — ce fichier est inclus globalement
 */

define('STEPS', [
    'step1_reclamation' => [
        'icon'     => '🧾',
        'label'    => 'شكاوي',
        'color'    => '#dc3545',
        'requires' => null,
        'optional' => false,
        'step_num' => 1,
    ],
    'step2_pv' => [
        'icon'     => '📋',
        'label'    => 'محضر',
        'color'    => '#f39c12',
        'requires' => 'step1_reclamation',
        'optional' => false,
        'step_num' => 2,
    ],
    'step3_expert_request' => [
        'icon'     => '⚖️',
        'label'    => 'تكليف خبير',
        'color'    => '#f39c12',
        'requires' => 'step2_pv',
        'optional' => false,
        'step_num' => 3,
    ],
    'step4_expert_report' => [
        'icon'     => '🧪',
        'label'    => 'رجوع التقرير',
        'color'    => '#f39c12',
        'requires' => 'step3_expert_request',
        'optional' => false,
        'step_num' => 4,
    ],
    'step5_decision' => [
        'icon'     => '✅',
        'label'    => 'قرار الإخلاء/الهدم',
        'color'    => '#28a745',
        'requires' => 'step4_expert_report',
        'optional' => false,
        'step_num' => 5,
    ],
]);

function getStepClass($type, $docs) {
    if (!defined('STEPS') || !isset(STEPS[$type])) return 'step-todo';
    $cfg     = STEPS[$type];
    $statut  = isset($docs[$type]) ? $docs[$type] : null;
    $exists  = ($statut !== null);
    $isFinal = ($statut === 'finalise');
    $req     = $cfg['requires'];
    $locked  = ($req !== null && !isset($docs[$req]));
    $num     = $cfg['step_num'];

    if ($locked) return 'step-btn step-locked';
    if ($exists && $isFinal) return "step-btn step-done s{$num} step-final";
    if ($exists) return "step-btn step-done s{$num} step-draft";
    return 'step-btn step-todo';
}

function getStepTooltip($type, $docs) {
    if (!defined('STEPS') || !isset(STEPS[$type])) return '';
    $cfg    = STEPS[$type];
    $statut = isset($docs[$type]) ? $docs[$type] : null;
    $req    = $cfg['requires'];
    $locked = ($req !== null && !isset($docs[$req]));

    if ($locked)                return "🔒 يجب إتمام «" . (STEPS[$req]['label'] ?? '') . "» أولاً";
    if ($statut === 'finalise') return "✅ {$cfg['label']} — نهائي";
    if ($statut === 'brouillon')return "✏️ {$cfg['label']} — مسودة";
    return "➕ إنشاء {$cfg['label']}";
}
?>
