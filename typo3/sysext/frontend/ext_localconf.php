<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

if (TYPO3_MODE === 'FE' && !isset($_REQUEST['eID'])) {
	\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\SignalSlot\\Dispatcher')->connect(
		'TYPO3\\CMS\\Core\\Resource\\Index\\MetaDataRepository',
		'recordPostRetrieval',
		'TYPO3\\CMS\\Frontend\\Aspect\\FileMetadataOverlayAspect',
		'languageAndWorkspaceOverlay'
	);
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('
	options.saveDocNew.sys_file = 0
	options.saveDocNew.sys_file_metadata = 0
	options.disableDelete.sys_file = 1
');

if ($GLOBALS['TYPO3_CONF_VARS']['FE']['redirectToUtf8EncodedRequestUriIfNeeded']) {
	// Register hook to redirect to unicode-nomalized request-uri if needed
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkAlternativeIdMethods-PostProc'][] = 'TYPO3\\CMS\\Frontend\\Hooks\\UnicodeNormalizationHooks->hook_redirectRequestUriIfNeeded';
}

