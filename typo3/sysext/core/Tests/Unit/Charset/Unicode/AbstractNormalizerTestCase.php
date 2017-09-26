<?php
namespace TYPO3\CMS\Core\Tests\Unit\Charset\Unicode;

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

use TYPO3\CMS\Core\Charset\Unicode\NormalizerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Tests\Unit\Charset\Fixtures\UnicodeNormalizationTestReader;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
abstract class AbstractNormalizerTestCase extends UnitTestCase
{

    /**
     * @var \TYPO3\CMS\Core\Charset\Unicode\NormalizerInterface
     */
    protected $subject;

    /**
     * @var string
     */
    protected $implementationClass;

    /**
     * @var boolean
     */
    protected $implementationIsStrict;

    /**
     * @var string
     */
    protected $unicodeConformanceLevel;

    /**
     * @var array
     */
    protected $unicodeNormalizationForms;

    /**
     * This method must be called by any dataProvider before continues its execution.
     */
    protected function setUpDataProvider() {
        $this->setUpBeforeDataProvider();
        if ($this->implementationIsAvailable()) {
            $this->acquireImplementationConstants();
            $this->setUpAfterDataProvider();
        } else {
            $this->skipTestForUnavailableImplementation();
        }
    }

    /**
     * This method will be called before any dataProvider continues its setUp.
     * Override as needed.
     */
    protected function setUpBeforeDataProvider() {}

    /**
     * This method will be called after any dataProvider setUp.
     * Override as needed.
     */
    protected function setUpAfterDataProvider() {}

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp()
    {
        $this->setUpBefore();
        if ($this->implementationIsAvailable()) {
            $this->acquireImplementationConstants();
            $this->subject = GeneralUtility::makeInstance($this->implementationClass);
            $this->setUpAfter();
        } else {
            $this->skipTestForUnavailableImplementation();
        }
    }

    /**
     * This method will be called before setUp.
     * Override as needed.
     */
    protected function setUpBefore() {}

    /**
     * This method will be called after setUp.
     * Override as needed.
     */
    protected function setUpAfter() {}

