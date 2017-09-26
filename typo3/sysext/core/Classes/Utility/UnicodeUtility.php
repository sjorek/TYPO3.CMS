<?php
namespace TYPO3\CMS\Core\Utility;

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

use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Charset\UnicodeNormalizer;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 * Class to handle unicode specific functionality
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class UnicodeUtility
{

    /**
     * The string '\xe2\x97\x8c' is equivalent to '◌' a combining character as defined in the glossary
     * linked below. It is meant for internal usage as part of NFC-compatible string-filter methods below.
     *
     * @var string
     * @link http://www.unicode.org/glossary/#combining_character
     */
    const LEADING_COMBINATOR = "\xe2\x97\x8c";

    /**
     * Ensures that given input is a well-formed and normalized UTF-8 string.
     *
     * This implementation has been shamelessly taken from the “patchwork/utf8”
     * package's “Bootup::filterString()”-method and tweaked for our needs.
     *
     * @param string  $input    The string to filter
     * @param integer $form     [optional] normalization form to apply, overriding the default
     * @param string  $charset  [optional] charset to try to convert from, default is ISO-8859-1 
     * @return string
     * @see \Patchwork\Utf8\Bootup::filterString()
     */
    public static function filterUtf8String($input, $form = null, $charset = null)
    {
        // Workaround for https://bugs.php.net/65732 - fixed for PHP 7.0.11 and above.
        if (version_compare(PHP_VERSION, '7.0.11', '<') && false !== strpos($input, "\r")) {
            $input = explode("\r", $input);
            $input = array_map(
                function($string) use($form, $charset){
                    static::filterUtf8String($string, $form, $charset);
                },
                $input
            );
            return implode("\r", $input);
        }

        if (preg_match('/[\x80-\xFF]/', $input) || !preg_match('//u', $input)) {
            /** @var CharsetConverter $charsetConverter */
            $charsetConverter = GeneralUtility::makeInstance(CharsetConverter::class);
            $normalized = $charsetConverter->conv($input, 'utf-8', 'utf-8', false, $form);
            if (isset($normalized[0]) && preg_match('//u', $normalized)) {
                $input = $normalized;
            } else {
                $input = $charsetConverter->conv($input, $charset ?: 'iso-8859-1', 'utf-8', false, $form);
            }
            if ($input[0] >= "\x80" && isset($normalized[0]) && preg_match('/^\p{Mn}/u', $input)) {
                // Prepend leading combining chars for NFC-safe concatenations.
                $input = self::LEADING_COMBINATOR . $input;
            }
        }

        return $input;
    }

    /**
     * Test if given input is a well-formed and normalized UTF-8 string.
     *
     * @param string  $input    The string to check
     * @param integer $form     [optional] normalization form to check against, overriding the default
     * @param string  $charset  [optional] charset to try to convert from, default is ISO-8859-1 
     * @return boolean TRUE if the string is a well-formed and normalized UTF-8 string.
     */
    public static function stringIsWellFormedUtf8($input, $form = null, $charset = null) {
        return $input === self::filterUtf8String($input, $form);
    }

    /**
     * Ensures the URI is well formed UTF-8.
     * When not, assumes ISO-8859-1 and re-encodes the URL to the corresponding UTF-8 encoded equivalent.
     *
     * The implementation is adopted from \Patchwork\Utf8\Bootup::filterRequestUri() and tweaked for our needs.
     *
     * @param string  $uri      The uri to filter
     * @param integer $form     [optional] normalization form to check against, overriding the default
     * @param string  $charset  [optional] charset to try to convert from, default is ISO-8859-1 
     * @return string
     * @see \Patchwork\Utf8\Bootup::filterRequestUri()
     * @todo Feature #57695: Keep this method in sync patchwork's implementation
     * @todo Feature #57695: Figure out why patchwork's implementation assumes Windows-CP1252 as fallback
     */
    public static function filterUtf8RequestUri($uri, $form = null, $charset = null)
    {
        
        // is url empty or is the url-decoded url already valid utf8 ?
        if ($uri === '' || !preg_match('/[\x80-\xFF]/', urldecode($uri))) {
            return $uri;
        }

        // encode all unencoded single-byte characters from 128 to 255
        $uri = preg_replace_callback(
            '/[\x80-\xFF]+/',
            function ($match) {
                return urlencode($match[0]);
            },
            $uri
        );

        /** @var CharsetConverter $charsetConverter */
        $charsetConverter = GeneralUtility::makeInstance(CharsetConverter::class);

        // encode all unencoded multibyte-byte characters from 128 and above
        // for combined characters in NFD we need to prepend the preceding character
        $uri = preg_replace_callback(
            '/(^|.)(?:%[89A-F][0-9A-F])+/i',
            // url-decode -> utf8-encode -> url-encode
            function ($match) use($charsetConverter, $form, $charset) {
                return urlencode(static::filterUtf8String(urldecode($match[0]), $form, $charset));
            },
            $uri
        );

        return $uri;
    }

    /**
     * Test if given uri is properly url-encoded with well-formed and normalized UTF-8.
     *
     * @param string  $uri      The uri to to test
     * @param integer $form     [optional] normalization form to check against, overriding the default
     * @param string  $charset  [optional] charset to try to convert from, default is ISO-8859-1
     * @return boolean TRUE if the string is a well-formed and normalized UTF-8 string.
     */
    public static function requestUriIsWellFormedUtf8($uri, $form = null, $charset = null) {
        return $uri === '' || $uri === self::filterUtf8RequestUri($uri, $form, $charset);
    }

    /**
     * Enforce properly url-encoded with well-formed and normalized UTF-8 and send given response if needed.
     *
     * @param integer $headerCode   [optional] use given http status code for the response, works for 3xx and 4xx
     * @param integer $form         [optional] normalization form to check against, overriding the default
     * @param string  $charset      [optional] charset to try to convert from, default is ISO-8859-1
     * @return void
     */
    public static function enforceUtf8EncodedRequestUri(
        $headerCode = HttpUtility::HTTP_STATUS_404, $form = null, $charset = null
    ) {
        $uri = GeneralUtility::getIndpEnv('REQUEST_URI');
        if ($uri === '') {
            return;
        }

        $normalizedUri = self::filterUtf8RequestUri($uri, $form, $charset);
        if ($uri === $normalizedUri) {
            return;
        }

        if (StringUtility::beginsWith($headerCode, 'HTTP/1.0 4') || // 4xx
            StringUtility::beginsWith($headerCode, 'HTTP/1.1 4') || // 4xx
            StringUtility::beginsWith($headerCode, 'HTTP/2 4'))     // 4xx
        {
            HttpUtility::setResponseCodeAndExit($headerCode);

        } elseif (StringUtility::beginsWith($headerCode, 'HTTP/1.0 3') || // 3xx
                  StringUtility::beginsWith($headerCode, 'HTTP/1.1 3') || // 3xx
                  StringUtility::beginsWith($headerCode, 'HTTP/2 3'))     // 3xx
        {
            $url = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . $normalizedUri;
            HttpUtility::redirect($url, $headerCode);
        }
    }

    /**
     * Ensures all that all strings in the array are well formed and normalized UTF-8.
     *
     * NOTICE: Array is passed by reference!
     *
     * @param array   $array    Input array, possibly multidimensional, passed by reference
     * @param integer $form     [optional] normalization form to check against, overriding the default
     * @param string  $charset  [optional] charset to try to convert from, default is ISO-8859-1
     * @return void
     */
    public static function filterUtf8StringsInArraysRecursive(& $array, $form = null, $charset = 'iso-8859-1')
    {
        $callback = function($string) use($form) {
            return UnicodeUtility::filterUtf8String($string, $form);
        };
        ArrayUtility::processStringsInArrayRecursive($array, $callback, true);
    }

    /**
     * Ensures all that all strings in the array are normalized UTF-8.
     * 
     * NOTICE: Array is passed by reference!
     *
     * @param array   $array    Input array, possibly multidimensional, passed by reference
     * @param integer $form     [optional] normalization form to check against, overriding the default
     * @return void
     */
    public static function normalizeUtf8StringsInArraysRecursive(& $array, $form = null)
    {
        /** @var UnicodeNormalizer $unicodeNormalizer */
        $unicodeNormalizer = GeneralUtility::makeInstance(UnicodeNormalizer::class);
        $callback = function($string) use($unicodeNormalizer, $form) {
            return $unicodeNormalizer->normalizeStringTo($string, $form);
        };
        ArrayUtility::processStringsInArrayRecursive($array, $callback, true);
    }

    /**
     * Ensures all that (user-)inputs are well formed and normalized UTF-8.
     * Supported inputs are $_FILES, $_ENV, $_GET, $_POST, $_COOKIE, $_SERVER, $_SESSION and $_REQUEST.
     *
     * This implementation has been inspired by the contributed “Patchwork-UTF8” project's
     * “Bootup::filterRequestInputs()”-method and tweaked for our needs.
     *
     * @param string  $inputs   'ALL' or comma-separated list of global input-array names without leading "$_"
     * @param integer $form     [optional] normalization form to check against, overriding the default
     * @param string  $charset  [optional] charset to try to convert from, default is ISO-8859-1
     * @param string  $allowed  [optional] A comma-separated list of allowed input-arrays
     * @return void
     * @see \Patchwork\Utf8\Bootup::filterRequestInputs()
     */
    public static function filterUtf8StringsInInputArraysRecursive(
        $inputs = 'ALL', $form = null, $charset = null, $allowed='ALL'
    ) {
        $callback = function($string) use($form, $charset) {
            return static::filterUtf8String($string, $form, $charset);
        };
        ArrayUtility::processStringsInInputArraysRecursive($inputs, $callback, true, $allowed);
    }

    /**
     * Ensures all that (user-)inputs are normalized UTF-8.
     * Supported inputs are $_FILES, $_ENV, $_GET, $_POST, $_COOKIE, $_SERVER, $_SESSION and $_REQUEST.
     *
     * This implementation has been inspired by the contributed “Patchwork-UTF8” project's
     * “Bootup::filterRequestInputs()”-method and tweaked for our needs.
     *
     * @param string  $inputs   'ALL' or comma-separated list of global input-array names without leading "$_"
     * @param integer $form     [optional] normalization form to check against, overriding the default
     * @param string  $allowed  [optional] A comma-separated list of allowed input-arrays
     * @return void
     */
    public static function normalizeUtf8StringsInInputArraysRecursive(
        $inputs = 'ALL', $form = null, $allowed='ALL'
    ) {
        /** @var UnicodeNormalizer $unicodeNormalizer */
        $unicodeNormalizer = GeneralUtility::makeInstance(UnicodeNormalizer::class);
        $callback = function($string) use($unicodeNormalizer, $form) {
            return $unicodeNormalizer->normalizeTo($string, $form);
        };
        ArrayUtility::processStringsInInputArraysRecursive($inputs, $callback, true, $allowed);
    }

    /**
     * Convert the given value to a known normalization-form constant.
     *
     * Supported case-insensitive aliases:
     * <pre>
     * - Disable unicode-normalization     : 0,  false, null, empty
     * - Ignore/skip unicode-normalization : 1,  NONE, true, binary, default, validate
     * - Normalization form D              : 2,  NFD, FORM_D, D, form-d, decompose, collation
     * - Normalization form D (mac)        : 32, NFD_MAC, FORM_D_MAC, D_MAC, form-d-mac, d-mac, mac
     * - Normalization form KD             : 3,  NFKD, FORM_KD, KD, form-kd
     * - Normalization form C              : 4,  NFC, FORM_C, C, form-c, compose, recompose, legacy, html5
     * - Normalization form KC             : 5,  NFKC, FORM_KC, KC, form-kc, matching
     * </pre>
     *
     * Hints:
     * <pre>
     * - The W3C recommends NFC for HTML5 Output.
     * - Mac OS X's HFS+ filesystem uses a NFD variant to store paths. We provide one implementation for this
     *   special variant, but plain NFD works in most cases too. Even if you use something else than NFD or its
     *   variant HFS+ will always use decomposed NFD path-strings if needed.
     * </pre>
     *
     * @param string|integer|boolean|null $value
     * @throws \InvalidArgumentException
     */
    public static function parseNormalizationForm($value)
    {
        $value = trim((string) $value);
        if (in_array(
                $value,
                [
                    '0',
                    (string) UnicodeNormalizer::NONE,
                    (string) UnicodeNormalizer::NFC,
                    (string) UnicodeNormalizer::NFD,
                    (string) UnicodeNormalizer::NFD_MAC,
                    (string) UnicodeNormalizer::NFKC,
                    (string) UnicodeNormalizer::NFKD
                ],
                true
            )
        ) {
            return (int) $value;
        }
        $form = str_replace(
            [
                '-',
                'NF',
                'FORM_',
            ],
            [
                '_',
                '',
                '',
            ],
            strtoupper(trim((string) $value))
        );
        switch ($form) {
            case '':
            case 'NULL':
            case 'FALSE':
                return 0;
            case 'NONE':
            case 'TRUE':
            case 'BINARY':
            case 'DEFAULT':
            case 'VALIDATE':
                return UnicodeNormalizer::NONE;
            case 'D':
            case 'DECOMPOSE':
            case 'COLLATION':
                return UnicodeNormalizer::NFD;
            case 'KD':
                return UnicodeNormalizer::NFKD;
            case 'C':
            case 'COMPOSE':
            case 'RECOMPOSE':
            case 'LEGACY':
            case 'HTML5':
                return UnicodeNormalizer::NFC;
            case 'KC':
            case 'MATCHING':
                return UnicodeNormalizer::NFKC;
            case 'D_MAC':
            case 'MAC':
                return UnicodeNormalizer::NFD_MAC;
        }
        throw new \InvalidArgumentException(
            sprintf('Invalid unicode normalization form value: %s', $value), 1398603947
        );
    }

    /**
     * List of mapping unicode-normalization constants to filenames in corresponding unicode-normalizations
     * @var string[]|boolean[]
     */
    const FILESYSTEM_MODES = [
        // Raw binary data (not normalized, and not even a mix different normalizations):
        //
        //  php > $fileName = "ÖéöĄĆŻĘĆćążęóΘЩשݐซဤ⒜あ겫你你♥︎☺︎.txt";
        //  php > echo bin2hex($fileName);
        //  php > echo bin2hex(
        //         "\u{00D6}" // Ö
        //       . "\u{00E9}" // é - reserved character in Apple™'s HFS+ (OS X Extended) filesystem
        //       . "\u{00F6}" // ö
        //       . "\u{0104}" // Ą
        //       . "\u{0106}" // Ć
        //       . "\u{017B}" // Ż
        //       . "\u{0118}" // Ę
        //       . "\u{0106}" // Ć
        //       . "\u{0107}" // ć
        //       . "\u{0105}" // ą
        //       . "\u{017C}" // ż
        //       . "\u{0119}" // ę
        //       . "\u{00F3}" // ó
        //       . "\u{0398}" // Θ
        //       . "\u{0429}" // Щ
        //       . "\u{05E9}" // ש
        //       . "\u{0750}" // ݐ
        //       . "\u{0E0B}" // ซ︎
        //       . "\u{1024}" // ဤ
        //       . "\u{249C}" // ⒜  - special treatment in Apple™'s filename NFD normalization
        //       . "\u{3042}" // あ
        //       . "\u{ACAB}" // 겫
        //       . "\u{4F60}" // 你 - same as below, but in NFC
        //       . "\u{2F804}" // 你 - neither C, D, KC or KD + special in Apple™'s filename NFD normalization
        //       . "\u{2665}\u{FE0E}" // ♥
        //       . "\u{263A}\u{FE0E}" // ☺
        //       . ".txt"
        //  );
        // Many zeros to align with stuff below … turns into a single 0 
        00000000000000000000000 => 'c396c3a9c3b6c484c486c5bbc498c486c487c485c5bcc499c3b3ce98d0a9d7a9dd90e0b88be180a4e2929ce38182eab2abe4bda0f0afa084e299a5efb88ee298baefb88e2e747874',

        // not normalized $fileName from above partially in NFC, partially in NFD and with special treatments
        //
        //  php > echo bin2hex(mb_substr($fileName, 0, 4) .
        //                     Normalizer::normalize(mb_substr($fileName, 4, 4), Normalizer::NFC).
        //                     Normalizer::normalize(mb_substr($fileName, 8, 4), Normalizer::NFD).
        //                     mb_substr($fileName, 12));
        //
        UnicodeNormalizer::NONE => 'c396c3a9c3b6c484c486c5bbc498c48663cc8161cca87acc8765cca8c3b3ce98d0a9d7a9dd90e0b88be180a4e2929ce38182eab2abe4bda0f0afa084e299a5efb88ee298baefb88e2e747874',

        // NFD-normalized variant of $fileName from above
        //  php > echo bin2hex(Normalizer::normalize($fileName, Normalizer::NFD));
        UnicodeNormalizer::NFD  => '4fcc8865cc816fcc8841cca843cc815acc8745cca843cc8163cc8161cca87acc8765cca86fcc81ce98d0a9d7a9dd90e0b88be180a4e2929ce38182e18480e185a7e186aae4bda0e4bda0e299a5efb88ee298baefb88e2e747874',
        // look right for difference to NFD_MAC =>                                                                                                                                ^^^^^^

        // NFD_MAC-normalized variant of $fileName from above, differing from NFD in 3 bytes 
        //  php > echo bin2hex(iconv('utf-8', 'utf-8-mac', $fileName));
        UnicodeNormalizer::NFD_MAC  => '4fcc8865cc816fcc8841cca843cc815acc8745cca843cc8163cc8161cca87acc8765cca86fcc81ce98d0a9d7a9dd90e0b88be180a4e2929ce38182e18480e185a7e186aae4bda0efbfbde299a5efb88ee298baefb88e2e747874',
        // look right for difference to plain NFD =>                                                                                                                              ^^^^^^
        
        // NFC-normalized variant of $fileName from above
        //  php > echo bin2hex(Normalizer::normalize($fileName, Normalizer::NFC));
        UnicodeNormalizer::NFC  => 'c396c3a9c3b6c484c486c5bbc498c486c487c485c5bcc499c3b3ce98d0a9d7a9dd90e0b88be180a4e2929ce38182eab2abe4bda0e4bda0e299a5efb88ee298baefb88e2e747874',

        // Not supported for file names
        UnicodeNormalizer::NFKD => false,
        UnicodeNormalizer::NFKC => false
    ];

    const UTF8_FILESYSTEM_CAPABILITY_DETECTION_FOLDER_NAME = '.utf8-filesystem-capabilities-detection';

    /**
     * Detect utf8-capabilities for given absolute path.
     * 
     * @param string $absolutePath
     * @param array|null $_allowedSchemes
     * @return boolean[]|array[]
     * @todo FEATURE #57695: Add more documentation to UnicodeUtility::detectUtf8CapabilitiesForPath()
     */
    public static function detectUtf8CapabilitiesForPath($absolutePath, $_allowedSchemes = null)
    {
        $capabilities = [
            'locale' => false,
            'shellescape' => false,
            'normalization' => false
        ];

        if ($absolutePath === '' || !self::isValidPath($absolutePath, $_allowedSchemes) || !is_dir($absolutePath))
        {
            return $capabilities;
        }

        $currentLocale = setlocale(LC_CTYPE, 0);
        if (strpos(strtolower(str_replace('-', '', $currentLocale)), '.utf8') !== false) {
            $capabilities['locale'] = true;

        // On Windows an empty locale value uses the regional settings from the Control Panel, we assume to be ok
        // On Windows the codepage 65001 refers to UTF-8
        // @see https://docs.microsoft.com/en-us/cpp/c-runtime-library/locale-names-languages-and-country-region-strings
        // @see https://docs.microsoft.com/en-us/cpp/c-runtime-library/code-pages
        // @see https://docs.microsoft.com/en-us/cpp/c-runtime-library/reference/setlocale-wsetlocale
        // @see https://msdn.microsoft.com/library/windows/desktop/dd317756.aspx
        } elseif (TYPO3_OS === 'WIN' && ($currentLocale === '' || strpos($currentLocale, '.65001') !== false)) {
            $capabilities['locale'] = true;
        }

        $fileName = hex2bin(self::FILESYSTEM_MODES[UnicodeNormalizer::NFC]);
        $quote = TYPO3_OS === 'WIN' ? '"' : '\'';

        // Since PHP 5.6.0 escapeshellarg uses the 'default_charset' on platforms lacking a 'mblen'-implementation
        // @see http://www.php.net/manual/en/function.escapeshellarg.php#refsect1-function.escapeshellarg-changelog
        // @see https://github.com/php/php-src/blob/PHP-5.6.0/ext/standard/exec.c#L349
        // @see https://github.com/php/php-src/blob/PHP-5.6.0/ext/standard/php_string.h#L155
        // @see http://man7.org/linux/man-pages/man3/mblen.3.html
        // @see https://www.freebsd.org/cgi/man.cgi?query=mblen
        // @see http://man.openbsd.org/mblen.3
        // @see https://developer.apple.com/legacy/library/documentation/Darwin/Reference/ManPages/man3/mblen_l.3.html
        // @see https://docs.microsoft.com/en-us/cpp/c-runtime-library/reference/mbclen-mblen-mblen-l
        if (escapeshellarg($fileName) === $quote . $fileName . $quote &&
            (
                version_compare(PHP_VERSION, '5.6.0', '<') || 
                strtolower(str_replace('-', '', (string) ini_get('default_charset'))) === 'utf8'
            ))
        {
            $capabilities['shellescape'] = true;
        } else {
            return $capabilities;
        }

        $fullPath =  $absolutePath . DIRECTORY_SEPARATOR . self::UTF8_FILESYSTEM_CAPABILITY_DETECTION_FOLDER_NAME;
        // The temporary base path should be missing, so try to create it and verify existance
        // We can not use GeneralUtility::mkdir($fullPath) as it does not work for stream-wrappers
        if (is_dir($fullPath) || ! (self::mkdir($fullPath) && is_dir($fullPath))) {
           return $capabilities;
        }

        $fileNames = [];
        $normalizations = array_map(function($_) { return false; }, self::FILESYSTEM_MODES);

        foreach (self::FILESYSTEM_MODES as $normalization => $fileName) {
            if ($fileName === false) {
                continue;
            }
            $normalizations[$normalization] = [
                'read' => false,
                'write' => false
            ];
            $fileName = $normalization . '-' . hex2bin($fileName);
            $fileNames[$normalization] = $fileName;
            if (touch($fullPath . '/' . $fileName)) {
                $normalizations[$normalization]['write'] = true;
            }
            clearstatcache(true, $fullPath . DIRECTORY_SEPARATOR . $fileName);
        }

        $handle = opendir($fullPath);
        while (is_resource($handle) && (false !== ($entry = readdir($handle)))) {
            if ($entry[0] === '.') {
                continue;
            }
            foreach ($fileNames as $normalization => $fileName) {
                if ($normalizations[$normalization]['read'] === true) {
                    continue;
                }
                if ($entry === $fileName) {
                    // If all files exist then the filesystem does not normalize unicode. If
                    // some files are missing then the filesystem, either normalizes unicode
                    // or it denies access to not-normalized filepaths or it simply does not
                    // support unicode at all, at least not those normalization forms we test.
                    $normalizations[$normalization]['read'] = true;
                }
            }
            unlink($fullPath . '/' . $entry);
        }
        closedir($handle);
        GeneralUtility::rmdir($fullPath, true);

        $capabilities['normalization'] = self::reduceDetectedNormalizationFormCapabilities($normalizations);

        return $capabilities;
    }

    /**
     * Detect utf8-capabilities for given resource storage.
     * 
     * @param ResourceStorage $storage
     * @return boolean[]|array[]
     * @todo FEATURE #57695: Add more documentation to UnicodeUtility::detectUtf8CapabilitiesForResourceStorage()
     */
    public static function detectUtf8CapabilitiesForResourceStorage(ResourceStorage $storage)
    {
        $capabilities = [
            'normalization' => false
        ];

        // If storage is not writable and browsable, we can not detect it's utf8 capabilities.
        if (!$storage->isWritable() || !$storage->isBrowsable()) {
            return $capabilities;
        }

        $parentFolder = $storage->getDefaultFolder();
        $folderName = self::UTF8_FILESYSTEM_CAPABILITY_DETECTION_FOLDER_NAME;

        $fileNames = [];
        $normalizations = array_map(function($_) { return false; }, self::FILESYSTEM_MODES);

        try {
            if ($storage->hasFolderInFolder($folderName, $parentFolder)) {
                $storage->deleteFolder($storage->getFolderInFolder($folderName, $parentFolder), true);
            }
            $folder = $storage->createFolder($folderName, $parentFolder);

            foreach (self::FILESYSTEM_MODES as $form => $fileName) {
                if ($fileName === false) {
                    continue;
                }
                $normalizations[$form] = [
                    'read' => false,
                    'write' => false
                ];
                $fileName = $form . '-' . hex2bin($fileName);
                $fileNames[$form] = $fileName;
                if (!$storage->hasFileInFolder($fileName, $folder) && 
                    $storage->createFile($fileName, $folder) instanceof FileInterface)
                {
                    $normalizations[$form]['write'] = true;
                }
            }
            foreach ($fileNames as $form => $fileName) {
                if ($normalizations[$form]['read'] === true) {
                    continue;
                }
                if ($storage->hasFileInFolder($fileName, $folder)) {
                    // If all files exist then the filesystem does not normalize unicode. If
                    // some files are missing then the filesystem, either normalizes unicode
                    // or it denies access to not-normalized filepaths or it simply does not
                    // support unicode at all, at least not those normalization forms we test.
                    $normalizations[$form]['read'] = true;
                }
            }
            $storage->deleteFolder($folder, true);

        } finally {
            $capabilities['normalization'] = self::reduceDetectedNormalizationFormCapabilities($normalizations);
        }
        return $capabilities;
    }

    /**
     * Reduces the given array of normalization detection results
     * 
     * @param array $normalizations
     * @return array
     */
    protected static function reduceDetectedNormalizationFormCapabilities(array $normalizations)
    {
        return array_map(
            function ($normalization) {
                if (false === $normalization) {
                    return $normalization;
                } else if (! in_array(false, $normalization, true)) {
                    return true;
                } else if (in_array(true, $normalization, true)) {
                    return $normalization;
                }
                return false;
            },
            $normalizations
        );
    }

    /**
     * @param string $path
     * @param array|null $allowedSchemes
     * @return boolean
     */
    protected static function isValidPath($path, $allowedSchemes = null)
    {
        if (is_array($allowedSchemes) && self::isValidStreamWrapperUrl($path, $allowedSchemes)) {
            return GeneralUtility::isValidUrl($path);
        }
        return GeneralUtility::isAllowedAbsPath($path);
    }

    /**
     * @param string $url
     * @param array $allowedSchemes
     * @return boolean
     */
    protected static function isValidStreamWrapperUrl($url, $allowedSchemes = [])
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === false || $scheme === null || empty($scheme)) {
            return false;
        }
        return in_array($scheme, array_intersect(stream_get_wrappers(), $allowedSchemes), true);
    }

    /**
     * Wrapper function for mkdir.
     * Sets folder permissions according to $GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask']
     * and group ownership according to $GLOBALS['TYPO3_CONF_VARS']['SYS']['createGroup']
     *
     * @param string $newFolder Absolute path to folder, see PHP mkdir() function. Removes trailing slash internally.
     * @return bool TRUE if @mkdir went well!
     */
    protected static function mkdir($newFolder)
    {
        $result = @mkdir($newFolder, octdec($GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask']));
        if ($result) {
            self::fixPermissions($newFolder);
        }
        return $result;
    }

    /**
     * This works for stream-wrappers - always check path validity on beforehand!
     *
     * @param string $path
     * @return boolean
     */
    protected static function fixPermissions($path)
    {
        $result = false;
        if (@is_file($path)) {
            $targetPermissions = isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask'])
            ? $GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask']
            : '0644';
        } elseif (@is_dir($path)) {
            $targetPermissions = isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask'])
            ? $GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask']
            : '0755';
        }
        if (!empty($targetPermissions)) {
            // make sure it's always 4 digits
            $targetPermissions = str_pad($targetPermissions, 4, 0, STR_PAD_LEFT);
            $targetPermissions = octdec($targetPermissions);
            // "@" is there because file is not necessarily OWNED by the user
            $result = @chmod($path, $targetPermissions);
        }
        // Set createGroup if not empty
        if (
            isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['createGroup'])
            && $GLOBALS['TYPO3_CONF_VARS']['SYS']['createGroup'] !== ''
        ) {
            // "@" is there because file is not necessarily OWNED by the user
            $changeGroupResult = @chgrp($path, $GLOBALS['TYPO3_CONF_VARS']['SYS']['createGroup']);
            $result = $changeGroupResult ? $result : false;
        }
        return $result;
    }
}
