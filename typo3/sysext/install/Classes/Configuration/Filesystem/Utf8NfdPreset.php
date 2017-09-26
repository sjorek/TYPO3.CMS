<?php
namespace TYPO3\CMS\Install\Configuration\Filesystem;

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

/**
 * Unicode filesystem preset
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class Utf8NfdPreset extends AbstractPreset
{

    /**
     *
     * @var string Name of preset
     */
    protected $name = 'Utf8Nfd';

    /**
     *
     * @var integer Priority of preset
     */
    protected $priority = 20;

    /**
     *
     * @var array Configuration values handled by this preset
     */
    protected $configurationValues = array(
        'SYS/UTF8filesystem' => UnicodeNormalizer::NFD
    );

    /**
     * Check utf8 filesystem is supported
     *
     * @return boolean TRUE if utf8 filesystem with NFD normalization is supported
     */
    public function isAvailable()
    {
        if (!$this->hasNormalizionForm(UnicodeNormalizer::NFD)) {
            return false;
        }
        $capabilities = $this->detectUtf8Capabilities();
        return (
            $capabilities['locale'] === true && 
            $capabilities['shellescape'] === true && 
            $capabilities['normalization'][UnicodeNormalizer::NFD] === true
        );
    }
}
