<?php
namespace TYPO3\CMS\Core\Charset\Unicode;

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


if (!class_exists(__NAMESPACE__ . '\\NormalizerImplementation', false) &&
    !empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizer']) &&
    class_exists(
        __NAMESPACE__ . '\\' . ucfirst($GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizer'])
        . 'Normalizer', true
    ))
{
    class_alias(
        __NAMESPACE__ . '\\' . ucfirst($GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizer']) . 'Normalizer',
        __NAMESPACE__ . '\\NormalizerImplementation',
        true
    );

} elseif (class_exists(__NAMESPACE__ . '\\NormalizerImplementation', false)) {

    // Nothing to do here …

} else {

    /**
     * Legacy implementation, primary for IDE only. The real implementation should
     * have been registered above, otherwise this class makes it fail as it should!
     *
     * @author Stephan Jorek <stephan.jorek@gmail.com>
     */
    class NormalizerImplementation implements MissingNormalizerInterface
    {
        use NormalizerTrait;
        use MissingNormalizerTrait;
    }
}
