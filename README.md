# Twig Grab

A Craft CMS 5 plugin that lets developers hover over any element in the browser, see which Twig template rendered it, and copy that context to clipboard for AI coding agents.

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later

## Installation

```bash
composer require wayborne/twig-grab
```

Then install the plugin from the Craft Control Panel under **Settings > Plugins**, or via the CLI:

```bash
php craft plugin/install twig-grab
```

### Local Development

Add as a path repository in your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../craft-twig-grab"
        }
    ]
}
```

Then require it:

```bash
composer require wayborne/twig-grab @dev
```

## Usage

When a logged-in user visits the frontend of your Craft site:

1. A floating icon appears in the bottom-right corner of the page
2. Press **G** (or click the icon) to activate **grab mode**
3. Hover over any element to see which Twig template rendered it
4. **Click** to copy the element's HTML and template context to your clipboard
5. Press **Escape** or **G** again to deactivate

The shortcut key is ignored when typing in form fields.

### What gets copied

**Plain text** — ready to paste into an AI coding agent:
```
[include] _components/card.twig:12

<div class="card">...</div>

Template chain:
  in block "content" at _pages/home.twig:45
  in template at _layouts/base.twig:1
```

**HTML** — includes a hidden `data-twig-grab` attribute with full structured JSON for programmatic access.

### Supported template types

- Templates (non-extending)
- Blocks
- Static includes (`{% include '_components/card' %}`)
- Dynamic includes (`{% include templateVar %}`)
- Embeds (`{% embed '_components/card' %}`)

### Dynamic content

Twig Grab automatically detects DOM changes while grab mode is active. Content injected by Alpine.js (`x-if`), Sprig, htmx, or other JavaScript frameworks is picked up without needing to re-activate.

## Configuration

Create `config/twig-grab.php` in your Craft project to customize settings:

```php
<?php

return [
    // The key to toggle grab mode (default: 'g')
    'shortcutKey' => 'g',
];
```

## Under the Hood

- A **Twig NodeVisitor** injects HTML comment annotations around template boundaries during compilation
- Annotated templates are stored in a **separate compiled template cache**, so anonymous visitors always get clean HTML
- A **vanilla web component** with Shadow DOM parses the comment tree and renders a highlight overlay on hover
- A **MutationObserver** watches for DOM changes while active, re-parsing when new twig-grab comments appear
- All annotations are gated behind a **runtime flag** — non-HTML responses (JSON, XML, RSS) are never annotated

## Roadmap

- [ ] Drill-down navigation (ArrowUp/Down for z-stack, ArrowLeft/Right for siblings)
- [ ] Click-to-lock selection
- [ ] Open-in-editor integration
- [ ] Live editing foundation
