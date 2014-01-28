<?php
/**
 * Yiinit.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__));
spl_autoload_register();

class Yiinit extends yiinit\Base {};