    /**
     * @return array
     */
    public function provideCheckIsNormalizedData()
    {
        $this->setUpDataProvider();

        $forms = $this->unicodeNormalizationForms;
        $strict = $this->implementationIsStrict;

        // déjà 훈쇼™⒜你
        $s_nfc  = hex2bin('64c3a96ac3a020ed9b88ec87bce284a2e2929ce4bda0');
        $s_nfd  = hex2bin('6465cc816a61cc8020e18492e185aee186abe18489e185ade284a2e2929ce4bda0');
        $s_nfkc = hex2bin('64c3a96ac3a020ed9b88ec87bc544d286129e4bda0');
        $s_nfkd = hex2bin('6465cc816a61cc8020e18492e185aee186abe18489e185ad544d286129e4bda0');
        $s_mac  = hex2bin('6465cc816a61cc8020e18492e185aee186abe18489e185ade284a2e2929ce4bda0');

        $f_NONE = NormalizerInterface::NONE;
        $f_NFC = NormalizerInterface::NFC;
        $f_NFD = NormalizerInterface::NFD;
        $f_NFKC = NormalizerInterface::NFKC;
        $f_NFKD = NormalizerInterface::NFKD;
        $f_MAC = NormalizerInterface::NFD_MAC;

        $data = [];

        $data['Empty string is normalized for default'] = [true, '', null];
        $data['ASCII string is always normalized for default'] = [true, 'abc', null];
        if ($strict) {
            $data['NFC string is strict normalized for default'] = [true, $s_nfc, null];
        } else {
            // Loose implementations return false, to prevent false positives
            $data['NFC string is loosely not normalized for default'] = [false, $s_nfc, null];
        }

        $data['Reserved byte FF is not normalized for default'] = [false, "\xFF", null];
        $data['Empty string with invalid form is not normalized for default'] = [false, '', -1];
        $data['Empty string is not normalized for NONE'] = [false, '', $f_NONE];

        if (in_array($f_NFC, $forms, true)) {
            if ($strict) {
                $data['NFC string is strict normalized for NFC'] = [true, $s_nfc, $f_NFC];
                $data['NFKC string is strict normalized for NFC'] = [true, $s_nfkc, $f_NFC];
            } else {
                // Loose implementations return false, to prevent false positives
                $data['NFC string is loosely not normalized for NFC'] = [false, $s_nfc, $f_NFC];
                $data['NFKC string is loosely not normalized for NFC'] = [false, $s_nfkc, $f_NFC];
            }

            $data['NFD string is not normalized for NFC'] = [false, $s_nfd, $f_NFC];
            $data['NFKD string is not normalized for NFC'] = [false, $s_nfkd, $f_NFC];
            $data['NFD_MAC string is not normalized for NFC'] = [false, $s_mac, $f_NFC];

            $data['Empty string is normalized for NFC'] = [true, '', $f_NFC];
        }

        if (in_array($f_NFD, $forms, true)) {
            if ($strict) {
                $data['NFD string is strict normalized for NFD'] = [true, $s_nfd, $f_NFD];
                $data['NFKD string is strict normalized for NFD'] = [true, $s_nfkd, $f_NFD];
                $data['NFD_MAC string is strict normalized for NFD'] = [true, $s_mac, $f_NFD];
            } else {
                // Loose implementations return false, to prevent false positives
                $data['NFD string is loosely not normalized for NFD'] = [false, $s_nfd, $f_NFD];
                $data['NFKD string is loosely not normalized for NFD'] = [false, $s_nfkd, $f_NFD];
                $data['NFD_MAC string is loosely not normalized for NFD'] = [false, $s_mac, $f_NFD];
            }

            $data['NFC string is not normalized for NFD'] = [false, $s_nfc, $f_NFD];
            $data['NFKC string is not normalized for NFD'] = [false, $s_nfkc, $f_NFD];

            $data['Empty string is normalized for NFD'] = [true, '', $f_NFD];
        }

        if (in_array($f_NFKC, $forms, true)) {
            if ($strict) {
                $data['NFKC string is strict normalized for NFKC'] = [true, $s_nfkc, $f_NFKC];
            } else {
                // Loose implementations return false, to prevent false positives
                $data['NFKC string is loosely not normalized for NFKC'] = [false, $s_nfkc, $f_NFKC];
            }
            $data['NFC string is not normalized for NFKC'] = [false, $s_nfc, $f_NFKC];
            $data['NFD string is not normalized for NFKC'] = [false, $s_nfd, $f_NFKC];
            $data['NFKD string is not normalized for NFKC'] = [false, $s_nfkd, $f_NFKC];
            $data['NFD_MAC string is not normalized for NFKC'] = [false, $s_mac, $f_NFKC];

            $data['Empty string is normalized for NFKC'] = [true, '', $f_NFKC];
        }

        if (in_array($f_NFKD, $forms, true)) {
            if ($strict) {
                $data['NFKD string is strict normalized for NFKD'] = [true, $s_nfkd, $f_NFKD];
            } else {
                // Loose implementations return false, to prevent false positives
                $data['NFKD string is loosely not normalized for NFKD'] = [false, $s_nfkd, $f_NFKD];
            }
            $data['NFC string is not normalized for NFKD'] = [false, $s_nfc, $f_NFKD];
            $data['NFD string is not normalized for NFKD'] = [false, $s_nfd, $f_NFKD];
            $data['NFKC string is not normalized for NFKD'] = [false, $s_nfkc, $f_NFKD];
            $data['NFD_MAC string is not normalized for NFKD'] = [false, $s_mac, $f_NFKD];

            $data['Empty string is normalized for NFKD'] = [true, '', $f_NFKD];
        }

        if (in_array($f_MAC, $forms, true)) {
            if ($strict) {
                $data['NFD_MAC string is strict normalized for NFD_MAC'] = [true, $s_mac, $f_MAC];
                $data['NFD string is strict normalized for NFD_MAC'] = [true, $s_nfd, $f_MAC];
                $data['NFKD string is strict normalized for NFD_MAC'] = [true, $s_nfkd, $f_MAC];
            } else {
                // Loose implementations return false, to prevent false positives
                $data['NFD_MAC string is loosely not normalized for NFD_MAC'] = [false, $s_mac, $f_MAC];
                $data['NFD string is loosely not normalized for NFD_MAC'] = [false, $s_nfd, $f_MAC];
                $data['NFKD string is loosely not normalized for NFD_MAC'] = [false, $s_nfkd, $f_MAC];
            }
            $data['NFC string is not normalized for NFD_MAC'] = [false, $s_nfc, $f_MAC];
            $data['NFKC string is not normalized for NFD_MAC'] = [false, $s_nfkc, $f_MAC];

            $data['Empty string is normalized for NFD_MAC'] = [true, '', $f_MAC];
        }

        return $data;
    }

