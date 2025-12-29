<?php
/**
 * Simple .env file loader
 * Loads environment variables from .env file
 */

function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        error_log("⚠️ .env file not found at: " . $filePath);
        return false;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set as environment variable
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
    
    return true;
}

/**
 * Get environment variable with optional default
 */
function env($key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Auto-load .env from root directory
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);
?>