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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Tests\Unit\Charset\Fixtures\StubCustomNormalizer;

/**
 * Testcase for \TYPO3\CMS\Core\Charset\Unicode\CustomNormalizer
 *
 * @author Stephan Jorek <stephan.jorek@gmail.com>
 */
class RegisterCustomNormalizerTest extends \TYPO3\TestingFramework\Core\Unit\UnitTestCase
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
                StubCustomNormalizer::class,
                true
            ))
        {
            $this->markTestSkipped('Skipped test as "CustomNormalizer" has been set up somewhere else. '
                                   . 'Please run this test standalone.');
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['unicode']['customNormalizerClass'] = StubCustomNormalizer::class;
            // We must not use a "use"-statement in the head, as we test the class-aliasing !
            $this->subject = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Charset\\Unicode\\CustomNormalizer');
        }
    }

    /**
     * @test
     */
    public function checkCustomNormalizerExists()
    {
        $this->assertInstanceOf(
            StubCustomNormalizer::class, 
            $this->subject
        );
    }

    /**
     * @test
     */
    public function checkCustomNormalizerIdentifierSameAsStub()
    {
        $this->assertSame(
            StubCustomNormalizer::IMPLEMENTATION_IDENTIFIER,
            // We must not use a "use"-statement in the head, as we test the class-aliasing !
            \TYPO3\CMS\Core\Charset\Unicode\CustomNormalizer::IMPLEMENTATION_IDENTIFIER
        );
    }
}