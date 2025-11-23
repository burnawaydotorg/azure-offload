# WP-CLI Commands for Microsoft Azure Storage

This document provides comprehensive documentation for all WP-CLI commands available in the Microsoft Azure Storage for WordPress plugin.

## Table of Contents

- [Requirements](#requirements)
- [Container Management](#container-management)
- [Blob Management](#blob-management)
- [Bulk Offload Operations](#bulk-offload-operations)
- [Examples & Use Cases](#examples--use-cases)

---

## Requirements

- WP-CLI installed and configured
- Microsoft Azure Storage plugin activated
- Azure Storage credentials configured (via Settings or wp-config.php)
- **For bulk offload:** Action Scheduler (bundled with WooCommerce or install standalone)

---

## Container Management

### List Containers

List all containers in your Azure Storage account.

```bash
wp windows-azure-storage containers-list [--prefix=<prefix>]
```

**Options:**
- `--prefix=<prefix>` - Optional. List containers which names start with prefix.

**Examples:**
```bash
# List all containers
wp windows-azure-storage containers-list

# List containers starting with "prod"
wp windows-azure-storage containers-list --prefix=prod
```

**Output:**
```
+------------------+
| Name             |
+------------------+
| media            |
| production-media |
| staging-media    |
+------------------+
```

---

### Create Container

Create a new container in your Azure Storage account.

```bash
wp windows-azure-storage container-create <name>
```

**Arguments:**
- `<name>` - Container name (required). Must be lowercase, 3-63 characters, alphanumeric and hyphens only.

**Examples:**
```bash
# Create a new container
wp windows-azure-storage container-create my-new-container
```

**Output:**
```
Success: Created container with name "my-new-container"
```

---

### Get Container Properties

Get properties for a specific container.

```bash
wp windows-azure-storage container-properties <name>
```

**Arguments:**
- `<name>` - Container name (required).

**Examples:**
```bash
wp windows-azure-storage container-properties media
```

**Output:**
```
+-------------------+--------------------------------+
| Property          | Value                          |
+-------------------+--------------------------------+
| Last-Modified     | Thu, 23 Nov 2025 10:30:00 GMT  |
| ETag              | "0x8DCEB3A1B2C3D4E"            |
| x-ms-lease-status | unlocked                       |
+-------------------+--------------------------------+
```

---

### Get Container ACL

Get the access control list (ACL) for a container.

```bash
wp windows-azure-storage container-acl <name>
```

**Arguments:**
- `<name>` - Container name (required).

**Examples:**
```bash
wp windows-azure-storage container-acl media
```

**Output:**
```
Success: Container "media" access policy set to: "blob"
```

---

## Blob Management

### List Blobs

List blobs in a specific container.

```bash
wp windows-azure-storage blobs-list --container=<name> [--prefix=<prefix>]
```

**Options:**
- `--container=<name>` - Container name (required).
- `--prefix=<prefix>` - Optional. List blobs which names start with prefix.

**Examples:**
```bash
# List all blobs in "media" container
wp windows-azure-storage blobs-list --container=media

# List blobs in "media" container starting with "2024/"
wp windows-azure-storage blobs-list --container=media --prefix=2024/

# List blobs with specific file extension
wp windows-azure-storage blobs-list --container=media --prefix=uploads/images/
```

**Output:**
```
+--------------------------------+
| Name                           |
+--------------------------------+
| 2024/11/image1.jpg            |
| 2024/11/image2.png            |
| 2024/11/document.pdf          |
+--------------------------------+
```

---

### Get Blob Properties

Get properties for a specific blob.

```bash
wp windows-azure-storage blob-properties <container> <remote_path>
```

**Arguments:**
- `<container>` - Container name (required).
- `<remote_path>` - Blob path within container (required).

**Examples:**
```bash
wp windows-azure-storage blob-properties media 2024/11/image1.jpg
```

**Output:**
```
+-------------------+--------------------------------+
| Property          | Value                          |
+-------------------+--------------------------------+
| Content-Type      | image/jpeg                     |
| Content-Length    | 245678                         |
| Last-Modified     | Thu, 23 Nov 2025 09:15:00 GMT  |
| ETag              | "0x8DCEB3A1B2C3D4E"            |
+-------------------+--------------------------------+
```

---

### Delete Blob

Delete a blob from a container.

```bash
wp windows-azure-storage delete-blob <container> <path>
```

**Arguments:**
- `<container>` - Container name (required).
- `<path>` - Remote file path within container (required).

**Examples:**
```bash
# Delete a single blob
wp windows-azure-storage delete-blob media 2024/11/old-image.jpg
```

**Output:**
```
Success: Blob has been deleted.
```

**⚠️ Warning:** This operation is irreversible. Make sure you have backups before deleting blobs.

---

## Bulk Offload Operations

### Bulk Offload Media

Offload existing media library items to Azure Storage in the background.

```bash
wp windows-azure-storage bulk-offload [--limit=<number>] [--force] [--remove-local] [--pending-only]
```

**Options:**

- `--limit=<number>` - Maximum number of attachments to offload. Default: all.
- `--force` - Force re-upload of already offloaded items. Default: false.
- `--remove-local` - Remove local files after successful upload. Default: false.
- `--pending-only` - Only offload items not yet offloaded. Default: true.

**How It Works:**

1. Queries the WordPress Media Library for attachments
2. Queues items for background processing via Action Scheduler
3. Returns immediately (does not block)
4. Processing happens asynchronously in the background
5. Use `offload-status` command to check progress

**Examples:**

```bash
# Offload all pending media (not yet uploaded to Azure)
wp windows-azure-storage bulk-offload

# Offload only 100 items (useful for testing)
wp windows-azure-storage bulk-offload --limit=100

# Offload 500 items and remove local files to save disk space
wp windows-azure-storage bulk-offload --limit=500 --remove-local

# Force re-upload ALL media (including already offloaded)
wp windows-azure-storage bulk-offload --force

# Force re-upload and remove local files (DANGEROUS - ensure backups!)
wp windows-azure-storage bulk-offload --force --remove-local

# Offload pending items without limit
wp windows-azure-storage bulk-offload --pending-only
```

**Output:**
```
Querying attachments...
Success: Found 2,543 attachments to offload.
Success: Bulk offload started. Run "wp windows-azure-storage offload-status" to check progress.
```

**Requirements:**
- Action Scheduler must be installed and active
- Bundled with WooCommerce, or install standalone: `composer require woocommerce/action-scheduler`

**Notes:**
- Processing happens in background - safe to close terminal
- Can pause/resume operations via WordPress admin (Media → Bulk Offload to Azure)
- Progress survives server restarts
- Failed items are automatically retried (up to 3 times)

---

### Check Offload Status

Check the progress of an active bulk offload operation.

```bash
wp windows-azure-storage offload-status
```

**No options required.** Simply run the command to get current status.

**Examples:**

```bash
# Check current offload progress
wp windows-azure-storage offload-status
```

**Output (In Progress):**
```

Bulk Offload Status:
==================================================
Status:      RUNNING
Progress:    45.3%
Processed:   1,152 of 2,543
Successful:  1,145
Failed:      3
Skipped:     4
==================================================

Warning: Recent errors (3 total):
  - Attachment #12345: File not found: /path/to/file.jpg
  - Attachment #12389: Azure upload failed: Connection timeout
  - Attachment #12401: File not found: /path/to/image.png

```

**Output (Completed):**
```

Bulk Offload Status:
==================================================
Status:      COMPLETED
Progress:    100.0%
Processed:   2,543 of 2,543
Successful:  2,535
Failed:      3
Skipped:     5
==================================================

Success: Bulk offload completed!
```

**Output (No Active Operation):**
```
Warning: No active bulk offload operation found.
```

**Status Values:**
- `RUNNING` - Operation in progress
- `PAUSED` - Temporarily paused (can resume via admin UI)
- `COMPLETED` - Finished successfully
- `CANCELLED` - User cancelled the operation

**Progress Metrics:**
- **Processed** - Total items processed so far
- **Successful** - Items successfully uploaded to Azure
- **Failed** - Items that failed after 3 retry attempts
- **Skipped** - Items that were skipped (e.g., already offloaded)

**Notes:**
- Run this command as many times as needed during processing
- Recent errors show last 5 failures with attachment IDs for debugging
- Progress data is stored in WordPress options table (`azure_bulk_offload_progress`)

---

## Examples & Use Cases

### Example 1: Fresh Site Migration to Azure

Migrate all existing media to Azure Storage for the first time:

```bash
# Step 1: Test with small batch
wp windows-azure-storage bulk-offload --limit=10

# Step 2: Check progress
wp windows-azure-storage offload-status

# Step 3: If successful, offload everything
wp windows-azure-storage bulk-offload

# Step 4: Monitor progress
watch -n 10 'wp windows-azure-storage offload-status'

# Step 5: After completion, optionally remove local files
wp windows-azure-storage bulk-offload --force --remove-local
```

---

### Example 2: Staging to Production Workflow

Copy media from staging to production container:

```bash
# On staging: Export container list
wp windows-azure-storage blobs-list --container=staging-media > staging-blobs.txt

# On production: Offload all production media
wp windows-azure-storage bulk-offload

# Future: Use container copy feature (Phase 4)
# wp windows-azure-storage copy-container staging-media production-media
```

---

### Example 3: Cleanup Old Files to Save Disk Space

Offload everything to Azure and remove local copies:

```bash
# Step 1: Ensure everything is offloaded first
wp windows-azure-storage bulk-offload

# Step 2: Check status - wait for completion
wp windows-azure-storage offload-status

# Step 3: Only after 100% success, remove local files
wp windows-azure-storage bulk-offload --force --remove-local

# Step 4: Verify in WordPress admin that images still display correctly
```

**⚠️ IMPORTANT:** Always verify uploads are successful before removing local files!

---

### Example 4: Re-upload After Container Migration

After moving to a new Azure Storage account:

```bash
# Force re-upload all media to new account
wp windows-azure-storage bulk-offload --force

# Monitor progress
wp windows-azure-storage offload-status

# Update database URLs using Better Search Replace plugin or WP-CLI
wp search-replace 'oldaccount.blob.core.windows.net' 'newaccount.blob.core.windows.net'
```

---

### Example 5: Batch Processing with Rate Limiting

Process large libraries in controlled batches:

```bash
#!/bin/bash
# Process 500 items at a time with delays

for i in {1..10}; do
    echo "Processing batch $i..."
    wp windows-azure-storage bulk-offload --limit=500

    # Wait for batch to complete
    while true; do
        STATUS=$(wp windows-azure-storage offload-status --format=json | jq -r '.status')
        if [[ "$STATUS" == "completed" ]]; then
            echo "Batch $i completed"
            break
        fi
        sleep 30
    done

    # Rest between batches
    sleep 60
done
```

---

### Example 6: Cron Job for Automatic Offloading

Automatically offload new uploads daily:

```bash
# Add to crontab (crontab -e)
0 2 * * * /usr/local/bin/wp windows-azure-storage bulk-offload --limit=1000 --path=/var/www/html
```

This runs at 2:00 AM daily, offloading up to 1000 pending items.

---

### Example 7: Debugging Failed Uploads

Identify and retry failed uploads:

```bash
# Check status for error details
wp windows-azure-storage offload-status

# Example output shows:
#   - Attachment #12345: File not found
#   - Attachment #12389: Connection timeout

# Fix issues (restore missing files, check network, etc.)
# Then retry failed items
wp windows-azure-storage bulk-offload --force --limit=10
```

---

## Troubleshooting

### Command Not Found

**Error:** `Error: 'windows-azure-storage' is not a registered wp command.`

**Solution:**
1. Ensure plugin is activated: `wp plugin list`
2. Activate if needed: `wp plugin activate windows-azure-storage`
3. Verify WP-CLI can see the plugin: `wp cli has-command windows-azure-storage`

---

### Action Scheduler Not Available

**Error:** `Error: Action Scheduler is required for bulk offload...`

**Solution:**
1. Install WooCommerce: `wp plugin install woocommerce --activate`
2. OR install Action Scheduler standalone via Composer:
   ```bash
   composer require woocommerce/action-scheduler
   ```

---

### Permission Denied

**Error:** `Error: Sorry, you are not allowed to do that.`

**Solution:**
- Run WP-CLI as the web server user:
  ```bash
  sudo -u www-data wp windows-azure-storage bulk-offload
  ```

---

### Memory Exhausted

**Error:** `Fatal error: Allowed memory size exhausted`

**Solution:**
- Process in smaller batches:
  ```bash
  wp windows-azure-storage bulk-offload --limit=100
  ```
- Or increase PHP memory limit:
  ```bash
  php -d memory_limit=512M $(which wp) windows-azure-storage bulk-offload
  ```

---

### Connection Timeout

**Error:** `Azure upload failed: Connection timeout`

**Solution:**
1. Check network connectivity to Azure
2. Verify Azure credentials are correct
3. Try again with smaller batch size
4. Check Azure Storage account firewall settings

---

## Advanced Usage

### JSON Output (for scripting)

Many commands support `--format=json` for easier parsing:

```bash
# Get container list as JSON
wp windows-azure-storage containers-list --format=json

# Get offload status as JSON
wp windows-azure-storage offload-status --format=json | jq '.progress'
```

---

### Combining with Other WP-CLI Commands

```bash
# Get all attachment IDs
wp post list --post_type=attachment --format=ids

# Count total attachments
wp post list --post_type=attachment --format=count

# Find attachments not yet offloaded
wp post list --post_type=attachment --meta_key=_azure_offload_status --meta_compare=NOT EXISTS --format=count

# Find failed offloads
wp post meta list <post-id> --keys=_azure_offload_status
```

---

## See Also

- [Plugin Documentation](README.md)
- [User Guide](UserGuide.md)
- [Implementation Roadmap](implementation.md)
- [Action Scheduler Documentation](https://actionscheduler.org/)
- [WP-CLI Handbook](https://make.wordpress.org/cli/handbook/)

---

## Support

For issues, questions, or feature requests:
- GitHub Issues: https://github.com/10up/windows-azure-storage/issues
- WordPress Support Forum: https://wordpress.org/support/plugin/windows-azure-storage/

---

**Last Updated:** November 23, 2025
**Plugin Version:** 6.0.0 (Phase 1: Background Processing System)
