<?php
require 'config.php';
requireStepAccess('step1_reclamation');
require 'db.php';

$errors = [];

function saveUploadedFile($field, $required = false) {
    if (empty($_FILES[$field]['name'])) return $required ? null : '';
    if (($_FILES[$field]['size'] ?? 0) > 10 * 1024 * 1024) return null;
    if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0775, true);
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf','jpg','jpeg','png','doc','docx'];
    if (!in_array($ext, $allowed, true)) return null;
    $mimeAllowed = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $_FILES[$field]['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);
    if (!in_array($mime, $mimeAllowed, true)) return null;
    $name = $field . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $rel = 'uploads/' . $name;
    $ok = move_uploaded_file($_FILES[$field]['tmp_name'], __DIR__ . '/' . $rel);
    return $ok ? $rel : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bureau = trim($_POST['bureau_ordre_id'] ?? '');
    $date = $_POST['date_reclamation'] ?? '';
    $owner = trim($_POST['proprietaire'] ?? '');
    $scan = saveUploadedFile('reclamation_scan', true);

    if ($bureau === '') $errors[] = "معرف مكتب الضبط مطلوب";
    if ($date === '') $errors[] = "تاريخ الشكاية مطلوب";
    if (!$scan) $errors[] = "ملف الشكاية الممسوح إجباري";

    if (empty($errors)) {
        $pdo->prepare("
            INSERT INTO batiments
            (bureau_ordre_id,date_reclamation,proprietaire,reclamation_scan_path,notification_pending,numero_rapport,lieu,date_rapport)
            VALUES(?,?,?,?,?,?,?,?)
        ")->execute([
            $bureau, $date, ($owner ?: null), $scan, !empty($_POST['notification_pending']) ? 1 : 0,
            $bureau, '', $date
        ]);
        header("Location: index.php?msg=added");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة شكاية</title>
    <?php include '_styles_form.php'; ?>
</head>
<body>
<?php include '_menu.php'; ?>
<header style="background:linear-gradient(135deg,#dc3545,#c82333)"><h1>🧾 إضافة شكاية </h1></header>
<div class="wrap">
    <h2>بيانات الشكاية</h2>

    <?php if ($errors): ?>
        <div class="error-box"><ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="grid">
            <div class="fg">
                <label><span class="req">*</span> ID du bureau d'ordre</label>
                <input type="text" name="bureau_ordre_id" value="<?= htmlspecialchars($_POST['bureau_ordre_id'] ?? '') ?>">
            </div>
            <div class="fg">
                <label><span class="req">*</span> التاريخ</label>
                <input type="date" name="date_reclamation" value="<?= htmlspecialchars($_POST['date_reclamation'] ?? '') ?>">
            </div>
            <div class="fg full">
                <label>المالك (اختياري)</label>
                <input type="text" name="proprietaire" value="<?= htmlspecialchars($_POST['proprietaire'] ?? '') ?>">
            </div>
            <div class="fg full">
                <label><span class="req">*</span> الشكاية الممسوحة (PDF/JPG/PNG/DOC)</label>
                <input type="file" name="reclamation_scan" required>
            </div>
            <div class="fg full">
                <label><input type="checkbox" name="notification_pending" value="1" <?= !empty($_POST['notification_pending']) ? 'checked' : '' ?>> إشعار بمعلومات غير معالجة</label>
            </div>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn-success">💾 حفظ</button>
            <a href="index.php" class="btn btn-secondary">↩️ رجوع</a>
        </div>
    </form>
</div>
</body>
</html>
