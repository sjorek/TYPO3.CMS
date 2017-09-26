<?php
namespace TYPO3\CMS\Install\Updates;

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

use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Migrate resource storage configurations adding UTF8-filesystem configuration
 */
class ResourceStorageConfigurationUpdate extends AbstractUpdate
{
    /**
     * @var string
     */
    protected $title = 'Migrate resource storage configurations and add UTF8-filesystem configuration';

    /**
     * Checks if an update is needed
     *
     * @param string &$description The description for the update
     * @return bool Whether an update is needed (TRUE) or not (FALSE)
     */
    public function checkForUpdate(&$description)
    {
        if ($this->isWizardDone()) {
            return false;
        }
        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $elementCount = $queryBuilder->count('uid')
            ->from('sys_file_storage')
            ->where(
                $queryBuilder->expr()->notLike(
                    'constants',
                    $queryBuilder->createNamedParameter('%<UTF8filesystem>%', \PDO::PARAM_STR)
                )
            )
            ->execute()->fetchColumn(0);
        if ($elementCount) {
            $description = 'Add UTF-8 filesystem configuration to resources storages.';
        }
        return (bool)$elementCount;
    }

    /**
     * Performs the database update
     *
     * @param array &$databaseQueries Queries done in this update
     * @param string &$customMessage Custom message
     * @return bool
     */
    public function performUpdate(array &$databaseQueries, &$customMessage)
    {
        $UTF8filesystem = (int) $GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem'];

        /** @var FlexFormTools $flexObj */
        $flexObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(FlexFormTools::class);

        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $queryBuilder = $connectionPool->getQueryBuilderForTable('sys_file_storage');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder->select('uid', 'driver', 'configuration')
            ->from('sys_file_storage')
            ->where(
                $queryBuilder->expr()->notLike(
                    'constants',
                    $queryBuilder->createNamedParameter('%<UTF8filesystem>%', \PDO::PARAM_STR)
                )
            )
            ->execute();

        while ($record = $statement->fetch()) {
            $configuration = GeneralUtility::xml2array($record['configuration']);
            if($record['driver'] === 'Local') {
                $configuration['data']['sDEF']['lDEF']['UTF8filesystem'] = array('vDEF' => $UTF8filesystem);
            } else {
                // TODO Feature #57695: assume utf-8 file name support for all non-local storages or better use detection?
                $configuration['data']['sDEF']['lDEF']['UTF8filesystem'] = array('vDEF' => 1);
            }
            $configuration = $flexObj->flexArray2Xml($configuration);

            $queryBuilder = $connectionPool->getQueryBuilderForTable('sys_file_storage');
            $queryBuilder->update('sys_file_storage')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($record['uid'], \PDO::PARAM_INT)
                    )
                )
                ->set('configuration', $configuration);

            $databaseQueries[] = $queryBuilder->getSQL();
            $queryBuilder->execute();
        }
        $this->markWizardAsDone();
        return true;
    }
}
