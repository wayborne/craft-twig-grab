# Twig Grab

A Craft CMS 5 plugin that lets developers hover over any element in the browser and see which Twig template rendered it.

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

## How It Works

When a logged-in user visits the frontend of your Craft site:

1. A floating icon appears in the bottom-right corner of the page
2. Click it to activate **grab mode**
3. Hover over any element to see which Twig template rendered it
4. Press **Escape** or click the icon again to deactivate

### Under the Hood

- A **Twig NodeVisitor** injects HTML comment annotations around template boundaries during compilation
- Annotated templates are stored in a **separate compiled template cache**, so anonymous visitors always get clean HTML
- A **vanilla web component** with Shadow DOM parses the comment tree and renders a highlight overlay on hover
- All annotations are gated behind a **runtime flag** — non-HTML responses (JSON, XML, RSS) are never annotated

## Roadmap

- [ ] Copy element context to clipboard for AI coding agents
- [ ] Drill-down navigation (ArrowUp/Down for z-stack, ArrowLeft/Right for siblings)
- [ ] Dynamic include and embed support
- [ ] MutationObserver for dynamically inserted content (htmx, Sprig, Alpine.js)
- [ ] Open-in-editor integration
- [ ] Live editing foundation
