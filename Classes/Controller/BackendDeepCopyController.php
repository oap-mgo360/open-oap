<?php

declare(strict_types=1);

namespace OpenOAP\OpenOap\Controller;

use OpenOAP\OpenOap\Domain\Model\Call;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

#[AsController]
class BackendDeepCopyController extends OapBackendController
{
    const TRANS = 'LLL:EXT:open_oap/Resources/Private/Language/locallang_BackendDeepCopy.xlf:';

    protected ConnectionPool $connectionPool;

    public function injectConnectionPool(ConnectionPool $connectionPool): void
    {
        $this->connectionPool = $connectionPool;
    }

    public function initializeAction(): void
    {
        parent::initializeAction();

        $querySettings = $this->callRepository->createQuery()->getQuerySettings();
        $querySettings->setIgnoreEnableFields(true);
        $querySettings->setEnableFieldsToBeIgnored(['disabled']);
        $querySettings->setRespectStoragePage(false);
        $this->callRepository->setDefaultQuerySettings($querySettings);
    }

    /**
     * Find a call by UID using a custom query that respects the repository's default query settings.
     * Unlike findByUid(), which uses PersistenceManager::getObjectByIdentifier() and ignores
     * the repository's default query settings, this method creates a proper query so that
     * disabled (hidden) calls can be found.
     */
    protected function findCallByUid(int $uid): ?Call
    {
        $query = $this->callRepository->createQuery();
        $query->matching($query->equals('uid', $uid));

        return $query->execute()->getFirst();
    }

    public function indexAction(int $call = 0): ResponseInterface
    {
        $callObject = $call > 0 ? $this->findCallByUid($call) : null;
        $callPid = $callObject ? $callObject->getPid() : 0;
        $returnUrl = $this->request->getQueryParams()['returnUrl'] ?? '';

        $rootPidsCall = $this->parseCommaSeparatedPids($this->settings['callPoolId'] ?? '', $callPid);
        $rootPidsFormPages = $this->parseCommaSeparatedPids($this->settings['pidFormPages'] ?? '', $callPid);
        $rootPidsFormGroups = $this->parseCommaSeparatedPids($this->settings['pidFormGroups'] ?? '', $callPid);
        $rootPidsItems = $this->parseCommaSeparatedPids($this->settings['pidFormItems'] ?? '', $callPid);
        $rootPidsItemOptions = $this->parseCommaSeparatedPids($this->settings['pidItemOptions'] ?? '', $callPid);

        $noCopyLabel = LocalizationUtility::translate(self::TRANS . 'deepCopy.noCopy', 'OpenOap')
            ?? '--- Do not copy (keep originals) ---';
        $noCopyOption = [['uid' => 0, 'title' => $noCopyLabel]];

        $this->moduleTemplate->assignMultiple([
            'call' => $callObject,
            'requestedCallUid' => $call,
            'folderOptionsCall' => $this->getFolderOptions($rootPidsCall),
            'folderOptionsFormPages' => array_merge($noCopyOption, $this->getFolderOptions($rootPidsFormPages)),
            'folderOptionsFormGroups' => array_merge($noCopyOption, $this->getFolderOptions($rootPidsFormGroups)),
            'folderOptionsItems' => array_merge($noCopyOption, $this->getFolderOptions($rootPidsItems)),
            'folderOptionsItemOptions' => array_merge($noCopyOption, $this->getFolderOptions($rootPidsItemOptions)),
            'returnUrl' => $returnUrl,
        ]);

        return $this->moduleTemplate->renderResponse('BackendDeepCopy/Index');
    }

