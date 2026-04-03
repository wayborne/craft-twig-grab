/**
 * Twig Grab — Frontend overlay
 *
 * Vanilla web component with Shadow DOM.
 * Parses twig-grab HTML comments, builds a region tree with DOM Ranges,
 * and renders a highlight overlay with template name + line number.
 */
(function () {
    'use strict';

    const COMMENT_START_PREFIX = ' twig-grab:start ';
    const COMMENT_END_PREFIX = ' twig-grab:end ';
    const CONFIG = window.__twigGrabConfig || {};
    const SHORTCUT_KEY = CONFIG.shortcutKey || 'g';

    // ── Region tree ──────────────────────────────────────────────────

    class TwigGrabRegion {
        constructor(data, startComment) {
            this.type = data.type;         // 'template' | 'block' | 'include'
            this.template = data.template;
            this.line = data.line || 0;
            this.block = data.block || '';
            this.children = [];
            this.parent = null;
            this.startComment = startComment;
            this.endComment = null;
            this.range = null;             // DOM Range from start to end comment
        }

        get label() {
            let label = this.template;
            if (this.block) {
                label += ' :: ' + this.block;
            }
            if (this.line) {
                label += ':' + this.line;
            }
            return label;
        }

        getBoundingRect() {
            if (!this.range) return null;
            return this.range.getBoundingClientRect();
        }
    }

    // ── Comment parser ───────────────────────────────────────────────

    function parseCommentTree(root) {
        const regions = [];
        const stack = [];
        const allRegions = [];

        const walker = document.createTreeWalker(
            root,
            NodeFilter.SHOW_COMMENT,
            null
        );

        let node;
        while ((node = walker.nextNode())) {
            const text = node.textContent;

            if (text.startsWith(COMMENT_START_PREFIX)) {
                const json = text.slice(COMMENT_START_PREFIX.length).trim();
                try {
                    const data = JSON.parse(json);
                    const region = new TwigGrabRegion(data, node);

                    if (stack.length > 0) {
                        region.parent = stack[stack.length - 1];
                        stack[stack.length - 1].children.push(region);
                    } else {
                        regions.push(region);
                    }

                    stack.push(region);
                    allRegions.push(region);
                } catch (e) {
                    // Malformed comment — skip
                }
            } else if (text.startsWith(COMMENT_END_PREFIX)) {
                if (stack.length > 0) {
                    const region = stack.pop();
                    region.endComment = node;

                    // Create a DOM Range spanning from start to end comment
                    try {
                        const range = document.createRange();
                        range.setStartAfter(region.startComment);
                        range.setEndBefore(node);
                        region.range = range;
                    } catch (e) {
                        // Range creation can fail if comments are in different containers
                    }
                }
            }
        }

        return { regions, allRegions };
    }

    // ── Hit testing ──────────────────────────────────────────────────

    function findRegionAtPoint(allRegions, x, y) {
        // Find the smallest (most specific) region containing the point.
        // Regions are in document order; children are always more specific than parents.
        let best = null;
        let bestArea = Infinity;

        for (const region of allRegions) {
            if (!region.range) continue;

            const rect = region.getBoundingRect();
            if (!rect || rect.width === 0 || rect.height === 0) continue;

            if (x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom) {
                const area = rect.width * rect.height;
                if (area < bestArea) {
                    bestArea = area;
                    best = region;
                }
            }
        }

        return best;
    }

    // ── Web component ────────────────────────────────────────────────

    class TwigGrabOverlay extends HTMLElement {
        constructor() {
            super();

            this._active = false;
            this._allRegions = [];
            this._currentRegion = null;
            this._observer = null;
            this._reparseTimer = null;

            const shadow = this.attachShadow({ mode: 'open' });

            shadow.innerHTML = `
                <style>
                    :host {
                        position: fixed;
                        z-index: 2147483647;
                        pointer-events: none;
                        inset: 0;
                    }

                    .twig-grab-button {
                        position: fixed;
                        bottom: 16px;
                        right: 16px;
                        width: 40px;
                        height: 40px;
                        border-radius: 50%;
                        background: #1e293b;
                        border: 2px solid #475569;
                        color: #e2e8f0;
                        font-size: 18px;
                        cursor: pointer;
                        pointer-events: auto;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: background 0.15s, border-color 0.15s;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                        line-height: 1;
                    }

                    .twig-grab-shortcut {
                        position: fixed;
                        bottom: 60px;
                        right: 16px;
                        background: #1e293b;
                        color: #94a3b8;
                        font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace;
                        font-size: 10px;
                        padding: 3px 7px;
                        border-radius: 4px;
                        pointer-events: none;
                        white-space: nowrap;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.25);
                    }

                    .twig-grab-button:hover {
                        background: #334155;
                        border-color: #64748b;
                    }

                    .twig-grab-button.active {
                        background: #0f766e;
                        border-color: #14b8a6;
                    }

                    .twig-grab-overlay {
                        position: fixed;
                        pointer-events: none;
                        border: 2px solid #14b8a6;
                        background: rgba(20, 184, 166, 0.08);
                        border-radius: 3px;
                        transition: all 0.1s ease-out;
                        display: none;
                    }

                    .twig-grab-overlay.visible {
                        display: block;
                    }

                    .twig-grab-label {
                        position: absolute;
                        top: -24px;
                        left: -2px;
                        background: #1e293b;
                        color: #5eead4;
                        font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace;
                        font-size: 11px;
                        line-height: 1;
                        padding: 4px 8px;
                        border-radius: 3px 3px 0 0;
                        white-space: nowrap;
                        box-shadow: 0 -1px 4px rgba(0,0,0,0.2);
                    }

                    .twig-grab-label-type {
                        color: #94a3b8;
                        margin-right: 4px;
                    }

                    .twig-grab-overlay.copied {
                        animation: twig-grab-flash 0.4s ease-out;
                    }

                    @keyframes twig-grab-flash {
                        0%   { background: rgba(20, 184, 166, 0.35); border-color: #5eead4; }
                        100% { background: rgba(20, 184, 166, 0.08); border-color: #14b8a6; }
                    }

                    .twig-grab-copied-toast {
                        position: fixed;
                        background: #1e293b;
                        color: #5eead4;
                        font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, monospace;
                        font-size: 12px;
                        padding: 6px 12px;
                        border-radius: 6px;
                        pointer-events: none;
                        opacity: 0;
                        transition: opacity 0.2s;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                        transform: translate(-50%, -100%);
                    }

                    .twig-grab-copied-toast.visible {
                        opacity: 1;
                    }
                </style>

                <button class="twig-grab-button" title="Twig Grab">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                </button>
                <div class="twig-grab-shortcut"></div>

                <div class="twig-grab-overlay">
                    <div class="twig-grab-label">
                        <span class="twig-grab-label-type"></span>
                        <span class="twig-grab-label-name"></span>
                    </div>
                </div>

                <div class="twig-grab-copied-toast">Copied</div>
            `;

            this._button = shadow.querySelector('.twig-grab-button');
            this._overlay = shadow.querySelector('.twig-grab-overlay');
            this._labelType = shadow.querySelector('.twig-grab-label-type');
            this._labelName = shadow.querySelector('.twig-grab-label-name');
            this._toast = shadow.querySelector('.twig-grab-copied-toast');
            this._shortcutHint = shadow.querySelector('.twig-grab-shortcut');
            this._shortcutHint.textContent = SHORTCUT_KEY.toUpperCase();
            this._toastTimer = null;

            this._onButtonClick = this._onButtonClick.bind(this);
            this._onMouseMove = this._onMouseMove.bind(this);
            this._onClick = this._onClick.bind(this);
            this._onKeyDown = this._onKeyDown.bind(this);
            this._onGlobalKeyDown = this._onGlobalKeyDown.bind(this);
        }

        connectedCallback() {
            this._button.addEventListener('click', this._onButtonClick);
            window.addEventListener('keydown', this._onGlobalKeyDown);
        }

        disconnectedCallback() {
            this._deactivate();
            this._button.removeEventListener('click', this._onButtonClick);
            window.removeEventListener('keydown', this._onGlobalKeyDown);
        }

        _parse() {
            const result = parseCommentTree(document.documentElement);
            this._allRegions = result.allRegions;
        }

        _scheduleReparse() {
            clearTimeout(this._reparseTimer);
            this._reparseTimer = setTimeout(() => {
                this._parse();
                this._currentRegion = null;
            }, 80);
        }

        _hasTwigGrabComments(node) {
            if (node.nodeType === Node.COMMENT_NODE) {
                const t = node.textContent;
                return t.startsWith(COMMENT_START_PREFIX) || t.startsWith(COMMENT_END_PREFIX);
            }
            if (node.nodeType === Node.ELEMENT_NODE) {
                const walker = document.createTreeWalker(node, NodeFilter.SHOW_COMMENT, null);
                let c;
                while ((c = walker.nextNode())) {
                    const t = c.textContent;
                    if (t.startsWith(COMMENT_START_PREFIX) || t.startsWith(COMMENT_END_PREFIX)) {
                        return true;
                    }
                }
            }
            return false;
        }

        _onButtonClick(e) {
            e.stopPropagation();
            if (this._active) {
                this._deactivate();
            } else {
                this._activate();
            }
        }

        _activate() {
            this._parse();
            this._active = true;
            this._button.classList.add('active');
            this._shortcutHint.style.display = 'none';
            document.documentElement.style.cursor = 'crosshair';
            this._lastClickX = 0;
            this._lastClickY = 0;
            window.addEventListener('mousemove', this._onMouseMove, true);
            window.addEventListener('click', this._onClick, true);
            window.addEventListener('keydown', this._onKeyDown, true);

            // Watch for DOM changes that include twig-grab comments
            // (Alpine x-if, Sprig/htmx swaps) — ignore trivial updates like x-text
            this._observer = new MutationObserver((mutations) => {
                for (const m of mutations) {
                    for (const node of m.addedNodes) {
                        if (this._hasTwigGrabComments(node)) {
                            this._scheduleReparse();
                            return;
                        }
                    }
                    for (const node of m.removedNodes) {
                        if (this._hasTwigGrabComments(node)) {
                            this._scheduleReparse();
                            return;
                        }
                    }
                }
            });
            this._observer.observe(document.body, { childList: true, subtree: true });
        }

        _deactivate() {
            this._active = false;
            this._button.classList.remove('active');
            this._overlay.classList.remove('visible');
            this._shortcutHint.style.display = '';
            document.documentElement.style.cursor = '';
            this._currentRegion = null;
            clearTimeout(this._reparseTimer);
            if (this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }
            window.removeEventListener('mousemove', this._onMouseMove, true);
            window.removeEventListener('click', this._onClick, true);
            window.removeEventListener('keydown', this._onKeyDown, true);
        }

        _onGlobalKeyDown(e) {
            if (e.key !== SHORTCUT_KEY) return;

            // Don't trigger when typing in form fields
            const tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) return;

            // Don't trigger with modifier keys (let Alt+G, Ctrl+G, etc. pass through)
            if (e.metaKey || e.ctrlKey || e.altKey) return;

            e.preventDefault();
            if (this._active) {
                this._deactivate();
            } else {
                this._activate();
            }
        }

        _onKeyDown(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                this._deactivate();
            }
        }

        _onMouseMove(e) {
            if (!this._active) return;

            // Don't highlight when hovering our own component. In capture-phase
            // listeners, shadow DOM retargets e.target to the host element — check
            // the real target via composedPath().
            const realTarget = e.composedPath()[0];
            if (this.shadowRoot.contains(realTarget)) {
                this._overlay.classList.remove('visible');
                this._currentRegion = null;
                return;
            }

            const region = findRegionAtPoint(this._allRegions, e.clientX, e.clientY);

            if (!region) {
                this._overlay.classList.remove('visible');
                this._currentRegion = null;
                return;
            }

            if (region === this._currentRegion) return;

            this._currentRegion = region;
            this._highlightRegion(region);
        }

        _highlightRegion(region) {
            const rect = region.getBoundingRect();
            if (!rect || rect.width === 0 || rect.height === 0) {
                this._overlay.classList.remove('visible');
                return;
            }

            this._overlay.style.top = rect.top + 'px';
            this._overlay.style.left = rect.left + 'px';
            this._overlay.style.width = rect.width + 'px';
            this._overlay.style.height = rect.height + 'px';
            this._overlay.classList.add('visible');

            this._labelType.textContent = region.type;
            this._labelName.textContent = region.label;
        }

        // ── Copy to clipboard ───────────────────────────────────────

        _onClick(e) {
            if (!this._active) return;

            // Let clicks on our own shadow DOM elements through (e.g. the toggle button).
            // composedPath() gives the real target before shadow DOM retargeting.
            const realTarget = e.composedPath()[0];
            if (this.shadowRoot.contains(realTarget)) return;

            e.preventDefault();
            e.stopPropagation();

            if (!this._currentRegion) return;
            this._lastClickX = e.clientX;
            this._lastClickY = e.clientY;
            this._copyContext(this._currentRegion);
        }

        _copyContext(region) {
            const html = this._getRegionHtml(region);
            const ancestors = this._buildAncestorChain(region);
            const plainText = this._formatPlainText(region, html, ancestors);
            const jsonData = this._formatJson(region, html, ancestors);
            const htmlClip = this._formatHtmlClip(region, html, ancestors, jsonData);

            this._writeToClipboard(plainText, htmlClip);
        }

        _writeToClipboard(plainText, htmlClip) {
            try {
                const clipboardItem = new ClipboardItem({
                    'text/plain': new Blob([plainText], { type: 'text/plain' }),
                    'text/html': new Blob([htmlClip], { type: 'text/html' }),
                });

                navigator.clipboard.write([clipboardItem]).then(() => {
                    this._flashCopied();
                }).catch(() => {
                    this._writeTextFallback(plainText);
                });
            } catch (e) {
                this._writeTextFallback(plainText);
            }
        }

        _writeTextFallback(plainText) {
            navigator.clipboard.writeText(plainText).then(() => {
                this._flashCopied();
            }).catch(() => {
                // Clipboard API unavailable (e.g. insecure context)
            });
        }

        _getRegionHtml(region) {
            if (!region.range) return '';

            const fragment = region.range.cloneContents();

            // Strip twig-grab comments from the cloned fragment
            const walker = document.createTreeWalker(fragment, NodeFilter.SHOW_COMMENT, null);
            const toRemove = [];
            let node;
            while ((node = walker.nextNode())) {
                if (node.textContent.startsWith(COMMENT_START_PREFIX) ||
                    node.textContent.startsWith(COMMENT_END_PREFIX)) {
                    toRemove.push(node);
                }
            }
            toRemove.forEach(n => n.parentNode.removeChild(n));

            const div = document.createElement('div');
            div.appendChild(fragment);
            let html = div.innerHTML.trim();

            // Truncate if very large
            const MAX_HTML = 3000;
            if (html.length > MAX_HTML) {
                html = html.substring(0, MAX_HTML) + '\n<!-- ... truncated -->';
            }

            return html;
        }

        _buildAncestorChain(region) {
            const chain = [];
            let current = region.parent;
            while (current) {
                chain.push({
                    type: current.type,
                    template: current.template,
                    line: current.line,
                    block: current.block,
                });
                current = current.parent;
            }
            return chain;
        }

        _formatPlainText(region, html, ancestors) {
            let text = '';

            // Selected region info
            text += `[${region.type}] ${region.label}\n\n`;
            text += html + '\n';

            // Ancestor chain
            if (ancestors.length > 0) {
                text += '\nTemplate chain:\n';
                for (const a of ancestors) {
                    let entry = '  in ' + a.type;
                    if (a.block) entry += ' "' + a.block + '"';
                    entry += ' at ' + a.template;
                    if (a.line) entry += ':' + a.line;
                    text += entry + '\n';
                }
            }

            return text;
        }

        _formatJson(region, html, ancestors) {
            return JSON.stringify({
                type: region.type,
                template: region.template,
                line: region.line,
                block: region.block || undefined,
                html: html,
                ancestors: ancestors,
            });
        }

        _formatHtmlClip(region, html, ancestors, jsonData) {
            // HTML format with embedded JSON metadata
            let clip = '<pre><code>' + this._escapeHtml(html) + '</code></pre>\n';
            clip += '<p><strong>' + this._escapeHtml(region.label) + '</strong></p>\n';

            if (ancestors.length > 0) {
                clip += '<ul>\n';
                for (const a of ancestors) {
                    let entry = a.type;
                    if (a.block) entry += ' "' + a.block + '"';
                    entry += ' at ' + a.template;
                    if (a.line) entry += ':' + a.line;
                    clip += '  <li>' + this._escapeHtml(entry) + '</li>\n';
                }
                clip += '</ul>\n';
            }

            // Embed structured JSON as a hidden data attribute for programmatic access
            clip += '<div data-twig-grab="' + this._escapeHtml(jsonData) + '" style="display:none"></div>';

            return clip;
        }

        _escapeHtml(str) {
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        _flashCopied() {
            // Flash the overlay
            this._overlay.classList.remove('copied');
            // Force reflow to restart animation
            void this._overlay.offsetWidth;
            this._overlay.classList.add('copied');

            // Position toast near the click
            this._toast.style.left = this._lastClickX + 'px';
            this._toast.style.top = (this._lastClickY - 12) + 'px';

            // Show toast
            this._toast.classList.add('visible');
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => {
                this._toast.classList.remove('visible');
            }, 1200);
        }
    }

    // ── Bootstrap ────────────────────────────────────────────────────

    if (!customElements.get('twig-grab-overlay')) {
        customElements.define('twig-grab-overlay', TwigGrabOverlay);
    }

    function init() {
        if (document.querySelector('twig-grab-overlay')) return;
        const el = document.createElement('twig-grab-overlay');
        document.body.appendChild(el);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
