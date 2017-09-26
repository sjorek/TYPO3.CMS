<?php
namespace TYPO3\CMS\Install\Configuration\Unicode\NormalizerImplementation;

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

use TYPO3\CMS\Core\Charset\Unicode\PatchworkNormalizer;
use TYPO3\CMS\Install\Configuration\AbstractPreset;

/**
 * Unicode normalizer-implementation preset
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class PatchworkPreset extends AbstractPreset
{

    /**
     *
     * @var string Name of preset
     */
    protected $name = 'Patchwork';

    /**
     *
     * @var integer Priority of preset
     */
    protected $priority = 80;

    /**
     *
     * @var array Configuration values handled by this preset
     */
    protected $configurationValues = array(
        'SYS/unicode/normalizer' => PatchworkNormalizer::IMPLEMENTATION_IDENTIFIER
    );

    /**
     * Check if normalizer-implementation is supported
     *
     * @return boolean TRUE if patchwork normalizer-implementation is available
     */
    public function isAvailable()
    {
        return PatchworkNormalizer::isAvailable();
    }
}
