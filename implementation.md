# Azure Media Offload - Enhanced Feature Development Specification

## Project Overview
Fork of [10up/windows-azure-storage](https://github.com/10up/windows-azure-storage) with enhanced features matching WP Offload Media's capabilities, specifically for Azure Blob Storage.

**Primary Pain Points Addressed:**
- **Disk Space Management:** Free up server storage by offloading media to Azure
- **Developer Workflow:** Streamline staging-to-production media synchronization

**Base Plugin Architecture:**
- Uses custom REST API client (`Windows_Azure_Rest_Api_Client`) - no deprecated SDK dependencies
- WordPress HTTP API (`wp_remote_request`) for all Azure operations
- Azure Blob Storage REST API v2024-11-04 (latest stable)
- Existing hooks into WordPress media upload pipeline
- Current support: new uploads, URL rewriting, container management

---

## Prerequisites

### ✅ Completed: v5.0.0 SDK Modernization

**Status:** Complete and merged to main branch

**Changes:**
- Removed deprecated `microsoft/azure-storage-blob` and `microsoft/azure-storage-common` SDK dependencies
- Modernized Azure integration using direct REST API calls via `wp_remote_request()`
- Updated Azure Blob Storage REST API version from `2020-04-08` to `2024-11-04`
- Refactored response classes to parse XML directly using `simplexml_load_string()`
- Created `Blob_Item` compatibility class for existing code interfaces
- Updated authentication signature generation for latest API version
- All existing functionality maintained with zero breaking changes for end users

**Files Modified:**
- `composer.json` - Removed SDK dependencies
- `includes/class-windows-azure-rest-api-client.php` - Complete rewrite
- `includes/class-windows-azure-list-containers-response.php` - XML parsing
- `includes/class-windows-azure-list-blobs-response.php` - XML parsing
- `includes/class-windows-azure-generic-list-response.php` - Updated constructor
- `includes/class-windows-azure-helper.php` - Array-based property handling
- `windows-azure-storage.php` - Version to 5.0.0

---

## Feature Implementation Roadmap

### Phase 1: Background Processing System

**Objective:** Offload existing Media Library items without blocking the admin interface.

**Technical Requirements:**
- **REQUIRED:** Integrate Action Scheduler as dependency (used by WooCommerce)
- Create admin page for bulk offload operations
- Implement pause/resume functionality
- Add progress tracking with real-time updates (AJAX or REST API endpoint)
- Queue system should handle failures gracefully with retry logic

**Implementation Details:**
- Create new class: `Azure_Background_Processor`
- Hook into Action Scheduler: `as_enqueue_async_action()` for queuing media items
- Store progress in WordPress options table or post meta
- Build admin UI at `Settings > Microsoft Azure > Bulk Offload`
- Add WP-CLI command for offloading via CLI: `wp azure offload`
- Add Action Scheduler as required plugin dependency (check on activation)

**Files to Create:**
- `includes/class-azure-background-processor.php`
- `includes/class-azure-bulk-offload-ui.php`
- `assets/js/admin-bulk-offload.js`
- `assets/css/admin-bulk-offload.css`

**Files to Modify:**
- `windows-azure-storage-settings.php` - Add new settings page tab
- `windows-azure-storage.php` - Register new classes, check Action Scheduler dependency

**User Stories:**
- As a site owner, I can click "Offload Existing Media" and see progress without keeping the tab open
- As a developer, I can run `wp azure offload --limit=100` to offload media via CLI
- As a user, I can pause offloading, close my browser, return later, and resume

**Dependency Management:**
- Display admin notice if Action Scheduler is not active
- Provide installation/activation instructions in notice
- Disable background processing features if Action Scheduler unavailable

---

### Phase 2: Enhanced Media Library Integration

**Objective:** Show offload status and provide bulk management controls directly in Media Library.

**Technical Requirements:**
- Add column to Media Library showing offload status (✓ Offloaded, ⏳ Pending, ✗ Failed)
- Add bulk actions: "Offload to Azure", "Remove from Azure", "Download from Azure"
- Add quick action links on hover for individual items
- Store offload metadata in post meta: `_azure_offload_status`, `_azure_blob_url`, `_azure_offload_date`
- Add filter dropdown to show only offloaded/not offloaded items

**Implementation Details:**
- Hook into `manage_media_columns` and `manage_media_custom_column` filters
- Hook into `bulk_actions-upload` and `handle_bulk_actions-upload` filters
- Use AJAX for individual item actions
- Add custom SQL query for filtering by offload status

**Files to Create:**
- `includes/class-azure-media-library-integration.php`
- `assets/js/media-library-ajax.js`

**Files to Modify:**
- `windows-azure-storage.php` - Register media library hooks
- `windows-azure-storage-util.php` - Add helper functions for metadata

**User Stories:**
- As an editor, I can see which images are stored in Azure at a glance
- As an admin, I can select multiple items and offload/remove them in one action
- As a user, I can filter to show only items that failed to upload

---

### Phase 3: Remove from Server & Download Tools

**Objective:** Free up local disk space and provide restore functionality.

**Technical Requirements:**
- Add setting: "Automatically remove local files after successful upload"
- Add safety checks: only delete if confirmed offloaded and Azure URL returns 200
- Bulk action: "Remove Local Files" (only for offloaded items)
- Bulk action: "Download from Azure" to restore files to server
- Implement file verification before deletion (check blob exists, matches size)
- Handle thumbnails/intermediate sizes correctly

**Implementation Details:**
- Hook into upload completion to trigger automatic removal if enabled
- Create verification function that checks blob existence via HEAD request
- Download function should recreate directory structure and all image sizes
- Add `_azure_local_removed` post meta flag
- Log all deletions for audit trail

**Files to Create:**
- `includes/class-azure-local-file-manager.php`

**Files to Modify:**
- `windows-azure-storage-util.php` - Add upload completion hooks
- `windows-azure-storage-settings.php` - Add removal settings
- `includes/class-azure-media-library-integration.php` - Add bulk actions

**User Stories:**
- As a site owner, I want local files deleted automatically to save disk space
- As an admin, I need to restore files from Azure if I disable the plugin
- As a developer, I want verification before any files are deleted

---

### Phase 4: Copy Between Containers

**Objective:** Enable staging-to-production workflows by copying media between containers.

**Technical Requirements:**
- Admin tool: "Copy Container Contents"
- Select source and destination containers
- Background processing for large containers using Action Scheduler
- Option to overwrite existing files or skip duplicates
- Progress indicator and completion report
- Support for cross-storage-account copying (if credentials provided)

**Implementation Details:**
- Use Azure Blob Copy API (`x-ms-copy-source` header)
- Server-side copy (fast, no download/reupload needed)
- Background job via Action Scheduler for bulk operations
- Store mapping of old URL → new URL for search/replace
- Handle container authentication for source and destination
- Verify blob existence before copying
- Option to update WordPress media library references after copy

**Files to Create:**
- `includes/class-azure-container-copier.php`
- Admin page at `Settings > Microsoft Azure > Copy Container`
- `assets/js/admin-container-copier.js`
- `assets/css/admin-container-copier.css`

**Files to Modify:**
- `includes/class-windows-azure-rest-api-client.php` - Add blob copy method
- `windows-azure-storage-settings.php` - Add container copier page

**User Stories:**
- As a developer, I can copy production media to staging without manual downloads
- As an admin, copying happens in background without timeout issues
- As a user, I see progress and can track which files were copied
- As a developer, I can sync media between environments to match production data

**Developer Workflow Benefits:**
- Eliminates manual media file downloads/uploads between environments
- Maintains consistent media references across staging/production
- Reduces deployment time by automating media synchronization
- Enables testing with production-like media content

---

### Phase 5: CDN Integration

**Objective:** Serve media through CDN (Azure Front Door, Fastly, Cloudflare, etc.).

**Technical Requirements:**
- Add setting: "CDN/Custom Domain" (e.g., `cdn.example.com`)
- Rewrite all Azure Blob URLs to use custom domain
- Support HTTPS enforcement
- Purge cache option (if CDN supports API purging)
- Validate custom domain configuration before saving

**Implementation Details:**
- Filter: `azure_storage_url` to rewrite blob URLs
- Validation: check DNS CNAME points to Azure endpoint
- Optional: Integrate with Cloudflare/Fastly API for cache purging
- Add notice if HTTPS not enabled on custom domain

**Files to Modify:**
- `windows-azure-storage-settings.php` - Add CDN settings
- `windows-azure-storage-util.php` - Filter blob URLs
- `includes/class-windows-azure-rest-api-client.php` - URL generation

**User Stories:**
- As a site owner, I can use my custom domain for media URLs
- As an admin, the plugin validates my CDN configuration is correct
- As a user, all media loads through my CDN automatically

---

### Phase 6: Asset Offloading (CSS, JS, Fonts)

**Objective:** Offload theme and plugin assets to Azure for faster delivery.

**Technical Requirements:**
- Scan output HTML for local asset URLs (CSS, JS, fonts, etc.)
- Upload identified assets to Azure (in separate container or path)
- Rewrite URLs in HTML output to point to Azure
- Handle cache busting (version query strings)
- Only offload production assets (not development files)
- Exclude list for problematic files

**Implementation Details:**
- Hook into `wp_enqueue_scripts` to capture registered assets
- Background job to upload assets (one-time or on theme/plugin update)
- Filter: `style_loader_src` and `script_loader_src` to rewrite URLs
- Store asset manifest in options table
- Admin page: scan site, preview assets, manual offload

**Files to Create:**
- `includes/class-azure-asset-offloader.php`
- `includes/class-azure-asset-scanner.php`
- Admin page for asset management

**Files to Modify:**
- `windows-azure-storage.php` - Register asset hooks

**User Stories:**
- As a site owner, CSS and JS files load from Azure CDN automatically
- As a developer, I can exclude specific files from offloading
- As an admin, I can trigger a re-scan when themes/plugins update

---

### Phase 7: Plugin Compatibility & Integrations

**Objective:** Ensure seamless operation with popular WordPress plugins, especially image optimization and thumbnail regeneration tools.

**Plugins to Support:**
- **ShortPixel** (Priority - detailed spec below)
- **Regenerate Thumbnails Pro** (Priority - detailed spec below)
- **WooCommerce:** Product images, downloadable files
- **Elementor:** Page builder image handling
- **Advanced Custom Fields (ACF):** Image and file fields
- **WPML:** Multilingual media handling
- **EWWW Image Optimizer:** Optimize before upload
- **Enable Media Replace:** Handle replaced files
- **MetaSlider:** Slider image support

#### ShortPixel Integration (Detailed Specification)

**Reference:** Based on [WP-Stateless ShortPixel Addon](https://github.com/udx/wp-stateless-shortpixel-addon/)

**Workflow:** Create Attachment → Upload Original (tracked to WP Media ID) → Optimize → Upload Optimized → Seamless Restoration

**Technical Requirements:**
- Store **BOTH optimized AND original files** on Azure
- Serve optimized version by default (primary URL)
- Keep original accessible for re-optimization or restoration
- Support WebP format generation and upload
- Handle bulk optimization + bulk offload scenarios
- Support re-optimization triggering re-upload to Azure
- Support "ephemeral mode" (remove from server after upload)
- Maintain optimization metadata in post meta

**Azure Storage Structure:**
```
/uploads/2025/01/
  ├── image.jpg              (optimized version - served by default)
  ├── image.jpg.webp         (WebP variant)
  ├── image-150x150.jpg      (optimized thumbnail)
  ├── image-150x150.jpg.webp (WebP thumbnail)
  └── /originals/
      ├── image.jpg          (original unoptimized version)
      └── image-150x150.jpg  (original thumbnail)
```

**WordPress Hooks to Implement:**

**Actions:**
1. `wp_generate_attachment_metadata` (priority 5) - Upload original file to Azure AFTER attachment created, BEFORE ShortPixel optimization
2. `shortpixel_image_optimised` - Upload optimized images to Azure (overwrites main path)
3. `shortpixel_before_restore_image` - Download originals from Azure using attachment ID post meta (seamless)
4. `shortpixel_after_restore_image` - Re-sync restored files to Azure main path
5. Custom: `azure_synced_image` - Handle WebP file syncing after optimization
6. Custom: `azure_sync_delete_file` - Remove WebP files from Azure when needed

**Filters:**
1. `shortpixel_image_exists` - Check if images exist on Azure (return true if found)
2. `shortpixel_image_urls` - Convert Azure URLs to proper format for ShortPixel
3. `shortpixel_skip_backup` - Skip ShortPixel's local backup system (we have originals on Azure)
4. `wp_get_attachment_url` - Serve optimized version by default
5. `azure_add_media_args` - Handle WebP image uploads with proper metadata

**Implementation Details:**

**Critical Timing Sequence (ShortPixel MUST Have Local File Access):**

```
Timeline:
--------
1. User uploads image.jpg
   └─> WordPress saves to: /wp-content/uploads/2025/01/image.jpg (LOCAL)

2. WordPress creates attachment with ID #123
   └─> Attachment exists in database

3. WordPress generates thumbnails (LOCAL)
   └─> image-150x150.jpg, image-300x300.jpg, etc. (all LOCAL)

4. wp_generate_attachment_metadata filter fires

   Priority 5 (Our Plugin - ShortPixel Integration):
   ├─> LOCAL file exists: /wp-content/uploads/2025/01/image.jpg ✓
   ├─> Upload COPY to Azure: /originals/2025/01/image.jpg
   ├─> Store in post meta: _azure_original_blob_path
   └─> LOCAL file still exists: /wp-content/uploads/2025/01/image.jpg ✓

   Priority 10 (ShortPixel):
   ├─> LOCAL file exists: /wp-content/uploads/2025/01/image.jpg ✓
   ├─> ShortPixel reads LOCAL file
   ├─> ShortPixel optimizes LOCAL file IN PLACE
   ├─> Generates WebP files (LOCAL)
   └─> LOCAL file now optimized: /wp-content/uploads/2025/01/image.jpg ✓

5. shortpixel_image_optimised action fires

   Our Plugin:
   ├─> LOCAL optimized file exists: /wp-content/uploads/2025/01/image.jpg ✓
   ├─> Upload to Azure main path: /2025/01/image.jpg (OPTIMIZED VERSION)
   ├─> Upload WebP files to Azure: /2025/01/image.jpg.webp
   └─> Optionally remove LOCAL files (ephemeral mode)

Result on Azure:
├─> /2025/01/image.jpg (OPTIMIZED - served to users)
├─> /2025/01/image.jpg.webp (WebP variant)
└─> /originals/2025/01/image.jpg (ORIGINAL - for restoration)
```

**Key Points:**
- ✅ Original is uploaded to Azure `/originals/` as a **COPY** (file remains local)
- ✅ ShortPixel processes the **LOCAL** file (always available)
- ✅ Optimized version uploaded to **main path** after optimization
- ✅ Priority 5 < Priority 10 ensures correct ordering
- ✅ Local file exists throughout the entire process
- ✅ Only removed if ephemeral mode enabled (after everything completes)

**Important: Core Azure Plugin Integration**
- The core Azure plugin may have its own `wp_generate_attachment_metadata` hook
- If it uploads to main path before ShortPixel runs, that's OK - we'll overwrite with optimized version
- Alternatively, add a flag to skip core auto-upload for images pending optimization:
  ```php
  update_post_meta( $attachment_id, '_azure_pending_optimization', true );
  // Core plugin checks this flag and skips auto-upload
  // After optimization completes, we delete the flag and upload optimized version
  ```

**Step 1: Upload Original COPY to Azure (Before ShortPixel Optimization)**
```php
add_filter( 'wp_generate_attachment_metadata', 'azure_upload_original_before_optimization', 5, 2 );

function azure_upload_original_before_optimization( $metadata, $attachment_id ) {
    // CRITICAL TIMING: Priority 5 runs BEFORE ShortPixel (priority 10)
    // This is a COPY operation - local file remains untouched for ShortPixel to process

    $file_path = get_attached_file( $attachment_id );

    if ( ! file_exists( $file_path ) ) {
        return $metadata;
    }

    // Determine Azure blob path for original
    $upload_dir = wp_upload_dir();
    $relative_path = str_replace( $upload_dir['basedir'] . '/', '', $file_path );
    $original_blob_path = 'originals/' . $relative_path;

    // Upload COPY to Azure /originals/ directory
    // LOCAL FILE REMAINS: /wp-content/uploads/2025/01/image.jpg ✓
    // AZURE COPY CREATED: /originals/2025/01/image.jpg ✓
    $uploaded = azure_upload_file_to_blob( $file_path, $original_blob_path );

    if ( $uploaded ) {
        // Store original blob path in post meta for seamless restoration
        update_post_meta( $attachment_id, '_azure_original_blob_path', $original_blob_path );
        update_post_meta( $attachment_id, '_azure_has_original', true );

        // Mark as pending optimization to skip core plugin auto-upload (if needed)
        update_post_meta( $attachment_id, '_azure_pending_optimization', true );

        // Also upload COPIES of original thumbnails
        if ( ! empty( $metadata['sizes'] ) ) {
            foreach ( $metadata['sizes'] as $size => $size_data ) {
                $thumb_path = dirname( $file_path ) . '/' . $size_data['file'];
                $thumb_relative = str_replace( $upload_dir['basedir'] . '/', '', $thumb_path );
                $thumb_original_blob = 'originals/' . $thumb_relative;

                if ( file_exists( $thumb_path ) ) {
                    // Upload COPY - local thumbnail remains for ShortPixel
                    azure_upload_file_to_blob( $thumb_path, $thumb_original_blob );
                }
            }
        }
    }

    // IMPORTANT: Return $metadata unchanged
    // Local files still exist for ShortPixel to process next (priority 10)
    return $metadata;
}
```

**Step 2: Upload Optimized Images and WebP Files (After ShortPixel Completes)**
```php
add_action( 'shortpixel_image_optimised', 'azure_handle_shortpixel_optimized', 10, 2 );

function azure_handle_shortpixel_optimized( $post_id, $optimization_data ) {
    // ShortPixel has finished optimizing the LOCAL files
    // Now we upload the OPTIMIZED versions to Azure main path

    $metadata = wp_get_attachment_metadata( $post_id );
    $upload_dir = wp_upload_dir();

    if ( empty( $metadata['file'] ) ) {
        return;
    }

    // Upload OPTIMIZED main file to Azure main path
    // LOCAL FILE: /wp-content/uploads/2025/01/image.jpg (NOW OPTIMIZED by ShortPixel)
    // AZURE MAIN: /2025/01/image.jpg (OPTIMIZED version for serving to users)
    $file_path = $upload_dir['basedir'] . '/' . $metadata['file'];
    $relative_path = str_replace( $upload_dir['basedir'] . '/', '', $file_path );

    if ( file_exists( $file_path ) ) {
        // Upload to main path (not /originals/) - this is the OPTIMIZED version
        azure_upload_file_to_blob( $file_path, $relative_path );
    }

    // Upload OPTIMIZED thumbnail sizes to main path
    if ( ! empty( $metadata['sizes'] ) ) {
        foreach ( $metadata['sizes'] as $size => $size_data ) {
            $thumb_path = dirname( $file_path ) . '/' . $size_data['file'];
            $thumb_relative = str_replace( $upload_dir['basedir'] . '/', '', $thumb_path );

            if ( file_exists( $thumb_path ) ) {
                // Upload OPTIMIZED thumbnail
                azure_upload_file_to_blob( $thumb_path, $thumb_relative );
            }
        }
    }

    // Trigger WebP sync (ShortPixel generates these during optimization)
    do_action( 'azure_synced_image', $post_id, $optimization_data );

    // Update optimization tracking and clear pending flag
    update_post_meta( $post_id, '_azure_shortpixel_optimized_date', time() );
    update_post_meta( $post_id, '_azure_has_original', true );
    delete_post_meta( $post_id, '_azure_pending_optimization' );
}

add_action( 'azure_synced_image', 'azure_sync_webp_files', 10, 2 );

function azure_sync_webp_files( $post_id, $optimization_data ) {
    // ShortPixel generates WebP files during optimization
    // These are LOCAL files: image.jpg.webp alongside image.jpg
    // We upload these to Azure for serving

    $metadata = wp_get_attachment_metadata( $post_id );
    $upload_dir = wp_upload_dir();

    if ( empty( $metadata['file'] ) ) {
        return;
    }

    // Upload main WebP variant (e.g., image.jpg.webp)
    // LOCAL FILE: /wp-content/uploads/2025/01/image.jpg.webp (created by ShortPixel)
    // AZURE: /2025/01/image.jpg.webp
    $file_path = $upload_dir['basedir'] . '/' . $metadata['file'];
    $webp_path = $file_path . '.webp';

    if ( file_exists( $webp_path ) ) {
        $webp_relative = str_replace( $upload_dir['basedir'] . '/', '', $webp_path );
        azure_upload_file_to_blob( $webp_path, $webp_relative );
    }

    // Upload thumbnail WebP files
    if ( ! empty( $metadata['sizes'] ) ) {
        foreach ( $metadata['sizes'] as $size => $size_data ) {
            $thumb_path = dirname( $file_path ) . '/' . $size_data['file'];
            $thumb_webp = $thumb_path . '.webp';

            if ( file_exists( $thumb_webp ) ) {
                $thumb_webp_relative = str_replace( $upload_dir['basedir'] . '/', '', $thumb_webp );
                azure_upload_file_to_blob( $thumb_webp, $thumb_webp_relative );
            }
        }
    }

    // NOW and only now: optionally remove LOCAL files if in ephemeral mode
    // All uploads complete, safe to delete from server
    if ( get_option( 'azure_ephemeral_mode', false ) ) {
        azure_remove_local_files( $post_id );
    }
}
```

**Step 3: Seamless Restoration (Automatic via Attachment ID)**
```php
add_action( 'shortpixel_before_restore_image', 'azure_download_originals_for_restore', 10, 1 );

function azure_download_originals_for_restore( $attachment_id ) {
    // Check if we have originals stored on Azure via post meta
    $has_original = get_post_meta( $attachment_id, '_azure_has_original', true );
    $original_blob_path = get_post_meta( $attachment_id, '_azure_original_blob_path', true );

    if ( ! $has_original || ! $original_blob_path ) {
        return; // No Azure originals tracked for this attachment
    }

    // Download from stored blob path - no manual lookup needed!
    $file_path = get_attached_file( $attachment_id );
    azure_download_blob_to_file( $original_blob_path, $file_path );

    // Download original thumbnails
    $metadata = wp_get_attachment_metadata( $attachment_id );
    $upload_dir = wp_upload_dir();

    if ( ! empty( $metadata['sizes'] ) ) {
        foreach ( $metadata['sizes'] as $size => $size_data ) {
            $thumb_path = dirname( $file_path ) . '/' . $size_data['file'];
            $thumb_relative = str_replace( $upload_dir['basedir'] . '/', '', $thumb_path );
            $thumb_original_blob = 'originals/' . $thumb_relative;

            // Download original thumbnail
            azure_download_blob_to_file( $thumb_original_blob, $thumb_path );
        }
    }

    // User just clicked "Restore" in ShortPixel UI - everything else is automatic!
}

add_action( 'shortpixel_after_restore_image', 'azure_sync_restored_to_main_path', 10, 1 );

function azure_sync_restored_to_main_path( $attachment_id ) {
    // After restoration, the local files are now originals again
    // Upload them to the main Azure path (overwrites optimized versions)
    $file_path = get_attached_file( $attachment_id );
    $metadata = wp_get_attachment_metadata( $attachment_id );
    $upload_dir = wp_upload_dir();

    // Upload restored main file to main Azure path
    $relative_path = str_replace( $upload_dir['basedir'] . '/', '', $file_path );

    if ( file_exists( $file_path ) ) {
        azure_upload_file_to_blob( $file_path, $relative_path );
    }

    // Upload restored thumbnails
    if ( ! empty( $metadata['sizes'] ) ) {
        foreach ( $metadata['sizes'] as $size => $size_data ) {
            $thumb_path = dirname( $file_path ) . '/' . $size_data['file'];
            $thumb_relative = str_replace( $upload_dir['basedir'] . '/', '', $thumb_path );

            if ( file_exists( $thumb_path ) ) {
                azure_upload_file_to_blob( $thumb_path, $thumb_relative );
            }
        }
    }

    // Clean up optimization metadata (no longer optimized)
    delete_post_meta( $attachment_id, '_azure_shortpixel_optimized_date' );
    // Note: Keep _azure_has_original and _azure_original_blob_path for future re-optimization
}
```

**Step 4: File Existence Checks (Check Azure When Local Missing)**
```php
add_filter( 'shortpixel_image_exists', 'azure_check_image_exists', 10, 2 );

function azure_check_image_exists( $exists, $file_path ) {
    // If file doesn't exist locally but we're in ephemeral mode, check Azure
    if ( ! $exists || ! file_exists( $file_path ) ) {
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace( $upload_dir['basedir'] . '/', '', $file_path );

        // Check main path on Azure (optimized version)
        if ( azure_blob_exists_by_path( $relative_path ) ) {
            return true;
        }

        // Check originals path on Azure
        $original_blob_path = 'originals/' . $relative_path;
        if ( azure_blob_exists_by_path( $original_blob_path ) ) {
            return true;
        }
    }

    return $exists;
}
```

**Step 5: Skip ShortPixel's Local Backup System**
```php
add_filter( 'shortpixel_skip_backup', 'azure_skip_shortpixel_backup', 10, 1 );

function azure_skip_shortpixel_backup( $skip ) {
    // We store originals on Azure, so skip ShortPixel's local backup system
    return true;
}

add_filter( 'shortpixel_backup_folder', 'azure_override_backup_folder', 10, 2 );

function azure_override_backup_folder( $backup_folder, $attachment_id ) {
    // Return null to indicate we handle backups via Azure
    // This prevents ShortPixel from creating local backup folders
    return null;
}
```

**Step 6: Serve Optimized by Default**
```php
add_filter( 'wp_get_attachment_url', 'azure_serve_optimized_url', 10, 2 );

function azure_serve_optimized_url( $url, $attachment_id ) {
    // By default, serve from main Azure path (which contains optimized version)
    // This is already handled by core Azure integration
    return $url;
}

// Helper function to get original URL when needed
function azure_get_original_url( $attachment_id ) {
    $metadata = wp_get_attachment_metadata( $attachment_id );
    $upload_dir = wp_upload_dir();

    if ( empty( $metadata['file'] ) ) {
        return false;
    }

    $relative_path = $metadata['file'];
    $original_blob_path = 'originals/' . $relative_path;

    // Return Azure URL for original version
    return azure_get_blob_url_by_path( $original_blob_path );
}
```

**Seamless Restoration Process:**
1. User clicks "Restore" in ShortPixel UI for attachment ID #123
2. `shortpixel_before_restore_image` hook fires with `$attachment_id = 123`
3. Plugin reads post meta: `_azure_original_blob_path` = `originals/2025/01/image.jpg`
4. Downloads from Azure automatically (no manual lookup, no user intervention)
5. ShortPixel restores the image locally
6. `shortpixel_after_restore_image` hook fires
7. Restored original is re-uploaded to main Azure path
8. **Result:** User experience is identical to local-only ShortPixel - completely seamless!

**Edge Cases to Handle:**
- **Dual Storage**: Both original (in /originals/) and optimized (in main path) stored on Azure
- **Attachment ID Tracking**: Original uploaded AFTER attachment created (has ID), stored in post meta
- **Upload Timing**: `wp_generate_attachment_metadata` priority 5 (before ShortPixel at priority 10)
- **Overwrite Pattern**: Optimized version overwrites main path after optimization completes
- **WebP Generation**: Upload .webp files alongside optimized images to main path
- **Restoration Workflow**: Automatic lookup via `_azure_original_blob_path` post meta - zero manual work
- **Ephemeral Mode**: Skip ShortPixel's local backup system entirely (we have Azure originals)
- **Re-Optimization**: Can re-optimize from Azure originals by downloading via stored blob path
- **Bulk Operations**: Queue coordination via Action Scheduler for bulk optimization + offload
- **Storage Cleanup**: Optionally delete originals from /originals/ after X days to save storage costs
- **Missing Originals**: If `_azure_has_original` is false, gracefully skip Azure restore (use ShortPixel's backup if available)

**Post Meta Fields:**
- `_azure_has_original` (boolean) - Indicates original is stored in Azure /originals/ path
- `_azure_shortpixel_optimized_date` (timestamp) - When optimization occurred
- `_azure_original_blob_path` (string) - Full blob path to original version

**User Stories:**
- As a site owner, both original and optimized images are stored on Azure
- As a user, the website serves optimized images by default for best performance
- As a user, if I re-optimize an image, the Azure optimized version is updated automatically
- As a user, if I restore an image, the original is downloaded from Azure and replaces the optimized version
- As a developer, I can access the original unoptimized file from Azure when needed
- As an admin, WebP images are automatically uploaded alongside optimized files
- As an admin, optimization status is visible alongside offload status in Media Library
- As a developer, ephemeral mode removes local files after upload to save disk space
- As a site owner, I have the flexibility to re-optimize from originals stored on Azure

#### Regenerate Thumbnails Pro Integration (Detailed Specification)

**Scenarios to Support:**
1. **Download if needed:** If original file not on server, download from Azure before regenerating
2. **Auto-upload regenerated sizes:** Upload new thumbnail sizes back to Azure after regeneration
3. **Delete old sizes:** Remove old thumbnail sizes from Azure that are no longer needed

**Technical Requirements:**
- Detect when thumbnail regeneration is requested
- Check if local file exists, download from Azure if missing
- Hook into regeneration completion to upload new sizes
- Identify and delete orphaned thumbnails on Azure
- Handle batch regeneration without blocking

**Implementation Details:**
- Hook: `regenerate_thumbnails_pro_resize_image` - Before regeneration starts
- Hook: `regenerate_thumbnails_pro_resized_image` - After regeneration completes
- Alternative hooks for standard Regenerate Thumbnails plugin compatibility

**Integration Points:**

**Scenario 1: Download if Needed**
```php
add_action( 'regenerate_thumbnails_pro_resize_image', 'azure_prepare_for_regeneration', 10, 2 );

function azure_prepare_for_regeneration( $attachment_id, $metadata ) {
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/' . $metadata['file'];

    // Check if local file exists
    if ( ! file_exists( $file_path ) ) {
        // Download from Azure
        $azure_url = get_post_meta( $attachment_id, '_azure_blob_url', true );

        if ( $azure_url ) {
            azure_download_attachment( $attachment_id, $include_thumbnails = true );
        }
    }
}
```

**Scenario 2: Auto-Upload Regenerated Sizes**
```php
add_action( 'regenerate_thumbnails_pro_resized_image', 'azure_upload_regenerated_sizes', 10, 2 );

function azure_upload_regenerated_sizes( $attachment_id, $new_metadata ) {
    // Get previous metadata to identify new sizes
    $old_metadata = get_post_meta( $attachment_id, '_azure_previous_metadata', true );

    // Identify new thumbnail sizes
    $new_sizes = array_diff_key( $new_metadata['sizes'], $old_metadata['sizes'] );

    // Upload new sizes to Azure
    foreach ( $new_sizes as $size_name => $size_data ) {
        azure_upload_thumbnail_size( $attachment_id, $size_name, $size_data );
    }

    // Update stored metadata
    update_post_meta( $attachment_id, '_azure_previous_metadata', $new_metadata );
}
```

**Scenario 3: Delete Old Sizes**
```php
add_action( 'regenerate_thumbnails_pro_resized_image', 'azure_cleanup_old_sizes', 10, 2 );

function azure_cleanup_old_sizes( $attachment_id, $new_metadata ) {
    // Get list of blobs for this attachment from Azure
    $azure_blobs = azure_list_attachment_blobs( $attachment_id );

    // Get current size names from new metadata
    $current_sizes = array_keys( $new_metadata['sizes'] );

    // Identify orphaned blobs (sizes that no longer exist)
    foreach ( $azure_blobs as $blob ) {
        $blob_size = azure_extract_size_from_blob_name( $blob );

        if ( ! in_array( $blob_size, $current_sizes ) && $blob_size !== 'full' ) {
            // Delete orphaned thumbnail from Azure
            azure_delete_blob( $blob );
        }
    }
}
```

**Edge Cases to Handle:**
- User regenerates thumbnails with local files removed → Download first
- Bulk regeneration of 1000+ images → Use Action Scheduler for re-upload queue
- Regeneration fails mid-process → Don't delete old sizes until confirmed success
- Multiple size registrations added/removed → Maintain size manifest in post meta
- Network failure during re-upload → Retry logic with backoff

**User Stories:**
- As a developer, I can regenerate thumbnails without manually downloading files from Azure
- As a user, regenerated thumbnails are automatically synced back to Azure
- As an admin, old unused thumbnail sizes are cleaned up from Azure to save storage costs
- As a site owner, bulk thumbnail regeneration works seamlessly with offloaded media

**Implementation Details (Continued):**
- Create helper function: `azure_list_attachment_blobs()` to query all blobs for an attachment
- Add size tracking in post meta: `_azure_thumbnail_sizes` (array of size names)
- Implement comparison logic to detect added/removed sizes
- Add admin notice after regeneration: "X new sizes uploaded to Azure, Y old sizes removed"
- Log all operations for debugging

#### Gravity Forms Integration (Detailed Specification)

**Reference:** Based on [WP-Stateless Gravity Forms Addon](https://github.com/udx/wp-stateless-gravity-forms-addon/)

**Objective:** Sync files uploaded via Gravity Forms to Azure and update URLs in form entries.

**Technical Requirements:**
- Intercept file uploads from Gravity Forms fields
- Upload files to Azure immediately after form submission
- Update form entry metadata with Azure URLs
- Handle file uploads, post image fields, and custom upload fields
- Support multiple file uploads per field
- Handle file deletion when entries are deleted
- Skip cache busting for export operations

**WordPress Hooks to Implement:**

**Filters:**
1. `gform_upload_path` - Modify upload directory paths for form submissions
2. `gform_save_field_value` - Intercept field values to sync files and update URLs
3. `gform_file_path_pre_delete_file` - Handle file deletion from Azure
4. `gform_after_create_post` - Process post image fields after post creation
5. `upload_dir` - Redirect export files to local `/tmp` instead of Azure

**Implementation Details:**

**Step 1: Upload Path Modification**
```php
add_filter( 'gform_upload_path', 'azure_gravity_forms_upload_path', 10, 2 );

function azure_gravity_forms_upload_path( $upload_path, $form_id ) {
    // Keep original path - we'll sync to Azure after upload
    return $upload_path;
}
```

**Step 2: Sync Files on Form Submission**
```php
add_filter( 'gform_save_field_value', 'azure_gravity_forms_sync_field', 10, 4 );

function azure_gravity_forms_sync_field( $value, $entry, $field, $form ) {
    // Only process file upload fields
    if ( ! in_array( $field->type, array( 'fileupload', 'post_image' ) ) ) {
        return $value;
    }

    // Handle multiple files (JSON array) or single file
    $files = is_string( $value ) && json_decode( $value ) ? json_decode( $value, true ) : array( $value );

    $azure_urls = array();

    foreach ( $files as $file_url ) {
        if ( empty( $file_url ) ) {
            continue;
        }

        // Get local file path from URL
        $upload_dir = wp_upload_dir();
        $file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $file_url );

        if ( file_exists( $file_path ) ) {
            // Upload to Azure
            $azure_url = azure_upload_gravity_form_file( $file_path, $form_id, $field->id, $entry['id'] );

            if ( $azure_url ) {
                $azure_urls[] = $azure_url;

                // Optionally remove local file if in ephemeral mode
                if ( get_option( 'azure_ephemeral_mode', false ) ) {
                    @unlink( $file_path );
                }
            }
        }
    }

    // Return Azure URLs (single or JSON array)
    if ( count( $azure_urls ) === 1 ) {
        return $azure_urls[0];
    } elseif ( count( $azure_urls ) > 1 ) {
        return json_encode( $azure_urls );
    }

    return $value;
}
```

**Step 3: Handle File Deletion**
```php
add_filter( 'gform_file_path_pre_delete_file', 'azure_gravity_forms_delete_file', 10, 1 );

function azure_gravity_forms_delete_file( $file_path ) {
    // Convert local path to Azure blob URL
    $azure_url = azure_get_blob_url_from_path( $file_path );

    if ( $azure_url ) {
        // Delete from Azure
        azure_delete_blob_by_url( $azure_url );
    }

    // Return original path (Gravity Forms will delete local if it exists)
    return $file_path;
}
```

**Step 4: Handle Post Creation**
```php
add_action( 'gform_after_create_post', 'azure_gravity_forms_sync_post_images', 10, 3 );

function azure_gravity_forms_sync_post_images( $post_id, $entry, $form ) {
    // Get all post image fields
    foreach ( $form['fields'] as $field ) {
        if ( $field->type !== 'post_image' ) {
            continue;
        }

        // Get featured image or image attachments created by Gravity Forms
        if ( has_post_thumbnail( $post_id ) ) {
            $attachment_id = get_post_thumbnail_id( $post_id );
            azure_sync_attachment( $attachment_id );
        }
    }
}
```

**Step 5: Skip Cache Busting for Exports**
```php
add_filter( 'upload_dir', 'azure_gravity_forms_export_to_tmp', 10, 1 );

function azure_gravity_forms_export_to_tmp( $upload_dir ) {
    // Detect Gravity Forms export operations
    if ( doing_action( 'wp_ajax_rg_start_export' ) ||
         ( isset( $_GET['page'] ) && $_GET['page'] === 'gf_export' ) ) {

        // Redirect exports to /tmp instead of Azure
        $upload_dir['path'] = '/tmp';
        $upload_dir['basedir'] = '/tmp';
    }

    return $upload_dir;
}
```

**User Stories:**
- As a site owner, files uploaded via Gravity Forms are automatically synced to Azure
- As a user, form entries display correct Azure URLs for uploaded files
- As an admin, when I delete a form entry, associated files are removed from Azure
- As a developer, Gravity Forms exports work without triggering Azure uploads

#### WooCommerce Integration (Detailed Specification)

**Reference:** Based on [WP-Stateless WooCommerce Addon](https://github.com/udx/wp-stateless-woocommerce-addon/)

**Objective:** Handle WooCommerce product images, downloadable files, and temporary export files.

**Technical Requirements:**
- Sync product images to Azure (handled by core media integration)
- Handle downloadable product files (PDFs, ZIPs, etc.)
- Skip Azure for temporary export/import files
- Support order attachments and invoice PDFs
- Handle product variation images
- Support WooCommerce placeholder images

**WordPress Hooks to Implement:**

**Filters:**
1. `upload_dir` - Redirect temporary export files to local storage
2. `woocommerce_file_download_method` - Handle downloadable product files from Azure
3. `woocommerce_product_variation_get_image_id` - Ensure variation images are synced

**Implementation Details:**

**Step 1: Skip Azure for Temporary Export Files**
```php
add_filter( 'upload_dir', 'azure_woocommerce_export_to_tmp', 10, 1 );

function azure_woocommerce_export_to_tmp( $upload_dir ) {
    // Detect WooCommerce export operations
    if ( doing_action( 'wp_ajax_woocommerce_do_ajax_product_export' ) ||
         ( isset( $_GET['action'] ) && $_GET['action'] === 'download_product_csv' ) ) {

        // Use local /tmp for temporary export files
        $upload_dir['path'] = '/tmp';
        $upload_dir['basedir'] = '/tmp';
        $upload_dir['url'] = '/tmp';
        $upload_dir['baseurl'] = '/tmp';
    }

    return $upload_dir;
}
```

**Step 2: Handle Downloadable Files**
```php
add_filter( 'woocommerce_downloadable_file_exists', 'azure_woocommerce_file_exists', 10, 2 );

function azure_woocommerce_file_exists( $exists, $file ) {
    // If file doesn't exist locally, check Azure
    if ( ! $exists ) {
        $azure_url = azure_get_blob_url_from_path( $file );

        if ( $azure_url ) {
            return azure_blob_exists( $azure_url );
        }
    }

    return $exists;
}

// Download from Azure when customer accesses downloadable product
add_filter( 'woocommerce_file_download_path', 'azure_woocommerce_download_path', 10, 2 );

function azure_woocommerce_download_path( $file_path, $product ) {
    // If file doesn't exist locally, check Azure
    if ( ! file_exists( $file_path ) ) {
        $azure_url = azure_get_blob_url_from_path( $file_path );

        if ( $azure_url ) {
            // Option 1: Redirect to Azure blob URL
            if ( get_option( 'azure_woocommerce_direct_downloads', true ) ) {
                return $azure_url; // Direct download from Azure
            }

            // Option 2: Download to temp, serve, delete
            $temp_file = download_url( $azure_url );
            if ( ! is_wp_error( $temp_file ) ) {
                return $temp_file;
            }
        }
    }

    return $file_path;
}
```

**Step 3: Sync Product Variation Images**
```php
add_action( 'woocommerce_save_product_variation', 'azure_sync_variation_image', 10, 2 );

function azure_sync_variation_image( $variation_id, $i ) {
    $variation = wc_get_product( $variation_id );

    if ( $variation && $variation->get_image_id() ) {
        azure_sync_attachment( $variation->get_image_id() );
    }
}
```

**Step 4: Handle Product Gallery Images**
```php
add_action( 'woocommerce_process_product_meta', 'azure_sync_product_gallery', 10, 1 );

function azure_sync_product_gallery( $post_id ) {
    $product = wc_get_product( $post_id );

    if ( ! $product ) {
        return;
    }

    // Sync featured image
    if ( $product->get_image_id() ) {
        azure_sync_attachment( $product->get_image_id() );
    }

    // Sync gallery images
    $gallery_ids = $product->get_gallery_image_ids();
    foreach ( $gallery_ids as $attachment_id ) {
        azure_sync_attachment( $attachment_id );
    }
}
```

**User Stories:**
- As a store owner, product images are automatically synced to Azure
- As a customer, downloadable products work seamlessly whether files are on Azure or local
- As an admin, product exports don't trigger unnecessary Azure uploads
- As a developer, product variation images are properly synced to Azure

**Files to Create:**
- `includes/integrations/class-azure-shortpixel-integration.php`
- `includes/integrations/class-azure-regenerate-thumbnails-integration.php`
- `includes/integrations/class-azure-gravity-forms-integration.php`
- `includes/integrations/class-azure-woocommerce-integration.php`
- `includes/integrations/class-azure-elementor-integration.php`
- `includes/integrations/class-azure-acf-integration.php`

**Files to Modify:**
- `windows-azure-storage.php` - Register integration classes
- `includes/class-windows-azure-rest-api-client.php` - Add helper methods for integration needs

**Testing Checklist:**

**ShortPixel Integration:**
- [ ] Upload new image → Verify original uploaded to Azure /originals/ path BEFORE optimization
- [ ] Optimize single image → Verify optimized version uploaded to main Azure path
- [ ] Verify dual storage → Confirm both original (/originals/) and optimized (main path) exist on Azure
- [ ] Re-optimize already-offloaded image → Verify Azure optimized blob overwritten, original preserved
- [ ] Bulk optimize 100 images → Verify all optimized versions uploaded, originals preserved
- [ ] ShortPixel WebP generation → Verify .webp files uploaded alongside optimized images
- [ ] Restore optimized image → Verify original downloaded from Azure /originals/, restored, re-uploaded to main path
- [ ] Check image exists (local deleted) → Verify ShortPixel can find image on Azure
- [ ] Ephemeral mode → Verify local files removed after upload, both versions on Azure
- [ ] Access original URL → Verify `azure_get_original_url()` returns correct /originals/ path

**Regenerate Thumbnails Integration:**
- [ ] Regenerate with local file present → New sizes uploaded
- [ ] Regenerate with local file removed → Download from Azure, regenerate, upload
- [ ] Add new thumbnail size → Old sizes remain, new size added to Azure
- [ ] Remove thumbnail size → Old size deleted from Azure
- [ ] Bulk regenerate 1000+ images → Background processing completes without errors

**Gravity Forms Integration:**
- [ ] Submit form with file upload field → Verify file uploaded to Azure
- [ ] Submit form with multiple file uploads → Verify all files uploaded
- [ ] Delete form entry → Verify Azure files deleted
- [ ] Post image field → Verify attachment created and synced to Azure
- [ ] Export form entries → Verify export doesn't trigger Azure uploads

**WooCommerce Integration:**
- [ ] Create product with images → Verify product images uploaded to Azure
- [ ] Add product variation with image → Verify variation image uploaded
- [ ] Product gallery images → Verify all gallery images uploaded
- [ ] Downloadable product → Verify download works from Azure
- [ ] Product export → Verify temporary files stay local (not uploaded to Azure)
- [ ] Order attachments → Verify attachments synced properly

**Other Integrations:**
- [ ] Create pages with Elementor using offloaded media → Display correctly
- [ ] ACF image fields → Display and upload correctly
- [ ] EWWW Image Optimizer → Processes before Azure upload

---

### Phase 8: UI/UX Modernization

**Objective:** Create modern, intuitive admin interface matching WP Offload Media quality.

**Technical Requirements:**
- React-based settings page (optional, or enhanced PHP/jQuery UI)
- Real-time connection testing with visual feedback
- Better error messages and troubleshooting guidance
- Onboarding wizard for first-time setup
- Dashboard widget showing storage stats
- Activity log showing recent uploads/deletions

**Implementation Details:**
- Use WordPress REST API for React frontend (if going React route)
- Or enhance existing jQuery with better UX patterns
- Add contextual help throughout admin pages
- Include video tutorials and documentation links
- Visual indicators for connection status, offload status, etc.

**Files to Create:**
- `assets/js/admin-ui.jsx` (if React) or enhance existing JS
- `assets/css/admin-modern.css`
- `includes/class-azure-onboarding-wizard.php`
- `includes/class-azure-dashboard-widget.php`

**Design Principles:**
- Clear visual hierarchy
- Immediate feedback on actions
- Progressive disclosure (advanced options hidden by default)
- Consistent with WordPress admin design language

---

## Development Guidelines for Claude Code

### Code Standards
- Follow WordPress Coding Standards (WPCS)
- Use WordPress-native functions (wp_remote_request, wp_insert_post, etc.)
- Properly escape output (`esc_html`, `esc_url`, `wp_kses`)
- Sanitize input (`sanitize_text_field`, `sanitize_url`, etc.)
- Use nonces for all form submissions
- Add proper PHP DocBlocks for all functions and classes

### Security Considerations
- Never expose Azure storage keys in frontend
- Validate all user input
- Check user capabilities before sensitive operations
- Use WordPress's prepared statements for database queries
- Implement rate limiting for API operations
- Log security events (unauthorized access attempts, etc.)

### Performance Optimization
- Use transients for caching Azure API responses
- Implement lazy loading for admin UI heavy operations
- Background jobs for bulk operations (never block UI)
- Batch API requests when possible
- Optimize database queries with proper indexes

### Error Handling
- Use `WP_Error` for error returns
- Log errors to WordPress debug.log
- Display user-friendly error messages in admin
- Provide actionable troubleshooting steps
- Never expose sensitive information in errors

### Testing Strategy
- Unit tests for core classes (PHPUnit)
- Integration tests for Azure API calls
- E2E tests for admin workflows (Cypress - already set up)
- Manual testing checklist for each feature
- Test on WordPress 6.0+ and PHP 8.0+

### File Organization
```
windows-azure-storage/
├── includes/
│   ├── class-windows-azure-rest-api-client.php (existing - extend as needed)
│   ├── class-azure-background-processor.php (new)
│   ├── class-azure-bulk-offload-ui.php (new)
│   ├── class-azure-media-library-integration.php (new)
│   ├── class-azure-local-file-manager.php (new)
│   ├── class-azure-container-copier.php (new)
│   ├── class-azure-asset-offloader.php (new)
│   ├── class-azure-asset-scanner.php (new)
│   ├── class-azure-onboarding-wizard.php (new)
│   ├── class-azure-dashboard-widget.php (new)
│   └── integrations/ (new directory)
│       ├── class-azure-shortpixel-integration.php
│       ├── class-azure-regenerate-thumbnails-integration.php
│       ├── class-azure-woocommerce-integration.php
│       ├── class-azure-elementor-integration.php
│       └── class-azure-acf-integration.php
├── assets/
│   ├── js/
│   │   ├── admin-bulk-offload.js (new)
│   │   ├── media-library-ajax.js (new)
│   │   ├── admin-container-copier.js (new)
│   │   └── admin-ui.jsx (new - optional React)
│   └── css/
│       ├── admin-bulk-offload.css (new)
│       ├── admin-container-copier.css (new)
│       └── admin-modern.css (new)
└── windows-azure-storage.php (modify - register new classes)
```

### Existing Files Reference
**Key existing files to understand:**
- `includes/class-windows-azure-rest-api-client.php` - Core REST API wrapper (updated in v5.0.0)
- `windows-azure-storage-util.php` - Upload hooks and URL rewriting
- `windows-azure-storage-settings.php` - Admin settings page
- `windows-azure-storage-dialog.php` - Legacy media browser

### Action Hooks & Filters to Use
**WordPress Core:**
- `wp_handle_upload` - Triggered after file upload
- `wp_get_attachment_url` - Filter attachment URLs
- `manage_media_columns` - Add Media Library columns
- `bulk_actions-upload` - Add bulk actions
- `delete_attachment` - Clean up when attachment deleted

**Plugin-Specific Filters to Add:**
- `azure_offload_enabled` - Allow disabling offload programmatically
- `azure_offload_file_types` - Filter allowed file types
- `azure_asset_exclude` - Exclude specific assets from offloading
- `azure_storage_url` - Modify final blob URL
- `azure_container_path` - Customize blob path structure

**Third-Party Plugin Hooks:**
- `shortpixel_image_optimised` - ShortPixel optimization complete
- `shortpixel/image/optimised` - ShortPixel alternative hook
- `regenerate_thumbnails_pro_resize_image` - Before thumbnail regeneration
- `regenerate_thumbnails_pro_resized_image` - After thumbnail regeneration

### Database Schema Extensions
**Post Meta Keys:**
- `_azure_offload_status` - enum: 'pending', 'offloaded', 'failed'
- `_azure_blob_url` - Full Azure blob URL
- `_azure_offload_date` - Timestamp of upload
- `_azure_local_removed` - Boolean: is local file deleted
- `_azure_container` - Which container (for multi-container setups)
- `_azure_shortpixel_optimized_date` - When ShortPixel last optimized
- `_azure_thumbnail_sizes` - Array of thumbnail size names offloaded
- `_azure_previous_metadata` - Previous attachment metadata (for regeneration comparison)

**Options:**
- `azure_bulk_offload_progress` - Serialized progress data
- `azure_asset_manifest` - List of offloaded assets
- `azure_last_scan_date` - When assets were last scanned

---

## Priority Order for Development

### Phase 1: Core Infrastructure (Weeks 1-4)
1. **Background Processing System** (Phase 1) - Foundation for all async operations
2. **Enhanced Media Library Integration** (Phase 2) - User-facing status and controls
3. **Remove from Server & Download Tools** (Phase 3) - Address disk space pain point

### Phase 2: Developer Workflow (Weeks 5-6)
4. **Copy Between Containers** (Phase 4) - Critical for staging/production workflow

### Phase 3: Performance & Integration (Weeks 7-10)
5. **CDN Integration** (Phase 5) - Performance optimization
6. **Asset Offloading** (Phase 6) - Extended disk space savings
7. **Plugin Compatibility** (Phase 7) - ShortPixel, Regenerate Thumbnails, etc.

### Phase 4: Polish (Weeks 11+)
8. **UI/UX Modernization** (Phase 8) - Enhanced user experience

---

## Success Criteria

### Functional Requirements
- ✅ Bulk offload 10,000+ media files without timeouts
- ✅ Real-time progress tracking in admin
- ✅ Media Library shows accurate offload status
- ✅ CDN URLs work correctly
- ✅ Container copying completes successfully
- ✅ ShortPixel integration: optimized files uploaded to Azure
- ✅ Regenerate Thumbnails: download, regenerate, upload, cleanup workflow works
- ✅ WooCommerce integration works seamlessly

### Performance Requirements
- Background jobs process ≥100 files/minute
- Admin UI responds in <2 seconds
- No blocking operations in user-facing code
- Efficient memory usage (handle large media libraries)

### Reliability Requirements
- Automatic retry on transient failures
- Graceful degradation if Azure unreachable
- Data integrity verification before file deletion
- Comprehensive error logging

### Developer Workflow Requirements
- Container copying reduces staging sync time by >90%
- CLI commands available for deployment automation
- No manual file transfers required between environments
- Consistent media references across all environments

---

## Notes for Claude Code

### Key Architectural Decisions
1. **Action Scheduler is REQUIRED** - Not optional, dependency check on activation
2. **Store metadata in post meta** not custom tables (unless performance requires)
3. **Extend existing REST client** rather than introducing new dependencies
4. **WordPress HTTP API only** - no cURL direct usage
5. **Progressive enhancement** - features should be optional/toggleable

### Common Pitfalls to Avoid
- Don't block admin UI with synchronous Azure operations
- Don't delete local files without verification
- Don't expose storage keys in JavaScript/HTML
- Don't assume file exists before operating on it
- Don't skip nonce verification on AJAX endpoints

### Testing Each Feature
- Create test site with sample media library
- Test with large files (100MB+)
- Test with slow Azure connection (throttle network)
- Test multisite installations
- Test with other plugins active (conflicts)

### Documentation Requirements
- Inline code comments explaining complex logic
- README.md with feature list and setup instructions
- CHANGELOG.md following Keep a Changelog format
- User-facing docs for each admin feature
- Developer hooks documentation

---

## Ready to Begin

Start with **Phase 1: Background Processing System** as it's foundational for many other features. The existing codebase already handles single file uploads well (with v5.0.0 modernization complete), so extending it to batch operations is the logical next step.

**Prerequisites Complete:**
- ✅ v5.0.0: Azure SDK modernization (wp_remote_request + REST API v2024-11-04)

**Next Up:**
- Phase 1: Background Processing System (requires Action Scheduler)
