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
 * Class for normalizing unicode. Depending on what has been configured in the install-tool or typoscript, the implementation
 * is a facade, either to “php-intl” extension's “Normalizer”-class or to the homonymous pure PHP-fallback from the faboulus
 * “Patchwork-UTF8” project.
 *
 *   “Normalization: A process of removing alternate representations of
 *    equivalent sequences from textual data, to convert the data into a
 *    form that can be binary-compared for equivalence. In the Unicode
 *    Standard, normalization refers specifically to processing to ensure
 *    that canonical-equivalent (and/or compatibility-equivalent) strings
 *    have unique representations.”
 *
 *     -- quoted from unicode glossary linked below
 *
 * @link http://www.unicode.org/glossary/#normalization
 * @link http://www.php.net/manual/en/class.normalizer.php
 * @link http://en.wikipedia.org/wiki/Unicode_equivalence
 * @link http://stackoverflow.com/questions/7931204/what-is-normalized-utf-8-all-about
 * @link http://www.w3.org/TR/charmod-norm/
 * @link http://blog.whatwg.org/tag/unicode
 * @link http://forge.typo3.org/issues/57695
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class UnicodeNormalizer implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * No unicode decomposition/composition
	 *
	 * Currently for compatibillity purposes to both existing implementations.
	 * Could also mean AUTO - It is meant to be used during future enhancements.
	 *
	 * @var integer
	 */
	const NONE = 1;

	/**
	 * Normalization Form D (NFD) - Canonical Decomposition
	 *
	 *   “A normalization form that erases any canonical differences,
	 *    and produces a decomposed result. For example, ä is converted
	 *    to a + umlaut in this form. This form is most often used in
	 *    internal processing, such as in collation.”
	 *
	 *     -- quoted from unicode glossary linked below
	 *
	 * @link http://www.unicode.org/glossary/#normalization_form_d
	 * @var integer
	 */
	const NFD  = 2, FORM_D  = 2;

	/**
	 * Normalization Form KD (NFKD) - Compatibility Decomposition
	 *
	 *   “A normalization form that erases both canonical and compatibility
	 *    differences, and produces a decomposed result: for example, the
	 *    single ǆ character is converted to d + z + caron in this form.”
	 *
	 *     -- quoted from unicode glossary linked below
	 *
	 * @link http://www.unicode.org/glossary/#normalization_form_kd
	 * @var integer
	 */
	const NFKD = 3, FORM_KD = 3;

	/**
	 * Normalization Form C (NFC) - Canonical Decomposition followed by Canonical Composition
	 *
	 *   “A normalization form that erases any canonical differences, and
	 *   generally produces a composed result. For example, a + umlaut is
	 *   converted to ä in this form. This form most closely matches
	 *   legacy usage.”
	 *
	 *     -- quoted from unicode glossary linked below
	 *
	 * @link http://www.unicode.org/glossary/#normalization_form_c
	 * @var integer
	 */
	const NFC  = 4, FORM_C  = 4;

	/**
	 * Normalization Form KC (NFKC) - Compatibility Decomposition followed by Canonical Composition
	 *
	 *   “A normalization form that erases both canonical and compatibility
	 *    differences, and generally produces a composed result: for example,
	 *    the single ǆ character is converted to d + ž in this form. This form
	 *    is commonly used in matching.”
	 *
	 *     -- quoted from unicode glossary linked below
	 *
	 * @link http://www.unicode.org/glossary/#normalization_form_kc
	 * @var integer
	 */
	const NFKC = 5, FORM_KC = 5;

	/**
	 * “Normalization Form. One of the four Unicode normalization forms … namely, NFC, NFD, NFKC, and NFKD.”
	 *     -- quoted from unicode glossary linked below
	 *
	 * Indicates which unicode normalization form to use. Must be set to one of the integer constants from
	 * above. Defaults to NONE.
	 *
	 * @link http://www.unicode.org/glossary/#normalization_form
	 * @see UnicodeNormalizer::NONE
	 * @see UnicodeNormalizer::NFC
	 * @see UnicodeNormalizer::NKC
	 * @see UnicodeNormalizer::NFD
	 * @see UnicodeNormalizer::NFKD
	 * @var integer
	 */
	protected $normalization = self::NONE;

	/**
	 * Gets a singleton instance of this class.
	 *
	 * @return \TYPO3\CMS\Core\Charset\UnicodeNormalizer
	 */
	static public function getInstance() {
		return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(__CLASS__);
	}

	/**
	 * Constructor
	 *
	 * @param integer|string $normalization Optionally set normalization form to one of the known constants; NONE is default.
	 * @see UnicodeNormalizer::parseNormalizationForm()
	 * @see http://www.php.net/manual/en/class.normalizer.php
	 */
	public function __construct($normalization = NULL) {
		if (self::registerImplementation()) {
			if ($normalization === NULL && isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['unicodeNormalization'])) {
				$normalization = $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicodeNormalization'];
			}
			$this->setNormalization($normalization);
		} else {
			throw new \Exception('Could not register unicode normalizer implementation.', 1401931878);
		}
	}

	/**
	 * Checks if the provided $string is already in the specified or current default normalization form.
	 *
	 * @param string $string The string to check.
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @return boolean TRUE if normalized, FALSE otherwise or if an error occurred.
	 * @see http://www.php.net/manual/en/normalizer.isnormalized.php
	 */
	public function isNormalized($string, $normalization = NULL) {
		// Hint: UnicodeNormalizerImpl is a class_alias defined in self::registerImplementation
		return UnicodeNormalizerImpl::isNormalized($string, $normalization === NULL ? $this->normalization : $normalization);
	}

	/**
	 * Normalizes the $string provided to the given $normalization or the default one, and returns the normalized string.
	 *
	 * Directly calls underlying implementation.
	 *
	 * @param string $string The string to normalize.
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @return string|NULL The normalized string or NULL if an error occurred.
	 * @see http://www.php.net/manual/en/normalizer.normalize.php
	 */
	public function normalize($string, $normalization = NULL) {
		// Hint: UnicodeNormalizerImpl is a class_alias defined in self::registerImplementation
		return UnicodeNormalizerImpl::normalize($string, $normalization === NULL ? $this->normalization : $normalization);
	}

	// Not really needed !
