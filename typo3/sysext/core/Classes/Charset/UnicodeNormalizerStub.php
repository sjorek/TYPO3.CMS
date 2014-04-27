<?php
namespace TYPO3\CMS\Core\Charset;

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

/**
 * Stub class faking a unicode-normalizer implementation.
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
final class UnicodeNormalizerStub {

	/**
	 * Disable direct creation of this object.
	 */
	protected function __construct() {
	}

	/**
	 * Disable direct cloning of this object.
	 */
	protected function __clone() {
	}

	/**
	 * Stub method which always returns TRUE.
	 *
	 * @param string $input
	 * @param integer $normalization
	 * @return boolean Always TRUE.
	 */
	public static function isNormalized($input, $normalization = NULL) {
		return TRUE;
	}

	/**
	 * Stub method which always returns given input as is
	 *
	 * @param string $input
	 * @param integer $normalization
	 * @return string The input string
	 */
	public static function normalize($input, $normalization = NULL) {
		return $input;
	}
}
