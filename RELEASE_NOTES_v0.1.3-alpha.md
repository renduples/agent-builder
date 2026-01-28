# 🚀 Agentic Plugin v0.1.3-alpha — Naming Standardization & System Requirements

A comprehensive update bringing naming consistency, architectural improvements, and new system validation tools.

## Major Changes

### Plugin Renaming & Standardization
- **Main file renamed**: `agentic-core.php` → `agentic-plugin.php`
- **Plugin name**: "Agentic Core" → "Agentic Plugin"
- **Namespace simplified**: `Agentic\Core` → `Agentic` (cleaner, more accurate)
- **Text domain standardized**: `agentic-core` → `agentic-plugin` across all 27 files
- **Constants renamed**: `AGENTIC_CORE_*` → `AGENTIC_*` throughout codebase
- **Version constant**: `AGENTIC_VERSION` → `AGENTIC_PLUGIN_VERSION`

### New Features

#### System Requirements Checker
- **Backend class**: `includes/class-system-checker.php` with comprehensive validation
- **REST API endpoints**:
  - `POST /wp-json/agentic/v1/system-check` — Run full system requirements check
  - `POST /wp-json/agentic/v1/timeout-test` — Test max execution time for Agent Builder
- **Admin UI integration**: Settings page now displays system requirements status
- **JavaScript implementation**: Real-time system check with visual pass/fail indicators
- **Validation checks**:
  - PHP version (8.1+)
  - WordPress version (6.4+)
  - Max execution time (60s+ recommended)
  - Memory limit (256MB+ recommended)
  - Permalinks enabled
  - API key configured
  - REST API functional

### Technical Improvements
- Updated plugin directory paths in library agents (`/agentic-core/` → `/agentic-plugin/`)
- Consistent constant usage across all core classes
- Improved namespace clarity (removed confusing "Core" layer)
- Better alignment between code version and GitHub releases

## What's Included
- Plugin entry: `agentic-plugin.php` with `AGENTIC_PLUGIN_VERSION = 0.1.3-alpha`
- Core classes in `includes/` including new System Requirements Checker
- Pre-built agents in `library/` with updated path references
- Admin pages in `admin/` with enhanced settings UI
- Frontend assets in `assets/` with new system checker JavaScript
- Documentation in `docs/` including System Requirements Checker spec

## Upgrade Notes

### Breaking Changes
⚠️ **Main file renamed**: If you have any custom code referencing `agentic-core.php`, update to `agentic-plugin.php`

⚠️ **Constants renamed**: Update any custom code using:
- `AGENTIC_CORE_VERSION` → `AGENTIC_PLUGIN_VERSION`
- `AGENTIC_CORE_DIR` → `AGENTIC_PLUGIN_DIR`
- `AGENTIC_CORE_URL` → `AGENTIC_PLUGIN_URL`
- `AGENTIC_CORE_BASENAME` → `AGENTIC_PLUGIN_BASENAME`
- `AGENTIC_CORE_FILE` → `AGENTIC_PLUGIN_FILE`

⚠️ **Namespace change**: Update any custom agents extending core classes:
- `use Agentic\Core\Agent_Base;` → `use Agentic\Agent_Base;`

⚠️ **Text domain**: Translation files should now use `agentic-plugin` domain

### Recommended Actions
1. Run System Requirements Check: Agentic → Settings → "Run System Check"
2. Verify all agents load correctly after update
3. Check custom agents for namespace/constant compatibility

## Files Changed in This Release
- **26 files modified**, **3 files created**, **1 file renamed**
- **1,043 insertions**, **255 deletions**

## API Endpoints (Updated)
- `POST /wp-json/agent/v1/chat` — Agent chat
- `GET /wp-json/agent/v1/status` — System status
- `GET /wp-json/agent/v1/capabilities` — Available capabilities
- `POST /wp-json/agentic/v1/system-check` — **NEW** System requirements validation
- `POST /wp-json/agentic/v1/timeout-test` — **NEW** Execution time test

## Documentation Updates
- **NEW**: `docs/SYSTEM_REQUIREMENTS_CHECKER.md` — System Checker specification
- Updated all internal references to use new naming conventions

## Known Issues
- Pre-existing WordPress coding standards formatting issues remain
- No functional breaking changes introduced

## What's Next (v0.1.4)
- WordPress coding standards compliance improvements
- Additional system validation features
- Enhanced error messaging and diagnostics

## Migration from v0.1.0-alpha
This release maintains backward compatibility at the functional level. However, due to naming changes:
1. Deactivate the old "Agentic Core" plugin
2. Install the new "Agentic Plugin" 
3. Reactivate and verify settings
4. Update any custom code using old constant/namespace names

Your data (audit logs, approvals, settings) will be preserved as database table names remain unchanged.

---

**Commit**: `2f7acad`  
**Tag**: `v0.1.3-alpha`  
**Released**: January 28, 2026

— Built by the Agentic community 🤖
