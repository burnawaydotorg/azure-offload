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

**Workflow:** Optimize → Offload

**Technical Requirements:**
- Hook into ShortPixel's optimization completion event
- Ensure **optimized file** (not original) is uploaded to Azure
- Handle bulk optimization + bulk offload scenarios
- Support re-optimization triggering re-upload to Azure
- Maintain optimization metadata in post meta

**Implementation Details:**
- Hook: `shortpixel_image_optimised` - Trigger after optimization completes
- Hook: `shortpixel/image/optimised` - Alternative hook for newer ShortPixel versions
- Check if file was modified by comparing hash/timestamp
- If optimized file changed, trigger re-upload to Azure (overwrite existing blob)
- Store optimization state in post meta: `_azure_shortpixel_optimized_date`
- Coordinate with background processor to avoid duplicate uploads

**Integration Points:**
```php
// Pseudo-code for integration
add_action( 'shortpixel_image_optimised', 'azure_handle_shortpixel_optimized', 10, 2 );

function azure_handle_shortpixel_optimized( $post_id, $optimization_data ) {
    // Get attachment metadata
    $metadata = wp_get_attachment_metadata( $post_id );

    // Check if already offloaded
    $azure_url = get_post_meta( $post_id, '_azure_blob_url', true );

    if ( $azure_url ) {
        // Re-upload optimized version to Azure (overwrite)
        azure_reupload_attachment( $post_id, $overwrite = true );
    } else {
        // First-time offload after optimization
        azure_offload_attachment( $post_id );
    }

    // Update optimization tracking
    update_post_meta( $post_id, '_azure_shortpixel_optimized_date', time() );
}
```

**Edge Cases to Handle:**
- User manually re-optimizes already-offloaded image → Detect and re-upload
- Bulk optimization running while bulk offload running → Queue coordination
- Local file removed but optimization requested → Download from Azure first, optimize, re-upload
- ShortPixel WebP generation → Upload both original and WebP versions to Azure

**User Stories:**
- As a site owner, images are automatically optimized before being offloaded to Azure
- As a user, if I re-optimize an image, the Azure version is updated automatically
- As an admin, optimization status is visible alongside offload status in Media Library

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

**Files to Create:**
- `includes/integrations/class-azure-shortpixel-integration.php`
- `includes/integrations/class-azure-regenerate-thumbnails-integration.php`
- `includes/integrations/class-azure-woocommerce-integration.php`
- `includes/integrations/class-azure-elementor-integration.php`
- `includes/integrations/class-azure-acf-integration.php`

**Files to Modify:**
- `windows-azure-storage.php` - Register integration classes
- `includes/class-windows-azure-rest-api-client.php` - Add helper methods for integration needs

**Testing Checklist:**
- [ ] ShortPixel: Optimize single image → Verify optimized version uploaded to Azure
- [ ] ShortPixel: Re-optimize already-offloaded image → Verify Azure blob overwritten
- [ ] ShortPixel: Bulk optimize 100 images → Verify all optimized versions uploaded
- [ ] Regenerate Thumbnails: Regenerate with local file present → New sizes uploaded
- [ ] Regenerate Thumbnails: Regenerate with local file removed → Download, regenerate, upload
- [ ] Regenerate Thumbnails: Add new thumbnail size → Old sizes remain, new size added to Azure
- [ ] Regenerate Thumbnails: Remove thumbnail size → Old size deleted from Azure
- [ ] Upload product images in WooCommerce
- [ ] Create pages with Elementor using offloaded media
- [ ] ACF image fields display correctly
- [ ] EWWW Image Optimizer processes before Azure upload

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
