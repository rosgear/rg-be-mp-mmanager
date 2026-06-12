<?php
/**
 * Этот файл является частью расширения модуля веб-приложения RosGear.
 * 
 * @link https://rosgear.ru/
 * @copyright Copyright (c) 2015 RosGear
 * @license https://rosgear.ru/license/
 */

namespace Rg\Backend\Marketplace\ModuleManager\Model;

use Ge;
use Ge\Panel\Data\Model\FormModel;

/**
 * Модель данных изменения модуля.
 * 
 * @author Anton Tivonenko <anton.tivonenko@gmail.com>
 * @package Rg\Backend\Marketplace\ModuleManager\Model
 * @since 1.0
 */
class Form extends FormModel
{
    /**
     * {@inheritdoc}
     */
    public array $localizerParams = [
        'tableName'  => '{{module_locale}}',
        'foreignKey' => 'module_id',
        'modelName'  => 'Ge\ModuleManager\Model\ModuleLocale',
    ];

    /**
     * {@inheritdoc}
     */
    public function getDataManagerConfig(): array
    {
        return [
            'useAudit'   => true,
            'tableName'  => '{{module}}',
            'primaryKey' => 'id',
            'fields'     => [
                ['id'],
                ['name'],
                ['description'],
                [
                    'module_id',
                    'alias' => 'moduleId'
                ],
                [
                    'enabled', 
                    'title' => 'Enabled'
                ],
                [
                    'visible', 
                    'title' => 'Visible'
                ],
                /**
                 * поля добавленные динамически:
                 * - title, имя модуля (для заголовка окна)
                 */
            ],
            // правила форматирования полей
            'formatterRules' => [
                [['enabled', 'visible'], 'logic']
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();

        $this
            ->on(self::EVENT_AFTER_SAVE, function ($isInsert, $columns, $result, $message) {
                // если всё успешно
                if ($result) {
                    /** @var \Ge\ModuleManager\ModuleRegistry $installed */
                    $installed = Ge::$app->modules->getRegistry();
                    $module = $installed->get($this->moduleId);
                    if ($module) {
                        $lock = (bool) ($module['lock'] ?? false);
                        // если модуль не системный
                        if (!$lock) {
                            // обвновление конфигурации установленных модулей
                            $installed->set($this->moduleId, [
                                'visible'     => (bool) $this->visible,
                                'enabled'     => (bool) $this->enabled,
                                'name'        => $this->name,
                                'description' => $this->description
                            ], true);
                        }
                    }
                }
                // всплывающие сообщение
                $this->response()
                    ->meta
                        ->cmdPopupMsg($message['message'], $message['title'], $message['type']);
                /** @var \Ge\Panel\Controller\FormController $controller */
                $controller = $this->controller();
                // обновить список
                $controller->cmdReloadGrid();
            })
            ->on(self::EVENT_AFTER_DELETE, function ($result, $message) {
                // обвновление конфигурации установленных модулей
                Ge::$app->modules->update();
                // всплывающие сообщение
                $this->response()
                    ->meta
                        ->cmdPopupMsg($message['message'], $message['title'], $message['type']);
                /** @var \Ge\Panel\Controller\FormController $controller */
                $controller = $this->controller();
                // обновить список
                $controller->cmdReloadGrid();
            });
    }

    /**
     * {@inheritdoc}
     */
    public function processing(): void
    {
        parent::processing();

        // для формирования загаловка по атрибутам
        $locale = $this->getLocalizer()->getModel();
        if ($locale) {
            $this->title = $locale->name ?: '';
        }
    }

    /**
     * {@inheritDoc}
     */
    public function afterValidate(bool $isValid): bool
    {
        if ($isValid) {
            if (!Ge::$app->modules->getRegistry()->has($this->moduleId)) {
                $this->setError(
                    Ge::t('app', 'There is no {0} with the specified id "{1}"', [Ge::t('app', 'Module'), $this->moduleId])
                );
                return false;
            }
        }
        return $isValid;
    }

    /**
     * {@inheritdoc}
     */
    public function getActionTitle():string
    {
        return isset($this->title) ? $this->title : parent::getActionTitle();
    }
}
