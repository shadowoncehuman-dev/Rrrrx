<?php
// ZXKAI PHP built-in server router
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$map = [
    '/player'    => '/play.php',
    '/pdf-viewer' => '/pdf.php',
];

$target = $path;
if (isset($map[$path])) {
    $target = $map[$path];
}

$file = __DIR__ . $target;
if (is_file($file)) {
    chdir(dirname($file));
    $_SERVER['SCRIPT_NAME'] = $target;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    require $file;
    return true;
}

http_response_code(404);
header('Content-Type: text/plain');
echo 'Not Found';
return true;
