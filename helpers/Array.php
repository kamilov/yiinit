<?php
/**
 * Array.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
namespace yiinit\helpers;

class ArrayX
{
    /**
     * загружает файл который передаёт массив
     * если файл передал дургое значение, то будет возвращено значение по умолчанию
     * @param string $fileName
     * @param array $default
     *
     * @return array|mixed
     */
    public static function file($fileName, $default = [])
    {
        if(is_file($fileName) and is_array(($data = require $fileName))) {
            return $data;
        }
        return $default;
    }

    /**
     * смешивание двух или более массивов
     * если в массивах идёт совпадение ключей, то они будут переопределены значением из следующего массива
     * @param array $a
     * @param array $b
     *
     * @return array
     */
    public static function merge($a, $b)
    {
        $arrays = func_get_args();
        $result = array_shift($arrays);

        while(($array = array_shift($arrays)) !== null) {
            foreach($array as $key => $value) {
                if(is_int($key)) {
                    isset($result[$key]) ? array_push($result, $value) : $result[$key] = $value;
                }
                else if(is_array($value) and isset($result[$key])) {
                    $result[$key] = self::merge($result[$key], $value);
                }
                else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * получение элемента из массива при помощи точечной нотации
     * @param array $array
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public static function get($array, $key, $default = null)
    {
        foreach(explode('.', $key) as $key) {
            if(!is_array($array) or !array_key_exists($key, $array)) {
                return $default;
            }
            $array = $array[$key];
        }
        return $array;
    }

    /**
     * сохранение значения элемента в массиве при помощи точечной нотации
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @param bool $merge
     */
    public static function set(&$array, $key, $value, $merge = false)
    {
        foreach(explode('.', $key) as $key) {
            if(!isset($array[$key]) or !is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }

        $array = (is_array($value) and $merge) ? self::merge($array, $value) : $value;
    }
}