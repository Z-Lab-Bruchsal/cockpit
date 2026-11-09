// Entry point for the public homepage. Alpine is provided by Livewire.
// Open Sans like auerbach-stiftung.de, self-hosted (latin only) instead of Google Fonts.
import '@fontsource/open-sans/latin-300.css';
import '@fontsource/open-sans/latin-400.css';
import '@fontsource/open-sans/latin-600.css';
import '@fontsource/open-sans/latin-700.css';
import { Calendar } from '@fullcalendar/core';
import deLocale from '@fullcalendar/core/locales/de';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';

const isSmallScreen = () => window.matchMedia('(max-width: 639px)').matches;

function initCourseCalendar(element) {
    const calendar = new Calendar(element, {
        plugins: [dayGridPlugin, listPlugin],
        locale: deLocale,
        initialView: isSmallScreen() ? 'listMonth' : 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth',
        },
        buttonText: { listMonth: 'Liste' },
        height: 'auto',
        dayMaxEvents: 3,
        displayEventEnd: true,
        noEventsText: 'In diesem Zeitraum sind keine Kurse geplant.',
        events: {
            url: element.dataset.eventsUrl,
            startParam: 'start',
            endParam: 'end',
            failure: () => {
                element.insertAdjacentHTML('afterbegin', '<p class="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">Die Termine konnten nicht geladen werden.</p>');
            },
        },
        eventDidMount: ({ event, el }) => {
            const { availability, location } = event.extendedProps;
            el.title = [event.title, availability, location].filter(Boolean).join(' · ');
        },
        windowResize: () => {
            const targetView = isSmallScreen() ? 'listMonth' : 'dayGridMonth';

            if (calendar.view.type !== targetView) {
                calendar.changeView(targetView);
            }
        },
    });

    calendar.render();
}

document.querySelectorAll('[data-course-calendar]').forEach(initCourseCalendar);

/**
 * Resize handle of a tile in the "Kacheln anordnen" mode (see livewire/homepage/tile-editor.blade.php).
 * Snaps the width to the allowed spans of the 6-column grid and the height to 1 or 2 rows; saves on release.
 */
