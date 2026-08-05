/**
 * Context menu actions for OpenOAP deep copy
 */
class OpenOapContextMenuActions {

    deepCopyCall(table, uid, dataset) {
        const actionUrl = dataset.actionUrl;
        if (actionUrl) {
            const separator = actionUrl.includes('?') ? '&' : '?';
            const returnUrl = top.TYPO3.Backend.ContentContainer.getUrl();
            let url = actionUrl + separator + 'call=' + encodeURIComponent(uid);
            if (returnUrl) {
                url += '&returnUrl=' + encodeURIComponent(returnUrl);
            }
            top.TYPO3.Backend.ContentContainer.setUrl(url);
        }
    }
}

export default new OpenOapContextMenuActions();
