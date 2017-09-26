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
 * A normalizer implementation that uses "symfony/polyfill-intl-normalizer" package for normalization.
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 * @link https://packagist.org/packages/symfony/polyfill-intl-normalizer
 * @link https://github.com/symfony/polyfill-intl-normalizer
 * @link https://github.com/symfony/polyfill/tree/master/src/Intl/Normalizer
 */
class SymfonyNormalizer implements NormalizerInterface, Feature\ConformanceLevel7, Feature\LooseImplementation
{

    use NormalizerTrait;

    const IMPLEMENTATION_IDENTIFIER = 'symfony';

    const UNICODE_NORMALIZATION_FORMS = [
        NormalizerInterface::NONE,
        NormalizerInterface::NFD,
        NormalizerInterface::NFKD,
        NormalizerInterface::NFC,
        NormalizerInterface::NFKC
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
        return \Symfony\Polyfill\Intl\Normalizer\Normalizer::normalize($input, $form);
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
        return \Symfony\Polyfill\Intl\Normalizer\Normalizer::isNormalized($input, $form);
    }

    /**
     * Return true if all dependencies of the underlying implementation are met
     *
     * @return boolean
     */
    public static function isAvailable() {
        return class_exists('Symfony\\Polyfill\\Intl\\Normalizer\\Normalizer', true);
    }

}
