<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Scaffold a new Vue based single page application.
 */
class MakeNewVueApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:app {appname : The name of the SPA to create} {--router : Include router in scaffold}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scaffold a new Vue based single page application';

    /**
     * This contains the base directory of the Laravel project.
     *
     * @var string
     */
    private $_baseDir;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_baseDir = getcwd();
    }

    private $_indexjs
    = <<<'EOF'
import Vue from 'vue';
import Vuex from 'vuex';
import VueLogr from 'vuelogr';
import Main from './pages/Main.vue';
import Store from './store';

Vue.use(Vuex);
Vue.use(VueLogr);

const store = new Vuex.Store(Store);

const app = new Vue(Vue.util.extend(Main, {
    store,
})).$mount('#main-content');
EOF;

    private $_indexjs_with_router
    = <<<'EOF'
import Vue from 'vue';
import Vuex from 'vuex';
import VueRouter from 'vue-router';
import VueLogr from 'vuelogr';
import Main from './pages/Main.vue';
import Store from './store';
import Routes from './routes';

Vue.use(Vuex);
Vue.use(VueRouter);
Vue.use(VueLogr);

const store = new Vuex.Store(Store);

const router = new VueRouter({
    linkActiveClass: 'active', //works with bootstrap4 out of the box
    routes: Routes,
});

const app = new Vue(Vue.util.extend(Main, {
    store,
    router,
})).$mount('#main-content');
EOF;

    private $_mainvue
    = <<<'EOF'
<template>
    <div>
        Hello, World!
    </div>
</template>
<script>
export default {
    name: 'MainApp'
};
</script>
EOF;

    private $_mainvue_with_router
    = <<<'EOF'
<template>
    <div>
        <router-view></router-view>
    </div>
</template>
<script>
export default {
    name: 'MainApp'
};
</script>
EOF;

    private $_hw_vue
    = <<<'EOF'
<template>
    <div>
        Hello World
    </div>
</template>
<script>
export default {
    name: 'HelloWorld'
};
</script>
EOF;

    private $_storejs
    = <<<'EOF'
export default {
    strict: false,
    state: {
        //
    },
    mutations: {
        //
    },
    actions: {
        //
    }
};
EOF;

    private $_routesjs
    = <<<'EOF'
import HelloWorld from '../pages/HelloWorld.vue';

export default [
    { path: '/', component: HelloWorld },
];
EOF;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $appName = $this->argument('appname');
        $useVueRouter = $this->option('router');

        $this->info('Creating directories...');

        $dirsToMake = [
            '/resources/js/apps/' . $appName,
            '/resources/js/apps/' . $appName . '/components',
            '/resources/js/apps/' . $appName . '/pages',
            '/resources/js/apps/' . $appName . '/store',
        ];

        if ($useVueRouter) {
            $dirsToMake[] = '/resources/js/apps/' . $appName . '/routes';
        }

        $bar = $this->output->createProgressBar(count($dirsToMake) + 1);
        if (!file_exists($this->_baseDir . '/resources/js/apps')) {
            try {
                if (!mkdir($this->_baseDir . '/resources/js/apps')) {
                    $this->info("\n");
                    $this->error('Unable to create base app directory {resources/assests/js/apps}');

                    return;
                }
            } catch (\Exception $e) {
                $this->info("\n");
                $this->error('Unable to create base app directory {resources/assests/js/apps}');

                return;
            }
        }
        $bar->advance();
        if (file_exists($this->_baseDir . '/resources/js/apps/' . $appName)) {
            $this->info("\n");
            $this->error("The application {$appName} already exists please remove the directory {$this->_baseDir}/resources/js/apps/{$appName} to continue.");

            return;
        }
        $bar->advance();
        foreach ($dirsToMake as $dir) {
            $fullpath = $this->_baseDir . $dir;
            try {
                if (!mkdir($fullpath)) {
                    $this->info("\n");
                    $this->error('Unable to create application directory: ' . $fullpath);
                }
            } catch (\Exception $e) {
                $this->info("\n");
                $this->error('Unable to create application directory: ' . $fullpath);

                return;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->info("\n");
        $this->info('Writing files...');

        $files = [
            "/resources/js/apps/{$appName}/index.js" => $useVueRouter ? $this->_indexjs_with_router : $this->_indexjs,
            "/resources/js/apps/{$appName}/pages/Main.vue" => $useVueRouter ? $this->_mainvue_with_router : $this->_mainvue,
            "/resources/js/apps/{$appName}/store/index.js" => $this->_storejs,
            "/resources/js/apps/{$appName}/components/deleteme.txt" => 'This file is here for git only.',
        ];

        if ($useVueRouter) {
            $files["/resources/js/apps/{$appName}/routes/index.js"] = $this->_routesjs;
            $files["/resources/js/apps/{$appName}/pages/HelloWorld.vue"] = $this->_hw_vue;
        }

        $bar = $this->output->createProgressBar(count($files) + 1);

        foreach ($files as $path => $content) {
            $fullpath = $this->_baseDir . $path;
            if (file_put_contents($fullpath, $content) === false) {
                $this->info("\n");
                $this->error('Unable to write to file: ' . $fullpath);

                return;
            }
            $bar->advance();
        }

        $webpack = explode("\n", file_get_contents($this->_baseDir . '/webpack.mix.js'));
        $lineToAdd = ".js('resources/js/apps/{$appName}/index.js', 'public/js/apps/{$appName}')";
        $outLines = [];
        $added = false;

        foreach ($webpack as $line) {
            $outLines[] = trim($line);
            if (strpos($line, 'mix.js(') !== false) {
                if (strpos($line, ')') !== false) {
                    $outLines[] = $lineToAdd;
                    $added = true;
                    $bar->advance();
                }
            }
        }
        if (file_put_contents($this->_baseDir . '/webpack.mix.js', implode("\n", $outLines)) === false) {
            $this->info("\n");
            $this->error('Could not write output to webpack.mix.js');

            return;
        }
        $bar->finish();

        if (!$added) {
            $this->info("\n");
            $this->error('Could not find appropriate location to add build command, please add: "' . $lineToAdd . '" to your webpack.mix.js file.');
        } else {
            $this->info("\n");
            $this->info('Done.');
        }
    }
}