// Color helpers matching CompanySetting::contrastTextColor(), readableOnWhite() and darken().
const hexChannels = (hex) => {
    let value = String(hex).replace('#', '');
    if (value.length === 3) {
        value = value.split('').map((c) => c + c).join('');
    }
    return /^[0-9a-f]{6}$/i.test(value) ? [0, 2, 4].map((i) => parseInt(value.slice(i, i + 2), 16)) : null;
};
const luminance = (hex) => {
    const channels = hexChannels(hex);
    if (!channels) {
        return null;
    }
    const [r, g, b] = channels.map((c) => {
        const v = c / 255;
        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const darken = (hex, factor) => {
    const channels = hexChannels(hex);
    return channels ? '#' + channels.map((c) => Math.round(c * factor).toString(16).padStart(2, '0')).join('') : hex;
};
const readableOnWhite = (hex, min = 4.5) => {
    let color = hex;
    let guard = 0;
    while (luminance(color) !== null && 1.05 / (luminance(color) + 0.05) < min && guard++ < 60) {
        color = darken(color, 0.9);
    }
    return color;
};
const contrastText = (hex) => {
    const l = luminance(hex);
    if (l === null) {
        return '#111827';
    }
    return 1.05 / (l + 0.05) >= (l + 0.05) / 0.0589 ? '#ffffff' : '#111827';
};
const roleVariables = (role, color) => {
    if (role === 'brand') {
        return {
            '--brand': color,
            '--brand-contrast': contrastText(color),
            '--brand-heading': readableOnWhite(color, 3),
            '--brand-ink': readableOnWhite(darken(color, 0.86)),
        };
    }
    if (role === 'accent') {
        return { '--accent': color, '--accent-contrast': contrastText(color) };
    }
    return { ['--' + role]: color };
};
const allVariableNames = (roles) => Object.keys(roles).flatMap((role) => Object.keys(roleVariables(role, '#000000')));

document.addEventListener('alpine:init', () => {
    // Design mode (?design=1): click an element, change the color of its role or of just this element.
    window.Alpine.data('designEditor', (initialState, roles, hasDraft = false) => ({
        roles,
        hasDraft,
        editing: false,
        state: JSON.parse(JSON.stringify(initialState)),
        history: [],
        future: [],
        selection: null,
        scope: 'role',
        color: '#000000',
        saving: false,
        dirty: false,
        saveTimer: null,
        hovered: null,
        selectedEl: null,
        styledElements: new Set(),

        init() {
            this.state.tokens = Array.isArray(this.state.tokens) ? {} : this.state.tokens;
            this.state.elements = Array.isArray(this.state.elements) ? {} : this.state.elements;

            this.onClick = (event) => this.pick(event);
            this.onOver = (event) => this.hover(event);
            document.addEventListener('click', this.onClick, true);
            document.addEventListener('mouseover', this.onOver, true);
            document.documentElement.classList.add('design-mode');
        },

        destroy() {
            document.removeEventListener('click', this.onClick, true);
            document.removeEventListener('mouseover', this.onOver, true);
        },

        isHex(value) {
            return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value);
        },

        target(event) {
            if (event.target.closest('[data-design-panel]') || event.target.closest('dialog')) {
                return null;
            }
            return event.target.closest('[data-design-role]');
        },

        hover(event) {
            const el = this.target(event);
            this.hovered?.classList.remove('design-hover');
            this.hovered = el;
            el?.classList.add('design-hover');
        },

        pick(event) {
            const el = this.target(event);
            if (!el) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();

            const availableRoles = [];
            let node = el;
            while (node) {
                node.dataset.designRole.split(' ').forEach((role) => {
                    if (roles[role] && !availableRoles.includes(role)) {
                        availableRoles.push(role);
                    }
                });
                node = node.parentElement?.closest('[data-design-role]');
            }

            const elementId = el.closest('[data-design-element]')?.dataset.designElement ?? null;

            this.selectedEl?.classList.remove('design-selected');
            this.selectedEl = el;
            el.classList.add('design-selected');

            this.selection = { role: availableRoles[0], availableRoles, elementId };
            this.scope = elementId && this.state.elements[elementId]?.[availableRoles[0]] ? 'element' : 'role';
            this.refreshColor();
        },

        refreshColor() {
            const { role, elementId } = this.selection;
            const own = this.scope === 'element' && elementId ? this.state.elements[elementId]?.[role] : null;
            this.color = own ?? this.state.tokens[role] ?? roles[role].default;
        },

        snapshot() {
            this.history.push(JSON.stringify(this.state));
            this.future = [];
            if (this.history.length > 100) {
                this.history.shift();
            }
        },

        // While the browser's color picker is open, all its changes count as one step for undo.
        beginEdit() {
            if (!this.editing) {
                this.editing = true;
                this.snapshot();
            }
        },

        endEdit() {
            this.editing = false;
        },

        setColor(value, recordStep = true) {
            if (!this.selection || !this.isHex(value)) {
                return;
            }
            if (recordStep) {
                this.snapshot();
            } else {
                this.beginEdit();
            }
            this.color = value;
            const { role, elementId } = this.selection;
            if (this.scope === 'element' && elementId) {
                this.state.elements[elementId] = { ...(this.state.elements[elementId] ?? {}), [role]: value };
            } else {
                this.state.tokens[role] = value;
            }
            this.changed();
        },

        resetSelection() {
            if (!this.selection) {
                return;
            }
            this.snapshot();
            const { role, elementId } = this.selection;
            if (this.scope === 'element' && elementId) {
                delete this.state.elements[elementId]?.[role];
            } else {
                delete this.state.tokens[role];
            }
            this.refreshColor();
            this.changed();
        },

        undo() {
            if (this.history.length === 0) {
                return;
            }
            this.future.push(JSON.stringify(this.state));
            this.state = JSON.parse(this.history.pop());
            this.selection && this.refreshColor();
            this.changed(false);
        },

        redo() {
            if (this.future.length === 0) {
                return;
            }
            this.history.push(JSON.stringify(this.state));
            this.state = JSON.parse(this.future.pop());
            this.selection && this.refreshColor();
            this.changed(false);
        },

        changed() {
            this.dirty = true;
            this.apply();
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.save(), 600);
        },

        async save() {
            this.saving = true;
            try {
                await this.$wire.saveDraft(this.state);
            } finally {
                this.saving = false;
            }
        },

        apply() {
            const names = allVariableNames(roles);
            const root = document.documentElement.style;
            names.forEach((name) => root.removeProperty(name));
            Object.entries(this.state.tokens).forEach(([role, color]) => {
                Object.entries(roleVariables(role, color)).forEach(([name, value]) => root.setProperty(name, value));
            });

            this.styledElements.forEach((el) => names.forEach((name) => el.style.removeProperty(name)));
            this.styledElements.clear();
            Object.entries(this.state.elements).forEach(([elementId, overrides]) => {
                document.querySelectorAll(`[data-design-element="${CSS.escape(elementId)}"]`).forEach((el) => {
                    Object.entries(overrides).forEach(([role, color]) => {
                        Object.entries(roleVariables(role, color)).forEach(([name, value]) => el.style.setProperty(name, value));
                    });
                    this.styledElements.add(el);
                });
            });
        },

        contrast() {
            const l = luminance(this.color);
            return l === null ? 21 : 1.05 / (l + 0.05);
        },

        async publish() {
            if (!window.confirm('Die Farben für alle Besucherinnen und Besucher übernehmen?')) {
                return;
            }
            clearTimeout(this.saveTimer);
            await this.$wire.publish(this.state);
        },

        async discard() {
            if (!window.confirm('Alle nicht veröffentlichten Farbänderungen verwerfen?')) {
                return;
            }
            clearTimeout(this.saveTimer);
            await this.$wire.discard();
        },
    }));

    // Dialog with a small cockpit page for editing a block or offer in place (edit mode, ?bearbeiten=1).
    window.Alpine.data('inlineEditor', () => ({
        url: null,

        open(url) {
            this.url = url;
            this.$refs.dialog.showModal();
        },

        receive(event) {
            if (event.origin !== window.location.origin || !this.url) {
                return;
            }

            if (event.data?.type === 'zlab-inline-saved') {
                window.location.reload();
            } else if (event.data?.type === 'zlab-inline-closed') {
                this.$refs.dialog.close();
            }
        },
    }));

    window.Alpine.data('tileResizer', (id, width, height, widths) => ({
        width,
        height,
        resizing: false,

        get previewStyle() {
            return this.resizing ? `grid-column: span ${this.width} / span ${this.width}; grid-row: span ${this.height} / span ${this.height};` : '';
        },

        start(event) {
            const grid = this.$root.closest('[data-tile-grid]');

            if (!grid || !window.matchMedia('(min-width: 1024px)').matches) {
                return;
            }

            const styles = getComputedStyle(grid);
            const gap = parseFloat(styles.columnGap) || 0;
            const columnWidth = (grid.getBoundingClientRect().width + gap) / 6;
            const minRowHeight = parseFloat((styles.gridAutoRows.match(/[\d.]+px/) ?? ['208'])[0]);
            const rowHeight = minRowHeight + (parseFloat(styles.rowGap) || 0);
            const startX = event.clientX;
            const startY = event.clientY;
            // Read the saved size from the server render, it may have changed via the buttons since init.
            const startWidth = Number(this.$root.dataset.tileWidth) || this.width;
            const startHeight = Number(this.$root.dataset.tileHeight) || this.height;

            this.resizing = true;

            const move = (moveEvent) => {
                const wantedWidth = startWidth + (moveEvent.clientX - startX) / columnWidth;
                this.width = widths.reduce((best, candidate) => (Math.abs(candidate - wantedWidth) < Math.abs(best - wantedWidth) ? candidate : best), widths[0]);
                this.height = startHeight + (moveEvent.clientY - startY) / rowHeight >= 1.5 ? 2 : 1;
            };

            const stop = async () => {
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', stop);

                if (this.width !== startWidth || this.height !== startHeight) {
                    await this.$wire.resize(id, this.width, this.height);
                }

                this.resizing = false;
            };

            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', stop);
        },
    }));
});
