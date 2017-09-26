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

use TYPO3\CMS\Core\Charset\Unicode\MissingNormalizerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for \TYPO3\CMS\Core\Charset\Unicode\CustomNormalizer
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class MissingCustomNormalizerTest extends \TYPO3\TestingFramework\Core\Unit\UnitTestCase
{

    /**
     * @var \TYPO3\CMS\Core\Charset\Unicode\MissingNormalizerInterface
     */
    protected $subject;

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    public function setUp()
    {
        if (class_exists('TYPO3\\CMS\\Core\\Charset\\Unicode\\CustomNormalizer', false) &&
            !is_a(
                'TYPO3\\CMS\\Core\\Charset\\Unicode\\CustomNormalizer',
                MissingNormalizerInterface::class,
                true
            ))
        {
            $this->markTestSkipped('Skipped test as "CustomNormalizer" has been set up by another test. '
                                   . 'Please run this test standalone.');
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['customNormalizerClass'] = 'Non\Existent\CustomNormalizer';
            // We must not use a "use"-statement in the head, as we test the class-aliasing !
            $this->subject = GeneralUtility::makeInstance('TYPO3\CMS\Core\Charset\Unicode\CustomNormalizer');
        }
    }

    /**
     * @test
     */
    public function checkNonExistantCustomNormalizerIsAMissingNormalizer()
    {
        $this->assertInstanceOf(
            MissingNormalizerInterface::class,
            $this->subject
        );
    }

    /**
     * @test
     */
    public function checkNonExistantCustomNormalizerIdentifierSameAsMissing()
    {
        $this->assertSame(
            MissingNormalizerInterface::IMPLEMENTATION_IDENTIFIER,
            // We must not use a "use"-statement in the head, as we test the class-aliasing !
            \TYPO3\CMS\Core\Charset\Unicode\CustomNormalizer::IMPLEMENTATION_IDENTIFIER
        );
    }

    /**
     * @test
     * @expectedException \Exception
     * @expectedExceptionMessage Missing unicode normalizer implementation
     * @expectedExceptionCode 1506447027
     * @covers \TYPO3\CMS\Core\Charset\Unicode\MissingNormalizerInterface::isNormalized()
     * @covers \TYPO3\CMS\Core\Charset\Unicode\MissingNormalizerTrait::isNormalized()
     */
    public function checkIsNormalizedThrowsException()
    {
        $this->subject->isNormalized('xxx');
    }

    /**
     * @test
     * @expectedException \Exception
     * @expectedExceptionMessage Missing unicode normalizer implementation
     * @expectedExceptionCode 1506447027
     * @covers \TYPO3\CMS\Core\Charset\Unicode\MissingNormalizerInterface::normalize()
     * @covers \TYPO3\CMS\Core\Charset\Unicode\MissingNormalizerTrait::normalize()
     */
    public function checkNormalizeThrowsException()
    {
        $this->subject->normalize('xxx');
    }
}