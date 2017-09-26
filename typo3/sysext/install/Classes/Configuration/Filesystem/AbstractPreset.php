<?php
namespace TYPO3\CMS\Install\Configuration\Filesystem;

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


use TYPO3\CMS\Install\Configuration;
use TYPO3\CMS\Core\Utility\UnicodeUtility;

/**
 * Abstract class implements common filesystem preset code
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
abstract class AbstractPreset extends Configuration\Unicode\NormalizationForm\AbstractPreset
{

    /**
     * @return boolean[]|array[]
     */
    protected function detectUtf8Capabilities()
    {
        static $capabilities;

        if (isset($capabilities)) {
            return $capabilities;
        }

        $capabilities = UnicodeUtility::detectUtf8CapabilitiesForPath(PATH_site . '/typo3temp');

        return $capabilities;
    }
}
