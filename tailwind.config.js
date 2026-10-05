import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Support/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans Arabic"', ...defaultTheme.fontFamily.sans],
            },
            // Design batch 1 (docs/design/01-design-system). Only `primary` varies per company (T17);
            // neutrals and semantic colours are fixed for every company. Badges use Tailwind's default
            // palette (amber / green / red / orange / slate), which matches the design exactly.
            colors: {
                primary: {
                    DEFAULT: 'var(--color-primary)',
                    dark: 'var(--color-primary-dark)',
                    light: 'var(--color-primary-light)',
                    ring: 'var(--color-primary-ring)',
                },
                ink: '#16191D',
                muted: '#5B6270',
                subtle: '#8A909B',
                line: {
                    DEFAULT: '#E3E5E8',
                    strong: '#C9CDD3',
                    soft: '#EEF0F2',
                },
                canvas: '#F5F6F7',
                success: '#15803D',
                warning: '#C2410C',
                danger: {
                    DEFAULT: '#C81E1E',
                    dark: '#A51818',
                    soft: '#FEF6F6',
                    border: '#F5C9C9',
                },
            },
        },
    },

    plugins: [forms],
};
