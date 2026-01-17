<?php
// views/404.php
function render_404() {
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Not Found</title>
    <link rel="stylesheet" href="/css/compiled.css">
</head>
<body class="bg-blue-300 min-h-screen flex flex-col items-center justify-center p-4">
    <img src="/images/luftballoon.png" alt="A red balloon floating away" class="w-1/2 max-w-md mb-8">
    <p class="text-blue-600 leading-relaxed text-xl font-semibold text-center">
        Your page is lost in the wind! Please go back and try again.
    </p>
    <button onclick="history.back()" class="mt-8 bg-gray-200 text-gray-700 px-6 py-2 rounded hover:bg-gray-300 transition cursor-pointer">Back</button>
</body>
</html>
HTML;
}
