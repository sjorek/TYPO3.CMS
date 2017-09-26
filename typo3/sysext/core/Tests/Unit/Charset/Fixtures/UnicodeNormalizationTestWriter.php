<?php
namespace TYPO3\CMS\Core\Tests\Unit\Charset\Fixtures;

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
  * A file object to write "UnicodeNormalizationTest.X.Y.Z.txt" fixture files.
  * Attention: You must not use any TYPO3 dependencies in here!
  *  
  * @author Stephan Jorek <stephan.jorek@gmail.com>
  */
class UnicodeNormalizationTestWriter extends \SplFileObject {

    const FIRST_LINE  = '# Generator : %s';
    const SECOND_LINE = '# Source    : %s';

    /**
     * @var string
     */
    public $filePath;

    /**
     * Constructor
     *
     * @param $unicodeVersion string
     * @param $sourceTemplate string
     */
    public function __construct($unicodeVersion, $generator, $source)
    {
        $destinationTemplate = __DIR__ . '/UnicodeNormalizationTest.%s.txt.gz';
        $this->filePath = sprintf($destinationTemplate, $unicodeVersion);
        parent::__construct('compress.zlib://' . $this->filePath, 'w', false);
        $this->add(sprintf(self::FIRST_LINE, $generator) . chr(10));
        $this->add(sprintf(self::SECOND_LINE, $source) . chr(10));
        $this->add('# --------------------------------------------------------------------------------' . chr(10));
    }

    /**
     * @param string $line
     */
    public function add($line)
    {
        if ($this->fwrite($line) === null) {
            throw new \Exception('Could not write "UnicodeNormalizationTest.X.Y.Z.txt.gz file.');
        }
    }
}
