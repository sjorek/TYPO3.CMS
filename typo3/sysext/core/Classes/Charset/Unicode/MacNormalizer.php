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
 * A normalizer implementation that uses "\Normalizer" class plus "iconv" extension for normalization.
 * This normalizer implements Apple™'s NFD-variant (NFD_MAC) for HFS+ filesystems aka "OS X Extended".
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 * @link http://php.net/manual/en/book.iconv.php
 * @link http://php.net/manual/en/function.iconv.php
 * @link https://opensource.apple.com/source/libiconv/libiconv-50/libiconv/lib/utf8mac.h.auto.html
 */
class MacNormalizer implements NormalizerInterface, Feature\MacConformanceLevel, Feature\MacImplementation
{

    use NormalizerTrait;

    const IMPLEMENTATION_IDENTIFIER = 'mac';

    const UNICODE_NORMALIZATION_FORMS = [
        NormalizerInterface::NONE,
        NormalizerInterface::NFC,
        NormalizerInterface::NFD,
        NormalizerInterface::NFKC,
        NormalizerInterface::NFKD,
        NormalizerInterface::NFD_MAC
    ];

    /**
     * Normalizes the input provided and returns the normalized string.
     *
     * @param string $input     The input string to normalize.
     * @param int $form         [optional] One of the normalization forms.
     * @return string The normalized string or FALSE if an error occurred.
     */
    public function normalize($input, $form = null)
    {
        if ($form === null) {
            $form = $this->form;
        }
        if (!in_array($form, self::UNICODE_NORMALIZATION_FORMS, true)) {
            return false;
        }
        if ($form !== self::NFD_MAC) {
            return \Normalizer::normalize($input, $form);
        } elseif ($input === "\xFF") {
            return false;
        // Empty string or plain ASCII is always valid for all forms, let it through.
        } elseif ($input === '' || !preg_match( '/[\x80-\xff]/', $input)) {
            return $input;
        } else {
            $result = \Normalizer::normalize($input, self::NFD);
            if ($result === null || $result === false) {
                return false;
            }
            return iconv('utf-8', 'utf-8-mac', $result);
        }
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
        if ($form !== self::NFD_MAC) {
            return \Normalizer::isNormalized($input, $form);
        } elseif ($input === '') {
                return true;
        } else {
            if (self::IMPLEMENTATION_IS_STRICT) {
                $result = \Normalizer::normalize($input, self::NFD);
                if ($result === null || $result === false) {
                    return false;
                }
                // Having no cheap check here, forces us to do a full equality-check here.
                // As we just want it to use for file names, this full check should be ok.
                return $input === iconv('utf-8', 'utf-8-mac', $result);
            } else {
                // To behave conform to other implementations we return false
                return false;
            }
        }
    }

    /**
     * Return true if all dependencies of the underlying implementation are met
     *
     * @return boolean
     */
    public static function isAvailable() {
        $nfc = hex2bin('64c3a96ac3a020ed9b88ec87bce284a2e2929ce4bda0');
        $mac = hex2bin('6465cc816a61cc8020e18492e185aee186abe18489e185ade284a2e2929ce4bda0');
        return class_exists('Normalizer', true) &&
            extension_loaded('iconv') &&
            $mac === @iconv('utf-8', 'utf-8-mac', $nfc) &&
            $nfc === @iconv('utf-8-mac', 'utf-8', $mac);
    }
}
