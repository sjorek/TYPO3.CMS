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
 * Unicode filesystem preset
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class DefaultPreset extends Configuration\AbstractPreset {

	/**
	 * @var string Name of preset
	 */
	protected $name = 'Default';

	/**
	 * @var integer Priority of preset
	 */
	protected $priority = 20;

	/**
	 * @var array Configuration values handled by this preset
	 */
	protected $configurationValues = array(
		'SYS/UTF8filesystem' => 0,
	);

	/**
	 * Default preset is always available
	 *
	 * @return boolean TRUE
	 */
	public function isAvailable() {
		return TRUE;
	}
}

