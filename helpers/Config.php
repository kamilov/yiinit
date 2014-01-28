<?php
/**
 * Config.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
namespace yiinit\helpers;

class Config
{
    /**
     * имя директории с кофинурацией
     */
    const DIRECTORY_NAME = 'config';

    /**
     * имя конфиг файла содержащего информацию о приложении
     */
    const YIINIT_FILENAME = 'yiinit.php';

    /**
     * загрузка конфигурации приложения с учётом окружения
     * @param string $directory
     * @param array $fileList
     * @param null $environment
     * @param array $config
     *
     * @return array
     */
    public static function application($directory, array $fileList, $environment = null, $config = [])
    {
        foreach(self::_getFileList(self::_getDirectoryPath($directory), $fileList, $environment) as $fileName => $key) {
            if($key !== null) {
                ArrayX::set($config, $key, ArrayX::file($fileName), true);
            }
            else {
                $config = ArrayX::merge($config, ArrayX::file($fileName));
            }
        }
        return $config;
    }

    /**
     * получение информации о приложении с учётом окружения
     * @param string $directory
     * @param null|string $environment
     *
     * @return array
     */
    public static function yiinit($directory, $environment = null)
    {
        return self::application($directory, [self::YIINIT_FILENAME], $environment);
    }

    /**
     * формирование списка конфигурационных файлов
     * @param string $directory
     * @param array $fileList
     * @param null|string $environment
     *
     * @return array
     */
    private function _getFileList($directory, $fileList, $environment)
    {
        $result = [];

        foreach($fileList as $key => $fileName) {
            $fileName = $directory . DIRECTORY_SEPARATOR . $fileName;

            if(is_file($fileName)) {
                $result[$fileName] = is_int($key) ? null : $key;
            }
        }

        if($environment !== null) {
            $directory .= DIRECTORY_SEPARATOR . $environment;
            $result     = ArrayX::merge($result, self::_getFileList($directory, $fileList, null));
        }

        return $result;
    }

    /**
     * обработка пути к директории
     * @param string $directory
     *
     * @return string
     */
    private function _getDirectoryPath($directory)
    {
        $directory = rtrim($directory, '\\/');

        if(substr($directory, -strlen(self::DIRECTORY_NAME)) !== self::DIRECTORY_NAME) {
            $directory .= DIRECTORY_SEPARATOR . self::DIRECTORY_NAME;
        }

        return $directory;
    }
}