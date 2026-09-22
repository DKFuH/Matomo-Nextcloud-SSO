<?php

echo "=== Matomo NextcloudSSO Plugin Validator ===" . PHP_EOL;

$baseDir = dirname(__DIR__);

// 1. Validate JSON files
$jsonFiles = [
    'plugin.json',
    'lang/de.json',
    'lang/en.json'
];

foreach ($jsonFiles as $file) {
    $path = $baseDir . '/' . $file;
    if (!file_exists($path)) {
        echo "[FAIL] Missing file: $file" . PHP_EOL;
        exit(1);
    }
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    if ($data === null) {
        echo "[FAIL] JSON parse error in $file: " . json_last_error_msg() . PHP_EOL;
        exit(1);
    }
    echo "[OK] JSON valid: $file" . PHP_EOL;
}

// 2. Validate PHP files syntax
$phpFiles = [
    'SystemSettings.php',
    'Auth.php',
    'NextcloudSSO.php',
    'Controller.php'
];

foreach ($phpFiles as $file) {
    $path = $baseDir . '/' . $file;
    if (!file_exists($path)) {
        echo "[FAIL] Missing file: $file" . PHP_EOL;
        exit(1);
    }
    $output = [];
    $returnCode = 0;
    exec('C:\\xampp\\php\\php.exe -l ' . escapeshellarg($path), $output, $returnCode);
    if ($returnCode !== 0) {
        echo "[FAIL] PHP syntax error in $file: " . implode(PHP_EOL, $output) . PHP_EOL;
        exit(1);
    }
    echo "[OK] PHP syntax clean: $file" . PHP_EOL;
}

// 3. Verify templates and stylesheets
$assetFiles = [
    'templates/loginButton.twig',
    'stylesheets/loginButton.css',
    'README.md',
    'LICENSE'
];

foreach ($assetFiles as $file) {
    $path = $baseDir . '/' . $file;
    if (!file_exists($path) || filesize($path) === 0) {
        echo "[FAIL] Missing or empty asset: $file" . PHP_EOL;
        exit(1);
    }
    echo "[OK] Asset present: $file (" . filesize($path) . " bytes)" . PHP_EOL;
}

echo "=== All checks passed successfully! ===" . PHP_EOL;
exit(0);
