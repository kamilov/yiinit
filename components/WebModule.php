<?php
/**
 * WebModule.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
namespace yiinit\components;

class WebModule extends \CWebModule
{
    /**
     * если модуль расположен в глобальной директории, а контроллеры и представления расположены отдельно по приложениям,
     * то пути к директориям с контроллерами и представлениями будут переопределены
     */
    public function init()
    {
        if(($parent = $this->parentModule) === null) {
            $parent = \Yii::app();
        }

        $inApplicationPath = $parent->modulePath . DIRECTORY_SEPARATOR . $this->id;
        $controllerPath    = $inApplicationPath . DIRECTORY_SEPARATOR . dirname($this->controllerPath);
        $viewPath          = $inApplicationPath . DIRECTORY_SEPARATOR . dirname($this->viewPath);

        if(is_dir($inApplicationPath)) {
            \Yii::setPathOfAlias('_' . $this->id, $inApplicationPath);

            if(is_dir($controllerPath)) {
                $this->controllerPath = $controllerPath;
            }

            if(is_dir($viewPath)) {
                $this->viewPath = $viewPath;
            }
        }

        parent::init();
    }
}