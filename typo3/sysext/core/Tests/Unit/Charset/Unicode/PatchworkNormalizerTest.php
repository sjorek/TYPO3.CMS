<?php
namespace TYPO3\CMS\Core\Tests\Unit\Charset\Unicode;

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
use TYPO3\CMS\Core\Tests\Unit\Charset\Fixtures;

/**
 * Testcase for \TYPO3\CMS\Core\Charset\Unicode\PatchworkNormalizer
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class PatchworkNormalizerTest extends AbstractNormalizerTestCase
{
    /**
     * @var \TYPO3\CMS\Core\Charset\Unicode\PatchworkNormalizer
     */
    protected $subject;

    /**
     * @var string
     */
    protected $implementationClass = PatchworkNormalizer::class;

    /**
     *
     * @param boolean $assert
     * @param string $string
     * @param integer|null $form
     * @test
     * @covers \TYPO3\CMS\Core\Charset\Unicode\PatchworkNormalizer::isNormalized()
     * @dataProvider provideCheckIsNormalizedData
     */
    public function checkIsNormalized($assert, $string, $form)
    {
        if ($assert) {
            $this->assertTrue($this->subject->isNormalized($string, $form));
        } else {
            $this->assertFalse($this->subject->isNormalized($string, $form));
        }
    }

    /**
     *
     * @param string|false $same
     * @param string $string
     * @param integer|null $form
     * @test
     * @covers \TYPO3\CMS\Core\Charset\Unicode\PatchworkNormalizer::normalize()
     * @dataProvider provideCheckNormalizeData
     */
    public function checkNormalize($same, $string, $form)
    {
        if ($same !== false) {
            $this->assertSame($same, $this->subject->normalize($string, $form));
        } else {
            $this->assertFalse($this->subject->normalize($string, $form));
        }
    }

    /**
     *
     * @param string $unicodeVersion
     * @param integer $form
     * @param Fixtures\UnicodeNormalizationTestReader $fileIterator
     * @test
     * @large
     * @covers \TYPO3\CMS\Core\Charset\Unicode\PatchworkNormalizer::normalize()
     * @dataProvider provideCheckNormalizeConformanceData
     */
    public function checkNormalizeConformance(
        $unicodeVersion, $form, Fixtures\UnicodeNormalizationTestReader $fileIterator
    ) {
        foreach($fileIterator as $lineNumber => $data) {
            list($comment, $codes) = $data;
            $testIterator = $this->checkNormalizeConformanceIterator(
                $unicodeVersion, $form, $lineNumber, $comment, $codes
            );
            foreach($testIterator as $message => $data) {
                list($expected, $actual) = $data;
                $this->assertSame($expected, $this->subject->normalize($actual, $form), $message);
            }
        }
    }
}