<?php
declare(strict_types=1);

if (is_file(dirname(__DIR__) . '/.env')) {
    foreach (parse_ini_file(dirname(__DIR__) . '/.env', false, INI_SCANNER_RAW) ?: [] as $key => $value) {
        if (getenv($key) === false) { putenv("$key=$value"); }
    }
}
function env(string $key, ?string $default = null): ?string { $value = getenv($key); return $value === false ? $default : $value; }
date_default_timezone_set('America/Sao_Paulo');
