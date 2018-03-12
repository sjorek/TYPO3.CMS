<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Frontend\Http;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface as PsrRequestHandlerInterface;
use TYPO3\CMS\Backend\FrontendBackendUserAuthentication;
use TYPO3\CMS\Core\FrontendEditing\FrontendEditingController;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Http\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageGenerator;

/**
 * This is the main entry point of the TypoScript driven standard front-end
 *
 * Basically put, this is the script which all requests for TYPO3 delivered pages goes to in the
 * frontend (the website). The script instantiates a $TSFE object, includes libraries and does a little logic here
 * and there in order to instantiate the right classes to create the webpage.
 * Previously, this was called index_ts.php and also included the logic for the lightweight "eID" concept,
 * which is now handled in a separate middleware (EidHandler).
 */
class RequestHandler implements RequestHandlerInterface, PsrRequestHandlerInterface
{
    /**
     * Instance of the timetracker
     * @var TimeTracker
     */
    protected $timeTracker;

    /**
     * Handles a frontend request
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handle($request);
    }

    /**
     * Handles a frontend request, after finishing running middlewares
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface|null
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Fetch the initialized time tracker object
        $this->timeTracker = GeneralUtility::makeInstance(TimeTracker::class);
        /** @var TypoScriptFrontendController $controller */
        $controller = $GLOBALS['TSFE'];

        // Admin Panel & Frontend editing
        if ($controller->isBackendUserLoggedIn()) {
            $GLOBALS['BE_USER']->initializeFrontendEdit();
            if ($GLOBALS['BE_USER']->frontendEdit instanceof FrontendEditingController) {
                $GLOBALS['BE_USER']->frontendEdit->initConfigOptions();
            }
        }

        // Starts the template
        $this->timeTracker->push('Start Template', '');
        $controller->initTemplate();
        $this->timeTracker->pull();
        // Get from cache
        $this->timeTracker->push('Get Page from cache', '');
        $controller->getFromCache();
        $this->timeTracker->pull();
        // Get config if not already gotten
        // After this, we should have a valid config-array ready
        $controller->getConfigArray();
        // Setting language and locale
        $this->timeTracker->push('Setting language and locale', '');
        $controller->settingLanguage();
        $controller->settingLocale();
        $this->timeTracker->pull();

        // Convert POST data to utf-8 for internal processing if metaCharset is different
        $controller->convPOSTCharset();

        // Ensures all that (user-)inputs are well formed and/or normalized UTF-8.
        $controller->filterAndNormalizeInputs();

        $controller->initializeRedirectUrlHandlers();

        $controller->handleDataSubmission();

        // Check for shortcut page and redirect
        $controller->checkPageForShortcutRedirect();
        $controller->checkPageForMountpointRedirect();

        // Generate page
        $controller->setUrlIdToken();
        $this->timeTracker->push('Page generation', '');
        if ($controller->isGeneratePage()) {
            $controller->generatePage_preProcessing();
            $controller->preparePageContentGeneration();
            // Content generation
            if (!$controller->isINTincScript()) {
                PageGenerator::renderContent();
                $controller->setAbsRefPrefix();
            }
            $controller->generatePage_postProcessing();
        } elseif ($controller->isINTincScript()) {
            $controller->preparePageContentGeneration();
        }
        $controller->releaseLocks();
        $this->timeTracker->pull();

        // Render non-cached parts
        if ($controller->isINTincScript()) {
            $this->timeTracker->push('Non-cached objects', '');
            $controller->INTincScript();
            $this->timeTracker->pull();
        }

        // Create a Response object when sending content
        $response = new Response();

        // Output content
        $isOutputting = $controller->isOutputting();
        if ($isOutputting) {
            $this->timeTracker->push('Print Content', '');
            $controller->processOutput();
            $this->timeTracker->pull();
        }
        // Store session data for fe_users
        $controller->storeSessionData();

        $redirectResponse = $controller->redirectToExternalUrl();
        if ($redirectResponse instanceof ResponseInterface) {
            return $redirectResponse;
        }

        // Statistics
        $GLOBALS['TYPO3_MISC']['microtime_end'] = microtime(true);
        if ($isOutputting && ($controller->config['config']['debug'] ?? !empty($GLOBALS['TYPO3_CONF_VARS']['FE']['debug']))) {
            $response = $response->withHeader('X-TYPO3-Parsetime', $this->timeTracker->getParseTime() . 'ms');
        }

        // Preview info
        $controller->previewInfo();
        // Hook for end-of-frontend
        $controller->hook_eofe();
        // Finish timetracking
        $this->timeTracker->pull();

        // Admin panel
        if ($controller->isBackendUserLoggedIn() && $GLOBALS['BE_USER'] instanceof FrontendBackendUserAuthentication && $GLOBALS['BE_USER']->isAdminPanelVisible()) {
            $controller->content = str_ireplace('</body>', $GLOBALS['BE_USER']->displayAdminPanel() . '</body>', $controller->content);
        }

        if ($isOutputting) {
            $response->getBody()->write($controller->content);
        }

        return $isOutputting ? $response : new NullResponse();
    }

    /**
     * This request handler can handle any frontend request.
     *
     * @param ServerRequestInterface $request
     * @return bool If the request is not an eID request, TRUE otherwise FALSE
     */
    public function canHandleRequest(ServerRequestInterface $request): bool
    {
        return true;
    }

    /**
     * Returns the priority - how eager the handler is to actually handle the
     * request.
     *
     * @return int The priority of the request handler.
     */
    public function getPriority(): int
    {
        return 50;
    }
}
