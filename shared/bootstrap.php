<?php
declare(strict_types=1);

   
                                                                                               
                                                               
                                                                  
  
                                                                                                      
                                                                 
   

if (defined('PTL_BOOTED')) {
    return;
}
define('PTL_BOOTED', true);
define('PTL_VERSION', '1.0.0');
define('PTL_SHARED', __DIR__);
define('PTL_ROOT', dirname(__DIR__));
define('PTL_STORAGE', PTL_ROOT . '/storage');

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'PTL\\', 4) !== 0) {
        return;
    }
    $file = PTL_SHARED . '/lib/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require PTL_SHARED . '/lib/helpers.php';
\PTL\Env::load(PTL_ROOT . '/secrets/.env');

date_default_timezone_set('Africa/Lagos');
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', PTL_STORAGE . '/logs/php-error.log');

set_exception_handler(static function (\Throwable $e): void {
    error_log('[uncaught] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (defined('PTL_API')) {
        echo '{"ok":false,"error":"Server error"}';
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title><h1>Something went wrong</h1><p>We logged the problem. Please try again in a moment.</p>';
    }
    exit;
});

require PTL_SHARED . '/views/layout.php';

if (PHP_SAPI !== 'cli' && !defined('PTL_NO_SESSION')) {
                                                         
    \PTL\Security::boot();
}
