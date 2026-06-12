<?php
/**
 * Этот файл является частью расширения модуля веб-приложения RosGear.
 * 
 * Файл конфигурации установки расширения.
 * 
 * @link https://rosgear.ru/
 * @copyright Copyright (c) 2015 RosGear
 * @license https://rosgear.ru/license/
 */

return [
    'priority'    => 1,
    'id'          => 'rg.be.mp.mmanager',
    'moduleId'    => 'rg.be.mp',
    'name'        => 'Module Manager',
    'description' => 'Management of system modules',
    'namespace'   => 'Rg\Backend\Marketplace\ModuleManager',
    'path'        => '/rg/rg.be.mp.mmanager',
    'route'       => 'mmanager',
    'locales'     => ['ru_RU', 'en_GB'],
    'permissions' => ['any', 'view', 'read', 'install', 'uninstall', 'info'],
    'events'      => [],
    'required'    => [
        ['php', 'version' => '8.2'],
        ['app', 'code' => 'RG Workspace'],
        ['app', 'code' => 'RG CMS'],
        ['app', 'code' => 'RG CRM'],
        ['module', 'id' => 'rg.be.mp']
    ]
];
