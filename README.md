# Twig Grab for Craft CMS

**Instantly see which Twig template rendered any element on your page.**

Twig Grab adds a developer overlay to your Craft CMS site that lets you hover over any element and immediately see which template is responsible for it. Click to copy the full template context to your clipboard — ready to paste into your editor or AI coding assistant.

Think of it as browser DevTools, but for your Twig templates.

## Features

- **Hover to identify** — Activate grab mode and hover over any element to see its source template and line number
- **Click to copy** — One click copies the element's HTML and full template chain to your clipboard
- **Template chain** — See the complete ancestry: which template included which block, all the way up to your layout
- **Works with everything** — Static includes, dynamic includes, embeds, and blocks are all supported
- **Dynamic content aware** — Content injected by Alpine.js, Sprig, htmx, or any JavaScript framework is automatically detected
- **Zero impact on visitors** — Annotations are only served to logged-in users. Anonymous visitors always get clean, unmodified HTML
- **No frontend dependencies** — Pure vanilla JavaScript with Shadow DOM isolation. No framework conflicts, no CSS bleed

## Installation

### Plugin Store

Search for **Twig Grab** in the Craft Plugin Store and click Install.

### Composer

```bash
composer require wayborne/twig-grab
php craft plugin/install twig-grab
```

## How It Works

1. Press **G** (or click the floating icon) to activate grab mode
2. Hover over any element — a highlight overlay shows the template name and line number
3. Click to copy the template context to your clipboard
4. Press **Escape** or **G** again to deactivate

The copied output includes the element's HTML, template type, and the full template chain — giving you (or your AI assistant) everything needed to find and edit the right file.

```
[include] _components/card.twig:12

<div class="card">...</div>

Template chain:
  in block "content" at _pages/home.twig:45
  in template at _layouts/base.twig:1
```

## Configuration

Optionally customize the shortcut key via `config/twig-grab.php`:

```php
<?php

return [
    'shortcutKey' => 'g',
];
```

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later

## Roadmap

- [ ] Drill-down navigation through nested templates
- [ ] Click-to-lock selection
- [ ] Open-in-editor integration

## License

[MIT](LICENSE)
