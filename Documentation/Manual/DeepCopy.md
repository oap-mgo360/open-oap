# Open OAP - Deep copy of a call

The **Deep Copy** function creates a new call based on an existing call. Unlike the standard TYPO3 copy function, it can also copy the complete form structure and rebuild the relations between the new records.

Use this function when an existing call should serve as the basis for a new call without later changes affecting the original form structure.

## What is copied

For a complete deep copy, new records are created for:

- the call
- form pages
- form groups and group titles
- items
- item options

The relations between these records are rebuilt for the new call. Validators and modificators are not duplicated; the copied form structure continues to use the existing validator and modificator records.

The call itself is always copied. For each subordinate record type, you can decide whether records are copied or whether the new call continues to use the original records.

## Opening the function

The Deep Copy function can be opened in two ways:

1. In the TYPO3 **List** module, open the context menu of a call record and select **More options... > Deep Copy Call**.
2. In **Web > OAP Forms**, open the preview of a call and select **Deep Copy Call** in the document header.

The configuration page displays the title and UID of the selected source call. Hidden calls can also be copied.

If the action is not available, the backend user or user group may not have access to the Deep Copy module.

## Selecting target folders

Select one target folder for every type of record. The available folders are provided by the system configuration.

| Field | Records stored in the selected folder |
| ----- | ------------------------------------- |
| **Target PID for Call** | The new call |
| **Target PID for Form Pages** | Copied form pages |
| **Target PID for Form Groups** | Copied form groups and group titles |
| **Target PID for Items** | Copied items |
| **Target PID for Item Options** | Copied item options |

### Reusing original records

For form pages, form groups, items and item options, the option **Do not copy (keep originals)** is available. If selected, no new records of that type are created and the copied structure refers to the existing records where they are required.

Use this option only when the records are deliberately intended to be shared. Later changes to a reused record can affect both the original and the copied call.

For an independent copy, select a target folder for all record types. When only parts of the structure are copied, check the resulting call afterwards, especially if a parent level is reused while subordinate records are copied.

## Creating the deep copy

1. Open the Deep Copy function for the required call.
2. Verify the displayed title and UID of the source call.
3. Select the target folder for the new call.
4. Select a target folder or **Do not copy (keep originals)** for each subordinate record type.
5. Select **Start Deep Copy**.
6. Wait until the process is complete. The button is disabled while the records are being copied.

After successful completion, TYPO3 displays the UID of the new call and returns to the previous backend view. The new call can then be opened and adapted.

## Recommended checks after copying

Before using or publishing the new call, verify:

- the title, UID and storage folder of the new call
- the order and completeness of form pages, groups and items
- item options, validators and conditional behaviour
- dates, e-mail settings, texts and other call-specific configuration
- whether deliberately reused records may safely remain shared with the original call

If TYPO3 displays an error instead of the new call UID, do not assume that the copy is complete. Note the error message and contact an administrator if the cause cannot be corrected through the selected target folders or access permissions.
