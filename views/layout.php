<?php
// views/layout.php
function render_layout($title, $content, $flash = null) {
    $flash_html = $flash ? '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">' . htmlspecialchars($flash) . '</div>' : '';

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - Admin</title>
    <link rel="stylesheet" href="/css/compiled.css">
</head>
<body class="bg-gray-50">
    {$flash_html}
    {$content}
</body>
</html>
HTML;
}
