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


/**
 * Interface that must be implemented by custom normalizer implementations.
 *  
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
interface CustomNormalizerInterface extends NormalizerInterface
{

    const IMPLEMENTATION_IDENTIFIER = 'custom';

}
