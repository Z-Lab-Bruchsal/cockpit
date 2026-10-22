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
