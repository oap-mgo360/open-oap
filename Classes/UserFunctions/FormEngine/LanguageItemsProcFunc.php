<?php

namespace OpenOAP\OpenOap\UserFunctions\FormEngine;

use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * User function to provide all configured site languages as items for a select field
 */
class LanguageItemsProcFunc
{
    /**
     * @param array $params
     */
    public function getLanguageItems(array &$params): void
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites = $siteFinder->getAllSites();

        $availableLanguages = [];
        foreach ($sites as $site) {
            foreach ($site->getLanguages() as $language) {
                $availableLanguages[$language->getLanguageId()] = $language->getTitle();
            }
        }

        // Clear existing items if any (except maybe an empty one if desired, but here we want a list)
        $params['items'] = [];

        foreach ($availableLanguages as $id => $title) {
            $params['items'][] = [
                'label' => $title,
                'value' => $id,
            ];
        }
    }
}
