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


use TYPO3\CMS\Core\Charset\Unicode\StubNormalizer;
use TYPO3\CMS\Install\Configuration\AbstractPreset;

/**
 * Unicode normalizer-implementation preset
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class StubPreset extends AbstractPreset
{

    /**
     *
     * @var string Name of preset
     */
    protected $name = 'Stub';

    /**
     *
     * @var integer Priority of preset
     */
    protected $priority = 10;

    /**
     *
     * @var array Configuration values handled by this preset
     */
    protected $configurationValues = array(
        'SYS/unicode/normalizer' => StubNormalizer::IMPLEMENTATION_IDENTIFIER
    );

    /**
     * Check if normalizer-implementation is supported
     *
     * @return boolean TRUE if stub normalizer-implementation is available, which is always true
     */
    public function isAvailable()
    {
        return StubNormalizer::isAvailable();
    }
}