    public function deepCopyAction(int $call): ResponseInterface
    {
        $parsedBody = $this->request->getParsedBody();
        $callObject = $this->findCallByUid($call);
        if ($callObject === null) {
            $this->addFlashMessage(
                'Call with UID ' . $call . ' not found.',
                'Error',
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('index');
        }
        $callUid = $callObject->getUid();
        $targetPidCall = (int)($parsedBody['targetPidCall'] ?? 0);
        $targetPidFormPages = (int)($parsedBody['targetPidFormPages'] ?? 0);
        $targetPidItems = (int)($parsedBody['targetPidItems'] ?? 0);
        $targetPidFormGroups = (int)($parsedBody['targetPidFormGroups'] ?? 0);
        $targetPidItemOptions = (int)($parsedBody['targetPidItemOptions'] ?? 0);

        try {
            $result = $this->performDeepCopy($callUid, $targetPidCall, $targetPidFormPages, $targetPidFormGroups, $targetPidItems, $targetPidItemOptions);
            $notificationType = 'success';
            $notificationTitle = LocalizationUtility::translate(self::TRANS . 'deepCopy.success.title', 'OpenOap') ?? 'Success';
            $notificationMessage = sprintf(
                LocalizationUtility::translate(self::TRANS . 'deepCopy.success.message', 'OpenOap') ?? 'Deep copy completed successfully. New Call UID: %d',
                $result['newCallUid']
            );
        } catch (\Exception $e) {
            $notificationType = 'error';
            $notificationTitle = LocalizationUtility::translate(self::TRANS . 'deepCopy.error.title', 'OpenOap') ?? 'Error';
            $notificationMessage = sprintf(
                LocalizationUtility::translate(self::TRANS . 'deepCopy.error.message', 'OpenOap') ?? 'Error during deep copy: %s',
                $e->getMessage()
            );
        }

        $returnUrl = $parsedBody['returnUrl'] ?? '';

        $this->moduleTemplate->assignMultiple([
            'notificationType' => $notificationType,
            'notificationTitle' => $notificationTitle,
            'notificationMessage' => $notificationMessage,
            'returnUrl' => $returnUrl,
            'call' => $callObject,
            'requestedCallUid' => $call,
        ]);

        return $this->moduleTemplate->renderResponse('BackendDeepCopy/Result');
    }

    protected function performDeepCopy(int $callUid, int $targetPidCall, int $targetPidFormPages, int $targetPidFormGroups, int $targetPidItems, int $targetPidItemOptions): array
    {
        // Copy form items
        $callItemUids = $this->getRelatedUids('tx_openoap_call_formitem_mm', 'uid_local', $callUid, 'items');
        $itemOverrides = ['options' => '', 'validators' => '', 'modificators' => ''];
        $itemUidMap = $this->copyRecords('tx_openoap_domain_model_formitem', $callItemUids, $targetPidItems, $itemOverrides);

        // Copy form groups and their items
        $formPageUids = $this->getRelatedUids('tx_openoap_call_formpage_mm', 'uid_local', $callUid);
        $formGroupUids = [];
        foreach ($formPageUids as $formPageUid) {
            $groupUids = $this->getRelatedUids('tx_openoap_formpage_formgroup_mm', 'uid_local', $formPageUid);
            foreach ($groupUids as $groupUid) {
                $formGroupUids[$groupUid] = $groupUid;
            }
        }

        // Copy form items belonging to form groups
        $groupItemUidMap = [];
        foreach ($formGroupUids as $groupUid) {
            $groupItemUids = $this->getRelatedUids('tx_openoap_formgroup_formitem_mm', 'uid_local', $groupUid);
            foreach ($groupItemUids as $itemUid) {
                if (!isset($itemUidMap[$itemUid]) && !isset($groupItemUidMap[$itemUid])) {
                    $newMap = $this->copyRecords('tx_openoap_domain_model_formitem', [$itemUid], $targetPidItems, $itemOverrides);
                    $groupItemUidMap += $newMap;
                }
            }
        }
        $allItemUidMap = $itemUidMap + $groupItemUidMap;

        // Copy item options for all form items
        $itemOptionUidMap = [];
        foreach (array_keys($allItemUidMap) as $oldItemUid) {
            $optionUids = $this->getRelatedUids('tx_openoap_formitem_itemoption_mm', 'uid_local', $oldItemUid);
            foreach ($optionUids as $optionUid) {
                if (!isset($itemOptionUidMap[$optionUid])) {
                    $newMap = $this->copyRecords('tx_openoap_domain_model_itemoption', [$optionUid], $targetPidItemOptions);
                    $itemOptionUidMap += $newMap;
                }
            }
        }

        // Copy group titles for all form groups
        $groupTitleUidMap = [];
        foreach ($formGroupUids as $groupUid) {
            $groupTitleUids = $this->getRelatedUids('tx_openoap_formgroup_grouptitle_mm', 'uid_local', $groupUid);
            foreach ($groupTitleUids as $groupTitleUid) {
                if (!isset($groupTitleUidMap[$groupTitleUid])) {
                    $newMap = $this->copyRecords('tx_openoap_domain_model_grouptitle', [$groupTitleUid], $targetPidFormGroups);
                    $groupTitleUidMap += $newMap;
                }
            }
        }

        // Copy form groups
        $groupOverrides = ['items' => '', 'group_title' => '', 'modificators' => '', 'item_groups' => ''];
        $groupUidMap = $this->copyRecords('tx_openoap_domain_model_formgroup', array_values($formGroupUids), $targetPidFormGroups, $groupOverrides);

        // Copy form pages
        $formPageOverrides = ['item_groups' => '', 'modificators' => ''];
        $formPageUidMap = $this->copyRecords('tx_openoap_domain_model_formpage', $formPageUids, $targetPidFormPages, $formPageOverrides);

        // Copy the call record itself
        $callOverrides = ['form_pages' => '', 'items' => '', 'assessment_items' => ''];
        $callUidMap = $this->copyRecords('tx_openoap_domain_model_call', [$callUid], $targetPidCall, $callOverrides);
        $newCallUid = $callUidMap[$callUid] ?? 0;

        // Set all MM relations via DataHandler
        $datamap = [];

        // FormItem MM relations
        foreach ($allItemUidMap as $oldItemUid => $newItemUid) {
            $oldOptionUids = $this->getRelatedUids('tx_openoap_formitem_itemoption_mm', 'uid_local', $oldItemUid);
            $newOptionUids = array_map(fn($uid) => $itemOptionUidMap[$uid] ?? $uid, $oldOptionUids);
            $datamap['tx_openoap_domain_model_formitem'][$newItemUid]['options'] = implode(',', $newOptionUids);

            $validatorUids = $this->getRelatedUids('tx_openoap_formitem_itemvalidator_mm', 'uid_local', $oldItemUid);
            $datamap['tx_openoap_domain_model_formitem'][$newItemUid]['validators'] = implode(',', $validatorUids);

            $modUids = $this->getRelatedUids('tx_openoap_formitem_formmodificator_mm', 'uid_local', $oldItemUid);
            $datamap['tx_openoap_domain_model_formitem'][$newItemUid]['modificators'] = implode(',', $modUids);
        }

        // FormGroup MM relations
        foreach ($groupUidMap as $oldGroupUid => $newGroupUid) {
            $oldGroupItemUids = $this->getRelatedUids('tx_openoap_formgroup_formitem_mm', 'uid_local', $oldGroupUid);
            $newGroupItemUids = array_map(fn($uid) => $allItemUidMap[$uid] ?? $uid, $oldGroupItemUids);
            $datamap['tx_openoap_domain_model_formgroup'][$newGroupUid]['items'] = implode(',', $newGroupItemUids);

            $oldGroupTitleUids = $this->getRelatedUids('tx_openoap_formgroup_grouptitle_mm', 'uid_local', $oldGroupUid);
            $newGroupTitleUids = array_map(fn($uid) => $groupTitleUidMap[$uid] ?? $uid, $oldGroupTitleUids);
            $datamap['tx_openoap_domain_model_formgroup'][$newGroupUid]['group_title'] = implode(',', $newGroupTitleUids);

            $groupModUids = $this->getRelatedUids('tx_openoap_formgroup_formmodificator_mm', 'uid_local', $oldGroupUid);
            $datamap['tx_openoap_domain_model_formgroup'][$newGroupUid]['modificators'] = implode(',', $groupModUids);

            $oldSubGroupUids = $this->getRelatedUids('tx_openoap_formgroup_formgroup_mm', 'uid_local', $oldGroupUid);
            $newSubGroupUids = array_map(fn($uid) => $groupUidMap[$uid] ?? $uid, $oldSubGroupUids);
            $datamap['tx_openoap_domain_model_formgroup'][$newGroupUid]['item_groups'] = implode(',', $newSubGroupUids);
        }

        // FormPage MM relations
        foreach ($formPageUidMap as $oldPageUid => $newPageUid) {
            $oldGroupUidsForPage = $this->getRelatedUids('tx_openoap_formpage_formgroup_mm', 'uid_local', $oldPageUid);
            $newGroupUidsForPage = array_map(fn($uid) => $groupUidMap[$uid] ?? $uid, $oldGroupUidsForPage);
            $datamap['tx_openoap_domain_model_formpage'][$newPageUid]['item_groups'] = implode(',', $newGroupUidsForPage);

            $oldModUids = $this->getRelatedUids('tx_openoap_formpage_formmodificator_mm', 'uid_local', $oldPageUid);
            $datamap['tx_openoap_domain_model_formpage'][$newPageUid]['modificators'] = implode(',', $oldModUids);
        }

        // Call MM relations
        $originalFormPageUids = $this->getRelatedUids('tx_openoap_call_formpage_mm', 'uid_local', $callUid);
        $newFormPageUids = array_map(fn($uid) => $formPageUidMap[$uid] ?? $uid, $originalFormPageUids);
        $datamap['tx_openoap_domain_model_call'][$newCallUid]['form_pages'] = implode(',', $newFormPageUids);

        $originalItemUids = $this->getRelatedUids('tx_openoap_call_formitem_mm', 'uid_local', $callUid, 'items');
        $newItemUidsForCall = array_map(fn($uid) => $allItemUidMap[$uid] ?? $uid, $originalItemUids);
        $datamap['tx_openoap_domain_model_call'][$newCallUid]['items'] = implode(',', $newItemUidsForCall);

        $originalAssessmentUids = $this->getRelatedUids('tx_openoap_call_formitem_mm', 'uid_local', $callUid, 'assessment_items');
        $newAssessmentUids = array_map(fn($uid) => $allItemUidMap[$uid] ?? $uid, $originalAssessmentUids);
        $datamap['tx_openoap_domain_model_call'][$newCallUid]['assessment_items'] = implode(',', $newAssessmentUids);

        // Process all MM relations via DataHandler
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, []);
        $dataHandler->process_datamap();

        return ['newCallUid' => $newCallUid];
    }

