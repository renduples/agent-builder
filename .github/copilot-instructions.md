# Copilot Instructions for Agent Builder

## Last Completed Task

**Task:** Documentation Consolidation  
**Date:** January 28, 2026  
**Status:** ✅ Complete  
**Pull Request:** #3

### Summary

The last major task was consolidating all documentation from the website (https://agentic-plugin.com/documentation/) into the GitHub Wiki. This centralized all documentation in one authoritative location and eliminated duplication.

### What Was Done

1. **Created New Wiki Pages:**
   - `Architecture.md` (27 KB) - Consolidated 6 roadmap subpages into comprehensive architecture documentation
   - `Use-Cases.md` (8 KB) - Real-world scenarios and examples
   - `Discussion-Points.md` (11 KB) - Open questions for community input
   - Updated `Roadmap.md` - Added migration path with 5 phases

2. **Consolidated Content:**
   - Moved all roadmap documentation from website to GitHub Wiki
   - Organized content into logical groupings
   - Created proper table of contents and navigation
   - Added version control through Git

3. **Files Added to Repository:**
   - `.github/DOCUMENTATION_CONSOLIDATION.md` - Complete summary of the consolidation work
   - `.github/wiki/` directory - Contains all wiki markdown files
   - `.github/ISSUE_TEMPLATE/` - Issue templates for bug reports and feature requests
   - `.github/pull_request_template.md` - PR template
   - `.github/workflows/release.yml` - Release workflow

### Benefits Achieved

✅ **Single Source of Truth** - No confusion about which version is current  
✅ **Community Collaboration** - Wiki is easier for community to edit  
✅ **Reduced Maintenance** - Update one place instead of two  
✅ **Better Organization** - Logical grouping with proper navigation

### Reference Documentation

For complete details about the documentation consolidation, see:
- `.github/DOCUMENTATION_CONSOLIDATION.md` - Full consolidation summary
- [GitHub Wiki](https://github.com/renduples/agent-builder/wiki) - Live documentation

## Repository Overview

**Agent Builder** is a WordPress plugin that allows users to build AI agents without writing code. It provides:
- Agent creation and management
- LLM integration (OpenAI, Anthropic)
- Marketplace for sharing agents
- Security and approval workflows
- Job queue for async processing

## Current State

The repository is in active development with:
- Main codebase in PHP
- Comprehensive test coverage (unit and integration tests)
- WordPress coding standards (PHPCS/WPCS)
- Documentation in GitHub Wiki
- Issue #2 open for PHPCS cleanup (311 errors, 60 warnings remaining)

## Working with This Repository

### Running Tests
```bash
composer install
./tests/run-tests.sh
```

### Code Standards
```bash
vendor/bin/phpcs --standard=WordPress admin includes templates
vendor/bin/phpcbf --standard=WordPress admin includes templates
```

### Key Directories
- `admin/` - WordPress admin interface pages
- `includes/` - Core plugin classes
- `library/` - Built-in agent implementations
- `tests/` - Unit and integration tests
- `templates/` - Frontend templates
- `.github/wiki/` - Wiki documentation source

## Instructions for Future Tasks

When working on this repository:

1. **Code Style:** Follow WordPress coding standards (WPCS)
2. **Testing:** Add tests for new functionality, run existing tests
3. **Documentation:** Update wiki pages for significant changes
4. **Security:** Review code for security vulnerabilities
5. **Minimal Changes:** Make surgical, focused changes only

### Common Tasks

**Adding a New Feature:**
1. Review existing architecture in wiki
2. Add tests first (TDD approach)
3. Implement feature following WordPress patterns
4. Run PHPCS and fix violations
5. Update documentation if needed

**Fixing Bugs:**
1. Reproduce the issue
2. Add a test that fails
3. Fix the bug
4. Verify test passes
5. Check for related issues

**Documentation Updates:**
1. Edit files in `.github/wiki/`
2. Follow existing markdown structure
3. Update table of contents if adding new pages
4. Keep it synchronized with live wiki

## Next Steps

After the documentation consolidation, potential next tasks include:
1. Complete PHPCS cleanup (Issue #2)
2. Implement Phase 2 roadmap items (Q2 2026)
3. Add more built-in agents to the library
4. Enhance marketplace functionality
5. Improve test coverage

---

**Note:** This file provides context for Copilot coding agents working on this repository. It should be updated when significant milestones are completed.
