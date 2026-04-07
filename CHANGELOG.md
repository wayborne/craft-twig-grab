# Changelog

All notable changes to Twig Grab will be documented in this file.

## 1.0.0 - 2026-04-03

### Added
- Hover to identify any element's source Twig template and line number
- Click to copy template context (HTML + full template chain) to clipboard
- Multi-format clipboard output (text/plain + text/html with embedded JSON)
- Full template chain tracking through includes, embeds, and blocks
- Dynamic include and embed support with correct template name resolution
- MutationObserver for dynamic content (Alpine.js, Sprig, htmx)
- Configurable keyboard shortcut (default: G) via `config/twig-grab.php`
- Parallel compiled template cache — annotated for logged-in users, clean for visitors
- Craft ClearCaches integration for grab cache directory
- Shadow DOM isolation for zero CSS conflicts
- Visual feedback: highlight overlay, flash animation, toast notification
