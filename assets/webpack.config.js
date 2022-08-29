const path = require('path');
const webpack = require('webpack');

module.exports = {
	name: 'js_bundle',
	context: path.resolve(__dirname, 'src'),
	entry: {
		'builder.editor.js': './jet-form-builder-action.js',
	},
	output: {
		path: path.resolve( __dirname, 'js' ),
		filename: '[name]'
	},
	devtool: 'eval-source-map',
	resolve: {
		modules: [
			path.resolve( __dirname, 'src' ),
			'node_modules'
		],
		extensions: [ '.js' ],
		alias: {
			'@': path.resolve( __dirname, 'src' )
		}
	},
	module: {
		rules: [
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: /node_modules/
			},
		]
	}
}