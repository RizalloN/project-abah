import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                brand: {
                    50: '#f3f8f7',
                    100: '#dcecea',
                    500: '#1f6f68',
                    600: '#195952',
                    700: '#12463f',
                    900: '#0d2b2a',
                },
            },
            fontFamily: {
                sans: [...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                soft: '0 30px 80px -32px rgba(15, 23, 42, 0.35)',
            },
        },
    },

    plugins: [forms],
};
