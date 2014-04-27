<?php
namespace TYPO3\CMS\Install\Configuration\UnicodeNormalization;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Stephan Jorek (stephan.jorek@gmail.com)
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Install\Configuration;

/**
 * Unicode normalization feature
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class UnicodeNormalizerFeature extends Configuration\AbstractFeature implements Configuration\FeatureInterface {

	/**
	 * @var string Name of feature
	 */
	protected $name = 'UnicodeNormalizer';

	/**
	 * @var array List of preset classes
	 */
	protected $presetRegistry = array(
		'TYPO3\\CMS\\Install\\Configuration\\UnicodeNormalization\\IntlPreset',
		'TYPO3\\CMS\\Install\\Configuration\\UnicodeNormalization\\PatchworkPreset',
		'TYPO3\\CMS\\Install\\Configuration\\UnicodeNormalization\\DefaultPreset',
		'TYPO3\\CMS\\Install\\Configuration\\UnicodeNormalization\\CustomPreset',
	);
}
