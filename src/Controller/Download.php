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
use Ge\FilePackager\FilePackager;
use Ge\Panel\Controller\BaseController;

/**
 * Контроллер скачивания файла пакета модуля.
 * 
 * @author Anton Tivonenko <anton.tivonenko@gmail.com>
 * @package Rg\Backend\Marketplace\ModuleManager\Controller
 * @since 1.0
 */
class Download extends BaseController
{
    /**
     * {@inheritdoc}
     */
    protected string $defaultAction = 'index';

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'verb' => [
                'class'    => '\Ge\Filter\VerbFilter',
                'autoInit' => true,
                'actions'  => [
                    ''     => ['POST', 'ajax' => 'GJAX'],
                    'file' => ['GET']
                ]
            ],
            'audit' => [
                'class'    => '\Ge\Panel\Behavior\AuditBehavior',
                'autoInit' => true,
                'allowed'  => '*',
                'enabled'  => $this->enableAudit
            ]
        ];
    }

    /**
     * Действие "index" подготавливает пакет моудля для скачивания.
     * 
     * @return Response
     */
    public function indexAction(): Response
    {
        /** @var Response $response */
        $response = $this->getResponse(Response::FORMAT_JSONG);
        /** @var \Ge\ModuleManager\ModuleManager Менеджер модулей */
        $manager = Ge::$app->modules;

        /** @var null|string $moduleId Идентификатор установленного модуля */
        $moduleId = Ge::$app->request->post('id');
        if (empty($moduleId)) {
            $message = Ge::t('backend', 'Invalid argument "{0}"', ['id']);

            Ge::debug('Error', ['error' => $message]);
            $response
                ->meta->error($message);
            return $response;
        }

        /** @var null|array $params Параметры установленного модуля */
        $params = $manager->getRegistry()->get($moduleId);
        // модуль с указанным идентификатором не установлен
        if ($params === null) {
            $message = $this->module->t('There is no module with the specified id "{0}"', [$moduleId]);

            Ge::debug('Error', ['error' => $message]);
            $response
                ->meta->error($message);
            return $response;
        }

        /** @var null|array $version Параметры установленного модуля */
        $version = $manager->getVersion($moduleId);
        // модуль с указанным идентификатором не установлен
        if ($version === null) {
            $message = $this->module->t('There is no module with the specified id "{0}"', [$moduleId]);

            Ge::debug('Error', ['error' => $message]);
            $response
                ->meta->error($message);
            return $response;
        }

        /** @var string $packageName Название файла пакета */
        $packageName = FilePackager::generateFilename($moduleId, $version['version']);
        /** @var FilePackager Файл пакета  */
        $packager = new FilePackager([
            'filename' => Ge::alias('@runtime') . DS . $packageName,
        ]);

        /** @var \Ge\FilePackager\Package $package Пакет */
        $package = $packager->getPackage([
            'format' => 'json',
            'path'   => Ge::alias('@runtime')
        ]);
        $package->id     = $moduleId;
        $package->type   = 'module';
        $package->author = $version['author'];
        $package->date   = $version['versionDate'];
        $package->name   = 'Module "' . $version['name'] . '" v' . $version['version'];
        $package->note   = $version['description'];

        // добавление файлов в пакет
        $package->addFiles(Ge::getAlias('@module' . $params['path']), '@module' . $params['path']);

        // проверка и сохранение файла пакета
        if (!$package->save(true)) {
            $message = $package->getError();

            Ge::debug('Error', ['error' => $message]);
            $response
                ->meta->error($message);
            return $response;
        }

        // архивация пакета
        if (!$packager->pack($package)) {
            $message = $package->getError();

            Ge::debug('Error', ['error' => $message]);
            $response
                ->meta->error($message);
            return $response;
        }

        $response
            ->meta
                // всплывающие сообщение
                ->cmdPopupMsg($this->t('The module package will now be loaded'), $this->t('Downloading'), 'success')
                // загрузка файла
                ->cmdGe('download', ['@backend/marketplace/mmanager/download/file/' . $params['rowId']]);
        return $response;
    }

    /**
     * Действие "file" скачивает файл пакета модуля.
     * 
     * @return Response
     */
    public function fileAction(): Response
    {
        /** @var Response $response */
        $response = $this->getResponse(Response::FORMAT_RAW);
        /** @var \Ge\ModuleManager\ModuleManager Менеджер модулей */
        $manager = Ge::$app->modules;

        /** @var null|int $moduleId Идентификатор установленного модуля */
        $moduleId = (int) Ge::$app->router->get('id');
        if (empty($moduleId)) {
            $message = Ge::t('backend', 'Invalid argument "{0}"', ['id']);

            Ge::debug('Error', ['error' => $message]);
            return $response->setContent($message);
        }

        /** @var null|array $params Параметры установленного модуля */
        $params = $manager->getRegistry()->getAt($moduleId);
        // модуль с указанным идентификатором не установлен
        if ($params === null) {
            $message = $this->module->t('There is no module with the specified id "{0}"', [$moduleId]);

            Ge::debug('Error', ['error' => $message]);
            return $response->setContent($message);
        }

        /** @var null|array $version Параметры установленного модуля */
        $version = $manager->getVersion($params['id']);
        // модуль с указанным идентификатором не установлен
        if ($version === null) {
            $message = $this->module->t('There is no module with the specified id "{0}"', [$params['id']]);

            Ge::debug('Error', ['error' => $message]);
            return $response->setContent($message);
        }

        /** @var string $packageName Название файла пакета */
        $filename = Ge::alias('@runtime') . DS . FilePackager::generateFilename($params['id'], $version['version']);
        if (!file_exists($filename)) {
            $message = Ge::t('app', 'File "{0}" not found', [$filename]);

            Ge::debug('Error', ['error' => $message]);
            return $response->setContent($message);
        }

        $response->sendFile($filename);
        return $response;
    }
}
