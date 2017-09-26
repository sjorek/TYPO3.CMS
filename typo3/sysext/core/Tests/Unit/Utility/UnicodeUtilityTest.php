<?php
namespace TYPO3\CMS\Core\Tests\Unit\Charset;

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

// We must not import it here, to make the setup work!
// use TYPO3\CMS\Core\Charset\UnicodeNormalizer;
use TYPO3\CMS\Core\Charset\Unicode\CustomNormalizer;
use TYPO3\CMS\Core\Charset\Unicode\IntlNormalizer;
use TYPO3\CMS\Core\Charset\Unicode\MacNormalizer;
use TYPO3\CMS\Core\Charset\Unicode\NormalizerInterface;
use TYPO3\CMS\Core\Charset\Unicode\PatchworkNormalizer;
use TYPO3\CMS\Core\Charset\Unicode\SymfonyNormalizer;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
// We must not import it here, to make the setup work!
// use TYPO3\CMS\Core\Utility\UnicodeUtility;
use org\bovigo\vfs;

/**
 * Testcase for \TYPO3\CMS\Core\Charset\UnicodeNormalizer
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class UnicodeUtilityTest extends \TYPO3\TestingFramework\Core\Unit\UnitTestCase
{

    /**
     * This method must be called by any dataProvider before continues its execution.
     */
    protected function setUpDataProvider() {
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizer'])) {
            return;
        }

        if(MacNormalizer::isAvailable()) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizer'] = 'mac';
        } elseif(IntlNormalizer::isAvailable()) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizer'] = 'intl';
        } elseif(PatchworkNormalizer::isAvailable()) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizer'] = 'patchwork';
        } elseif(SymfonyNormalizer::isAvailable()) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizer'] = 'symfony';
        } elseif(CustomNormalizer::isAvailable()) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizer'] = 'custom';
        }

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizationForm'] = NormalizerInterface::NONE;
    }

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp()
    {
        $this->setUpDataProvider();
    }

    // ///////////////////////////////////////
    // Tests concerning filtered utf-8 string
    // ///////////////////////////////////////

    public function provideCheckFilterUtf8StringData()
    {
        $this->setUpDataProvider();

        // é
        $iso_8859_1 = hex2bin('e9');
        // é
        $utf8_nfc = hex2bin('c3a9');
        // é
        $utf8_nfd = hex2bin('65cc81');
        // é
        $utf8_nfd_without_leading_combinator = substr($utf8_nfd, 1);
        // Test https://bugs.php.net/65732
        $bugs_65732 = "\n\r" . $utf8_nfc . "\n\r";
        // A number guaranteed to be random as specified by RFC 1149.5
        $number = 4;

        $f_NONE = NormalizerInterface::NONE;
        $f_NFC = NormalizerInterface::NFC;

        $available = \TYPO3\CMS\Core\Charset\UnicodeNormalizer::UNICODE_NORMALIZATION_FORMS;
        $leading_combinator = \TYPO3\CMS\Core\Utility\UnicodeUtility::LEADING_COMBINATOR;
        $data = [
            'ISO-8859-1 string is same as UTF8-NFC string without normalization' => 
                [$utf8_nfc, $iso_8859_1, $f_NONE, null],
            'UTF8-NFC string is same as UTF8-NFC string without normalization' =>
                [$utf8_nfc, $utf8_nfc, $f_NONE, null],
            'test bug https://bugs.php.net/65732 without normalization' =>
                [$bugs_65732, $bugs_65732, $f_NONE, null],
            'number stays a number without normalization' =>
                [$number, $number, $f_NONE, null],
            'UTF8-NFD string without leading combinator gets a leading combinator without normalization' =>
                [
                    $leading_combinator . $utf8_nfd_without_leading_combinator,
                    $utf8_nfd_without_leading_combinator,
                    $f_NONE,
                    null
                ],
        ];

        if (in_array($f_NFC, $available, true)) {
            $data['UTF8-NFC string is same as UTF8-NFC string for NFC normalization'] = 
                [$utf8_nfc, $utf8_nfc, $f_NFC, null];
            $data['test bug https://bugs.php.net/65732 for NFC normalization'] =
                [$bugs_65732, $bugs_65732, $f_NFC, null];
            $data['number stays a number for NFC normalization'] =
                [$number, $number, $f_NFC, null];
            $data['UTF8-NFD string is same as UTF8-NFC string for NFC normalization'] =
                [$utf8_nfc, $utf8_nfd, $f_NFC, null];
            $data['UTF8-NFD string without leading combinator gets a leading combinator for NFC normalization'] =
                [
                    $leading_combinator . $utf8_nfd_without_leading_combinator,
                    $utf8_nfd_without_leading_combinator,
                    $f_NFC,
                    null
                ];
        }

        return $data;
    }

    /**
     * Check if unicode-normalization works for the provided types of strings
     *
     * @test
     * @dataProvider provideCheckFilterUtf8StringData
     *
     * @param boolean $expectedResult
     * @param string $testString
     * @param integer $normalizationForm
     * @return void
     * @link http://forge.typo3.org/issues/57695
     */
    public function checkFilterUtf8String($expectedResult, $testString, $normalizationForm, $charset)
    {
        $actualResult = \TYPO3\CMS\Core\Utility\UnicodeUtility::filterUtf8String(
            $testString, $normalizationForm, $charset
        );
        $this->assertSame(
            sprintf('%s [%s]', $expectedResult, bin2hex($expectedResult)),
            sprintf('%s [%s]', $actualResult, bin2hex($actualResult))
        );
    }

    // /////////////////////////////////////////////
    // Tests concerning is well-formed utf-8 string
    // /////////////////////////////////////////////

    public function provideCheckStringIsWellFormedUtf8Data()
    {
        $this->setUpDataProvider();

        // é
        $iso_8859_1 = hex2bin('e9');
        // é
        $utf8_nfc = hex2bin('c3a9');
        // é
        $utf8_nfd = hex2bin('65cc81');
        // é
        $utf8_nfd_without_leading_combinator = substr($utf8_nfd, 1);
        // Test https://bugs.php.net/65732
        $bugs_65732 = "\n\r" . $utf8_nfc . "\n\r";
        // A number guaranteed to be random as specified by RFC 1149.5
        $number = 4;

        $f_NONE = NormalizerInterface::NONE;
        $f_NFC = NormalizerInterface::NFC;

        $available = \TYPO3\CMS\Core\Charset\UnicodeNormalizer::UNICODE_NORMALIZATION_FORMS;
        $data = [
            'ISO-8859-1 string is not well-formed UTF8 without normalization' =>
                [false, $iso_8859_1, $f_NONE, null],
            'UTF8-NFC string is well-formed UTF8 string without normalization' =>
                [true, $utf8_nfc, $f_NONE, null],
            'test bug https://bugs.php.net/65732 without normalization' =>
                [true, $bugs_65732, $f_NONE, null],
            'number is well-formed UTF8 without normalization' =>
                [true, $number, $f_NONE, null],
            'UTF8-NFD string without leading combinator not well-formed UTF8 without normalization' =>
                [
                    false,
                    $utf8_nfd_without_leading_combinator,
                    $f_NONE,
                    null
                ],
        ];
        
        if (in_array($f_NFC, $available, true)) {
            $data['UTF8-NFC string is well-formed UTF8 for NFC normalization'] =
                [true, $utf8_nfc, $f_NFC, null];
            $data['test bug https://bugs.php.net/65732 for NFC normalization'] =
                [true, $bugs_65732, $f_NFC, null];
            $data['number is well-formed UTF8 for NFC normalization'] =
                [true, $number, $f_NFC, null];
            $data['UTF8-NFD string is not well-formed UTF8 for NFC normalization'] =
                [false, $utf8_nfd, $f_NFC, null];
            $data['UTF8-NFD string without leading combinator is not well-formed UTF8 for NFC normalization'] =
                [
                    false,
                    $utf8_nfd_without_leading_combinator,
                    $f_NFC,
                    null
                ];
        }
        
        return $data;
    }

    /**
     * Check if unicode-normalization works for the provided types of strings
     *
     * @test
     * @dataProvider provideCheckStringIsWellFormedUtf8Data
     *
     * @param boolean $expectedResult
     * @param string $testString
     * @param integer $normalizationForm
     * @param string $charset
     * @return void
     * @link http://forge.typo3.org/issues/57695
     */
    public function checkStringIsWellFormedUtf8($expectedResult, $testString, $normalizationForm, $charset)
    {
        $actualResult = \TYPO3\CMS\Core\Utility\UnicodeUtility::stringIsWellFormedUtf8(
            $testString, $normalizationForm, $charset
        );
        if ($expectedResult) {
            $this->assertTrue($actualResult);
        } else {
            $this->assertFalse($actualResult);
        }
    }

    // ////////////////////////////////////
    // Tests concerning filtered utf-8 uri
    // ////////////////////////////////////

    public function provideCheckFilterUtf8RequestUriData()
    {
        $this->setUpDataProvider();

        // é
        $iso_8859_1 = hex2bin('e9');
        // é
        $utf8_nfc = hex2bin('c3a9');
        // é
        $utf8_nfd = hex2bin('65cc81');
        // é
        $utf8_nfd_without_leading_combinator = substr($utf8_nfd, 1);

        $f_NONE = NormalizerInterface::NONE;
        $f_NFC = NormalizerInterface::NFC;

        $available = \TYPO3\CMS\Core\Charset\UnicodeNormalizer::UNICODE_NORMALIZATION_FORMS;
        $leading_combinator = \TYPO3\CMS\Core\Utility\UnicodeUtility::LEADING_COMBINATOR;
        $data = [
            'urlencoded ISO-8859-1 uri is same as UTF8-NFC uri without normalization' =>
                [urlencode($utf8_nfc), urlencode($iso_8859_1), $f_NONE, null],
            'raw ISO-8859-1 uri is same as UTF8-NFC uri without normalization' =>
                [urlencode($utf8_nfc), $iso_8859_1, $f_NONE, null],
            'urlencoded UTF8-NFC uri is same as UTF8-NFC uri without normalization' =>
                [urlencode($utf8_nfc), urlencode($utf8_nfc), $f_NONE, null],
            'raw UTF8-NFC uri is same as UTF8-NFC uri without normalization' =>
                [urlencode($utf8_nfc), $utf8_nfc, $f_NONE, null],
            'urlencoded UTF8-NFD uri without leading combinator gets a leading combinator without normalization' =>
                [
                    urlencode($leading_combinator . $utf8_nfd_without_leading_combinator),
                    urlencode($utf8_nfd_without_leading_combinator),
                    $f_NONE,
                    null
                ],
            'raw UTF8-NFD uri without leading combinator gets a leading combinator without normalization' =>
                [
                    urlencode($leading_combinator . $utf8_nfd_without_leading_combinator),
                    $utf8_nfd_without_leading_combinator,
                    $f_NONE,
                    null
                ],
        ];

        if (in_array($f_NFC, $available, true)) {
            $data['urlencoded UTF8-NFC uri is same as UTF8-NFC uri for NFC normalization'] =
                [urlencode($utf8_nfc), urlencode($utf8_nfc), $f_NFC, null];
            $data['raw UTF8-NFC uri is same as UTF8-NFC uri for NFC normalization'] =
                [urlencode($utf8_nfc), $utf8_nfc, $f_NFC, null];
            $data['urlencoded UTF8-NFD uri is same as UTF8-NFC uri for NFC normalization'] =
                [urlencode($utf8_nfc), urlencode($utf8_nfd), $f_NFC, null];
            $data['raw UTF8-NFD uri is same as UTF8-NFC uri for NFC normalization'] =
                [urlencode($utf8_nfc), $utf8_nfd, $f_NFC, null];
            $data['urlencoded UTF8-NFD uri without leading combinator gets a leading combinator for NFC normalization'] =
                [
                    urlencode($leading_combinator . $utf8_nfd_without_leading_combinator),
                    urlencode($utf8_nfd_without_leading_combinator),
                    $f_NFC,
                    null
                ];
            $data['raw UTF8-NFD uri without leading combinator gets a leading combinator for NFC normalization'] =
                [
                    urlencode($leading_combinator . $utf8_nfd_without_leading_combinator),
                    $utf8_nfd_without_leading_combinator,
                    $f_NFC,
                    null
                ];
        }

        return $data;
    }
    
    /**
     * Check if unicode-normalization works for the provided types of uris
     *
     * @test
     * @dataProvider provideCheckFilterUtf8RequestUriData
     *
     * @param boolean $expectedResult
     * @param string $testUri
     * @param integer $normalizationForm
     * @return void
     * @link http://forge.typo3.org/issues/57695
     */
    public function checkFilterUtf8RequestUri($expectedResult, $testUri, $normalizationForm, $charset)
    {
        $actualResult = \TYPO3\CMS\Core\Utility\UnicodeUtility::filterUtf8RequestUri(
            $testUri, $normalizationForm, $charset
        );
        $this->assertSame($expectedResult, $actualResult);
    }

    // /////////////////////////////////
    // Tests concerning valid utf-8 uri
    // /////////////////////////////////

    public function provideCheckRequestUriIsWellFormedUtf8Data()
    {
        $this->setUpDataProvider();

        // é
        $iso_8859_1 = hex2bin('e9');
        // é
        $utf8_nfc = hex2bin('c3a9');
        // é
        $utf8_nfd = hex2bin('65cc81');
        // é
        $utf8_nfd_without_leading_combinator = substr($utf8_nfd, 1);

        $f_NONE = NormalizerInterface::NONE;
        $f_NFC = NormalizerInterface::NFC;

        $available = \TYPO3\CMS\Core\Charset\UnicodeNormalizer::UNICODE_NORMALIZATION_FORMS;
        $data = [
            'urlencoded ISO-8859-1 uri is not a valid UTF8-NFC uri without normalization' =>
                [false, urlencode($iso_8859_1), $f_NONE, null],
            'raw ISO-8859-1 uri is is not a valid UTF8-NFC uri without normalization' =>
                [false, $iso_8859_1, $f_NONE, null],
            'urlencoded UTF8-NFC uri is a valid UTF8-NFC uri without normalization' =>
                [true, urlencode($utf8_nfc), $f_NONE, null],
            'raw UTF8-NFC uri is not a valid UTF8-NFC uri without normalization' =>
                [false, $utf8_nfc, $f_NONE, null],
            'urlencoded UTF8-NFD uri without leading combinator is not a valid uri without normalization' =>
                [
                    false,
                    urlencode($utf8_nfd_without_leading_combinator),
                    $f_NONE,
                    null
                ],
            'raw UTF8-NFD uri without leading combinator is not a valid uri without normalization' =>
            [
                false,
                $utf8_nfd_without_leading_combinator,
                $f_NONE,
                null
            ],
        ];

        if (in_array($f_NFC, $available, true)) {
            $data['urlencoded UTF8-NFC uri is a valid UTF8-NFC uri for NFC normalization'] =
                [true, urlencode($utf8_nfc), $f_NFC, null];
            $data['raw UTF8-NFC uri is not a valid UTF8-NFC uri for NFC normalization'] =
                [false, $utf8_nfc, $f_NFC, null];
            $data['urlencoded UTF8-NFD uri is not a valid UTF8-NFC uri for NFC normalization'] =
                [false, urlencode($utf8_nfd), $f_NFC, null];
            $data['raw UTF8-NFD uri is not a valid UTF8-NFC uri for NFC normalization'] =
                [false, $utf8_nfd, $f_NFC, null];
            $data['urlencoded UTF8-NFD uri without leading combinator is not a valid uri for NFC normalization'] =
                [
                    false,
                    urlencode($utf8_nfd_without_leading_combinator),
                    $f_NFC,
                    null
                ];
            $data['raw UTF8-NFD uri without leading combinator is not a valid uri for NFC normalization'] =
                [
                    false,
                    $utf8_nfd_without_leading_combinator,
                    $f_NFC,
                    null
                ];
        }

        return $data;
    }
    
    /**
     * Check if unicode-normalization works for the provided types of uris
     *
     * @test
     * @dataProvider provideCheckRequestUriIsWellFormedUtf8Data
     *
     * @param boolean $expectedResult
     * @param string $testUri
     * @param integer $normalizationForm
     * @return void
     * @link http://forge.typo3.org/issues/57695
     */
    public function checkRequestUriIsWellFormedUtf8($expectedResult, $testUri, $normalizationForm, $charset)
    {
        $actualResult = \TYPO3\CMS\Core\Utility\UnicodeUtility::requestUriIsWellFormedUtf8(
            $testUri, $normalizationForm, $charset
        );
        if ($expectedResult) {
            $this->assertTrue($actualResult);
        } else {
            $this->assertFalse($actualResult);
        }
    }

    // ///////////////////////////////////////
    // Tests concerning filtered utf-8 string
    // ///////////////////////////////////////

    public function provideCheckFilterUtf8StringsInArraysRecursiveData()
    {
        $data = [];
        foreach($this->provideCheckFilterUtf8StringData() as $caption => $arguments) {
            list($expectedResult, $testString, $normalizationForm, $charset) = $arguments;
            $caption = str_replace(
                ['string', 'number', 'bug'],
                ['string in array', 'number in array', 'bug in array'],
                $caption
            );
            $data[$caption] = [
                $expectedResult,
                [
                    'level1' => [
                        'level2' => $testString
                    ]
                ],
                $normalizationForm,
                $charset,
                'level1/level2'
            ];
        }
        return $data;
    }

    /**
     * Check if unicode-normalization works for the provided types of strings in nested array
     *
     * @test
     * @dataProvider provideCheckFilterUtf8StringsInArraysRecursiveData
     *
     * @param string $excpectedResult
     * @param array $array
     * @param integer $normalization
     * @param string $charset
     * @param string $path
     * @return void
     * @link http://forge.typo3.org/issues/57695
     */
    public function checkFilterUtf8StringsInArraysRecursive(
        $excpectedResult, $array, $normalization, $charset, $path
    ) {
        \TYPO3\CMS\Core\Utility\UnicodeUtility::filterUtf8StringsInArraysRecursive(
            $array, $normalization, $charset
        );
        $this->assertSame($excpectedResult, ArrayUtility::getValueByPath($array, $path));
    }

    // /////////////////////////////////////////
    // Tests concerning utf-8 normalized arrays
    // /////////////////////////////////////////

    public function provideCheckNormalizeUtf8StringsInArraysRecursiveData()
    {
        $this->setUpDataProvider();

        $ascii_dejavu = 'dejavu';
        // fantasy-string: déjàvü
        $nfc_dejavu = hex2bin('64c3a96ac3a076c3bc');
        // the same string as above, but decomposed
        $nfd_dejavu = hex2bin('6465cc816a61cc807675cc88');
        // combination of all three strings from above
        $ascii_nfc_nfd_dejavu = $ascii_dejavu . $nfc_dejavu . $nfd_dejavu;
        // the same as from two above, but already normalized to form C
        $nfc_dejavu_triple = hex2bin('64656a61767564c3a96ac3a076c3bc64c3a96ac3a076c3bc');

        $f_NONE = NormalizerInterface::NONE;
        $f_NFC = NormalizerInterface::NFC;
        $available = \TYPO3\CMS\Core\Charset\UnicodeNormalizer::UNICODE_NORMALIZATION_FORMS;

        $data = [
            'skip normalizing an array with ASCII string without normalization' => [
                $ascii_dejavu,
                [
                    'level1' => [
                        'level2' => $ascii_dejavu
                    ]
                ],
                $f_NONE,
                'level1/level2'
            ],
        ];
        if (in_array($f_NFC, $available, true)) {
            $data['normalize an array with NFD string to NFC'] = [
                $nfc_dejavu,
                [
                    'level1' => [
                        'level2' => $nfd_dejavu
                    ]
                ],
                NormalizerInterface::NFC,
                'level1/level2'
            ];
            $data['normalize an array with wild string-combination to NFC'] = [
                $nfc_dejavu_triple,
                [
                    'level1' => [
                        'level2' => $ascii_nfc_nfd_dejavu
                    ]
                ],
                NormalizerInterface::NFC,
                'level1/level2'
            ];
        }
        return $data;
    }

    /**
     * @test
     * @dataProvider provideCheckNormalizeUtf8StringsInArraysRecursiveData
     *
     * @param string $excpectedResult
     * @param array $array
     * @param integer $normalization
     * @param string $path
     * @return void
     */
    public function checkNormalizeUtf8StringsInArraysRecursive($excpectedResult, $array, $normalization, $path)
    {
        \TYPO3\CMS\Core\Utility\UnicodeUtility::normalizeUtf8StringsInArraysRecursive($array, $normalization);
        $this->assertSame($excpectedResult, ArrayUtility::getValueByPath($array, $path));
    }

    // ////////////////////////////////////////////////////////
    // Tests concerning filtered utf-8 strings in input arrays
    // ////////////////////////////////////////////////////////

    public function provideCheckFilterUtf8StringsInInputArraysRecursiveData()
    {
        $source = $this->provideCheckFilterUtf8StringsInArraysRecursiveData();
        $inputs = array(
            'ENV',
            'GET',
            'POST',
            'FILES',
            'COOKIE',
            'SERVER',
            'REQUEST',
            'SESSION'
        );
        $data = [];
        foreach ($inputs as $input) {
            foreach ($source as $caption => $arguments) {

                $name = sprintf('for global $_%s - %s', $input, $caption);
                $data[$name] = array_merge($arguments, [$input, $input]);

                $name = sprintf('for global $_%s (via ALL) - %s', $input, $caption);
                $data[$name] = array_merge($arguments, ['ALL', $input]);
            }
        }
        return $data;
    }
    
    /**
     * Check if unicode-normalization works for the provided types of strings in nested array
     *
     * @test
     * @dataProvider provideCheckFilterUtf8StringsInInputArraysRecursiveData
     *
     * @param string $excpectedResult
     * @param array $array
     * @param integer $normalization
     * @param string $charset
     * @param string $path
     * @param string $input
     * @param string $global
     * @return void
     * @link http://forge.typo3.org/issues/57695
     */
    public function checkFilterUtf8StringsInInputArraysRecursive(
        $excpectedResult, $array, $normalization, $charset, $path, $input, $global
    ) {
        $GLOBALS['_' . $global] = $array;
        \TYPO3\CMS\Core\Utility\UnicodeUtility::filterUtf8StringsInInputArraysRecursive(
            $input, $normalization, $charset
        );
        $this->assertSame($excpectedResult, ArrayUtility::getValueByPath($GLOBALS['_' . $global], $path));
    }

    // ////////////////////////////////////////////////////////
    // Tests concerning normalized utf-8 string in input array
    // ////////////////////////////////////////////////////////

    public function provideCheckNormalizeUtf8StringsInInputArraysRecursiveData()
    {
        $source = $this->provideCheckNormalizeUtf8StringsInArraysRecursiveData();
        $inputs = array(
            'ENV',
            'GET',
            'POST',
            'FILES',
            'COOKIE',
            'SERVER',
            'REQUEST',
            'SESSION'
        );
        $data = [];
        foreach ($inputs as $input) {
            foreach ($source as $caption => $arguments) {

                $name = sprintf('for global $_%s - %s', $input, $caption);
                $data[$name] = array_merge($arguments, [$input, $input]);

                $name = sprintf('for global $_%s (via ALL) - %s', $input, $caption);
                $data[$name] = array_merge($arguments, ['ALL', $input]);
            }
        }
        return $data;
    }

    /**
     * @test
     * @dataProvider provideCheckNormalizeUtf8StringsInInputArraysRecursiveData
     *
     * @param string $excpectedResult
     * @param array $array
     * @param integer $normalization
     * @param string $path
     * @param string $input
     * @param string $global
     * @return void
     */
    public function checkNormalizeUtf8StringsInInputArraysRecursive(
        $excpectedResult, $array, $normalization, $path, $input, $global
    ) {
        $GLOBALS['_' . $global] = $array;
        \TYPO3\CMS\Core\Utility\UnicodeUtility::normalizeUtf8StringsInInputArraysRecursive($input, $normalization);
        $this->assertSame($excpectedResult, ArrayUtility::getValueByPath($GLOBALS['_' . $global], $path));
    }

    // ///////////////////////////////////////////////////
    // Tests concerning normalization form string parsing
    // ///////////////////////////////////////////////////

    public function provideCheckParseNormalizationFormData()
    {
        $this->setUpDataProvider();

        $data = [];
        $matches = null;

        $reflector  = new \ReflectionMethod(
            \TYPO3\CMS\Core\Utility\UnicodeUtility::class,
            'parseNormalizationForm'
        );
        $docComment = $reflector->getDocComment();

        preg_match_all('/- ([^:]*) *: ([0-9]+), (.*)$/umU', $docComment, $matches, PREG_SET_ORDER);
        foreach($matches as $match) {
            list($_, $name, $form, $alternatives) = $match;
            $name = trim($name);

            $caption = sprintf('%s - parse as string \'%s\'', $name, $form);
            $data[$caption] = [(int) $form, (string) $form];

            $caption = sprintf('%s - parse as integer %s', $name, $form);
            $data[$caption] = [(int) $form, (int) $form];

            foreach(GeneralUtility::trimExplode(',', $alternatives) as $alternative) {
                switch (strtolower($alternative)) {
                    case 'empty':
                        $caption = sprintf('%s - parse as empty string', $name);
                        $data[$caption] = [(int) $form, ''];
                        // no break here - continue foreach loop !
                        continue 2;
                    case 'null':
                        $caption = sprintf('%s - parse as null', $name);
                        $data[$caption] = [(int) $form, null];
                        break;
                    case 'true':
                        $caption = sprintf('%s - parse as boolean true', $name);
                        $data[$caption] = [(int) $form, true];
                        break;
                    case 'false':
                        $caption = sprintf('%s - parse as boolean false', $name);
                        $data[$caption] = [(int) $form, false];
                        break;
                }
                $caption = sprintf('%s - parse as string \'%s\'', $name, $alternative);
                $data[$caption] = [(int) $form, (string) $alternative];
            }
        }
        return $data;
    }

    /**
     * @test
     * @dataProvider provideCheckParseNormalizationFormData
     * 
     * @param integer $expected
     * @param mixed $form
     */
    public function checkParseNormalizationForm($expected, $form)
    {
        $this->assertSame($expected, \TYPO3\CMS\Core\Utility\UnicodeUtility::parseNormalizationForm($form));
    }

    /**
     * @test
     * @expectedException           InvalidArgumentException
     * @expectedExceptionMessage    Invalid unicode normalization form value: nonsense
     * @expectedExceptionCode       1398603947
     */
    public function checkParseNormalizationFormThrowsInvalidArgumentException()
    {
        \TYPO3\CMS\Core\Utility\UnicodeUtility::parseNormalizationForm('nonsense');
    }

    // /////////////////////////////////////////////////
    // Tests concerning unicode filesystem capabilities
    // /////////////////////////////////////////////////

    /**
     * @test
     */
    public function checkDetectUtf8CapabilitiesForPathWithInvalidPath()
    {
        $root = vfs\vfsStream::setup();

        $expected = [
            'locale' => false,
            'shellescape' => false,
            'normalization' => false
        ];

        foreach(['', 'path/is/not/absolute', $root->url() . DIRECTORY_SEPARATOR . 'path-does-not-exist'] as $path)
        {
            $actual = \TYPO3\CMS\Core\Utility\UnicodeUtility::detectUtf8CapabilitiesForPath($path, ['vfs']);
            $this->assertEquals(
                $expected,
                $actual,
                sprintf('Expect no capabilities if %s', $path ?: 'path is empty')
            );
        }

        vfs\vfsStreamWrapper::unregister();
    }

    /**
     * A list of unsupported locales
     *
     * @var array
     */
    const UNSUPPORTED_LOCALES = ['C', 'POSIX'];

    /**
     * @test
     */
    public function checkDetectUtf8CapabilitiesForPathWithUnsupportedLocaleAndCharset()
    {
        $root = vfs\vfsStream::setup();

        $expected = [
            'locale' => false,
            'shellescape' => false,
            'normalization' => false
        ];

        $currentLocale = setlocale(LC_CTYPE, 0);
        $locale = setlocale(LC_CTYPE, static::UNSUPPORTED_LOCALES);
        $this->assertTrue($locale !== false, 'could not set locale');
        $this->assertTrue(in_array($locale, static::UNSUPPORTED_LOCALES, true), 'unexpected locale set');

        $currentCharset = ini_get('default_charset');
        $charset = ini_set('default_charset', 'ASCII');
        $this->assertSame($currentCharset, $charset, 'could not set default charset');
        $this->assertSame('ASCII', ini_get('default_charset'), 'unexpected default charset set');

        $actual = \TYPO3\CMS\Core\Utility\UnicodeUtility::detectUtf8CapabilitiesForPath($root->url(), ['vfs']);
        $this->assertEquals(
            $expected,
            $actual,
            'Expect no capabilities for unsupported locale'
        );

        setlocale(LC_CTYPE, $currentLocale);
        ini_set('default_charset', $currentCharset);

        vfs\vfsStreamWrapper::unregister();
    }

    /**
     * A list of unsupported windows locales. On Windows the codepage 28591 refers to ISO-8859-1
     * 
     * @var array
     * @see https://docs.microsoft.com/en-us/cpp/c-runtime-library/locale-names-languages-and-country-region-strings
     * @see https://msdn.microsoft.com/library/windows/desktop/dd317756.aspx
     * @see https://docs.microsoft.com/en-us/cpp/c-runtime-library/code-pages
     * @see https://docs.microsoft.com/en-us/cpp/c-runtime-library/reference/setlocale-wsetlocale
     */
    const UNSUPPORTED_WINDOWS_LOCALES = ['en-US.28591', 'C.28591', '.28591'];

    /**
     * @test
     */
    public function checkDetectUtf8CapabilitiesForPathWithExplicitlyUnsupportedWindowsLocaleAndCharset()
    {
        if (TYPO3_OS !== 'WIN') {
            $this->markTestSkipped('This test can only be run on Microsoft Windows operating system');
            return;
        }

        $root = vfs\vfsStream::setup();

        $expected = [
            'locale' => false,
            'shellescape' => false,
            'normalization' => false
        ];

        $currentLocale = setlocale(LC_CTYPE, 0);
        $locale = setlocale(LC_CTYPE, static::UNSUPPORTED_WINDOWS_LOCALES);
        $this->assertTrue($locale !== false, 'could not set locale');
        $this->assertTrue(in_array($locale, static::UNSUPPORTED_WINDOWS_LOCALES, true), 'unexpected locale set');

        $currentCharset = ini_get('default_charset');
        $charset = ini_set('default_charset', 'ASCII');
        $this->assertSame($currentCharset, $charset, 'could not set default charset');
        $this->assertSame('ASCII', ini_get('default_charset'), 'unexpected default charset set');

        $actual = \TYPO3\CMS\Core\Utility\UnicodeUtility::detectUtf8CapabilitiesForPath($root->url(), ['vfs']);
        $this->assertEquals(
            $expected,
            $actual,
            'Expect no capabilities for unsupported windows locale'
        );

        setlocale(LC_CTYPE, $currentLocale);
        ini_set('default_charset', $currentCharset);

        vfs\vfsStreamWrapper::unregister();
    }

    /**
     * A list of utf-8 capable locales. On Windows the codepage 65001 refers to UTF-8.
     * 
     * @var array
     * @see https://docs.microsoft.com/en-us/cpp/c-runtime-library/locale-names-languages-and-country-region-strings
     * @see https://msdn.microsoft.com/library/windows/desktop/dd317756.aspx
     * @see https://docs.microsoft.com/en-us/cpp/c-runtime-library/code-pages
     * @see https://docs.microsoft.com/en-us/cpp/c-runtime-library/reference/setlocale-wsetlocale
     */
    const UTF8_LOCALES = [
        'en_US.UTF-8', 'en_US.UTF8',
        'en_US.utf-8', 'en_US.utf8',
        'en-US.UTF-8', 'en-US.UTF8',
        'en-US.utf-8', 'en-US.utf8',
        'en-US.65001', 'C.65001',
        'C.UTF-8', 'C.UTF8'];

    /**
     * @test
     */
    public function checkDetectUtf8CapabilitiesForPathWithExistingWorkingDirectory()
    {
        $root = vfs\vfsStream::setup(
            'root',
            null,
            [
                \TYPO3\CMS\Core\Utility\UnicodeUtility::UTF8_FILESYSTEM_CAPABILITY_DETECTION_FOLDER_NAME => []
            ]
        );

        $expected = [
            'locale' => true,
            'shellescape' => true,
            'normalization' => false
        ];

        $currentLocale = setlocale(LC_CTYPE, 0);
        $locale = setlocale(LC_CTYPE, static::UTF8_LOCALES);
        $this->assertTrue($locale !== false, 'could not set locale');
        $this->assertTrue(in_array($locale, static::UTF8_LOCALES, true), 'unexpected locale set');

        $currentCharset = ini_get('default_charset');
        $charset = ini_set('default_charset', 'UTF-8');
        $this->assertSame($currentCharset, $charset, 'could not set default charset');
        $this->assertSame('UTF-8', ini_get('default_charset'), 'unexpected default charset set');

        $actual = \TYPO3\CMS\Core\Utility\UnicodeUtility::detectUtf8CapabilitiesForPath($root->url(), ['vfs']);
        $this->assertEquals(
            $expected,
            $actual,
            'Expect locale- and shellescape-, but no normalization-capabilities for existing detection folder'
        );
        setlocale(LC_CTYPE, $currentLocale);
        ini_set('default_charset', $currentCharset);

        vfs\vfsStreamWrapper::unregister();
    }

    /**
     * @test
     */
    public function checkDetectUtf8CapabilitiesForPath()
    {
        $root = vfs\vfsStream::setup();

        $expected = [
            'locale' => true,
            'shellescape' => true,
            'normalization' => [
                0 => true,
                NormalizerInterface::NONE => true,
                NormalizerInterface::NFD => true,
                NormalizerInterface::NFKD => false,
                NormalizerInterface::NFC => true,
                NormalizerInterface::NFKC => false,
                NormalizerInterface::NFD_MAC => true,
            ]
        ];

        $currentLocale = setlocale(LC_CTYPE, 0);
        $locale = setlocale(LC_CTYPE, static::UTF8_LOCALES);
        $this->assertTrue($locale !== false, 'could not set locale');
        $this->assertTrue(in_array($locale, static::UTF8_LOCALES, true), 'unexpected locale set');

        $currentCharset = ini_get('default_charset');
        $charset = ini_set('default_charset', 'UTF-8');
        $this->assertSame($currentCharset, $charset, 'could not set default charset');
        $this->assertSame('UTF-8', ini_get('default_charset'), 'unexpected default charset set');

        $actual = \TYPO3\CMS\Core\Utility\UnicodeUtility::detectUtf8CapabilitiesForPath($root->url(), ['vfs']);
        $this->assertEquals(
            $expected,
            $actual,
            'Expect locale- and shellescape- and all (supported) normalization-capabilities'
        );
        $this->assertFalse(
            $root->hasChild(
                \TYPO3\CMS\Core\Utility\UnicodeUtility::UTF8_FILESYSTEM_CAPABILITY_DETECTION_FOLDER_NAME
            ),
            'The utf8 filesystem capability detection folder should not exist anymore'
        );
        
        setlocale(LC_CTYPE, $currentLocale);
        ini_set('default_charset', $currentCharset);

        vfs\vfsStreamWrapper::unregister();
    }
}