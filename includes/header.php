<?php
$prefix = basename(dirname($_SERVER['PHP_SELF'])) === 'pages' ? '../' : '';
$page_title = isset($page_title) ? $page_title : 'E-Absensi SMP';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="<?= $prefix ?>assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $prefix ?>assets/css/site.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <?= isset($extra_head) ? $extra_head : '' ?>
</head>
<body>