const config = require( '@wordpress/prettier-config' );

config.overrides = [
	{
		files: [ '*.scss', '*.css' ],
		options: {
			printWidth: 1000,
			singleQuote: false,
		},
	},
];

module.exports = config;
