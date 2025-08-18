import preset from '../../../../vendor/filament/filament/tailwind.config.preset';

/** @type {import('tailwindcss').Config} */
export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                // Couleurs personnalisées pour TinyTalkAI
                'custom-light': '#E0E0E0',
                'custom-white': '#FFFFFF',
                'custom-mid': '#CDCDCD',
                'custom-black': '#000000',
                'custom-light-dark-mode': '#323232',
                'custom-white-dark-mode': '#141313',
                'custom-mid-dark-mode': '#606060',
            },
        },
    },
};
