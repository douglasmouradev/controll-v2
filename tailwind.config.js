/** @type {import('tailwindcss').Config} */
module.exports = {
	content: [
		'./app/Views/**/*.php',
		'./public/assets/js/**/*.js',
	],
	theme: {
		extend: {
			fontFamily: {
				sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
			},
			colors: {
				brand: { DEFAULT: '#1e3a8a', light: '#1d4ed8', dark: '#0f172a' },
				accent: { DEFAULT: '#dc2626' },
			},
		},
	},
	plugins: [],
};
