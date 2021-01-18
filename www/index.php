<?php
function save_and_display_file($targetHost, $targetPath)
{
    global $allowedExtension;
    $currentPath = __DIR__;

    $localPath = pathinfo($targetPath, PATHINFO_DIRNAME);
    $localFileName = basename($targetPath);
    $localFileNameExt = pathinfo($localFileName, PATHINFO_EXTENSION);

    // Check file extension
    if (!in_array($localFileNameExt, $allowedExtension)) {
        die('error');
    }

    $targetUrl = $targetHost . $targetPath;
    $localFullPath = "{$currentPath}{$localPath}";

    // Get file from original server
    $ch = curl_init();
    setCurlOption($ch, $targetUrl);
    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result && $httpcode == 200) {
        $localFullFilePath = "{$localFullPath}/{$localFileName}";

        // Create a folder if doesn't exist
        if (!file_exists($localFullPath)) {
            exec("mkdir -p {$localFullPath}");
        }
        file_put_contents($localFullFilePath, $result);
        $contentType = mime_content_type($localFullFilePath);

        // Display file
        header('My-Cache-Status: MISS');
        header("Content-Type: {$contentType}");
        echo $result;
    } else if ($localFileNameExt == 'webp') {
        // Convert image to WebP
        $originalFileName = pathinfo($localFileName, PATHINFO_FILENAME);
        $originalFileNameExt = pathinfo($originalFileName, PATHINFO_EXTENSION);

        if (!file_exists("{$localFullPath}/{$originalFileName}")) {
            // Get original file if it doesn't exist
            $originalUrl = str_replace('.webp', '', $targetUrl);
            $originalCh = curl_init();
            setCurlOption($originalCh, $originalUrl);
            $originalChResult = curl_exec($originalCh);
            $originalChHttpCode = curl_getinfo($originalCh, CURLINFO_HTTP_CODE);
            curl_close($originalCh);

            if ($originalChResult && $originalChHttpCode == 200) {
                // Create a folder if does not exist
                if (!file_exists($localFullPath)) {
                    exec("mkdir -p {$localFullPath}");
                }
                file_put_contents("{$localFullPath}/{$originalFileName}", $originalChResult);
            } else {
                error_404();
            }
        }

        createWebp($originalFileName, $originalFileNameExt, $localFullPath);

        // Display file
        header('My-Cache-Status: MISS');
        header('Content-Type: image/webp');
        echo file_get_contents(".{$localPath}/{$localFileName}");
    } else {
        error_404();
    }

    exit;
}

function setCurlOption(&$ch, $url)
{
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'My-Cache/1.0');
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
}

function createWebp($originalFileName, $originalFileNameExt, $filePath)
{
    $fullFilePath = "{$filePath}/{$originalFileName}";
    $webpFileName = "{$fullFilePath}.webp";

    if ($originalFileNameExt == 'png') {
        $image = imagecreatefrompng($fullFilePath);

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        imagewebp($image, $webpFileName, 100);
        imagedestroy($image);
    } else if ($originalFileNameExt == 'jpeg' || $originalFileNameExt == 'jpg') {
        $image = @imagecreatefromjpeg($fullFilePath);

        if (!$image) {
            $image = imagecreatefromstring(file_get_contents($fullFilePath));
        }

        ob_start();
        imagejpeg($image, NULL, 100);
        $cont =  ob_get_contents();
        ob_end_clean();
        imagedestroy($image);
        $content =  imagecreatefromstring($cont);

        imagewebp($content, $webpFileName, 92);
        imagedestroy($content);
    }
}

function error_404()
{
    header('My-Cache-Status: MISS');
    header("HTTP/1.1 404 Not Found");
    echo '404 Not Found';
    exit;
}

$originalHost = 'https://example.com';

// Files to cache
$allowedExtension = array('png', 'jpg', 'jpeg', 'gif', 'webp', 'mp4', 'webm', 'svg');

$_SERVER['REQUEST_URI'] = preg_replace('/\/\/+/', '/', $_SERVER['REQUEST_URI']);
$_SERVER['REQUEST_URI'] = preg_replace('/\.\.+/', '.', $_SERVER['REQUEST_URI']);
$_SERVER['REQUEST_URI'] = urldecode($_SERVER['REQUEST_URI']);

$urlPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$urlExt = pathinfo($urlPath, PATHINFO_EXTENSION);

if (!in_array($urlExt, $allowedExtension)) { // extension check
    error_404();
}

save_and_display_file($originalHost, $urlPath);
