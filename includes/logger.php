<?php
/**
 * Error Logger - Monolog-inspired simple logging system
 */

class ErrorLogger {
    private static $instance = null;
    private $logFile;
    private $minLevel;
    
    const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4,
        'fatal' => 5
    ];
    
    private function __construct() {
        $this->logFile = defined('LOG_FILE') ? LOG_FILE : dirname(__DIR__) . '/logs/error.log';
        $this->minLevel = self::LEVELS[defined('LOG_LEVEL') ? LOG_LEVEL : 'error'];
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Set up error handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatal']);
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function handleError(int $errno, string $errstr, string $file, int $line): bool {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $level = match($errno) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'error',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'warning',
            E_NOTICE, E_USER_NOTICE, E_STRICT => 'info',
            default => 'debug'
        };
        
        $this->log($level, $errstr, $file, $line);
        return true;
    }
    
    public function handleException(Throwable $e): void {
        $this->log(
            'error',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            ['exception' => get_class($e), 'trace' => $e->getTraceAsString()]
        );
    }
    
    public function handleFatal(): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->log(
                'fatal',
                $error['message'],
                $error['file'],
                $error['line']
            );
        }
    }
    
    public function log(string $level, string $message, string $file = null, int $line = null, array $context = []): void {
        if (!defined('LOG_ERRORS') || !LOG_ERRORS) {
            return;
        }
        
        if (self::LEVELS[$level] < $this->minLevel) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $url = $_SERVER['REQUEST_URI'] ?? 'CLI';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        
        $logEntry = sprintf(
            "[%s] [%s] [IP:%s] [URL:%s]\n  Message: %s\n  File: %s:%s\n  Context: %s\n%s\n",
            $timestamp,
            strtoupper($level),
            $ip,
            $url,
            $message,
            $file ?? 'unknown',
            $line ?? 0,
            json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            str_repeat('-', 80)
        );
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function debug(string $message, array $context = []): void {
        $this->log('debug', $message, null, null, $context);
    }
    
    public function info(string $message, array $context = []): void {
        $this->log('info', $message, null, null, $context);
    }
    
    public function warning(string $message, array $context = []): void {
        $this->log('warning', $message, null, null, $context);
    }
    
    public function error(string $message, array $context = []): void {
        $this->log('error', $message, null, null, $context);
    }
    
    public function critical(string $message, array $context = []): void {
        $this->log('critical', $message, null, null, $context);
    }
}

// Initialize logger
if (defined('LOG_ERRORS') && LOG_ERRORS) {
    ErrorLogger::getInstance();
}
