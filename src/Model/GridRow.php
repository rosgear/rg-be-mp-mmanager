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
 * Модель данных профиля записи установленного модуля.
 * 
 * @author Anton Tivonenko <anton.tivonenko@gmail.com>
 * @package Rg\Backend\Marketplace\ModuleManager\Model
 * @since 1.0
 */
class GridRow extends FormModel
{
    /**
     * Идентификатор выбранного модуля.
     * 
     * @see GridRow::afterValidate()
     * 
     * @var string|null
     */
    protected ?string $moduleId;

    /**
     * Имя выбранного модуля.
     * 
     * @see GridRow::afterValidate()
     * 
     * @var string|null
     */
    public ?string $moduleName;

    /**
     * {@inheritdoc}
     */
    public function getDataManagerConfig(): array
    {
        return [
            'tableName'  => '{{module}}',
            'primaryKey' => 'id',
            'fields'     => [
                ['id'],
                ['enabled', 'label' => 'Enabled'],
                ['visible', 'label' => 'Visible'],
            ],
            'useAudit' => true
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
                if ($message['success']) {
                    if (isset($columns['visible'])) {
                        $visible = (int) $columns['visible'];
                        $message['message'] = $this->module->t('Module {0} - ' . ($visible > 0 ? 'show' : 'hide'), [$this->moduleName]);
                        $message['title']   = $this->module->t($visible > 0 ? 'Show' : 'Hide');
                    }
                    if (isset($columns['enabled'])) {
                        $enabled = (int) $columns['enabled'];
                        $message['message'] = $this->module->t('Module {0} - ' . ($enabled > 0 ? 'enabled' : 'disabled'), [$this->moduleName]);
                        $message['title']   = $this->module->t($enabled > 0 ? 'Enabled' : 'Disabled');
                    }
                }
                // всплывающие сообщение
                $this->response()
                    ->meta
                        ->cmdPopupMsg($message['message'], $message['title'], $message['type']);
            });
    }

    /**
     * {@inheritDoc}
     */
    public function afterValidate(bool $isValid): bool
    {
        if ($isValid) {
            /** @var \Ge\Http\Request $request */
            $request  = Ge::$app->request;
            // имя модуля
            $this->moduleName = $request->post('name');
            if (empty($this->moduleName)) {
                $this->setError(Ge::t('app', 'Parameter passed incorrectly "{0}"', ['Name']));
                return false;
            }
            // идентификатор модуля
            $this->moduleId = $request->post('moduleId');
            if (empty($this->moduleId)) {
                $this->setError(Ge::t('app', 'Parameter passed incorrectly "{0}"', ['Module Id']));
                return false;
            }
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
    public function beforeUpdate(array &$columns): void
    {
        /** @var \Ge\ModuleManager\ModuleRegistry $installed */
        $installed = Ge::$app->modules->getRegistry();
        /** @var \Ge\Http\Request $request */
        $request = Ge::$app->request;
        // видимость модуля (только для панели управления)
        $visible = $request->post('visible');
        if ($visible !== null) {
            $installed->set($this->moduleId, ['visible' => $visible], true);
        }
        // доступность модуля
        $enabled = $request->post('enabled');
        if ($enabled !== null) {
            $installed->set($this->moduleId, ['enabled' => $enabled], true);
        }
    }
}
