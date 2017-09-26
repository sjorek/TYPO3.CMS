<?php
namespace TYPO3\CMS\Frontend\Hooks;

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


use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Utility\UnicodeUtility;

/**
 * Unicode related hooks
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class UnicodeHooks
{

    /**
     * Enforce url-encoded, well-formed and optionally normalized UTF-8 in request-uri
     *
     * @return void
     */
    public function hook_enforceUtf8EncodedRequestUri()
    {
        $httpCode = (int) $GLOBALS['TYPO3_CONF_VARS']['FE']['unicode']['enforceUtf8EncodedRequestUri'];
        if ($httpCode === 1) {
            $httpCode = (int) $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['enforceUtf8EncodedRequestUri'];
        }
        if (in_array($httpCode, array(301, 303, 308, 400, 404))) {
            $httpCode = sprintf(HttpUtility::class . '::HTTP_STATUS_%s', $httpCode);
            if (defined($httpCode)) {
                UnicodeUtility::enforceUtf8EncodedRequestUri(constant($httpCode));
            }
        }
    }
}
