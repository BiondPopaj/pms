/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,jsx}',
    ],
    theme: {
        extend: {
            // ─── Brand Colors ──────────────────────────────────────────────
            colors: {
                brand: {
                    50:  '#f0f4ff',
                    100: '#e0e9ff',
                    200: '#c7d6ff',
                    300: '#a5baff',
                    400: '#8196fb',
                    500: '#6171f6',  // primary
                    600: '#4f50eb',
                    700: '#423fd4',
                    800: '#3635ab',
                    900: '#302f87',
                    950: '#1d1d52',
                },
                neutral: {
                    50:  '#f8fafc',
                    100: '#f1f5f9',
                    200: '#e2e8f0',
                    300: '#cbd5e1',
                    400: '#94a3b8',
                    500: '#64748b',
                    600: '#475569',
                    700: '#334155',
                    800: '#1e293b',
                    900: '#0f172a',
                    950: '#020617',
                },
                surface: {
                    DEFAULT: '#ffffff',
                    muted: '#f8fafc',
                    subtle: '#f1f5f9',
                },
                // Status colors
                success: {
                    50:  '#f0fdf4',
                    500: '#22c55e',
                    600: '#16a34a',
                    700: '#15803d',
                },
                warning: {
                    50:  '#fffbeb',
                    500: '#f59e0b',
                    600: '#d97706',
                },
                danger: {
                    50:  '#fef2f2',
                    500: '#ef4444',
                    600: '#dc2626',
                    700: '#b91c1c',
                },
                // Reservation states
                reservation: {
                    pending:    '#f59e0b',
                    confirmed:  '#3b82f6',
                    'checked-in': '#22c55e',
                    'checked-out': '#64748b',
                    'no-show':  '#ef4444',
                    cancelled:  '#9ca3af',
                },
                // Housekeeping states
                room: {
                    clean:      '#22c55e',
                    dirty:      '#f59e0b',
                    inspecting: '#3b82f6',
                    'out-of-order': '#ef4444',
                    vacant:     '#64748b',
                    occupied:   '#8b5cf6',
                },
            },

            // ─── Typography ────────────────────────────────────────────────
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
                mono: ['JetBrains Mono', 'Fira Code', 'monospace'],
            },
            fontSize: {
                '2xs': ['0.625rem', { lineHeight: '1rem' }],
            },

            // ─── Border Radius ─────────────────────────────────────────────
            borderRadius: {
                'xs': '4px',
                DEFAULT: '8px',
                'lg': '12px',
                'xl': '16px',
                '2xl': '20px',
                '3xl': '24px',
            },

            // ─── Box Shadows ───────────────────────────────────────────────
            boxShadow: {
                'soft-xs': '0 1px 2px 0 rgba(0, 0, 0, 0.04)',
                'soft-sm': '0 2px 4px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.04)',
                'soft':    '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.04)',
                'soft-md': '0 8px 16px -4px rgba(0, 0, 0, 0.06), 0 4px 6px -2px rgba(0, 0, 0, 0.04)',
                'soft-lg': '0 16px 32px -8px rgba(0, 0, 0, 0.08), 0 8px 16px -4px rgba(0, 0, 0, 0.04)',
                'inner-soft': 'inset 0 2px 4px 0 rgba(0, 0, 0, 0.04)',
                'card':    '0 1px 3px 0 rgba(0, 0, 0, 0.06), 0 1px 2px -1px rgba(0, 0, 0, 0.04)',
                'card-hover': '0 4px 12px 0 rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.05)',
                'popover': '0 20px 40px -8px rgba(0, 0, 0, 0.12), 0 8px 16px -4px rgba(0, 0, 0, 0.06)',
                'modal':   '0 40px 80px -16px rgba(0, 0, 0, 0.16)',
            },

            // ─── Animations ────────────────────────────────────────────────
            animation: {
                'fade-in':   'fadeIn 0.15s ease-out',
                'fade-out':  'fadeOut 0.1s ease-in',
                'slide-in':  'slideIn 0.2s ease-out',
                'slide-up':  'slideUp 0.2s ease-out',
                'scale-in':  'scaleIn 0.15s ease-out',
                'shimmer':   'shimmer 1.5s infinite',
                'pulse-soft': 'pulseSoft 2s ease-in-out infinite',
            },
            keyframes: {
                fadeIn:    { from: { opacity: '0' }, to: { opacity: '1' } },
                fadeOut:   { from: { opacity: '1' }, to: { opacity: '0' } },
                slideIn:   { from: { opacity: '0', transform: 'translateX(-8px)' }, to: { opacity: '1', transform: 'translateX(0)' } },
                slideUp:   { from: { opacity: '0', transform: 'translateY(8px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
                scaleIn:   { from: { opacity: '0', transform: 'scale(0.96)' }, to: { opacity: '1', transform: 'scale(1)' } },
                shimmer:   { from: { backgroundPosition: '-200% 0' }, to: { backgroundPosition: '200% 0' } },
                pulseSoft: { '0%, 100%': { opacity: '1' }, '50%': { opacity: '0.6' } },
            },

            // ─── Sidebar width ─────────────────────────────────────────────
            width: {
                'sidebar': '260px',
                'sidebar-collapsed': '64px',
            },

            // ─── Transitions ───────────────────────────────────────────────
            transitionTimingFunction: {
                'spring': 'cubic-bezier(0.34, 1.56, 0.64, 1)',
                'smooth': 'cubic-bezier(0.4, 0, 0.2, 1)',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms')({
            strategy: 'class',
        }),
        require('@tailwindcss/typography'),
        require('@tailwindcss/aspect-ratio'),
    ],
};
