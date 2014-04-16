<?php
/**
 * Base.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
namespace yiinit;

use yiinit\helpers\ArrayX;
use yiinit\helpers\Config;

class Base
{
    /**
     * список конфигурационных файлов
     * @var array
     */
    private $_configFiles;

    /**
     * имя окружения
     * @var null|string
     */
    private $_environment;

    /**
     * настройки приложений
     * @var array
     */
    private $_settings = [];

    /**
     * список псевдонимов путей передаваемых в метом Yiibase::setPathOfAlias()
     * @var array
     */
    private $_aliases = [];

    /**
     * глобальная конфигурация всех приложений
     * @var array
     */
    private $_config = [];

    /**
     * @var string
     */
    private $_activeId;

    /**
     * базовые настройки приложения
     * @var array
     */
    private static $_yiinitSettings;

    /**
     * возвращает базовые настройки приложения
     * @return array
     */
    public static function getDefaultYiinitSettings()
    {
        if(self::$_yiinitSettings === null) {
            self::$_yiinitSettings = ArrayX::file(__DIR__ . '/config/yiinit.php');
        }
        return self::$_yiinitSettings;
    }

    /**
     * возвращает флаг определяющий запуск скрипта из консоли
     * @return bool
     */
    public static function isCli()
    {
        return php_sapi_name() === 'cli';
    }

    /**
     * конструктор.
     * в список конфигурационных файлов можно передавать, как список файлов, так и их карту, где ключём будет секция
     * в общей конфигурации например
     * 'components.db' => 'database.php' - всё, что будет подгружено из database.php будет смешиваться не с корнем
     * всего массива данных, а лишь с секцией ['components']['db']
     *
     * если имя рабочего окружения было определено, то помимо основной конфигурации из директории application/config/*.php,
     * будет подгружена конфигурация окружения из директории application/config/$environment/*.php
     * конфигурация окружения имеет проритет над основным конфигом и в случае пересечения данных, переопределяет их.
     *
     * @param array $configFiles
     * @param null|string $environment
     */
    public function __construct(array $configFiles = ['main.php'], $environment = null)
    {
        $this->_configFiles = $configFiles;
        $this->_environment = $environment;
        $this->_config      = ArrayX::file(__DIR__ . '/config/application.php');
    }

    /**
     * добавляет приложение в список доступных для запуска
     * @param string $directory
     * @param null|string $alias
     *
     * @return $this
     */
    public function addApplication($directory, $alias = null)
    {
        if($alias === null) {
            $alias = basename($directory);
        }

        $settings = ArrayX::merge(
            ['alias' => $alias, 'basePath' => $directory],
            self::getDefaultYiinitSettings(),
            Config::yiinit($directory, $this->_environment)
        );

        if($settings['isCli'] === self::isCli()) {
            $this->_aliases[$alias]      = $directory;
            $this->_settings[$alias] = $settings;
        }

        return $this;
    }

    /**
     * добавляет директорию с общими файлами в структуру приложения
     * @param string $directory
     * @param null|string $alias
     *
     * @return $this
     */
    public function addGlobalDirectory($directory, $alias = null)
    {
        if($alias === null) {
            $alias = basename($directory);
        }

        $this->_config = Config::application($directory, $this->_configFiles, $this->_environment, $this->_config);
        $this->_aliases[$alias] = $directory;

        return $this;
    }

    /**
     * возвращает указанные настройки для каждого приложения
     * @param string $key
     * @param mixed|null $default
     *
     * @return array
     */
    public function getSettings($key, $default = null)
    {
        return array_map(function($value) use ($key, $default) {
            return isset($value[$key]) ? $value[$key] : $default;
        }, $this->_settings);
    }

    /**
     * возвращает идентификатор активного приложения, при необходимости идёт автоматическое определение
     * если приложение не было определено, то возвращает null
     * @return null|string
     */
    public function getActiveId()
    {
        if($this->_activeId === null) {
            $priority = $this->_getPriority();

            while($priority->valid()) {
                $isActive = $this->_settings[$priority->current()]['isActive'];

                if(is_callable($isActive)) {
                    $isActive = call_user_func($isActive, $this);
                }

                if($isActive) {
                    $this->_activeId = $priority->current();
                    break;
                }

                $priority->next();
            }
        }
        return $this->_activeId;
    }

    /**
     * запуск приложения
     * @throws \CHttpException
     * @throws \Exception
     */
    public function run()
    {
        if(!class_exists('Yii', false)) {
            throw new \Exception('Yii framework is not loaded.');
        }

        if(($alias = $this->getActiveId()) === null) {
            throw new \CHttpException(400, 'Bad Request.');
        }

        foreach($this->_aliases + ['yiinit' => __DIR__] as $name => $path) {
            \Yii::setPathOfAlias($name, $path);
        }

        /**
         * @var string $alias
         * @var string $basePath
         * @var string $class
         * @var bool $useGlobalConfig
         * @var bool $isCli
         */
        extract($this->_settings[$alias]);

        $config = $useGlobalConfig ? $this->_config : [];
        $config = Config::application($basePath, $this->_configFiles, $this->_environment, $config);

        ArrayX::set($config, 'params.yiinit', $this);

        $application = \Yii::createApplication($class, ArrayX::merge($config, [
            'id'       => $alias,
            'basePath' => $basePath,
        ]));

        if($application->messages !== null and property_exists($application->messages, 'extensionPaths')) {
            $application->messages->extensionPaths = array_map(function($value) {
                return $value . '/messages';
            }, $this->_aliases);
        }

        $application->run();
    }

    /**
     * формирует и возвращает объект списка приложений по приоритетам
     * @return \SplPriorityQueue
     */
    private function _getPriority()
    {
        $priority = new \SplPriorityQueue();
        $priority->setExtractFlags(\SplPriorityQueue::EXTR_DATA);

        foreach($this->_settings as $alias => $config) {
            $priority->insert($alias, $config['priority']);
        }

        return $priority;
    }
}