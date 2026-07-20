/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',
    content: [
        './**/*.html',
        './assets/js/**/*.js',
    ],
    safelist: [
        // Dark mode variants used in JS-generated HTML (renderRunnerOutput, renderError)
        // These can't be statically detected by Tailwind's scanner
        'dark:bg-slate-800',
        'dark:bg-slate-900',
        'dark:bg-red-900/20',
        'dark:bg-red-900/30',
        'dark:bg-green-900/30',
        'dark:border-slate-700',
        'dark:border-red-700',
        'dark:text-slate-300',
        'dark:text-slate-400',
        'dark:text-slate-500',
        'dark:text-red-400',
        'dark:text-green-400',
        // Light mode variants also used dynamically
        'bg-slate-50',
        'bg-slate-800',
        'bg-white',
        'bg-red-50',
        'bg-green-100',
        'bg-red-100',
        'border-slate-200',
        'border-red-200',
        'text-slate-700',
        'text-green-700',
        'text-red-700',
        'text-green-600',
        'text-red-600',
        'text-slate-400',
        'text-slate-500',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    50: '#f5f7ff',
                    100: '#ebf0ff',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                }
            }
        }
    },
    plugins: [],
}
