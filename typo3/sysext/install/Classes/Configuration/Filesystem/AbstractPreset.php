<?php
namespace TYPO3\CMS\Install\Configuration\Filesystem;

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
 * Abstract class implements common filesystem preset code
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
abstract class AbstractPreset extends Configuration\AbstractPreset {

	/**
	 *
	 * @return array<boolean>
	 */
	protected function detectUtf8Capabilities() {
		static $capabilities;

		if (isset($capabilities)) {
			return $capabilities;
		}

		$capabilities = \TYPO3\CMS\Core\Charset\UnicodeNormalizer::detectUtf8CapabilitiesForPath(PATH_site . '/typo3temp');

		return $capabilities;
	}

}
