<?php

declare(strict_types=1);

/**
 * Charge backend/.env une seule fois (sans écraser les variables déjà définies par le serveur).
 */
function lagoon_load_env(?string $path = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = $path ?? dirname(__DIR__) . '/.env';
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        if ($name === '') {
            continue;
        }

        if (getenv($name) !== false) {
            continue;
        }

        $value = trim($value);
        if ($value !== '' && (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        )) {
            $value = substr($value, 1, -1);
        }

        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

/**
 * Lecture d’une variable d’environnement avec valeur par défaut si absente.
 */
function lagoon_env(string $key, string $default = ''): string
{
    $v = getenv($key);
    return $v === false ? $default : $v;
}
