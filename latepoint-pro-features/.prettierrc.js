const config = require( '@wordpress/prettier-config' );

config.overrides = [
	{
		files: [ '*.scss', '*.css' ],
		options: {
			printWidth: 2000,
			singleQuote: true,
		},
	},
];

module.exports = config;
