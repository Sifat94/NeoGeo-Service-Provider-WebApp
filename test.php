<?php
$file = __DIR__ . '/test.json';
$ok = file_put_contents($file, json_encode(['time' => date('H:i:s'), 'value' => 'test']));
if ($ok === false) {
    echo 'CANNOT WRITE — permission issue';
} else {
    echo 'WRITE OK — check test.json file now';
}