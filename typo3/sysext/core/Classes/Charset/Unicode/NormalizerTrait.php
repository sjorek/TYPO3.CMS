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


use TYPO3\CMS\Core\Utility\UnicodeUtility;

/**
 * A trait providing getForm, setForm and some more methods.
 * 
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
trait NormalizerTrait
{

    /**
     * NONE or one of the five unicode normalization forms NFC, NFD, NFKC, NFKD or NFD_MAC.
     *
     * Must be set to one of the integer constants from Normalizer-implementation. Defaults to NFC.
     *
     * @link http://www.php.net/manual/en/class.normalizer.php
     * @link http://www.unicode.org/glossary/#normalization_form
     * @var integer
     */
    protected $form = self::NFC;

    /**
     * Set the default normalization form to the given value.
     *
     * @param integer|string $form
     * @return void
     * @see \TYPO3\CMS\Core\Utility\UnicodeUtility::parseNormalizationForm()
     * @throws \InvalidArgumentException
     */
    public function setForm($form = null)
    {
        if ($form === null && isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizationForm'])) {
            $form = $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizationForm'];
        }
        if (!is_integer($form)) {
            $form = UnicodeUtility::parseNormalizationForm($form);
        }
        if (!in_array((int) $form, self::UNICODE_NORMALIZATION_FORMS, true)) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported unicode-normalization form: %s.', $form), 1398603947
            );
        }
        $this->form = (int) $form;
    }
    
    /**
     * Retrieve the current normalization-form constant.
     *
     * @return integer
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * Return the implementation identifier
     *
     * @return boolean
     */
    public static function getIdentifier() {
        return self::IMPLEMENTATION_IDENTIFIER;
    }

    /**
     * Return the unicode conformance level
     *
     * @return string
     */
    public static function getConformanceLevel() {
        return self::UNICODE_CONFORMANCE_LEVEL;
    }

    /**
     * Return an array of supported normalization form constants
     *
     * @return array
     */
    public static function getNormalizations() {
        return self::UNICODE_NORMALIZATION_FORMS;
    }

}
