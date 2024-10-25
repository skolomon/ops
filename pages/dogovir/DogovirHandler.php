<?php

/**
 * @file pages/index/IndexHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IndexHandler
 *
 * @ingroup pages_index
 *
 * @brief Handle site index requests.
 */

namespace APP\pages\index;

use APP\core\Application;
use APP\facades\Repo;
use APP\observers\events\UsageEvent;
use APP\server\ServerDAO;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\pages\index\PKPIndexHandler;
use PKP\plugins\PluginRegistry;
use PKP\security\Validation;

class DogovirHandler extends PKPIndexHandler
{
    //
    // Public handler operations
    //
    /**
     * If no server is selected, display list of servers.
     * Otherwise, display the index page for the selected server.
     *
     * @param array $args
     * @param \APP\core\Request $request
     */
    public function index($args, $request)
    {

        $this->validate(null, $request);
        $server = $request->getServer();

        $this->setupTemplate($request);
        $router = $request->getRouter();
        $templateMgr = TemplateManager::getManager($request);

            // Assign header and content for home page
            $templateMgr->assign([
                'additionalHomeContent' => $server->getLocalizedData('additionalHomeContent'),
                'homepageImage' => $server->getLocalizedData('homepageImage'),
                'homepageImageAltText' => $server->getLocalizedData('homepageImageAltText'),
                'serverDescription' => $server->getLocalizedData('description'),
                
                'pubIdPlugins' => PluginRegistry::loadCategory('pubIds', true),
            
                'authorUserGroups' => Repo::userGroup()->getCollector()->filterByRoleIds([\PKP\security\Role::ROLE_ID_AUTHOR])->filterByContextIds([$server->getId()])->getMany()->remember(),
                "salutation" => $request->getUser()?->getFullName(), //skolomon
            ]);


            $templateMgr->display('sasztest.tpl');
        // $templateMgr->display('frontend/pages/indexServer.tpl');
            // event(new UsageEvent(Application::ASSOC_TYPE_SERVER, $server));
            return;

    }
}
