<?php
namespace TYPO3\CMS\Core\Charset\Unicode\Feature;

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

if (!interface_exists(__NAMESPACE__ . '\\MacConformanceLevel', false)) {
    class_alias(
        __NAMESPACE__ . '\\ConformanceLevel'
            . ((extension_loaded('intl') && class_exists('Normalizer', false)) ? '9' : '7'),
        __NAMESPACE__ . '\\MacConformanceLevel',
        true
    );

} elseif(false) {
    /**
     * Interface setting conformance level constant.
     *
     * Attention:
     *  Legacy implementation for IDE only.
     *  The real implementation is registered above!
     *
     * @author Stephan Jorek <stephan.jorek@gmail.com>
     */
    interface MacConformanceLevel {
        /**
         * Attention:
         *  Legacy implementation for IDE only.
         *  The real implementation is registered above!
         * 
         * @var string
         */
        const UNICODE_CONFORMANCE_LEVEL = null;
    }
}