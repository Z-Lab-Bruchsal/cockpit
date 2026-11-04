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
document.addEventListener('alpine:init', () => {
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
