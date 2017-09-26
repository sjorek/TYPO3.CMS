<?php
namespace TYPO3\CMS\Install\SystemEnvironment;

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

use TYPO3\CMS\Core\Charset\UnicodeNormalizer;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Service\OpcodeCacheService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\UnicodeUtility;

/**
 * Check TYPO3 setup status
 *
 * This class is a hardcoded requirement check for the TYPO3 setup.
 *
 * The status messages and title *must not* include HTML, use plain
 * text only. The return values of this class are not bound to HTML
 * and can be used in different scopes (eg. as json array).
 */
class SetupCheck implements CheckInterface
{
    /**
     * @var FlashMessageQueue
     */
    protected $messageQueue;

    /**
     * Get all status information as array with status objects
     *
     * @return FlashMessageQueue
     */
    public function getStatus(): FlashMessageQueue
    {
        $this->messageQueue = new FlashMessageQueue('install');

        $this->checkTrustedHostPattern();
        $this->checkDownloadsPossible();
        $this->checkSystemLocale();
        $this->checkLocaleWithUTF8filesystem();
        $this->checkUnicodeNormalizationWithUTF8Charset();
        $this->checkSomePhpOpcodeCacheIsLoaded();
        $this->isTrueTypeFontWorking();
        $this->checkLibXmlBug();

        return $this->messageQueue;
    }