    /**
     * @return array
     */
    public function provideCheckNormalizeData()
    {
        $this->setUpDataProvider();

        $forms = $this->unicodeNormalizationForms;

        // déjà 훈쇼™⒜你
        $s_nfc  = hex2bin('64c3a96ac3a020ed9b88ec87bce284a2e2929ce4bda0');
        $s_nfd  = hex2bin('6465cc816a61cc8020e18492e185aee186abe18489e185ade284a2e2929ce4bda0');
        $s_nfkc = hex2bin('64c3a96ac3a020ed9b88ec87bc544d286129e4bda0');
        $s_nfkd = hex2bin('6465cc816a61cc8020e18492e185aee186abe18489e185ad544d286129e4bda0');
        $s_mac  = hex2bin('6465cc816a61cc8020e18492e185aee186abe18489e185ade284a2e2929ce4bda0');

        $f_NONE = NormalizerInterface::NONE;
        $f_NFC = NormalizerInterface::NFC;
        $f_NFD = NormalizerInterface::NFD;
        $f_NFKC = NormalizerInterface::NFKC;
        $f_NFKD = NormalizerInterface::NFKD;
        $f_MAC = NormalizerInterface::NFD_MAC;

        $c = $s_nfc . $s_nfd . $s_nfkc . $s_nfkd;
        $c_plus_m = $c . $s_mac;

        $data = [];

        $data['Combined string is same for NONE'] = [$c, $c, $f_NONE];
        $data['Combined string plus NFD_MAC is same for NONE'] = [$c_plus_m, $c_plus_m, $f_NONE];

        $data['Empty string is same for default'] = ['', '', null];
        $data['Reseverd byte FF is false for default'] = [false, "\xFF", null];

        // if NFC and NFD are supported
        if (empty(array_diff([$f_NFC, $f_NFD], $forms))) {
            $data['NFC string is same for default with NFD string'] = [$s_nfc, $s_nfd, null];
            $data['NFC string is same for NFC with NFD string'] = [$s_nfc, $s_nfd, $f_NFC];
            $data['NFD string is same for NFD with NFC string'] = [$s_nfd, $s_nfc, $f_NFD];
            $data['Special string is same for NFC'] = ["\xcc\x83\xc3\x92\xd5\x9b", "\xcc\x83\xc3\x92\xd5\x9b", $f_NFC];
            $data['Special string is same for NFD'] = ["\xe0\xbe\xb2\xe0\xbd\xb1\xe0\xbe\x80\xe0\xbe\x80", "\xe0\xbd\xb6\xe0\xbe\x81", $f_NFD];
        }
        // if NFC and NFKC are supported
        if (empty(array_diff([$f_NFC, $f_NFKC], $forms))) {
            $data['NFKC string is same for NFKC with NFC string'] = [$s_nfkc, $s_nfc, $f_NFKC];
            $data['NFKC string is same for NFC with NFKC string'] = [$s_nfkc, $s_nfkc, $f_NFC];
        }
        // if NFC and NFKD are supported
        if (empty(array_diff([$f_NFC, $f_NFKD], $forms))) {
            $data['NFKD string is same for NFKD with NFC string'] = [$s_nfkd, $s_nfc, $f_NFKD];
            $data['NFKD string is same for NFC with NFKD string'] = [$s_nfkc, $s_nfkd, $f_NFC];
        }
        // if NFC and NFD_MAC are supported
        if (empty(array_diff([$f_NFC, $f_MAC], $forms))) {
            $data['NFD_MAC string is same for NFD_MAC with NFC string'] = [$s_mac, $s_nfc, $f_MAC];
            $data['NFC string is same for NFC with NFD_MAC string'] = [$s_nfc, $s_mac, $f_NFC];
        }
        // if NFD and NFKC are supported
        if (empty(array_diff([$f_NFD, $f_NFKC], $forms))) {
            $data['NFKC string is same for NFKC with NFD string'] = [$s_nfkc, $s_nfd, $f_NFKC];
            $data['NFKD string is same for NFD with NFKC string'] = [$s_nfkd, $s_nfkc, $f_NFD];
        }
        // if NFD and NFKD are supported
        if (empty(array_diff([$f_NFD, $f_NFKD], $forms))) {
            $data['NFKD string is same for NFKD with NFD string'] = [$s_nfkd, $s_nfd, $f_NFKD];
            $data['NFKD string is same for NFD with NFKD string'] = [$s_nfkd, $s_nfkd, $f_NFD];
        }
        // if NFKC and NFKD are supported
        if (empty(array_diff([$f_NFKC, $f_NFKD], $forms))) {
            $data['NFKD string is same for NFKD with NFKC string'] = [$s_nfkd, $s_nfkc, $f_NFKD];
            $data['NFKC string is same for NFKC with NFKD string'] = [$s_nfkc, $s_nfkd, $f_NFKC];
        }
        // if NFC, NFD, NFKC and NFKD are supported
        if (empty(array_diff([$f_NFC, $f_NFD, $f_NFKC, $f_NFKD], $forms))) {
            $data['Combined string is same for NFC'] = [$s_nfc.$s_nfc.$s_nfkc.$s_nfkc, $c, $f_NFC];
            $data['Combined string is same for NFD'] = [$s_nfd.$s_nfd.$s_nfkd.$s_nfkd, $c, $f_NFD];
            $data['Combined string is same for NFKC'] = [$s_nfkc.$s_nfkc.$s_nfkc.$s_nfkc, $c, $f_NFKC];
            $data['Combined string is same for NFKD'] = [$s_nfkd.$s_nfkd.$s_nfkd.$s_nfkd, $c, $f_NFKD];
            $data['Combined string is false for invalid form'] = [false, $c, -1];
        }
        // if NFC, NFD, NFKC, NFKD and NFD_MAC are supported
        if (empty(array_diff([$f_NFC, $f_NFD, $f_NFKC, $f_NFKD, $f_MAC], $forms))) {
            $data['Combined plus NFD_MAC string is same for NFC'] = [$s_nfc.$s_nfc.$s_nfkc.$s_nfkc.$s_nfc, $c_plus_m, $f_NFC];
            $data['Combined plus NFD_MAC string is same for NFD'] = [$s_nfd.$s_nfd.$s_nfkd.$s_nfkd.$s_nfd, $c_plus_m, $f_NFD];
            $data['Combined plus NFD_MAC string is same for NFKC'] = [$s_nfkc.$s_nfkc.$s_nfkc.$s_nfkc.$s_nfkc, $c_plus_m, $f_NFKC];
            $data['Combined plus NFD_MAC string is same for NFKD'] = [$s_nfkd.$s_nfkd.$s_nfkd.$s_nfkd.$s_nfkd, $c_plus_m, $f_NFKD];
            $data['Combined plus NFD_MAC string is same for NFD_MAC'] = [$s_mac.$s_mac.$s_nfkd.$s_nfkd.$s_mac, $c_plus_m, $f_MAC];
            $data['Combined plus NFD_MAC string is false for invalid form'] = [false, $c_plus_m, -1];
        }

        return $data;
    }