// 	public function normalizeNFD($string) {
// 		return $this->normalize($string, self::NFD);
// 	}

// 	public function normalizeNFKD($string) {
// 		return $this->normalize($string, self::NFKD);
// 	}

// 	public function normalizeNFC($string) {
// 		return $this->normalize($string, self::NFC);
// 	}

// 	public function normalizeNFKC($string) {
// 		return $this->normalize($string, self::NFKC);
// 	}

	/**
	 * Normalizes the $string provided to the given $normalization or the default one, and returns the normalized string.
	 *
	 * Always calls underlying implementation even if given normalization is NONE, but normalizes only if needed.
	 *
	 * @param string $string The string to normalize.
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @return string|NULL The normalized string or NULL if an error occurred.
	 * @see http://www.php.net/manual/en/normalizer.normalize.php
	 */
	public function normalizeTo($string, $normalization = NULL) {
		if ($this->isNormalized($string, $normalization)) {
			return $string;
		}
		return $this->normalize($string, $normalization);
	}

	// Not really needed !
// 	public function normalizeToNFD($string) {
// 		return $this->normalizeTo($string, self::NFD);
// 	}

// 	public function normalizeToNFKD($string) {
// 		return $this->normalizeTo($string, self::NFKD);
// 	}

// 	public function normalizeToNFC($string) {
// 		return $this->normalizeTo($string, self::NFC);
// 	}

// 	public function normalizeToNFKC($string) {
// 		return $this->normalizeTo($string, self::NFKC);
// 	}

	/**
	 * Normalizes the $string provided to the given $normalization or the default one, and returns the normalized string.
	 *
	 * Does not call underlying implementation if given normalization is NONE and normalizes only if needed.
	 *
	 * @param string $string The string to normalize.
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @return string|NULL The normalized string or NULL if an error occurred.
	 * @see http://www.php.net/manual/en/normalizer.normalize.php
	 */
	public function normalizeStringTo($string, $normalization = NULL) {
		if (self::NONE < (int) $normalization) {
			return $this->normalizeTo($string, $normalization);
		}
		return $string;
	}

	// Not really needed !