    /**
     * Checks the status of the trusted hosts pattern check
     */
    protected function checkTrustedHostPattern()
    {
        if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] === GeneralUtility::ENV_TRUSTED_HOSTS_PATTERN_ALLOW_ALL) {
            $this->messageQueue->enqueue(new FlashMessage(
                'Trusted hosts pattern is configured to allow all header values. Check the pattern defined in Install'
                    . ' Tool -> All configuration -> System -> trustedHostsPattern and adapt it to expected host value(s).',
                'Trusted hosts pattern is insecure',
                FlashMessage::WARNING
            ));
        } else {
            if (GeneralUtility::hostHeaderValueMatchesTrustedHostsPattern($_SERVER['HTTP_HOST'])) {
                $this->messageQueue->enqueue(new FlashMessage(
                    '',
                    'Trusted hosts pattern is configured to allow current host value.'
                ));
            } else {
                $defaultPort = GeneralUtility::getIndpEnv('TYPO3_SSL') ? '443' : '80';
                $this->messageQueue->enqueue(new FlashMessage(
                    'The trusted hosts pattern will be configured to allow all header values. This is because your $SERVER_NAME:[defaultPort]'
                        . ' is "' . $_SERVER['SERVER_NAME'] . ':' . $defaultPort . '" while your HTTP_HOST:SERVER_PORT is "'
                        . $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'] . '". Check the pattern defined in Install Tool -> All'
                        . ' configuration -> System -> trustedHostsPattern and adapt it to expected host value(s).',
                    'Trusted hosts pattern mismatch',
                    FlashMessage::ERROR
                ));
            }
        }
    }

    /**
     * Check if it is possible to download external data (e.g. TER)
     * Either allow_url_fopen must be enabled or curl must be used
     */
    protected function checkDownloadsPossible()
    {
        $allowUrlFopen = (bool)ini_get('allow_url_fopen');
        $curlEnabled = function_exists('curl_version');
        if ($allowUrlFopen || $curlEnabled) {
            $this->messageQueue->enqueue(new FlashMessage(
                '',
                'Fetching external URLs is allowed'
            ));
        } else {
            $this->messageQueue->enqueue(new FlashMessage(
                'Either enable PHP runtime setting "allow_url_fopen"' . LF . 'or compile curl into your PHP with --with-curl.',
                'Fetching external URLs is not allowed',
                FlashMessage::WARNING
            ));
        }
    }

    /**
     * Check if systemLocale setting is correct (locale exists in the OS)
     */
    protected function checkSystemLocale()
    {
        $currentLocale = setlocale(LC_CTYPE, 0);

        // On Windows an empty locale value uses the regional settings from the Control Panel
        if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['systemLocale'] === '' && TYPO3_OS !== 'WIN') {
            $this->messageQueue->enqueue(new FlashMessage(
                '$GLOBALS[TYPO3_CONF_VARS][SYS][systemLocale] is not set. This is fine as long as no UTF-8 file system is used.',
                'Empty systemLocale setting',
                FlashMessage::INFO
            ));
        } elseif (setlocale(LC_CTYPE, $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemLocale']) === false) {
            $this->messageQueue->enqueue(new FlashMessage(
                'Current value of the $GLOBALS[TYPO3_CONF_VARS][SYS][systemLocale] is incorrect. A locale with'
                    . ' this name doesn\'t exist in the operating system.',
                'Incorrect systemLocale setting',
                FlashMessage::ERROR
            ));
            setlocale(LC_CTYPE, $currentLocale);
        } else {
            $this->messageQueue->enqueue(new FlashMessage(
                '',
                'System locale is correct'
            ));
        }
    }

    /**
     * Checks whether we can use file names with UTF-8 characters.
     * Configured system locale must support UTF-8 when UTF8filesystem is set
     */
    protected function checkLocaleWithUTF8filesystem()
    {
        $UTF8filesystem = (int) $GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem'];
        if ($UTF8filesystem) {
            // On Windows an empty locale value uses the regional settings from the Control Panel
            if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['systemLocale'] === '' && TYPO3_OS !== 'WIN') {
                $this->messageQueue->enqueue(new FlashMessage(
                    '$GLOBALS[TYPO3_CONF_VARS][SYS][UTF8filesystem] is enabled, but '
                        . '$GLOBALS[TYPO3_CONF_VARS][SYS][systemLocale] is empty. Make '
                        . 'sure a valid locale which supports UTF-8 is set.',
                    'System locale not set on UTF-8 file system',
                    FlashMessage::ERROR
                ));
            } else  if (UnicodeNormalizer::NONE < $UTF8filesystem && 
                        in_array(UnicodeNormalizer::IMPLEMENTATION_IDENTIFIER, ['stub', 'missing'], true))
            {
                $this->messageQueue->enqueue(new FlashMessage(
                    '$GLOBALS[TYPO3_CONF_VARS][SYS][UTF8filesystem] is enabled to use unicode-normalized '
                        . 'identifiers. Please install/configure a proper normalization implementation in '
                        . '$GLOBALS[TYPO3_CONF_VARS][SYS][unicode][normalizer] or disable '
                        . 'unicode-normalization in $GLOBALS[TYPO3_CONF_VARS][SYS][UTF8filesystem].',
                    'Invalid configuration for UTF-8 file system',
                    FlashMessage::ERROR
                ));
            } else  if (!in_array($UTF8filesystem, UnicodeNormalizer::UNICODE_NORMALIZATION_FORMS, true)) {
                $this->messageQueue->enqueue(new FlashMessage(
                    '$GLOBALS[TYPO3_CONF_VARS][SYS][UTF8filesystem] is set, but contains an invalid value.'
                        . 'Valid and supported values are: '
                        . implode(
                            ', ',
                            array_merge([0],
                                // strip NFKD and NFKC from list of supported values
                                array_intersect(
                                    [UnicodeNormalizer::NONE, UnicodeNormalizer::NFC, UnicodeNormalizer::NFD],
                                    UnicodeNormalizer::UNICODE_NORMALIZATION_FORMS
                                )
                            )
                        ) . '. '
                        . 'Hint: The valid values are defined by the unicode-normalizer implementation '
                        . 'configured in $GLOBALS[TYPO3_CONF_VARS][SYS][unicode][normalizer].',
                    'Invalid configuration for UTF-8 file system',
                    FlashMessage::ERROR
                ));
            } else {

                $currentLocale = setlocale(LC_CTYPE, 0);
                setlocale(LC_CTYPE, $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemLocale']);
                $capabilities = UnicodeUtility::detectUtf8CapabilitiesForPath(PATH_site . '/typo3temp');
                setlocale(LC_CTYPE, $currentLocale);

                if ($capabilities['locale'] === true && $capabilities['shellescape'] === true) {

                    $message = 'The utf8-filesystem is configured to ';
                    switch ((int) $UTF8filesystem) {
                        case UnicodeNormalizer::NONE:
                            $message .= 'ignore unicode-normalization (NONE) ';
                            break;
                        case UnicodeNormalizer::NFD:
                            $normalizationName = 'NFD';
                            $message .= 'use decomposed unicode normalization (NFD) ';
                            break;
                        case UnicodeNormalizer::NFC:
                            $normalizationName = 'NFC';
                            $message .= 'use re-composed unicode normalization (NFC) ';
                            break;
                    }
                    $message .= 'for file names.';

                    // If it is not a mac - this is a stupid assumption that every Darwin is a Mac!
                    if (false === stripos(PHP_OS, 'Darwin')) {
                        $expectedForm = UnicodeNormalizer::NFC;
                        $expectedName = 'NFC';
                    } else {
                        /**
                         * Warning: This is the stupid assumption that every Mac and runs on
                         * a HFS+ filesystem (MacOS Extended) with NFD(-alike) normalization.
                         * This might not be true, due to NFS mounts or the like!
                         * 
                         * @todo Feature #57695 What about Apple™'s new normalization-insensitive APFS?
                         * @link https://developer.apple.com/library/content/documentation/FileManagement/Conceptual/APFS_Guide/FAQ/FAQ.html#//apple_ref/doc/uid/TP40016999-CH6-DontLinkElementID_3
                         * @see \TYPO3\CMS\Core\Charset\Unicode\NormalizerInterface::NFD
                         */
                        $expectedForm = UnicodeNormalizer::NFD_MAC;
                        $expectedName = 'NFD_MAC';
                    }

                    $hint = sprintf(
                        'Hint: If you move your typo3 installation between different operating- and filesystems,'
                            . ' it could be a good idea to normalize FAL\'s file identifiers to %s by setting'
                            . ' $GLOBALS[TYPO3_CONF_VARS][SYS][UTF8filesystem] to %s. After normalization via '
                            . ' the install-tool set the value back to %s.',
                        $expectedForm,
                        $expectedName,
                        UnicodeNormalizer::NONE
                    );

                    if (UnicodeNormalizer::NONE === $UTF8filesystem) {
                        $this->messageQueue->enqueue(new FlashMessage(
                            $message . LF . $hint,
                            'File names with UTF-8 characters can be used.'
                        ));
                    } elseif ($capabilities[$UTF8filesystem] !== true) {
                        $message .= LF . sprintf(
                            'The utf8-filesystem lacks support for %s unicode-normalization on file names. '
                                . 'We support decomposed normalization-form (NFD) on Apple\'s HFS+ filesystem, '
                                . 'but suggest re-composed normalization-form (NFC) on utf8-filesystems in '
                                . 'general. '
                                . LF . 'Set $GLOBALS[TYPO3_CONF_VARS][SYS][UTF8filesystem] to 1 (NONE).',
                            $normalizationName
                        );
                        $this->messageQueue->enqueue(new FlashMessage(
                            $message . LF . $hint,
                            'File names with UTF-8 characters use unsupported unicode-normalization.',
                            FlashMessage::ERROR
                        ));
                    } elseif ($UTF8filesystem !== $expectedForm) {
                        $message .= sprintf(
                            'Detected an unexpected unicode-normalization. We suggest %s unicode-normalization. '
                                . 'We expect decomposed normalization-form (NFD) on Apple\'s HFS+ filesystems '
                                . 'and suggest re-composed normalization-form (NFC) on all other utf8-filesytems. '
                                . LF . 'Try setting $GLOBALS[TYPO3_CONF_VARS][SYS][UTF8filesystem] to %s.',
                            $normalizationName, $expectedName, $expectedForm
                        );
                        $this->messageQueue->enqueue(new FlashMessage(
                            $message . LF . $hint,
                            'File names with UTF-8 characters use unexpected unicode-normalization.',
                            FlashMessage::NOTICE
                         ));
                    } else {
                        $this->messageQueue->enqueue(new FlashMessage(
                            $message . LF . $hint,
                            'File names with UTF-8 characters can be used.'
                        ));
                    }
                } else {
                    $this->messageQueue->enqueue(new FlashMessage(
                        'Please check your $GLOBALS[TYPO3_CONF_VARS][SYS][systemLocale] setting.',
                        'System locale setting doesn\'t support UTF-8 file names.',
                        FlashMessage::ERROR
                    ));
                }
            }
        } else {
            $this->messageQueue->enqueue(new FlashMessage(
                '',
                'Skipping test, as UTF8filesystem is not enabled.'
            ));
        }
    }

    /**
     * Checks whether we can use unicode-normalization with UTF-8 characters.
     */
    protected function checkUnicodeNormalizationWithUTF8Charset()
    {
        $feNormalizationForm = (int) $GLOBALS['TYPO3_CONF_VARS']['FE']['unicode']['normalizationForm'];
        $sysNormalizationForm = (int) $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['normalizationForm'];
        
        if (0 < $feNormalizationForm || 0 < (int) $sysNormalizationForm) {

            $identifier = UnicodeNormalizer::IMPLEMENTATION_IDENTIFIER;
            $forms = UnicodeNormalizer::UNICODE_NORMALIZATION_FORMS;

            $feOk = in_array((int) $feNormalizationForm, $forms, true);
            $sysOk = in_array((int) $sysNormalizationForm, $forms, true);
            if (!$feOk || !$sysOk) {
                $message = 'An unsupported unicode normalization form is set for ';
                if (!$feOk) {
                    $message .= '$GLOBALS[TYPO3_CONF_VARS][FE][unicode][normalizationForm]';
                }
                if (!$sysOk) {
                    if (!$feOk) {
                        $message .= ' and ';
                    }
                    $message .= '$GLOBALS[TYPO3_CONF_VARS][SYS][unicode][normalizationForm]';
                }
                $message .= '. Valid and supported values are: '
                    . implode(', ', array_merge([0],  UnicodeNormalizer::UNICODE_NORMALIZATION_FORMS)) . '. '
                    . 'Hint: The valid values are defined by the unicode-normalizer implementation '
                    . 'configured in $GLOBALS[TYPO3_CONF_VARS][SYS][unicode][normalizer].';

                $this->messageQueue->enqueue(new FlashMessage(
                    $message,
                    'Unicode normalization support has been turned on, using an unsupported normalization form.',
                    FlashMessage::ERROR
                ));
            } elseif ($identifier === 'missing') {
                $this->messageQueue->enqueue(new FlashMessage(
                    'There is no implementation available that implements unicode-normalization. Either configure '
                        . 'an implementation in $GLOBALS[TYPO3_CONF_VARS][SYS][unicode][normalizer] '
                        . 'and install its corresponding dependencies or disable normalization by setting '
                        . '$GLOBALS[TYPO3_CONF_VARS][FE][unicode][normalizationForm] and '
                        . '$GLOBALS[TYPO3_CONF_VARS][SYS][unicode][normalizationForm] to 0',
                    'Unicode normalization support has been turned on without any implementation available.',
                    FlashMessage::ERROR
                ));
            } elseif ($identifier === 'stub') {
                $this->messageQueue->enqueue(new FlashMessage(
                    'The stub implementation does not implement unicode-normalization. Either configure '
                        . 'an implementation in $GLOBALS[TYPO3_CONF_VARS][SYS][unicode][normalizer] '
                        . 'and install its corresponding dependencies or disable normalization by setting '
                        . '$GLOBALS[TYPO3_CONF_VARS][FE][unicode][normalizationForm] and '
                        . '$GLOBALS[TYPO3_CONF_VARS][SYS][unicode][normalizationForm] to 0',
                    'Unicode normalization support has been turned on with a stub implementation.',
                    FlashMessage::WARNING
                ));
            } elseif ($identifier === 'custom') {
                if (class_exists($GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['customNormalizerClass'], true)) {
                    $message = 'A custom normalizer implementation is provided by .'
                             . $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['customNormalizerClass'];
                    $severity = FlashMessage::NOTICE;
                } else {
                    $message = 'The custom normalizer implementation "'
                             . $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['customNormalizerClass']
                             . '" has not been found.';
                    $severity = FlashMessage::ERROR;
                }
                $this->messageQueue->enqueue(new FlashMessage(
                    $message,
                    'Unicode normalization support has been turned on with a custom implementation.',
                    $severity
                ));
            } elseif ($identifier === 'intl') {
                $this->messageQueue->enqueue(new FlashMessage(
                    'A native normalizer implementation is provided by "intl"-extension.',
                    'Unicode normalization support has been turned on.'
                ));
            } elseif ($identifier === 'patchwork') {
                $this->messageQueue->enqueue(new FlashMessage(
                    'A pure-php normalizer implementation is provided by "patchwork/utf8" package. '
                        . 'Try installing the "intl"-extension, which provides a superior native implementation.',
                    'Unicode normalization support has been turned on.',
                    FlashMessage::NOTICE
                ));
            } elseif ($identifier === 'symfony') {
                $this->messageQueue->enqueue(new FlashMessage(
                    'A pure-php normalizer implementation is provided by "symfony/polyfill-intl-normalizer" '
                    . 'package. Try installing the "intl"-extension, which provides a superior native '
                    . 'implementation.',
                    'Unicode normalization support has been turned on.',
                    FlashMessage::NOTICE
                ));
            } elseif ($identifier === 'mac') {
                $this->messageQueue->enqueue(new FlashMessage(
                    'A normalizer implementation is provided by TYPO3 utilizing the native "iconv"-extension '
                        . 'plus one of the other implementations. Besides all standard normalization forms this '
                        . 'implementation provides a special NFD_MAC normalization form for use on Apple™\'s '
                        . 'HFS+ filesystem (OS X Extended). Install the "intl"-extension, to enhance this '
                        . 'implementation with a superior native implementation.',
                    'Unicode normalization support has been turned on.',
                    FlashMessage::NOTICE
                ));
            } else {
                $this->messageQueue->enqueue(new FlashMessage(
                    'None of the supported unicode-normalization implementations have been found. Install one of '
                        . 'the supported solutions or disable unicode-normalization at all by setting '
                        . '$GLOBALS[TYPO3_CONF_VARS][FE][unicode][normalizationForm] and '
                        . '$GLOBALS[TYPO3_CONF_VARS][SYS][unicode][normalizationForm] to 0 as well as '
                        . '$GLOBALS[TYPO3_CONF_VARS][SYS][UTF8filesystem] to 0 or 1.',
                    'Unicode normalization support has been turned on, but normalizer implementation is missing.',
                    FlashMessage::ERROR
                ));
            }
        } else {
            $this->messageQueue->enqueue(new FlashMessage(
                '',
                'Skipping test as unicode normalization support has been turned off.'
            ));
        }
    }

    /**
     * Check if some opcode cache is loaded
     */
    protected function checkSomePhpOpcodeCacheIsLoaded()
    {
        // Link to our wiki page, so we can update opcode cache issue information independent of TYPO3 CMS releases.
        $wikiLink = 'For more information take a look in our wiki ' . TYPO3_URL_WIKI_OPCODECACHE . '.';
        $opcodeCaches = GeneralUtility::makeInstance(OpcodeCacheService::class)->getAllActive();
        if (empty($opcodeCaches)) {
            // Set status to notice. It needs to be notice so email won't be triggered.
            $this->messageQueue->enqueue(new FlashMessage(
                'PHP opcode caches hold a compiled version of executed PHP scripts in'
                    . ' memory and do not require to recompile a script each time it is accessed.'
                    . ' This can be a massive performance improvement and can reduce the load on a'
                    . ' server in general. A parse time reduction by factor three for fully cached'
                    . ' pages can be achieved easily if using an opcode cache.'
                    . LF . $wikiLink,
                'No PHP opcode cache loaded',
                FlashMessage::NOTICE
            ));
        } else {
            $status = FlashMessage::OK;
            $message = '';
            foreach ($opcodeCaches as $opcodeCache => $properties) {
                $message .= 'Name: ' . $opcodeCache . ' Version: ' . $properties['version'];
                $message .= LF;
                if ($properties['error']) {
                    $status = FlashMessage::ERROR;
                    $message .= ' This opcode cache is marked as malfunctioning by the TYPO3 CMS Team.';
                } elseif ($properties['canInvalidate']) {
                    $message .= ' This opcode cache should work correctly and has good performance.';
                } else {
                    // Set status to notice if not already error set. It needs to be notice so email won't be triggered.
                    if ($status !== FlashMessage::ERROR) {
                        $status = FlashMessage::NOTICE;
                    }
                    $message .= ' This opcode cache may work correctly but has medium performance.';
                }
                $message .= LF;
            }
            $message .= $wikiLink;
            // Set title of status depending on serverity
            switch ($status) {
                case FlashMessage::ERROR:
                    $title = 'A possibly malfunctioning PHP opcode cache is loaded';
                    break;
                case FlashMessage::OK:
                default:
                    $title = 'A PHP opcode cache is loaded';
                    break;
            }
            $this->messageQueue->enqueue(new FlashMessage(
                $message,
                $title,
                $status
            ));
        }
    }

    /**
     * Create true type font test image
     */
    protected function isTrueTypeFontWorking()
    {
        if (function_exists('imageftbbox')) {
            // 20 Pixels at 96 DPI
            $fontSize = (20 / 96 * 72);
            $textDimensions = @imageftbbox(
                $fontSize,
                0,
                __DIR__ . '/../../Resources/Private/Font/vera.ttf',
                'Testing true type support'
            );
            $fontBoxWidth = $textDimensions[2] - $textDimensions[0];
            if ($fontBoxWidth < 300 && $fontBoxWidth > 200) {
                $this->messageQueue->enqueue(new FlashMessage(
                    'Fonts are rendered by FreeType library. '
                        . 'We need to ensure that the final dimensions are as expected. '
                        . 'This server renderes fonts based on 96 DPI correctly',
                    'FreeType True Type Font DPI'
                ));
            } else {
                $this->messageQueue->enqueue(new FlashMessage(
                    'Fonts are rendered by FreeType library. '
                        . 'This server does not render fonts as expected. '
                        . 'Please check your FreeType 2 module.',
                    'FreeType True Type Font DPI',
                    FlashMessage::NOTICE
                ));
            }
        } else {
            $this->messageQueue->enqueue(new FlashMessage(
                'The core relies on GD library compiled into PHP with freetype2'
                    . ' support. This is missing on your system. Please install it.',
                'PHP GD library freetype2 support missing',
                FlashMessage::ERROR
            ));
        }
    }

    /**
     * Check for bug in libxml
     */
    protected function checkLibXmlBug()
    {
        $sampleArray = ['Test>><<Data'];
        $xmlContent = '<numIndex index="0">Test&gt;&gt;&lt;&lt;Data</numIndex>' . LF;
        $xml = GeneralUtility::array2xml($sampleArray, '', -1);
        if ($xmlContent !== $xml) {
            $this->messageQueue->enqueue(new FlashMessage(
                'Some hosts have problems saving ">><<" in a flexform.'
                    . ' To fix this, enable [BE][flexformForceCDATA] in'
                    . ' All Configuration.',
                'PHP libxml bug present',
                FlashMessage::ERROR
            ));
        } else {
            $this->messageQueue->enqueue(new FlashMessage(
                '',
                'PHP libxml bug not present'
            ));
        }
    }
}
