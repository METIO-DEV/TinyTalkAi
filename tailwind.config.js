import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './resources/js/**/*.ts',
        './resources/js/**/*.tsx',
        // Fichiers Filament
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'custom-light': '#E0E0E0',
                'custom-white': '#FFFFFF',
                'custom-mid': '#CDCDCD',
                'custom-black': '#000000',
                'custom-light-dark-mode': '#323232',
                'custom-white-dark-mode': '#141313',
                'custom-mid-dark-mode': '#606060',
                border: 'var(--border)',
                input: 'var(--input)',
                ring: 'var(--ring)',
                background: 'var(--background)',
                foreground: 'var(--foreground)',
                primary: {
                    DEFAULT: 'var(--primary)',
                    foreground: 'var(--primary-foreground)',
                },
                secondary: {
                    DEFAULT: 'var(--secondary)',
                    foreground: 'var(--secondary-foreground)',
                },
                destructive: {
                    DEFAULT: 'var(--destructive)',
                    foreground: 'var(--destructive-foreground)',
                },
                muted: {
                    DEFAULT: 'var(--muted)',
                    foreground: 'var(--muted-foreground)',
                },
                accent: {
                    DEFAULT: 'var(--accent)',
                    foreground: 'var(--accent-foreground)',
                },
                popover: {
                    DEFAULT: 'var(--popover)',
                    foreground: 'var(--popover-foreground)',
                },
                card: {
                    DEFAULT: 'var(--card)',
                    foreground: 'var(--card-foreground)',
                },
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },
            padding: {
                '7': '1.75rem',
                '8': '2rem',
                '9': '2.25rem',
                '10': '2.5rem',
                '11': '2.75rem',
                '12': '3rem',
                '14': '3.5rem',
                '16': '4rem',
                '20': '5rem',
                '24': '6rem',
                '28': '7rem',
                '32': '8rem',
                '36': '9rem',
                '40': '10rem',
                '44': '11rem',
                '48': '12rem',
                '52': '13rem',
                '56': '14rem',
                '60': '15rem',
                '64': '16rem',
                '72': '18rem',
                '80': '20rem',
                '96': '24rem',
            },
            height: {
                '80': '20rem',
                '96': '24rem',
                '112': '28rem',
                '128': '32rem',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0', transform: 'translateY(10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                typing: {
                    '0%': { width: '0' },
                    '100%': { width: '100%' },
                },
                blink: {
                    '0%, 100%': { borderColor: 'transparent' },
                    '50%': { borderColor: '#000' },
                },
            },
            animation: {
                'fade-in': 'fadeIn 0.3s ease-out forwards',
                'typing': 'typing 3s steps(40, end)',
                'cursor-blink': 'blink 0.75s step-end infinite',
            },
        },
    },

    plugins: [forms],
    darkMode: 'class', // Active le mode sombre basé sur la classe
};
