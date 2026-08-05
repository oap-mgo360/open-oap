<?php

declare(strict_types=1);

namespace OpenOAP\OpenOap\ContextMenu;

use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CallItemProvider extends AbstractProvider
{
    protected $itemsConfiguration = [
        'deepCopyCall' => [
            'type' => 'item',
            'label' => 'LLL:EXT:open_oap/Resources/Private/Language/locallang_backend.xlf:contextMenu.deepCopyCall',
            'iconIdentifier' => 'actions-document-duplicates-select',
            'callbackAction' => 'deepCopyCall',
        ],
    ];

    public function canHandle(): bool
    {
        return $this->table === 'tx_openoap_domain_model_call';
    }

    public function getPriority(): int
    {
        return 55;
    }

    public function addItems(array $items): array
    {
        $this->initDisabledItems();
        $localItems = $this->prepareItems($this->itemsConfiguration);
        if (isset($items['more']['childItems'])) {
            $items['more']['childItems'] = $items['more']['childItems'] + $localItems;
        } else {
            $items += $localItems;
        }
        return $items;
    }

    protected function canRender(string $itemName, string $type): bool
    {
        if (in_array($itemName, $this->disabledItems, true)) {
            return false;
        }
        return $itemName === 'deepCopyCall';
    }

    protected function getAdditionalAttributes(string $itemName): array
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        return [
            'data-callback-module' => '@openoap/open-oap/context-menu-actions',
            'data-action-url' => (string)$uriBuilder->buildUriFromRoute('web_OpenOapBackendDeepCopy'),
        ];
    }
}
