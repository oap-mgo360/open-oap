# Deep Copy Module

## Overview

The Deep Copy Module allows TYPO3 backend users to create a complete deep copy of a Call record, including all related Form pages, Form groups, Form items, Group titles and Item options. The copied records are stored in configurable target folders, and all MM relations are correctly rebuilt for the new records.

The module is accessible via the context menu on call records in the TYPO3 list view, as well as via a button in the Forms backend module when previewing a call.

## Features

- Deep copy of a call with all related records (form pages, form groups, form items, group titles, item options)
- Configurable target folders (PIDs) for each record type via dropdown selects
- Default target PIDs are read from TypoScript constants (`plugin.tx_openoap_dashboard.settings.*`)
- Option to keep original records instead of copying (per record type)
- Support for disabled (hidden) calls

## Access

The Deep Copy module can be accessed in two ways:

1. **Context Menu**: Right-click on a call record in the TYPO3 list view and select **"Deep Copy Call"**
2. **Forms Module Button**: When previewing a call in the **Web → OAP Forms** backend module, click the **"Deep Copy Call"** button in the document header

## Configuration

### TypoScript Constants

The default target PIDs for the deep copy are read from the following TypoScript constants. These can be configured in the TYPO3 Constants Editor:

| Constant | Description |
| -------- | ----------- |
| `plugin.tx_openoap_dashboard.settings.callPoolId` | Default target PID for the copied call record |
| `plugin.tx_openoap_dashboard.settings.pidFormPages` | Default target PID for copied form pages |
| `plugin.tx_openoap_dashboard.settings.pidFormGroups` | Default target PID for copied form groups and group titles |
| `plugin.tx_openoap_dashboard.settings.pidFormItems` | Default target PID for copied form items |
| `plugin.tx_openoap_dashboard.settings.pidItemOptions` | Default target PID for copied item options |

These constants may contain comma-separated values (e.g. `21,32`) to define multiple root folders. The dropdown selects will then show all subfolders of all specified root PIDs.

### Module Registration

The module is registered as a hidden backend module. It does not appear in the backend menu but is accessible via the context menu and the Forms module button.

To grant access to non-admin users, configure the module access in the backend user group settings.

## Usage

1. Right-click on a call record in the list view (or click the **"Deep Copy Call"** button in the OAP Forms module)
2. The Deep Copy configuration page opens, showing the call title and UID
3. Select the target folders for each record type using the dropdown selects:
   - **Target PID for Call** — where the copied call will be stored
   - **Target PID for Form Pages** — where copied form pages will be stored
   - **Target PID for Form Groups** — where copied form groups and group titles will be stored
   - **Target PID for Items** — where copied form items will be stored
   - **Target PID for Item Options** — where copied item options will be stored
4. To keep original records instead of copying, select **"--- Do not copy (keep originals) ---"** for the respective record type
5. Click **"Start Deep Copy"** to begin the copy process
6. After completion, a success message with the new call UID is displayed, and the user is redirected back to the original view
