<?php
namespace TYPO3\CMS\Install\Configuration\Unicode\NormalizationForm;

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
 * Unicode normalization preset
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class NonePreset extends AbstractPreset
{

    /**
     *
     * @var string Name of preset
     */
    protected $name = 'None';

    /**
     *
     * @var integer Priority of preset
     */
    protected $priority = 50;

    /**
     *
     * @var array Configuration values handled by this preset
     */
    protected $configurationValues = array(
        'SYS/unicode/normalizationForm' => UnicodeNormalizer::NONE,
        'FE/unicode/normalizationForm' => UnicodeNormalizer::NONE
    );

    /**
     * Check if normalization form is supported
     *
     * @return boolean TRUE if unicode normalization to NONE is supported
     */
    public function isAvailable()
    {
        return $this->hasNormalizionForm(UnicodeNormalizer::NONE);
    }
}
