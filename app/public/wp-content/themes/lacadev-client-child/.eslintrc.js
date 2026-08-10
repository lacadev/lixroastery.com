module.exports = {
	extends: ['plugin:@wordpress/eslint-plugin/recommended'],
	ignorePatterns: [
		'*.min.js',
		'dist/**',
		'node_modules/**',
		'vendor/**',
		'resources/scripts/lib/**', // Third-party libraries
		'resources/scripts/sw.js', // Service worker
		'resources/scripts/theme/ajax-search.js', // Has debug console statements
	],
	globals: {
		// WordPress globals
		themeSearch: 'readonly',
		lacaDashboard: 'readonly',
		ajaxurl_params: 'readonly',
		adminI18n: 'readonly',
		alert: 'readonly',

		// Browser globals
		localStorage: 'readonly',
		location: 'readonly',
		history: 'readonly',
		navigator: 'readonly',
		DOMParser: 'readonly',
		MutationObserver: 'readonly',
		IntersectionObserver: 'readonly',
		IntersectionObserverEntry: 'readonly',
		requestIdleCallback: 'readonly',
		requestAnimationFrame: 'readonly',
		MouseEvent: 'readonly',

		// Service Worker globals
		self: 'readonly',
		caches: 'readonly',

		// GSAP
		ScrollTrigger: 'readonly',
		SplitText: 'readonly',

		// Page loader functions
		showPageLoader: 'readonly',
		hidePageLoader: 'readonly',
	},
	rules: {
		// Allow console in development
		'no-console': process.env.NODE_ENV === 'production' ? 'error' : 'warn',

		// Relax some WordPress rules
		'jsdoc/require-param-type': 'off',
		'@wordpress/no-unused-vars-before-return': 'warn',
		camelcase: 'off',
		'no-alert': 'warn',
		eqeqeq: 'warn',
		'no-shadow': 'warn',
		'no-unused-expressions': 'warn',
		'no-unused-vars': 'warn',

		// Disable import resolution (handled by webpack)
		'import/no-unresolved': 'off',
		'import/no-extraneous-dependencies': 'off',
		'import/named': 'off',
		'import/default': 'off',

		// JSX accessibility
		'jsx-a11y/label-has-associated-control': 'warn',
	},
};
// 