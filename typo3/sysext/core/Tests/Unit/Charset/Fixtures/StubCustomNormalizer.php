<?php
namespace TYPO3\CMS\Core\Tests\Unit\Charset\Fixtures;

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

use TYPO3\CMS\Core\Charset\Unicode\CustomNormalizerInterface;
use TYPO3\CMS\Core\Charset\Unicode\NormalizerTrait;

/**
 * A stub normalizer used to test the "CustomNormalizer" registration.
 *  
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class StubCustomNormalizer implements CustomNormalizerInterface {

    use NormalizerTrait;

    const UNICODE_NORMALIZATION_FORMS = [
        CustomNormalizerInterface::NONE,
        CustomNormalizerInterface::NFC,
        CustomNormalizerInterface::NFD,
        CustomNormalizerInterface::NFKC,
        CustomNormalizerInterface::NFKD,
        CustomNormalizerInterface::NFD_MAC
    ];

    /**
     * Normalizes the input provided and returns the normalized string.
     *
     * @param string $input     The input string to normalize.
     * @param int $form         [optional] One of the normalization forms.
     * @return string The normalized string or NULL if an error occurred.
     */
    public function normalize($input, $form = null)
    {
        return $input;
    }

    /**
     * Checks if the provided string is already in the specified normalization form.
     *
     * @param string $input     The input string to normalize
     * @param int $form         [optional] One of the normalization forms.
     * @return bool TRUE if normalized, FALSE otherwise or if an error occurred.
     */
    public function isNormalized($input, $form = null)
    {
        return true;
    }

    /**
     * Return true if all dependencies of the underlying implementation are met
     *
     * @return boolean
     */
    public static function isAvailable()
    {
        return true;
    }
}