// 	public function normalizeStringToNFD($string, $onlyIfNeeded = TRUE) {
// 		return $this->normalizeStringTo($string, self::NFD, $onlyIfNeeded);
// 	}

// 	public function normalizeStringToNFKD($string, $onlyIfNeeded = TRUE) {
// 		return $this->normalizeStringTo($string, self::NFKD, $onlyIfNeeded);
// 	}

// 	public function normalizeStringToNFC($string, $onlyIfNeeded = TRUE) {
// 		return $this->normalizeStringTo($string, self::NFC, $onlyIfNeeded);
// 	}

// 	public function normalizeStringToNFKC($string, $onlyIfNeeded = TRUE) {
// 		return $this->normalizeStringTo($string, self::NFKC, $onlyIfNeeded);
// 	}

	/**
	 * Normalize all elements in ARRAY with type string to given unicode-normalization-form.
	 * NOTICE: Array is passed by reference!
	 *
	 * Always calls underlying implementation even if given normalization is NONE.
	 *
	 * @param array $array Input array, possibly multidimensional
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @return void
	 * @see UnicodeNormalizer::normalize()
	 */
	public function normalizeArray(array & $array, $normalization = NULL) {
		$this->processArrayWithMethod('normalize', $array, $normalization);
	}

	// Not really needed
// 	public function normalizeArrayNFD(array & $array) {
// 		$this->normalizeArray($array, self::NFD);
// 	}

// 	public function normalizeArrayNFKD(array & $array) {
// 		$this->normalizeArray($array, self::NFKD);
// 	}

// 	public function normalizeArrayNFC(array & $array) {
// 		return $this->normalizeArray($array, self::NFC);
// 	}

// 	public function normalizeArrayNFKC(array & $array) {
// 		$this->normalizeArray($array, self::NFKC);
// 	}

	/**
	 * Normalize all elements in ARRAY with type string to given unicode-normalization-form.
	 * NOTICE: Array is passed by reference!
	 *
	 * Does not call underlying implementation if given normalization is NONE and normalizes only if needed.
	 *
	 * @param array $array Input array, possibly multidimensional
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @return void
	 * @see UnicodeNormalizer::normalize()
	 */
	public function normalizeArrayTo(array & $array, $normalization = NULL) {
		if (self::NONE < (int) $normalization) {
			$this->processArrayWithMethod('normalizeTo', $array, $normalization);
		}
	}

	// Not really needed
// 	public function normalizeArrayToNFD(array & $array) {
// 		$this->normalizeArrayTo($array, self::NFD);
// 	}

// 	public function normalizeArrayToNFKD(array & $array) {
// 		$this->normalizeArrayTo($array, self::NFKD);
// 	}

// 	public function normalizeArrayToNFC(array & $array) {
// 		$this->normalizeArrayTo($array, self::NFC);
// 	}

