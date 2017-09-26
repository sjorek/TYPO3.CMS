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
  * An iterator to read "UnicodeNormalizationTest.X.Y.Z.txt.gz" fixture files.
  *  
  * @author Stephan Jorek <stephan.jorek@gmail.com>
  */
class UnicodeNormalizationTestReader implements \IteratorAggregate {

    /**
     * @var string
     */
    public $unicodeVersion;

    /**
     * @var string
     */
    public $source;

    /**
     * @var \SplFileObject
     */
    protected $fileObject;

    /**
     * @var \Iterator
     */
    protected $iterator;

    /**
     * Constructor
     *
     * @param $unicodeVersion string
     */
    public function __construct ($unicodeVersion)
    {

        $this->unicodeVersion = $unicodeVersion;

        $sourceTemplate = __DIR__ . '/UnicodeNormalizationTest.%s.txt.gz';
        $this->source = sprintf($sourceTemplate, $this->unicodeVersion);

        $this->fileObject = new \SplFileObject('compress.zlib://' . $this->source, 'r', false);
        $array = iterator_to_array(
            (function() {
                foreach ($this->fileObject as $lineNumber => $line) {
                    $lineNumber += 1;
                    yield from $this->processLine($lineNumber, $line);
                }
            })(),
            true
        );
        unset($this->fileObject);
        $this->iterator = new \ArrayIterator($array);
    }

    /**
     * @return \Iterator
     */
    public function getIterator()
    {
        return $this->iterator;
    }

    /**
     * @param integer $lineNumber
     * @param string $line
     * @throws \Exception
     * @return string[]
     */
    public function processLine($lineNumber, $line)
    {
        $codesAndComment = explode('#', $line);
        $codes = explode(';', array_shift($codesAndComment));
        $comment = array_pop($codesAndComment);
        $comment = explode(')', $comment);
        $comment = trim(array_pop($comment));

        if (count($codes) !== 7) {
            return;
        }

        $codes = array_map('trim', $codes);
        $codes = array_filter($codes);
        $codes = array_map('hex2bin', $codes);

        yield $lineNumber => [$comment, $codes];
    }
}
