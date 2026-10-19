const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,
	testPathIgnorePatterns: [ '/node_modules/', '<rootDir>/build/', '<rootDir>/vendor/' ],
	// @wordpress/components depends on the ESM-only uuid package; let Babel transform it.
	transformIgnorePatterns: [
		'/node_modules/(?!(@wordpress/components/node_modules/uuid|uuid)/)',
	],
};