// 	public function normalizeArrayToNFKC(array & $array) {
// 		$this->normalizeArrayTo($array, self::NFKC);
// 	}

	/**
	 * Ensures all that all (user-)inputs ($_FILES, $_ENV, $_GET, $_POST, $_COOKIE, $_SERVER, $_REQUEST)
	 * are normalized UTF-8 strings if needed (but without neccessarly beeing well-formed!).
	 *
	 * @param string $inputs comma-seperated list of global input-arrays as described above, but without leading "$_"
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @return void
	 * @see UnicodeNormalizer::normalizeArray()
	 */
	public function normalizeInputArrays($inputs, $normalization = NULL) {
		$this->processInputArraysWithMethod('normalizeTo', $inputs, $normalization);
	}

	/**
	 * Ensures all that all (user-)inputs ($_FILES, $_ENV, $_GET, $_POST, $_COOKIE, $_SERVER, $_REQUEST)
	 * are normalized UTF-8 strings if needed (but without neccessarly beeing well-formed!).
	 *
	 * @param string $inputs comma-seperated list of global input-arrays as described above, but without leading "$_"
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @return void
	 * @see UnicodeNormalizer::normalizeArray()
	 */
	public function normalizeInputArraysTo($inputs, $normalization = NULL) {
		if (self::NONE < (int) $normalization) {
			$this->processInputArraysWithMethod('normalizeTo', $inputs, $normalization);
		}
	}

	/**
	 * Ensures that given input is a well-formed and normalized UTF-8 string.
	 *
	 * This implementation has been taken from the contributed “Patchwork-UTF8” project's “Bootup::filterString()”-method and
	 * tweaked for our needs.
	 *
	 * @param string $string The string to filter
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @param string $leading_combining The default '\xe2\x97\x8c' is equivalent to '◌' a combining character as defined in
	 *                                  the glossary linked below. It is meant for internal usage as part of this
	 *                                  NFC-compatible string-filter method.
	 * @return string
	 * @link http://www.unicode.org/glossary/#combining_character
	 * @see \Patchwork\Utf8\Bootup::filterString()
	 * @todo TODO Feature #57695: Keep UnicodeNormalizer::filter method in sync with \Patchwork\Utf8\Bootup::filterString()
	 */
	public function filter($string, $normalization = NULL, $leading_combining = '\xe2\x97\x8c') {
		// TODO Feature #57695: Really use workaround for https://bugs.php.net/65732, means to enforce unix-linebreaks, everytime and everywhere !
		if (false !== strpos($string, "\r")) {
			$string = str_replace("\r\n", "\n", $string);
			$string = strtr($string, "\r", "\n");
		}

		if (preg_match('/[\x80-\xFF]/', $string)) {
			if ($this->isNormalized($string, $normalization)) {
				$normalized = '-';
			} else {
				$normalized = $this->normalize($string, $normalization);
				if (isset($normalized[0])) {
					$string = $normalized;
				} else {
					// TODO Feature #57695: Patchwork's original \Patchwork\Utf8\Bootup::filter() implementation assumes Windows-CP1252 as fallback … figure out why, as our code uses utf8_encode's ISO-8859-1.
					$string = utf8_encode($string);
				}
			}

			if ($string[0] >= "\x80" && isset($normalized[0], $leading_combining[0]) && preg_match('/^\p{Mn}/u', $string)) {
				// Prevent leading combining chars for NFC-safe concatenations.
				$string = $leading_combining . $string;
			}
		}

		return $string;
	}

	/**
	 * Ensures for all elements in ARRAY with type string to be well-formed and normalized UTF-8 strings.
	 * NOTICE: Array is passed by reference!
	 *
	 * @param array $array Input array, possibly multidimensional
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @return void
	 * @see UnicodeNormalizer::filter()
	 */
	public function filterArray(array & $array, $normalization = NULL) {
		$this->processArrayWithMethod('filter', $array, $normalization);
	}

	/**
	 * Ensures that "ALL" or the given (user-)inputs ($_FILES, $_ENV, $_GET, $_POST, $_COOKIE, $_SERVER, $_REQUEST)
	 * are well-formed and normalized UTF-8 strings.
	 *
	 * This implementation has been inspired by the contributed “Patchwork-UTF8” project's
	 * “Bootup::filterRequestInputs()”-method and tweaked for our needs.
	 *
	 * @param string $inputs comma-seperated list of global input-arrays as described above, but without leading "$_"
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @return void
	 * @see UnicodeNormalizer::filterArray()
	 * @todo TODO Feature #57695: Use UnicodeNormalizer::filterInputArrays method during core-bootstrap ? If yes, then avoid double-encoding by TSFE->convPOSTCharset !
	 */
	public function filterInputArrays($inputs, $normalization = NULL) {
		$this->processInputArraysWithMethod('filter', $inputs, $normalization);
	}

	/**
	 * Ensures the URL is well formed UTF-8. When not, assumes ISO-8859-1 and
	 * re-encodes the URL to the corresponding UTF-8 encoded equivalent.
	 *
	 * This implementation has been taken from the contributed “Patchwork-UTF8”
	 * project's “Bootup::filterRequestUri()”-method and tweaked for our needs.
	 *
	 *
	 * @param string $uri
	 * @return string
	 * @see \Patchwork\Utf8\Bootup::filterRequestUri()
	 * @todo TODO Feature #57695: Keep UnicodeNormalizer::filterUri method in sync with \Patchwork\Utf8\Bootup::filterRequestUri()
	 * @todo TODO Feature #57695: Patchwork's original \Patchwork\Utf8\Bootup::filterRequestUri() implementation assumes Windows-CP1252 as fallback … figure out why, as our code uses utf8_encode's ISO-8859-1.
	 */
	public function filterUri($uri) {

		// is uri empty or is the url-decoded uri already valid utf8 ?
		if (empty($uri) || preg_match('//u', urldecode($uri))) {
			return $uri;
		}

		// Ensures the URL is well formed UTF-8
		// When not, assumes Windows-1252 and redirects to the corresponding UTF-8 encoded URL

		// encode all unencoded single-byte characters from 128 to 255
		$uri = preg_replace_callback(
			'/[\x80-\xFF]+/',
			function($m) {return urlencode($m[0]);},
			$uri
		);

		// encode all unencoded multibyte-byte characters from 128 and above
		$uri = preg_replace_callback(
			'/(?:%[89A-F][0-9A-F])+/i',
			// url-decode -> utf8-encode -> url-encode
			function($m) {return urlencode(utf8_encode(urldecode($m[0])));},
			$uri
		);

		return $uri;
	}

	/**
	 * Set the current normalization-form constant to the given $normalization. Also see constructor.
	 *
	 * @param integer|string $normalization
	 * @return void
	 * @see UnicodeNormalizer::parseNormalizationForm
	 * @throws \InvalidArgumentException
	 */
	public function setNormalization($normalization) {
		if (!is_integer($normalization)) {
			$normalization = self::parseNormalizationForm($normalization);
		}
		if (!in_array((int) $normalization, range(1, 5), TRUE)) {
			throw new \Exception(sprintf('Unknown unicode-normalization form: %s.', $normalization), 1398603947);
		}
		$this->normalization = (int) $normalization;
	}

	/**
	 * Retrieve the current normalization-form constant.
	 *
	 * @return integer
	 */
	public function getNormalization() {
		return $this->normalization;
	}

	/**
	 * Process all elements in ARRAY with type string with a method of this object.
	 * NOTICE: Array is passed by reference!
	 *
	 * @param string $method the method used to process the value of all strings (either 'normalize' or 'filter')
	 * @param array $array Input array, possibly multidimensional
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @return void
	 */
	protected function processArrayWithMethod($method, array & $array, $normalization = NULL) {
		foreach ($array as & $item) {
			if (empty($item)) {
				continue;
			} elseif (is_array($item)) {
				$this->processArrayWithMethod($method, $item, $normalization);
			} elseif (is_string($item)) {
				$item = $this->{$method}($item, $normalization);
			}
		}
	}

	/**
	 * Ensures all that all (user-)inputs ($_FILES, $_ENV, $_GET, $_POST, $_COOKIE, $_SERVER, $_REQUEST)
	 * are (well formed and) normalized UTF-8 if needed.
	 *
	 * This implementation has been inspired by the contributed “Patchwork-UTF8” project's
	 * “Bootup::filterRequestInputs()”-method and tweaked for our needs.
	 *
	 * @param string $method the method used to process the value of all strings (either 'normalize' or 'filter')
	 * @param string $inputs comma-seperated list of global input-arrays as described above, but without leading "$_"
	 * @param integer $normalization An optional normalization form to check against, overriding the default; see constructor.
	 * @return void
	 * @see \Patchwork\Utf8\Bootup::filterRequestInputs()
	 */
	protected function processInputArraysWithMethod($method, $inputs = 'ALL', $normalization = NULL) {
		$inputs = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', strtoupper($inputs), TRUE);
		if (empty($inputs)) {
			return ;
		}
		$inputs = array_unique($inputs);
		$all = in_array('ALL', $inputs, TRUE);
		foreach (array(
			'GET' => &$_GET,
			'POST' => &$_POST,
			'FILES' => &$_FILES,
			'COOKIE' => &$_COOKIE,
			'REQUEST' => &$_REQUEST,
			'SERVER' => &$_SERVER,
			'ENV' => &$_ENV,
		) as $name => $array) {
			if ($all || in_array($name, $inputs, TRUE)) {
				if (!is_array($array) || empty($array)) {
					continue ;
				}
				$this->processArrayWithMethod($method, $array, $normalization);
			}
		}
	}

	/**
	 * Converts the given value to a known normalization-form constant.
	 *
	 * Examples of supported values:
	 * - case-insensitive abbreviation strings: D, KD, C, KC, NFC, nfc, FORM_C, formKC, …
	 * - integers from 0 to 5, which are: 0 = NONE, 1 = AUTO, 2 = NFD, 3 = NFKD, 4 = NFC, 5 = NFKC
	 * - case-insensitive keywords: none, default, disable(d), precompose(d), decompose(d)
	 *
	 * Meaning of the values:
	 * - 0: No Unicode-Normalization (NONE, disabled)    : disables any normalization-attempts
	 * - 1: Default Unicode-Normalization (AUTO, default): currently it disables any normalization-attempts too, but future
	 *                                                     implementations may automatically handle the normalization
	 * - 2: Normalization Form D (NFD, decomposed)       : canonical decomposition
	 * - 3: Normalization Form KD (NFKD)                 : compatibility decomposition
	 * - 4: Normalization Form C (NFC, precomposed)      : canonical decomposition followed by canonical composition
	 * - 5: Normalization Form KC (NFKC)                 : compatibility decomposition followed by canonical composition
	 *
	 * Hints:
	 * - The W3C recommends NFC for HTML5 Output.
	 * - Mac OSX's HFS+ filesystem uses NFD to store paths. It provides significant faster sorting algorithms. Even if you
	 *   choose something else than NFD here HFS+ Filesystems will always use NFD and decompose path-strings if needed.
	 *
	 * @param string|integer $value
	 */
	public static function parseNormalizationForm($value) {
		$value = trim((string) $value);
		if ($value === '0' || in_array((int) $value, range(1, 5))) {
			return max(1, (int) $value);
		}

		$value = str_replace(array('NF','FORM_','FORM'), '', strtoupper($value));

		switch($value) {
			case 'D':
			case 'DECOMPOSE':
			case 'DECOMPOSED':
				return self::NFD;
			case 'KD':
				return self::NFKD;
// Remember: if the following lines get enabled, remove '1' from initial if-conditional and the second occurrence below
// 			case '1':
// 			case 'AUTO':
// 			case 'DEFAULT':
			case 'C':
			case 'COMPOSE':
			case 'COMPOSED':
				return self::NFC;
			case 'KC':
				return self::NFKC;
// 			case '0':
// 			case '1':
// 			case 'NONE':
// 			case 'DISABLED':
// 			default:
// 				return self::NONE;
		}

		return self::NONE;
	}

	const NORMALIZER_INTL = 'Normalizer';
	const NORMALIZER_PATCHWORK = 'Patchwork\\PHP\\Shim\\Normalizer';

	/**
	 * Registers a class-alias for the globally configured normalizer implementation. This happens only once !
	 *
	 * @throws \InvalidArgumentException
	 * @return boolean TRUE on success
	 */
	static protected function registerImplementation() {
		$implClass = __CLASS__ . 'Impl';
		$stubClass = __CLASS__ . 'Stub';
		if (class_exists($implClass, FALSE)) {
			return TRUE;
		}
		if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['unicodeNormalizer'])) {
			return FALSE;
		}
		$implementation = trim(strtolower($GLOBALS['TYPO3_CONF_VARS']['SYS']['unicodeNormalizer']));

		if ($implementation === 'intl') {
			return class_alias(self::NORMALIZER_INTL, $implClass, TRUE);
		} else if($implementation === 'patchwork') {
			return class_alias(self::NORMALIZER_PATCHWORK, $implClass, TRUE);
		} else if($implementation === 'stub') {
			return class_alias($stubClass, $implClass, TRUE);
		} else if(strstr($implementation, '\\')) {
			return class_alias($implementation, $implClass, TRUE);
		}

		throw new \Exception(sprintf('Invalid unicode-normalizer implementation: %s.', $implementation), 1399749988);
	}

	/**
	 *
	 * @param string $absolutePath
	 * @return array<boolean, array>
	 * @todo TODO Feature #57695: Find the right place for UnicodeNormalizer::detectUtf8CapabilitiesForPath and add proper documentation - and isolate normalization part back here.
	 */
	static public function detectUtf8CapabilitiesForPath($absolutePath) {
		$capabilities = array();

		if (empty($absolutePath) || !is_dir($absolutePath)) {
			return $capabilities;
		}

		$currentLocale = setlocale(LC_CTYPE, 0);
		$capabilities['locale'] = FALSE;
		if (strstr($currentLocale, '.') !== FALSE &&
			strstr(strtolower(str_replace('-', '', $currentLocale)), '.utf8') === FALSE)
		{
			return $capabilities;
		}
		$capabilities['locale'] = TRUE;

		$testString = 'ÖöĄĆŻĘĆćążąęó.txt';
		$quote = TYPO3_OS === 'WIN' ? '"' : '\'';

		$capabilities['escape'] = FALSE;
		if (escapeshellarg($testString) !== $quote . $testString . $quote) {
			return $capabilities;
		}
		$capabilities['escape'] = TRUE;

		$fileNames = array(
			// not normalized variant of $testString from above partially in NFC and partially in NFD
			self::NONE => 'c396c3b6c484c486c5bbc498c48663cc8161cca87acc8761cca865cca86fcc812e747874',
			// NFD-normalized variant of $testString from above
			self::NFD => '4fcc886fcc8841cca843cc815acc8745cca843cc8163cc8161cca87acc8761cca865cca86fcc812e6a7067',
			// Not supported
			self::NFKD => FALSE,
			// NFC-normalized variant of $testString from above
			self::NFC => 'c396c3b6c484c486c5bbc498c486c487c485c5bcc485c499c3b32e6a7067',
			// Not supported
			self::NFKC => FALSE,
		);

		$basePath = $absolutePath . '/' . tempnam($absolutePath, 'unicode-normalization-support-test-');

		// The temporary base path should be missing, so try to create it
		if (is_dir($basePath) || !mkdir($basePath)) {
			return $capabilities;
		}

		// The base dir must exist
		if (!is_dir($basePath)) {
			return $capabilities;
		}

		$normalizations = array();
		foreach($fileNames as $normalization => $fileName) {
			if ($fileName === FALSE) {
				$normalizations[$normalization] = FALSE;
				unset($fileNames[$normalization]);
				continue ;
			}
			$normalizations[$normalization] = array('read'=>FALSE, 'write'=>FALSE);
			$fileName = $normalization . '-' . hex2bin($fileName);
			$fileNames[$normalization] = $fileName;
			if (touch($basePath . '/' . $fileName)) {
				$normalizations[$normalization]['write'] = TRUE;
			}
		}

		$handle = opendir($basePath);
		while(is_resource($handle) && (FALSE !== ($entry = readdir($handle)))) {
			if ($entry[0] === '.') {
				continue;
			}
			foreach($fileNames as $normalization => $fileName) {
				if ($normalizations[$normalization]['read'] === TRUE) {
					continue;
				}
				if ($entry === $fileName) {
					// If all files exist then the filesystem does not normalize unicode.
					// If some files are missing then the filesystem, either normalizes unicode
					// or it denies access to not-normalized filepaths or it simply does not
					// support unicode at all or at least not those normalization forms we test.
					$normalizations[$normalization]['read'] = TRUE;
				}
			}
			unlink($basePath . '/' . $entry);
		}
		closedir($handle);
		rmdir($basePath);

		$capabilities['normalization'] = array_map(function($normalization) {
			if (FALSE === $normalization) {
				return $normalization;
			} else if (!in_array(FALSE, $normalization, TRUE)) {
				return TRUE;
			} else if (in_array(TRUE, $normalization, TRUE)) {
				return $normalization;
			}
			return FALSE;
		}, $normalizations);

		return $capabilities;
	}
}
