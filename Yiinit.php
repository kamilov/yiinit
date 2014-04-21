<?php
/**
 * Yiinit.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__));
spl_autoload_register(function($className) {
    if(strpos($className, 'yiinit') === 0) {
        require __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, substr($className, 7)) . '.php';
    }
});

class Yiinit extends yiinit\Base {};