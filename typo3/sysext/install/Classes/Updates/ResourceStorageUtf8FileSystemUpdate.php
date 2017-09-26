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
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Charset\UnicodeNormalizer;

/**
 * Migrate resource identifier hashs if UTF8-filesystem configuration has been changed
 */
class ResourceStorageUtf8FileSystemUpdate extends AbstractUpdate
{
    /**
     * @var string
     */
    protected $title = 'Update local storage UTF8-filesystem configurations and local resource identifier hashs';

    /**
     * Checks if an update is needed
     *
     * @param string &$description The description for the update
     * @return bool Whether an update is needed (TRUE) or not (FALSE)
     */
    public function checkForUpdate(&$description)
    {
        // This wizard can run, whenever needed!
        //if ($this->isWizardDone()) {
        //    return false;
        //}

        $description = 'Re-hash resources identifiers for storages with changed utf8-filesystem configuration '
                        . 'and update storage configuration accordingly.';

        // FIXME #57695 - implement a ResourceStorageRegistryUpdate to clean old entries from registry

        $UTF8filesystem = (int) $GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem'];

        /** @var Registry $registry */
        $registry = GeneralUtility::makeInstance(Registry::class);
        if ($UTF8filesystem !== (int) $registry->get('UTF8filesystem', 0, -1)) {
            return true;
        }

        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $queryBuilder = $connectionPool->getQueryBuilderForTable('sys_file_storage');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder->select('uid')->from('sys_file_storage')->execute();

        $resourceFactory = ResourceFactory::getInstance();
        while ($record = $statement->fetch()) {
            $storage = $resourceFactory->getStorageObject($record['uid']);
            $storageUTF8filesystem = $storage->getUtf8FileSystemMode();
            if ($storage->getDriverType() === 'Local' && $storageUTF8filesystem !== $UTF8filesystem) {
                return true;
            } elseif ($storageUTF8filesystem !== (int) $registry->get('UTF8filesystem', $record['uid'], -1)) {
                return true;
            }
        }

        unset($description);
        return false;
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

        $resourceFactory = ResourceFactory::getInstance();

        /** @var FlexFormTools $flexObj */
        $flexObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(FlexFormTools::class);

        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $queryBuilder = $connectionPool->getQueryBuilderForTable('sys_file_storage');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder->select('uid', 'driver', 'configuration')->from('sys_file_storage')->execute();

        $internalFallbackStorageRecord = [
            'uid' => 0,
            'driver' => 'Local',
            'configuration' => array(
                'UTF8filesystem' => $UTF8filesystem
            )
        ];

        while (($record = $internalFallbackStorageRecord) || ($record = $statement->fetch())) {
            $internalFallbackStorageRecord = false;

            if ($record['uid'] === 0) {
                $configuration = $record['configuration'];
            } else {
                $configuration = $resourceFactory->convertFlexFormDataToConfigurationArray($record['configuration']);
            }
            $storageUTF8filesystem = (int) $configuration['UTF8filesystem'];
            $registryUTF8filesystem = (int) $registry->get('UTF8filesystem', $record['uid'], -1);
            
            // If nothing has changed, skip updating this storage
            if (($record['driver'] !== 'Local' || $storageUTF8filesystem === $UTF8filesystem) &&
                $storageUTF8filesystem ===  $registryUTF8filesystem)
            {
                continue;
            }

            // Skip updating the internal fallback storage and non-local storages that have not changed 
            if ($record['uid'] !== 0 && $record['driver'] === 'Local' && $storageUTF8filesystem !== $UTF8filesystem) {
                $configuration = GeneralUtility::xml2array($record['configuration']);
                $configuration['data']['sDEF']['lDEF']['UTF8filesystem'] = array('vDEF' => $UTF8filesystem);
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

            $storage = $resourceFactory->getStorageObject($record['uid']);
            $storageUTF8filesystem = $storage->getUtf8FileSystemMode();

            // Only re-calculate hashes if the current or former (registry) value indicates unicode-normalization
            if(UnicodeNormalizer::NONE < $storageUTF8filesystem ||
               UnicodeNormalizer::NONE < $registryUTF8filesystem)
            {
                $queryBuilder = $connectionPool->getQueryBuilderForTable('sys_file');
                $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
                $queryBuilder->select('uid', 'identifier', 'identifier_hash', 'folder_hash')
                    ->from('sys_file')
                    ->where(
                        $queryBuilder->expr()->eq(
                            'storage',
                            $queryBuilder->createNamedParameter($record['uid'], \PDO::PARAM_INT)
                        )
                    );

                while ($fileRecord = $statement->fetch()) {

                    // A non-existing file, could mean that the storage's unicode normalization form is wrong.
                    if (!$storage->hasFile($fileRecord['identifier'])) {
                        continue; 
                    }

                    $identifierHash = $storage->hashFileIdentifier($fileRecord['identifier']);
                    $folderIndentifier = $storage->getFolderIdentifierFromFileIdentifier($fileRecord['identifier']);
                    $folderHash = $storage->hashFileIdentifier($folderIndentifier);

                    if ($identifierHash === $fileRecord['identifier_hash'] &&
                        $folderHash === $fileRecord['folder_hash'])
                    {
                        continue;
                    }
                    $queryBuilder = $connectionPool->getQueryBuilderForTable('sys_file_storage');
                    $queryBuilder->update('sys_file_storage')
                        ->where(
                            $queryBuilder->expr()->eq(
                                'uid',
                                $queryBuilder->createNamedParameter($fileRecord['uid'], \PDO::PARAM_INT)
                            )
                        )
                        ->set('identifier_hash', $identifierHash)
                        ->set('folder_hash', $folderHash);

                    $databaseQueries[] = $queryBuilder->getSQL();
                    $queryBuilder->execute();
                }
            }

            $registry->set('UTF8filesystem', $record['uid'], $storageUTF8filesystem);
        }
        $this->markWizardAsDone();
        return true;
    }
}