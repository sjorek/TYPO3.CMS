#!/usr/bin/env php
<?php
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

$unicodeVersions = ['6.3.0', '7.0.0', '8.0.0', '9.0.0', '10.0.0'];

if (php_sapi_name() !== 'cli') {
    die('Script must be called from command line.' . chr(10));
}

if ($argc !== 1) {
    die('Invalid amount of command line arguments.' . chr(10));
}

$fixturePath = dirname(dirname(__DIR__)) . '/typo3/sysext/core/Tests/Unit/Charset/Fixtures';
if (!is_dir($fixturePath)) {
    die(sprintf('Path to fixtures "%s" does not exist. '
        . 'Please run this script from the TYPO3 root.', $fixturePath) . chr(10));
}
require $fixturePath .'/UnicodeNormalizationTestUpdater.php';

\TYPO3\CMS\Core\Tests\Unit\Charset\Fixtures\UnicodeNormalizationTestUpdater::setup();

foreach($unicodeVersions as $unicodeVersion) {

    try {
        $updater = new \TYPO3\CMS\Core\Tests\Unit\Charset\Fixtures\UnicodeNormalizationTestUpdater(
            $unicodeVersion
        );
        echo sprintf('Fetching unicode version %s from: %s', $unicodeVersion, $updater->source) . chr(10);
    
        $writer = new \TYPO3\CMS\Core\Tests\Unit\Charset\Fixtures\UnicodeNormalizationTestWriter(
            $unicodeVersion,
            basename(__FILE__),
            $updater->source
        );
        echo sprintf('Importing unicode version %s to %s', $unicodeVersion, $writer->filePath) . chr(10) . chr(10);

        foreach($updater as $lineNumber => $data) {
            $line = array_shift($data);
            $comment = array_shift($data);
            if ($comment) {
                echo sprintf('Processed line %s: %s', $lineNumber, $comment) . chr(10);
            }
            $writer->add($line);
        }
        echo sprintf('Imported unicode version %s to %s', $unicodeVersion, $writer->filePath) . chr(10) . chr(10);

    } catch (\Exception $e) {
        die(sprintf('An error occurred: %s', $e->getMessage()) . chr(10));
    }
}