    /**
     * @return array
     */
    public function provideCheckNormalizeConformanceData() {
        static $iterators;
        $this->setUpDataProvider();
        $data = [];
        foreach(['6.3.0', '7.0.0', '8.0.0', '9.0.0', '10.0.0'] as $unicodeVersion) {
            if (version_compare($this->unicodeConformanceLevel, $unicodeVersion, '>=')) {
                foreach($this->unicodeNormalizationForms as $form) {
                    $caption = 'unicode version %s with normalization form %s (%s)'; 
                    switch($form) {
                        case NormalizerInterface::NONE:
                            $caption = sprintf($caption, $unicodeVersion, $form, 'NONE');
                            break;
                        case NormalizerInterface::NFC:
                            $caption = sprintf($caption, $unicodeVersion, $form, 'NFC');
                            break;
                        case NormalizerInterface::NFD:
                            $caption = sprintf($caption, $unicodeVersion, $form, 'NFD');
                            break;
                        case NormalizerInterface::NFKC:
                            $caption = sprintf($caption, $unicodeVersion, $form, 'NFKC');
                            break;
                        case NormalizerInterface::NFKD:
                            $caption = sprintf($caption, $unicodeVersion, $form, 'NFKD');
                            break;
                        case NormalizerInterface::NFD_MAC:
                            $caption = sprintf($caption, $unicodeVersion, $form, 'NFD_MAC');
                            break;
                    }
                    if (!isset($iterators[$unicodeVersion])) {
                        $iterators[$unicodeVersion] = new UnicodeNormalizationTestReader($unicodeVersion);
                    }
                    $data[$caption] = [$unicodeVersion, $form, $iterators[$unicodeVersion]];
                }
            }
        }
        if (empty($data)) {
            $this->markTestSkipped(sprintf(
                'Skipped test as "%s" is not conform to any unicode version.',
                $this->implementationClass
            ));
        }
        return $data;
    }

