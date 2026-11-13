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
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: '#218A45',
                    deep: '#176B38',
                    soft: '#EAF6EE',
                    foreground: '#FFFFFF',
                },
                gold: {
                    DEFAULT: '#F5C928',
                    soft: '#FDF6DD',
                    foreground: '#5A4A0A',
                },
                destructive: {
                    DEFAULT: '#D92732',
                    soft: '#FBEAEA',
                    foreground: '#FFFFFF',
                },
                success: {
                    DEFAULT: '#176B38',
                    soft: '#EAF6EE',
                },
                warning: {
                    DEFAULT: '#946200',
                    soft: '#FDF3DC',
                },
                surface: '#FCFDFC',
                background: '#F6F8F6',
                foreground: '#18231F',
                border: '#E4E9E4',
                card: {
                    DEFAULT: '#FFFFFF',
                    foreground: '#18231F',
                },
                muted: {
                    DEFAULT: '#F1F4F1',
                    foreground: '#5B6B60',
                },
                sidebar: {
                    DEFAULT: '#14301F',
                    foreground: '#EEF5EF',
                    muted: '#9FB8A8',
                    accent: '#1E4A2E',
                    border: '#24563380',
                    primary: '#F5C928',
                },
            },
            boxShadow: {
                card: '0 1px 2px rgba(24,35,31,0.04), 0 1px 3px rgba(24,35,31,0.04)',
                elevated: '0 12px 32px -12px rgba(23,107,56,0.22), 0 2px 6px rgba(24,35,31,0.05)',
            },
            borderRadius: {
                xl: '0.85rem',
                '2xl': '1.1rem',
            },
        },
    },

    plugins: [forms],
};
