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


/**
 * A stub normalizer implementation, only working for normalization form NONE or ASCII strings.
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class StubNormalizer implements NormalizerInterface, Feature\LooseImplementation, Feature\NoneConformant
{
    
    use NormalizerTrait;

    const IMPLEMENTATION_IDENTIFIER = 'stub';

    const UNICODE_NORMALIZATION_FORMS = [
        NormalizerInterface::NONE,
        NormalizerInterface::NFC
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
        if ($form === null) {
            $form = $this->form;
        }
        if (!in_array($form, self::UNICODE_NORMALIZATION_FORMS, true)) {
            return false;
        }
        if (self::NONE === $form) {
            return $input;
        } elseif (self::NFC === $form &&
            // Empty string or plain ASCII is always valid for all forms, let it through.
            ($input === '' || !preg_match( '/[\x80-\xff]/', $input) ||
            // A cheap NFC detection
            (preg_match('//u', $input) && !preg_match('/[^\x00-\x{2FF}]/u', $input))))
        {
            return $input;
        }
        return false;
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
        if ($form === null) {
            $form = $this->form;
        }
        if (!in_array($form, self::UNICODE_NORMALIZATION_FORMS, true)) {
            return false;
        }
        if (self::NFC === $form) {
            return (
                // Empty string or plain ASCII is always valid for all forms, let it through.
                $input === '' || !preg_match( '/[\x80-\xff]/', $input) ||
                // A cheap NFC detection
                (preg_match('//u', $input) && !preg_match('/[^\x00-\x{2FF}]/u', $input))
            );
        }
        return false;
    }

    /**
     * Return true if all dependencies of the underlying implementation are met
     *
     * @return boolean
     */
    public static function isAvailable() {
        return true;
    }

}