    /**
     * @param string $unicodeVersion
     * @param integer $form
     * @param integer $lineNumber
     * @param string $comment
     * @param array $codes
     * @return \Generator
     */
    protected function checkNormalizeConformanceIterator(
        $unicodeVersion, $form, $lineNumber, $comment, array $codes)
    {

            //$f_NONE = NormalizerInterface::NONE;
        $f_NFC = NormalizerInterface::NFC;
        $f_NFD = NormalizerInterface::NFD;
        $f_NFKC = NormalizerInterface::NFKC;
        $f_NFKD = NormalizerInterface::NFKD;
        $f_MAC = NormalizerInterface::NFD_MAC;

        $validForMac = preg_match('/^(EFBFBD)+$/', bin2hex($codes[5]));

        if ($form === $f_NFC) {
            $message = sprintf(
                'Normalize to NFC for version %s line %s codepoint %%s: %s',
                $unicodeVersion, $lineNumber, $comment
            );
            yield sprintf($message, '1 (RAW)') => [$codes[1], $codes[0]];
            yield sprintf($message, '2 (NFC)') => [$codes[1], $codes[1]];
            yield sprintf($message, '3 (NFD)') => [$codes[1], $codes[2]];
            yield sprintf($message, '4 (NFKC)') => [$codes[3], $codes[3]];
            yield sprintf($message, '5 (NFKD)') => [$codes[3], $codes[4]];
            if ($validForMac) {
                yield sprintf($message, '6 (NFD_MAC)') => [$codes[1], $codes[5]];
            }
        }

        if ($form === $f_NFD) {
            $message = sprintf(
                'Normalize to NFD for version %s line %s codepoint %%s: %s',
                $unicodeVersion, $lineNumber, $comment
            );
            yield sprintf($message, '1 (RAW)') => [$codes[2], $codes[0]];
            yield sprintf($message, '2 (NFC)') => [$codes[2], $codes[1]];
            yield sprintf($message, '3 (NFD)') => [$codes[2], $codes[2]];
            yield sprintf($message, '4 (NFKC)') => [$codes[4], $codes[3]];
            yield sprintf($message, '5 (NFKD)') => [$codes[4], $codes[4]];
            if ($validForMac) {
                yield sprintf($message, '6 (NFD_MAC)') => [$codes[2], $codes[5]];
            }
        }

        if ($form === $f_NFKC) {
            $message = sprintf(
                'Normalize to NFKC for version %s line %s codepoint %%s: %s',
                $unicodeVersion, $lineNumber, $comment
            );
            yield sprintf($message, '1 (RAW)') => [$codes[3], $codes[0]];
            yield sprintf($message, '2 (NFC)') => [$codes[3], $codes[1]];
            yield sprintf($message, '3 (NFD)') => [$codes[3], $codes[2]];
            yield sprintf($message, '4 (NFKC)') => [$codes[3], $codes[3]];
            yield sprintf($message, '5 (NFKD)') => [$codes[3], $codes[4]];
            if ($validForMac) {
                yield sprintf($message, '6 (NFD_MAC)') => [$codes[3], $codes[5]];
            }
        }

        if ($form === $f_NFKD) {
            $message = sprintf(
                'Normalize to NFKD for version %s line %s codepoint %%s: %s',
                $unicodeVersion, $lineNumber, $comment
            );
            yield sprintf($message, '1 (RAW)') => [$codes[4], $codes[0]];
            yield sprintf($message, '2 (NFC)') => [$codes[4], $codes[1]];
            yield sprintf($message, '3 (NFD)') => [$codes[4], $codes[2]];
            yield sprintf($message, '4 (NFKC)') => [$codes[4], $codes[3]];
            yield sprintf($message, '5 (NFKD)') => [$codes[4], $codes[4]];
            if ($validForMac) {
                yield sprintf($message, '6 (NFD_MAC)') => [$codes[4], $codes[5]];
            }
        }

        if ($form === $f_MAC) {
            $message = sprintf(
                'Normalize to NFD_MAC for version %s line %s codepoint %%s: %s',
                $unicodeVersion, $lineNumber, $comment
            );
            yield sprintf($message, '1 (RAW)') => [$codes[5], $codes[0]];
            yield sprintf($message, '2 (NFC)') => [$codes[5], $codes[1]];
            yield sprintf($message, '3 (NFD)') => [$codes[5], $codes[2]];

            if ($validForMac) {
                yield sprintf($message, '4 (NFKC)') => [$codes[3], $codes[3]];
                yield sprintf($message, '5 (NFKD)') => [$codes[4], $codes[4]];
                yield sprintf($message, '6 (NFD_MAC)') => [$codes[5], $codes[5]];
            }
        }
    }

    /**
     * @return void
     */
    protected function acquireImplementationConstants()
    {
        $this->implementationIsStrict = constant($this->implementationClass . '::IMPLEMENTATION_IS_STRICT');
        $this->unicodeConformanceLevel = constant($this->implementationClass . '::UNICODE_CONFORMANCE_LEVEL');
        $this->unicodeNormalizationForms = constant($this->implementationClass . '::UNICODE_NORMALIZATION_FORMS');
    }

    /**
     * @return boolean
     */
    protected function implementationIsAvailable()
    {
        return class_exists($this->implementationClass, true) &&
               call_user_func($this->implementationClass .'::isAvailable');
    }

    /**
     * @return void
     */
    protected function skipTestForUnavailableImplementation()
    {
        $this->markTestSkipped(sprintf(
            'Skipped test as "%s" is not available.',
            $this->implementationClass
        ));
    }
}
