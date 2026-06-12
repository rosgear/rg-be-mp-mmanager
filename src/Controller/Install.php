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
use Rg\Backend\Marketplace\ModuleManager\Widget\InstallWindow;

/**
 * Контроллер установки модуля.
 * 
 * @author Anton Tivonenko <anton.tivonenko@gmail.com>
 * @package Rg\Backend\Marketplace\ModuleManager\Controller
 * @since 1.0
 */
class Install extends FormController
{
    /**
     * {@inheritdoc}
     * 
     * @var BaseModule|\Rg\Backend\Marketplace\ModuleManager\Extension
     */
    public BaseModule $module;

    /**
     * {@inheritdoc}
     * 
     * @return InstallWindow
     */
    public function createWidget(): InstallWindow
    {
        return new InstallWindow();
    }

    /**
     * Действие "complete" завершает установку модуля.
     * 
     * @return Response
     */
    public function completeAction(): Response
    {
        // добавляем шаблон локализации для установки (см. ".extension.php")
        $this->module->addTranslatePattern('install');

        /** @var \Ge\ModuleManager\ModuleManager $manager Менеджер модулей */
        $manager = Ge::$app->modules;
        /** @var Response $response */
        $response = $this->getResponse();

        /** @var null|string $installId Идентификатор установки модуля */
        $installId = Ge::$app->request->post('installId');

        /** @var string|array $decrypt Расшифровка идентификатора установки модуля */
        $decrypt = $manager->decryptInstallId($installId);
        if (is_string($decrypt)) {
            $response
                ->meta->error($decrypt);
            return $response;
        }

        // если модуль не имеет установщика "Installer\Installer.php"
        if (!$manager->installerExists($decrypt['path'])) {
            $response
                ->meta->error($this->module->t('The module installer at the specified path "{0}" does not exist', [$decrypt['path']]));
            return $response;
        }

        // каждый модуль обязан иметь установщик, управление установщиком передаётся текущему модулю
        /** @var \Ge\ModuleManager\ModuleInstaller $installer Установщик модуля */
        $installer = $manager->getInstaller([
            'module'    => $this->module, 
            'namespace' => $decrypt['namespace'],
            'path'      => $decrypt['path'], 
            'installId' => $installId
        ]);

        // если установщик не создан
        if ($installer === null) {
            $response
                ->meta->error($this->t('Unable to create module installer'));
            return $response;
        }

        // устанавливает модуль
        if ($installer->install()) {
            $response
                ->meta
                    ->cmdPopupMsg(
                        $this->module->t('Module installation "{0}" completed successfully', [$installer->info['name']]),
                        $this->t('Installing'),
                        'accept'
                    )
                    ->cmdReloadGrid($this->module->viewId('grid'));
        } else {
            $response
                ->meta->error($installer->getError());
        }
        return $response;
    }

    /**
     * Действие "view" выводит интерфейс установщика модуля.
     * 
     * @return Response
     */
    public function viewAction(): Response
    {
        // добавляем шаблон локализации для установки (см. ".extension.php")
        $this->module->addTranslatePattern('install');

        /** @var \Ge\ModuleManager\ModuleManager Менеджер модулей */
        $manager = Ge::$app->modules;
        /** @var Response $response */
        $response = $this->getResponse();

        /** @var null|string $installId Идентификатор установки модуля */
        $installId = Ge::$app->request->post('installId');

        /** @var string|array $decrypt Расшифровка идентификатора установки модуля */
        $decrypt = $manager->decryptInstallId($installId);
        if (is_string($decrypt)) {
            $response
                ->meta->error($decrypt);
            return $response;
        }

        // если модуль не имеет установщика "Installer\Installer.php"
        if (!$manager->installerExists($decrypt['path'])) {
            $response
                ->meta->error($this->module->t('The module installer at the specified path "{0}" does not exist', [$decrypt['path']]));
            return $response;
        }

        // каждый модуль обязан иметь установщик, управление установщиком передаётся текущему модулю
        /** @var \Ge\ModuleManager\ModuleInstaller|null $installer Установщик модуля */
        $installer = $manager->getInstaller([
            'module'    => $this->module, 
            'namespace' => $decrypt['namespace'],
            'path'      => $decrypt['path'], 
            'installId' => $installId
        ]);

        // если установщик не создан
        if ($installer === null) {
            $response
                ->meta->error($this->t('Unable to create module installer'));
            return $response;
        }

        /** @var null|\Ge\Panel\Widget\BaseWidget|\Ge\View\Widget $widget */
        $widget = $installer->getWidget();
        // если установщик не имеет модуль
        if ($widget === null) {
            /** @var InstallWindow $widget */
            $widget = $this->getWidget();
        }
        $widget->info = $installer->getModuleInfo();

        // проверка конфигурации устанавливаемого модуля
        if (!$installer->validateInstall()) {
            $widget->notice = $installer->getError();
        }

        // если была ошибка при формировании модуля
        if ($widget === false) {
            return $response;
        }

        $response
            ->setContent($widget->run())
            ->meta
                ->addWidget($widget);
        return $response;
    }
}