    protected function getRelatedUids(string $mmTable, string $localField, int $localUid, ?string $fieldname = null): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($mmTable);
        $queryBuilder->getRestrictions()->removeAll();

        $constraints = [
            $queryBuilder->expr()->eq($localField, $queryBuilder->createNamedParameter($localUid, Connection::PARAM_INT)),
        ];

        if ($fieldname !== null) {
            $constraints[] = $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($fieldname));
        }

        $foreignField = $localField === 'uid_local' ? 'uid_foreign' : 'uid_local';

        $result = $queryBuilder
            ->select($foreignField)
            ->from($mmTable)
            ->where(...$constraints)
            ->orderBy('sorting')
            ->executeQuery();

        $uids = [];
        while ($row = $result->fetchAssociative()) {
            $uids[] = (int)$row[$foreignField];
        }
        return $uids;
    }

    protected function copyRecords(string $table, array $uids, int $targetPid, array $overrideValues = []): array
    {
        if (empty($uids) || $targetPid === 0) {
            return [];
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $cmdmap = [];
        foreach ($uids as $uid) {
            $copyCmd = [
                'action' => 'paste',
                'target' => $targetPid,
                'update' => $overrideValues,
            ];
            $cmdmap[$table][$uid] = [
                'copy' => $copyCmd,
            ];
        }

        $dataHandler->start([], $cmdmap);
        $dataHandler->process_cmdmap();

        $uidMap = [];
        foreach ($uids as $uid) {
            if (isset($dataHandler->copyMappingArray_merged[$table][$uid])) {
                $uidMap[$uid] = $dataHandler->copyMappingArray_merged[$table][$uid];
            }
        }
        return $uidMap;
    }


    /**
     * Parse a comma-separated PID string into an array of integers.
     * Falls back to the given fallback PID if the string is empty or contains no valid PIDs.
     */
    protected function parseCommaSeparatedPids(string $pidString, int $fallbackPid = 0): array
    {
        $pids = GeneralUtility::intExplode(',', $pidString, true);
        if (empty($pids) && $fallbackPid > 0) {
            $pids = [$fallbackPid];
        }
        return $pids;
    }

    /**
     * Get folder pages (doktype=254) starting from one or more root PIDs as a flat list with hierarchy indication.
     * Each root page itself (if it's a folder) and all its subfolders are included.
     * Returns an array of ['uid' => int, 'title' => string] entries sorted by tree structure.
     */
    protected function getFolderOptions(array $rootPids = []): array
    {
        $rootPids = array_filter($rootPids, static fn(int $pid): bool => $pid > 0);
        if (empty($rootPids)) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        $result = $queryBuilder
            ->select('uid', 'pid', 'title', 'doktype')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('sys_language_uid', [0, -1])
            )
            ->orderBy('sorting')
            ->executeQuery();

        $allPages = [];
        $children = [];
        while ($row = $result->fetchAssociative()) {
            $uid = (int)$row['uid'];
            $allPages[$uid] = $row;
            $pid = (int)$row['pid'];
            if (!isset($children[$pid])) {
                $children[$pid] = [];
            }
            $children[$pid][] = $uid;
        }

        $options = [];
        $addedUids = [];

        foreach ($rootPids as $rootPid) {
            if (!isset($allPages[$rootPid])) {
                continue;
            }

            // Collect all descendant UIDs of rootPid
            $descendantUids = [];
            $this->collectDescendants($children, $rootPid, $descendantUids);

            // Include the root page itself if it's a folder and not already added
            if ((int)$allPages[$rootPid]['doktype'] === PageRepository::DOKTYPE_SYSFOLDER && !isset($addedUids[$rootPid])) {
                $title = $allPages[$rootPid]['title'] ?: '[' . $rootPid . ']';
                $options[] = [
                    'uid' => $rootPid,
                    'title' => $title . ' [' . $rootPid . ']',
                ];
                $addedUids[$rootPid] = true;
            }

            // Filter to only folder pages (doktype=254) among descendants
            $folderUids = [];
            foreach ($descendantUids as $uid) {
                if ((int)$allPages[$uid]['doktype'] === PageRepository::DOKTYPE_SYSFOLDER && !isset($addedUids[$uid])) {
                    $folderUids[$uid] = true;
                }
            }

            // Build flat list of folder subpages under rootPid
            $this->buildSubFolderTree($children, $allPages, $folderUids, $rootPid, 1, $options, $addedUids);
        }

        return $options;
    }

    /**
     * Recursively collect all descendant UIDs of a given parent.
     */
    protected function collectDescendants(array $children, int $parentUid, array &$descendantUids): void
    {
        if (!isset($children[$parentUid])) {
            return;
        }
        foreach ($children[$parentUid] as $uid) {
            $descendantUids[] = $uid;
            $this->collectDescendants($children, $uid, $descendantUids);
        }
    }

    /**
     * Recursively build a flat folder tree with indentation, starting from a given parent.
     * Only folder pages (doktype=254) are included as options.
     * Non-folder pages are traversed to find nested folders.
     */
    protected function buildSubFolderTree(array $children, array $allPages, array $folderUids, int $parentUid, int $depth, array &$options, array &$addedUids = []): void
    {
        if (!isset($children[$parentUid])) {
            return;
        }
        foreach ($children[$parentUid] as $uid) {
            if (isset($folderUids[$uid]) && !isset($addedUids[$uid])) {
                $prefix = str_repeat("\u{00A0}\u{00A0}\u{00A0}\u{00A0}", $depth);
                $title = $allPages[$uid]['title'] ?: '[' . $uid . ']';
                $options[] = [
                    'uid' => $uid,
                    'title' => $prefix . $title . ' [' . $uid . ']',
                ];
                $addedUids[$uid] = true;
            }
            $this->buildSubFolderTree($children, $allPages, $folderUids, $uid, $depth + 1, $options, $addedUids);
        }
    }


    protected function handleMenu(string $currentAction): void
    {
        $returnUrl = $this->request->getQueryParams()['returnUrl'] ?? '';

        if ($returnUrl) {
            $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
            $iconFactory = GeneralUtility::makeInstance(IconFactory::class);

            $cancelButton = $buttonBar->makeLinkButton()
                ->setHref($returnUrl)
                ->setTitle(LocalizationUtility::translate(self::TRANS . 'deepCopy.cancel', 'OpenOap') ?? 'Cancel')
                ->setShowLabelText(true)
                ->setIcon($iconFactory->getIcon('actions-close', IconSize::SMALL));

            $buttonBar->addButton($cancelButton, ButtonBar::BUTTON_POSITION_LEFT);
        }
    }
}
