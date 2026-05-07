import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';

const pages = import.meta.glob('./Pages/**/*.jsx');

if (document.querySelector('[data-page]')) {
    createInertiaApp({
        resolve: (name) => {
            const page = pages[`./Pages/${name}.jsx`];

            if (!page) {
                throw new Error(`Page not found: ${name}`);
            }

            return page();
        },
        setup({ el, App, props }) {
            createRoot(el).render(createElement(App, props));
        },
        progress: {
            color: '#111111',
        },
    });
}
