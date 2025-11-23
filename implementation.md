# Azure Media Offload - Enhanced Feature Development Specification

## Project Overview
Fork of [10up/windows-azure-storage](https://github.com/10up/windows-azure-storage) with enhanced features matching WP Offload Media's capabilities, specifically for Azure Blob Storage.

**Base Plugin Architecture:**
- Uses custom REST API client (`Windows_Azure_Rest_Api_Client`) - no deprecated SDK dependencies
- WordPress HTTP API (`wp_remote_request`) for all Azure operations
- Existing hooks into WordPress media upload pipeline
- Current support: new uploads, URL rewriting, container management

---

## Feature Implementation Roadmap

### Phase 1: Background Processing System

**Objective:** Offload existing Media Library items without blocking the admin interface.

**Technical Requirements:**
- Integrate Action Scheduler (used by WooCommerce) or build custom background processor
- Create admin page for bulk offload operations
- Implement pause/resume functionality
- Add progress tracking with real-time updates (AJAX or REST API endpoint)
- Queue system should handle failures gracefully with retry logic

**Implementation Details:**
- Create new class: `Azure_Background_Processor` 
- Hook into Action Scheduler: `as_enqueue_async_action()` for queuing media items
- Store progress in WordPress options table or custom table
- Build admin UI at `Settings > Microsoft Azure > Bulk Offload`
- Add WP-CLI command for offloading via CLI: `wp azure offload`

**Files to Create:**
- `includes/class-azure-background-processor.php`
- `includes/class-azure-bulk-offload-ui.php`
- `assets/js/admin-bulk-offload.js`
- `assets/css/admin-bulk-offload.css`

**Files to Modify:**
- `windows-azure-storage-settings.php` - Add new settings page tab
- `windows-azure-storage.php` - Register new classes

**User Stories:**
- As a site owner, I can click "Offload Existing Media" and see progress without keeping the tab open
- As a developer, I can run `wp azure offload --limit=100` to offload media via CLI
- As a user, I can pause offloading, close my browser, return later, and resume

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

### Phase 4: Private Media & Signed URLs

**Objective:** Serve private/restricted content using Azure SAS (Shared Access Signature) URLs.

**Technical Requirements:**
- Add per-attachment privacy setting (public/private)
- Generate SAS URLs with configurable expiration (default 1 hour)
- Integrate with membership plugins (WooCommerce, Easy Digital Downloads)
- Hook into `wp_get_attachment_url` to return SAS URL for private content
- Cache SAS tokens to avoid regenerating on every request
- Add setting: "Default expiration time for private URLs"

**Implementation Details:**
- Extend `Windows_Azure_Rest_Api_Client` to generate SAS tokens
- SAS token formula: `?sv=2021-06-08&ss=b&srt=o&sp=r&se=EXPIRY&st=START&spr=https&sig=SIGNATURE`
- Add meta box to attachment edit screen for privacy control
- Use transient cache for SAS URLs (cache key: `azure_sas_{attachment_id}_{timestamp}`)
- Filter attachment URLs based on user permissions

**Files to Create:**
- `includes/class-azure-sas-generator.php`
- `includes/class-azure-private-media.php`
- `assets/js/admin-privacy-control.js`

**Files to Modify:**
- `includes/class-windows-azure-rest-api-client.php` - Add SAS generation method
- `windows-azure-storage-util.php` - Hook into URL filters

**User Stories:**
- As a membership site owner, I can mark files as private for paying members only
- As a WooCommerce store owner, digital downloads are protected with expiring URLs
- As a user, private files automatically have access control without manual work

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

### Phase 6: Copy Between Containers

**Objective:** Enable staging-to-production workflows by copying media between containers.

**Technical Requirements:**
- Admin tool: "Copy Container Contents"
- Select source and destination containers
- Background processing for large containers
- Option to overwrite existing files or skip duplicates
- Progress indicator and completion report

**Implementation Details:**
- Use Azure Blob Copy API (x-ms-copy-source header)
- Server-side copy (fast, no download/reupload)
- Background job via Action Scheduler for bulk operations
- Store mapping of old URL → new URL for search/replace

**Files to Create:**
- `includes/class-azure-container-copier.php`
- Admin page at `Settings > Microsoft Azure > Copy Container`

**User Stories:**
- As a developer, I can copy production media to staging without manual downloads
- As an admin, copying happens in background without timeout issues
- As a user, I see progress and can track which files were copied

---

### Phase 7: Asset Offloading (CSS, JS, Fonts)

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

### Phase 8: Plugin Compatibility & Integrations

**Objective:** Ensure seamless operation with popular WordPress plugins.

**Plugins to Support:**
- **WooCommerce:** Product images, downloadable files
- **Elementor:** Page builder image handling
- **Advanced Custom Fields (ACF):** Image and file fields
- **WPML:** Multilingual media handling
- **EWWW Image Optimizer:** Optimize before upload
- **ShortPixel:** Image compression integration
- **Enable Media Replace:** Handle replaced files
- **Regenerate Thumbnails Pro:** Regenerate from Azure
- **MetaSlider:** Slider image support

**Implementation Details:**
- Hook into each plugin's filters/actions for media handling
- Test with each plugin to identify issues
- Document compatibility and workarounds
- Add automatic detection and display compatibility notices

**Files to Create:**
- `includes/integrations/class-azure-woocommerce-integration.php`
- `includes/integrations/class-azure-elementor-integration.php`
- `includes/integrations/class-azure-acf-integration.php`
- (One file per integration)

**Testing Checklist:**
- [ ] Upload product images in WooCommerce
- [ ] Create pages with Elementor using offloaded media
- [ ] ACF image fields display correctly
- [ ] Image optimization plugins process before Azure upload
- [ ] Thumbnail regeneration works with offloaded files

---

### Phase 9: UI/UX Modernization

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
│   ├── class-azure-sas-generator.php (new)
│   ├── class-azure-private-media.php (new)
│   ├── class-azure-container-copier.php (new)
│   ├── class-azure-asset-offloader.php (new)
│   ├── class-azure-asset-scanner.php (new)
│   ├── class-azure-onboarding-wizard.php (new)
│   ├── class-azure-dashboard-widget.php (new)
│   └── integrations/ (new directory)
│       ├── class-azure-woocommerce-integration.php
│       ├── class-azure-elementor-integration.php
│       └── class-azure-acf-integration.php
├── assets/
│   ├── js/
│   │   ├── admin-bulk-offload.js (new)
│   │   ├── media-library-ajax.js (new)
│   │   ├── admin-privacy-control.js (new)
│   │   └── admin-ui.jsx (new - optional React)
│   └── css/
│       ├── admin-bulk-offload.css (new)
│       └── admin-modern.css (new)
└── windows-azure-storage.php (modify - register new classes)
```

### Existing Files Reference
**Key existing files to understand:**
- `includes/class-windows-azure-rest-api-client.php` - Core REST API wrapper
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
- `azure_sas_expiration` - Customize SAS URL expiration
- `azure_asset_exclude` - Exclude specific assets from offloading
- `azure_storage_url` - Modify final blob URL
- `azure_container_path` - Customize blob path structure

### Database Schema Extensions
**Post Meta Keys:**
- `_azure_offload_status` - enum: 'pending', 'offloaded', 'failed'
- `_azure_blob_url` - Full Azure blob URL
- `_azure_offload_date` - Timestamp of upload
- `_azure_local_removed` - Boolean: is local file deleted
- `_azure_is_private` - Boolean: requires SAS URL
- `_azure_container` - Which container (for multi-container setups)

**Options:**
- `azure_bulk_offload_progress` - Serialized progress data
- `azure_asset_manifest` - List of offloaded assets
- `azure_last_scan_date` - When assets were last scanned

---

## Priority Order for Development

### Must Have (MVP - Weeks 1-4)
1. Background Processing System (Phase 1)
2. Enhanced Media Library Integration (Phase 2)
3. Remove from Server & Download Tools (Phase 3)

### Should Have (V1.0 - Weeks 5-8)
4. Private Media & Signed URLs (Phase 4)
5. CDN Integration (Phase 5)
6. Copy Between Containers (Phase 6)

### Nice to Have (V1.1+ - Weeks 9+)
7. Asset Offloading (Phase 7)
8. Plugin Compatibility (Phase 8)
9. UI/UX Modernization (Phase 9)

---

## Success Criteria

### Functional Requirements
- ✅ Bulk offload 10,000+ media files without timeouts
- ✅ Real-time progress tracking in admin
- ✅ Media Library shows accurate offload status
- ✅ Private files protected with SAS URLs
- ✅ CDN URLs work correctly
- ✅ Container copying completes successfully
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

---

## Notes for Claude Code

### Key Architectural Decisions
1. **Use Action Scheduler over WP-Cron** for reliability
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

Start with **Phase 1: Background Processing System** as it's foundational for many other features. The existing codebase already handles single file uploads well, so extending it to batch operations is the logical next step.

Good luck! 🚀
