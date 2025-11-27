const mix = require('laravel-mix');

/*
|--------------------------------------------------------------------------
| Mix Asset Management
|--------------------------------------------------------------------------
|
| Mix provides a clean, fluent API for defining some Webpack build steps
| for your Laravel application. By default, we are compiling the Sass
| file for the application as well as bundling up all the JS files.
|
*/

mix.js('resources/js/app.js', 'public/js')
    .js('resources/js/apps/SkillProfiles/index.js', 'public/js/apps/SkillProfiles')
    // .js('resources/js/apps/dashboard/index.js', 'public/js/apps/dashboard')
    .js('resources/js/jquery.tablesorter.js', 'public/js/jquery.tablesorter.js')
    .sass('resources/sass/app.scss', 'public/css')
    .copy('resources/assets/plugins', 'public/plugins', false)
    .js('resources/js/tpv_flashcard.js', 'public/plugins/tinymce/js/tinymce/plugins/tpv_flashcard/plugin.min.js')
    .options({
        processCssUrls: false,
    })
    .webpackConfig({
        resolve: {
            alias: {
                components: path.resolve(__dirname, './resources/js/components'),
                pages: path.resolve(__dirname, './resources/js/pages'),
                utils: path.resolve(__dirname, './resources/js/utils'),
                sass: path.resolve(__dirname, './resources/sass'),
            },
        },
    });
