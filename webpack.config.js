const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = (env, argv) => {
    const isProd = argv.mode === 'production';

    return {
        entry: {
            // JS entries
            'js/admin':                './assets/js/admin.js',
            'js/admin-batch':          './assets/js/admin-batch.js',
            'js/admin-download-buttons': './assets/js/admin-download-buttons.js',
            'js/batch-settings':       './assets/js/batch-settings.js',
            'js/pages':                './assets/js/pages.js',
            'js/recommendations':      './assets/js/recommendations.js',
            'js/scheduled-exports':    './assets/js/scheduled-exports.js',
            'js/settings':             './assets/js/settings.js',
            'js/themes-plugins':       './assets/js/themes-plugins.js',
            'js/cpt-acf':              './assets/js/cpt-acf.js',
            'js/users':                './assets/js/users.js',
            // CSS entries
            'css/admin':            './assets/css/admin.css',
            'css/global-peiwm.css':            './assets/css/global-peiwm.css',
            'css/recommendations':  './assets/css/recommendations.css',
            'css/scheduled-exports': './assets/css/scheduled-exports.css',
            'css/cpt-acf':          './assets/css/cpt-acf.css',
            'css/email-template':   './assets/css/email-template.css',
        },
        output: {
            path: path.resolve(__dirname, 'build'),
            filename: '[name].min.js',
            clean: true,
        },
        plugins: [
            new MiniCssExtractPlugin({
                filename: '[name].min.css',
            }),
        ],
        module: {
            rules: [
                {
                    test: /\.css$/,
                    use: [MiniCssExtractPlugin.loader, 'css-loader'],
                },
            ],
        },
        optimization: {
            minimize: isProd,
            minimizer: [
                new TerserPlugin({
                    terserOptions: {
                        compress: { drop_console: false },
                        format: { comments: false },
                    },
                    extractComments: false,
                }),
                new CssMinimizerPlugin(),
            ],
        },
        externals: {
            jquery: 'jQuery',
        },
        performance: {
            hints: false,
        },
    };
};
