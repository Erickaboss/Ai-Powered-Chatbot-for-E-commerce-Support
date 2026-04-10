<?php
/**
 * Environment Variable Loader
 * Loads configuration from .env file
 */

class EnvLoader {
    private static $loaded = false;
    
    public static function load(string $path = null): void {
        if (self::$loaded) return;
        
        $path = $path ?? dirname(__DIR__) . '/.env';
        
        if (!file_exists($path)) {
            // Fall back to .env.example if .env doesn't exist
            $path .= '.example';
        }
        
        if (!file_exists($path)) {
            throw new Exception('.env file not found');
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) continue;
            
            // Parse key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                
                // Set environment variable
                if (!empty($key)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    
                    // Define as constant if not already defined
                    if (!defined($key)) {
                        define($key, $value);
                    }
                }
            }
        }
        
        self::$loaded = true;
    }
    
    public static function get(string $key, $default = null) {
        self::load();
        return getenv($key) ?: $default;
    }
    
    public static function getInt(string $key, int $default = 0): int {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }
    
    public static function getBool(string $key, bool $default = false): bool {
        $value = strtolower(self::get($key, $default ? 'true' : 'false'));
        return in_array($value, ['true', '1', 'yes', 'on']);
    }
}

// Auto-load on include
EnvLoader::load();
