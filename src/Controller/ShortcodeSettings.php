<?php
/**
 * Этот файл является частью расширения модуля веб-приложения RosGear.
 * 
 * @link https://rosgear.ru/
 * @copyright Copyright (c) 2015 RosGear
 * @license https://rosgear.ru/license/
 */

namespace Rg\Backend\Marketplace\ModuleManager\Controller;

use Ge;
use Ge\Panel\Http\Response;
use Ge\Mvc\Module\BaseModule;
use Ge\Panel\Controller\FormController;

/**
 * Контроллер настройки шорткода модуля.
 * 
 * Действия контроллера:
 * - view, вывод интерфейса настроек шорткода модуля.
 * 
 * @author Anton Tivonenko <anton.tivonenko@gmail.com>
 * @package Rg\Backend\Marketplace\ModuleManager\Controller
 * @since 1.0
 */
class ShortcodeSettings extends FormController
{
    /**
     * {@inheritdoc}
     * 
     * @var BaseModule|\Rg\Backend\Marketplace\ModuleManager\Extension
     */
    public BaseModule $module;

    /**
     * {@inheritdoc}
     */
    public function translateAction(mixed $params, ?string $default = null): ?string
    {
        switch ($this->actionName) {
            // вывод интерфейса
            case 'view':
                return Ge::t(BACKEND, "{{$this->actionName} settings action}");

            default:
                return parent::translateAction(
                    $params,
                    $default ?: Ge::t(BACKEND, "{{$this->actionName} settings action}")
                );
        }
    }

    /**
     * Возвращает идентификатор выбранного модуля.
     *
     * @return int
     */
    public function getIdentifier(): int
    {
        return (int) Ge::$app->router->get('id');
    }

    /**
     * Действие "view" выводит интерфейс настроек шорткода модуля.
     * 
     * @return Response
     */
    public function viewAction(): Response
    {
        /** @var Response $response */
        $response = $this->getResponse();

        /** @var null|int $id Идентификатор модуля */
        $id = $this->getIdentifier();
        if (empty($id)) {
            return $this->errorResponse(
                GE_MODE_DEV ?
                    Ge::t('app', 'Parameter "{0}" not specified', ['id']) :
                    $this->module->t('Unable to show module shortcode settings')
            );
        }

        /** @var null|string $tagName Имя тега */
        $tagName = Ge::$app->request->getQuery('name');
        if (empty($tagName)) {
            return $this->errorResponse(
                GE_MODE_DEV ?
                    Ge::t('app', 'Parameter "{0}" not specified', ['name']) :
                    $this->module->t('Unable to show module shortcode settingss')
            );
        }

        /** @var null|array $moduleParams Параметры модуля */
        $moduleParams = Ge::$app->modules->getRegistry()->getAt($id);
        if ($moduleParams === null) {
            return $this->errorResponse(
                GE_MODE_DEV ?
                    Ge::t('app', 'There is no widget with the specified id "{0}"', ['$id']) :
                    $this->module->t('Unable to show module shortcode settings')
            );
        }

        /** @var null|array $install Параметры установки модуля */
        $install = Ge::$app->modules->getRegistry()->getConfigInstall($id);
        // если параметры установки не найдены
        if ($install === null) {
            return $this->errorResponse(
                GE_MODE_DEV ?
                    Ge::t('app', 'There is no widget with the specified id "{0}"', ['$id']) :
                    $this->module->t('Unable to show module shortcode settings')
            );
        }

        /** @var array|null $shortcode Параметры указанного шорткода модуля */
        $shortcode = $install['editor']['shortcodes'][$tagName] ?? null;
        if (empty($shortcode)) {
            return $this->errorResponse(
                GE_MODE_DEV ?
                    Ge::t('app', 'Parameter passed incorrectly "{0}"', ['shortcodes[' . $tagName . ']']) :
                    $this->module->t('Unable to show module shortcode settings')
            );
        }

        // если нет настроек шорткода
        if (empty($shortcode['settings'])) {
            return $this->errorResponse(
                GE_MODE_DEV ?
                    Ge::t('app', 'The value for parameter "{0}" is missing', ['shortcodes[settings]']) :
                    $this->module->t('Unable to show module shortcode settings')
            );
        }

        // для доступа к пространству имён объекта
        Ge::$loader->addPsr4($moduleParams['namespace']  . NS, Ge::$app->modulePath . $moduleParams['path'] . DS . 'src');

        $settingsClass = $moduleParams['namespace'] . NS . $shortcode['settings'];
        if (!class_exists($settingsClass)) {
            return $this->errorResponse(
                $this->module->t('Unable to create widget object "{0}"', [$settingsClass])
            );
        }

        // добавляем шаблон локализации модуля (которому принадлежит шорткод)
        $category = Ge::$app->translator->getCategory($this->module->id);
        // ключ шаблона при подключении не имеет значение
        $category->patterns['shortcodeSettings'] = [
            'basePath' => Ge::$app->modulePath . $moduleParams['path'] . DS . 'lang',
            'pattern'  => 'text-%s.php',
        ];
        $this->module->addTranslatePattern('shortcodeSettings');

        /** @var object|Ge\Panel\Widget\ShortcodeSettingsWindow $widget Виджет настроек шорткода */
        $widget = Ge::createObject($settingsClass);
        if ($widget instanceof Ge\Panel\Widget\ShortcodeSettingsWindow) {
            $widget->form->controller = 'rg-mp-mmanager-shortcodesettings';
            $widget
                ->setNamespaceJS('Rg.be.mp.mmanager')
                ->addRequire('Rg.be.mp.mmanager.ShortcodeSettingsController' . (GE_DEBUG ? '-debug' : ''));
        }

        $response
            ->setContent($widget->run())
            ->meta
                ->addWidget($widget);
        return $response;
    }
}
