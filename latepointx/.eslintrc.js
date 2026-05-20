module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	rules: {
		camelcase: 'off',
		'no-console': 'off',
		'no-alert': 'off',
		'@wordpress/no-unsafe-wp-apis': 'off',
		'import/no-unresolved': 'off',
		'jsdoc/require-jsdoc': 'off',
		'@wordpress/i18n-text-domain': [
			'error',
			{ allowedTextDomain: 'latepoint' },
		],
	},
	parserOptions: {
		requireConfigFile: false,
		babelOptions: {
			presets: [ '@wordpress/babel-preset-default' ],
		},
	},
	globals: {
		jQuery: 'readonly',
		latepoint_helper: 'readonly',
		ajaxurl: 'readonly',
	},
};
