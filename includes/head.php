<!DOCTYPE html>
<html lang="<?= isset($html_lang) ? htmlspecialchars($html_lang) : 'id' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Pusat Pembelajaran SIJA' ?></title>
    <?= $extra_head ?? '' ?>
</head>
<body class="<?= isset($body_class) ? htmlspecialchars($body_class) : 'bg-light' ?>">
