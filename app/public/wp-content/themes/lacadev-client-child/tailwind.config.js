/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    // Gutenberg blocks — PHP render templates + JS/SCSS
    './block-gutenberg/**/*.{js,jsx,php,scss}',
    // Theme templates (Twig / PHP)
    './app/**/*.php',
    './resources/**/*.{js,jsx,php,twig,html}',
    // Nhận tự động từ Parent Theme
    '../lacadev-client/block-gutenberg/**/*.{js,jsx,php,scss}',
    '../lacadev-client/app/**/*.php',
    '../lacadev-client/resources/**/*.{js,jsx,php,twig,html}'
  ],
  theme: {
    // Sync breakpoints với SCSS _variables.scss
    screens: {
      xs: '576px',
      sm: '768px',
      md: '992px',
      lg: '1200px',
      xl: '1440px',
    },
    extend: {
      colors: {
        // Material Design 3 surface tokens — fallback to CSS vars, default to neutral tones
        'surface':                 'var(--color-surface, #FFFBFF)',
        'surface-variant':         'var(--color-surface-variant, #E7E0EC)',
        'surface-container':       'var(--color-surface-container, #F3EDF7)',
        'surface-container-low':   'var(--color-surface-container-low, #F5F5F5)',
        'surface-container-high':  'var(--color-surface-container-high, #ECE6F0)',
        'surface-container-highest':'var(--color-surface-container-highest, #E6E0E9)',
        'on-surface':              'var(--color-on-surface, #1C1B1F)',
        'on-surface-variant':      'var(--color-on-surface-variant, #49454F)',
        'background':              'var(--color-background, #FFFBFF)',
        'on-background':           'var(--color-on-background, #1C1B1F)',
        'outline':                 'var(--color-outline, #79747E)',
        'primary':                 'var(--color-primary, #6750A4)',
        'on-primary':              'var(--color-on-primary, #FFFFFF)',
      },
      // Sync fonts với SCSS $primaryFont, $secondaryFont
      fontFamily: {
        primary:   ['"Be Vietnam Pro"', 'sans-serif'],
        secondary: ['Quicksand', 'sans-serif'],
        sans:      ['Quicksand', '"Be Vietnam Pro"', 'sans-serif'],
      },
      // Container max-width sync với $container-mw: 90rem
      maxWidth: {
        container: '1440px',
      },
      // Font weights sync với SCSS variables
      fontWeight: {
        thin:        '100',
        extralight:  '200',
        light:       '300',
        normal:      '400',
        medium:      '500',
        semibold:    '600',
        bold:        '700',
        extrabold:   '800',
        black:       '900',
      },
    },
  },
  plugins: [],
};

