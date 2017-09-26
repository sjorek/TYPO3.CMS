<?php
namespace TYPO3\CMS\Core\Charset;

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
 * Class for normalizing unicode.
 *
 *    “Normalization: A process of removing alternate representations of equivalent
 *    sequences from textual data, to convert the data into a form that can be
 *    binary-compared for equivalence. In the Unicode Standard, normalization refers
 *    specifically to processing to ensure that canonical-equivalent (and/or
 *    compatibility-equivalent) strings have unique representations.”
 *
 *     -- quoted from unicode glossary linked below
 *
 * Depending on which underlying supported unicode normalization implementation is available, this class
 * inherits from one of the facades or implementations located in TYPO3\CMS\Core\Charset\Unicode.
 * 
 * @see Unicode\NormalizerImplementation
 * @see Unicode\CustomNormalizer
 * @see Unicode\MacNormalizer
 * @see Unicode\IntlNormalizer
 * @see Unicode\PatchworkNormalizer
 * @see Unicode\StubNormalizer
 * @see Unicode\SymfonyNormalizer
 * @link http://www.unicode.org/glossary/#normalization
 * @link http://www.php.net/manual/en/class.normalizer.php
 * @link http://www.w3.org/wiki/I18N/CanonicalNormalization
 * @link http://www.w3.org/TR/charmod-norm/
 * @link http://blog.whatwg.org/tag/unicode
 * @link http://en.wikipedia.org/wiki/Unicode_equivalence
 * @link http://stackoverflow.com/questions/7931204/what-is-normalized-utf-8-all-about
 * @link http://forge.typo3.org/issues/57695
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class UnicodeNormalizer extends Unicode\NormalizerImplementation implements \TYPO3\CMS\Core\SingletonInterface
{

    /**
     * Constructor
     *
     * @param integer|string|null $form    [optional] Set normalization form, optional
     * @see \TYPO3\CMS\Core\Utility\UnicodeUtility::parseNormalizationForm()
     * @link http://www.php.net/manual/en/class.normalizer.php
     */
    public function __construct($form = null)
    {
        $this->setForm($form);
    }

    /**
     * Normalizes the $string provided to the given or default $normalization and returns the normalized string.
     *
     * Calls underlying implementation even if given $normalization is NONE, but finally it normalizes only if
     * there is something to normalize.
     *
     * @param string $input     The string to normalize.
     * @param integer $form     [optional] normalization form to use, overriding the default
     * @return string|null Normalized string or null if an error occurred
     * @link http://php.net/manual/en/normalizer.isnormalized.php
     * @link http://www.php.net/manual/en/normalizer.normalize.php
     */
    public function normalizeTo($input, $form = null)
    {
        if ($form === null) {
            $form = $this->form;
        }
        if ($this->isNormalized($input, $form)) {
            return $input;
        }
        return $this->normalize($input, $form);
    }

    /**
     * Normalizes the $string provided to the given or default $normalization and returns the normalized string.
     *
     * Does not call underlying implementation if given normalization is NONE and normalizes only if needed.
     *
     * @param string $input     The string to normalize.
     * @param integer $form     [optional] normalization form to use, overriding the default
     * @return string|null Normalized string or null if an error occurred
     * @see http://www.php.net/manual/en/normalizer.normalize.php
     */
    public function normalizeStringTo($input, $form = null)
    {
        if ($form === null) {
            $form = $this->form;
        }
        if (self::NONE < (int) $form) {
            return $this->normalizeTo($input, $form);
        }
        return $input;
    }

}
