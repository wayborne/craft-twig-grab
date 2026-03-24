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
            this._parsed = false;

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
                </style>

                <button class="twig-grab-button" title="Twig Grab — click to activate">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                </button>

                <div class="twig-grab-overlay">
                    <div class="twig-grab-label">
                        <span class="twig-grab-label-type"></span>
                        <span class="twig-grab-label-name"></span>
                    </div>
                </div>
            `;

            this._button = shadow.querySelector('.twig-grab-button');
            this._overlay = shadow.querySelector('.twig-grab-overlay');
            this._labelType = shadow.querySelector('.twig-grab-label-type');
            this._labelName = shadow.querySelector('.twig-grab-label-name');

            this._onButtonClick = this._onButtonClick.bind(this);
            this._onMouseMove = this._onMouseMove.bind(this);
            this._onKeyDown = this._onKeyDown.bind(this);
        }

        connectedCallback() {
            this._button.addEventListener('click', this._onButtonClick);
        }

        disconnectedCallback() {
            this._deactivate();
            this._button.removeEventListener('click', this._onButtonClick);
        }

        _parseIfNeeded() {
            if (this._parsed) return;
            const result = parseCommentTree(document.documentElement);
            this._allRegions = result.allRegions;
            this._parsed = true;
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
            this._parseIfNeeded();
            this._active = true;
            this._button.classList.add('active');
            window.addEventListener('mousemove', this._onMouseMove, true);
            window.addEventListener('keydown', this._onKeyDown, true);
        }

        _deactivate() {
            this._active = false;
            this._button.classList.remove('active');
            this._overlay.classList.remove('visible');
            this._currentRegion = null;
            window.removeEventListener('mousemove', this._onMouseMove, true);
            window.removeEventListener('keydown', this._onKeyDown, true);
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

            // Don't highlight inside our own shadow DOM
            if (this.shadowRoot.contains(e.target)) {
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
