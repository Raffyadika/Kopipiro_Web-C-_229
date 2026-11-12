<?php
// header.php — kepala halaman + navbar.
// Isi $pageTitle (opsional $metaRefresh = detik) sebelum meng-include file ini.
$pageTitle = $pageTitle ?? 'Kopipiro';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · Kopipiro</title>
    <?php if (!empty($metaRefresh)): ?>
        <meta http-equiv="refresh" content="<?= (int) $metaRefresh ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/style.css')) ?>" rel="stylesheet">
</head>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <main class="container py-4">
        <?= flash_render() ?>
