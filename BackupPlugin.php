<?php

/**
 * @file BackupPlugin.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class BackupPlugin
 * @brief Plugin to allow generation of a backup extract
 */

namespace APP\plugins\generic\backup;

use PKP\linkAction\LinkAction;
use PKP\plugins\GenericPlugin;
use PKP\linkAction\request\AjaxModal;
use PKP\config\Config;
use PKP\core\JSONMessage;
use APP\template\TemplateManager;

class BackupPlugin extends GenericPlugin {
    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null) {
        if (parent::register($category, $path, $mainContextId)) {
            $this->addLocaleData();
            return true;
        }
        return false;
    }

    /**
     * Get the display name of this plugin
     * @return string
     */
    public function getDisplayName() {
        return __('plugins.generic.backup.name');
    }

    /**
     * Get the description of this plugin
     * @return string
     */
    public function getDescription() {
        return __('plugins.generic.backup.description');
    }

    /**
     * Designate this plugin as a site plugin
     */
    public function isSitePlugin() {
        return true;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb) {
        $router = $request->getRouter();
        return array_merge(
            $this->getEnabled()?array(
                new LinkAction(
                    'backup',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, array('verb' => 'backup', 'plugin' => $this->getName(), 'category' => 'generic')),
                        __('plugins.generic.backup.link')
                    ),
                    __('plugins.generic.backup.link'),
                    null
                ),
            ):array(),
            parent::getActions($request, $verb)
        );
    }

    /**
     * @copydoc PKPPlugin::manage()
     */
    public function manage($args, $request) {
        $router = $request->getRouter();
        switch ($request->getUserVar('verb')) {
            case 'backup':
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign(array(
                    'pluginName'         => $this->getName(),
                    'isDumpConfigured'     => Config::getVar('cli', 'dump')!='',
                    'isTarConfigured'     => Config::getVar('cli', 'tar')!='',
                    'errorMessage'         => __('plugins.generic.backup.failure')
                ));
                $output = $templateMgr->fetch($this->getTemplateResource('index.tpl'));
                return new JSONMessage(true, $output);
            case 'db':
                $dumpTool = Config::getVar('cli', 'dump');
                header('Content-Description: File Transfer');
                header('Content-Disposition: attachment; filename=db-' . date('Y-m-d') . '.sql');
                header('Content-Type: application/sql');
                header('Content-Transfer-Encoding: binary');

                passthru(strtr($dumpTool, array(
                    '{$hostname}' => escapeshellarg(Config::getVar('database', 'host')),
                    '{$username}' => escapeshellarg(Config::getVar('database', 'username')),
                    '{$password}' => escapeshellarg(Config::getVar('database', 'password')),
                    '{$databasename}' => escapeshellarg(Config::getVar('database', 'name')),
                )), $returnValue);
                if ($returnValue !== 0) header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
                exit();
            case 'files':
                $tarTool = Config::getVar('cli', 'tar');
                header('Content-Description: File Transfer');
                header('Content-Disposition: attachment; filename=files-' . date('Y-m-d') . '.tar.gz');
                header('Content-Type: application/gzip');
                header('Content-Transfer-Encoding: binary');
                passthru($tarTool . ' -c -f - -z ' . escapeshellarg(Config::getVar('files', 'files_dir')), $returnValue);
                if ($returnValue !== 0) header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
                exit();
            case 'code':
                $tarTool = Config::getVar('cli', 'tar');
                header('Content-Description: File Transfer');
                header('Content-Disposition: attachment; filename=code-' . date('Y-m-d') . '.tar.gz');
                header('Content-Type: application/gzip');
                header('Content-Transfer-Encoding: binary');
                passthru($tarTool . ' -c -f - -z ' . escapeshellarg(dirname(dirname(dirname(dirname(__FILE__))))), $returnValue);
                if ($returnValue !== 0) header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
                exit();
        }
        return parent::manage($args, $request);
    }
}
