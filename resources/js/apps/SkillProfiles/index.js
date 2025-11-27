import Vue from 'vue';
import Vuex from 'vuex';
import VueRouter from 'vue-router';
import Main from './pages/Main.vue';
import Store from './store';
import routes from './routes';

Vue.use(Vuex);
Vue.use(VueRouter);

const store = new Vuex.Store(Store);

const router = new VueRouter({
    linkActiveClass: 'active', // works with bootstrap4 out of the box
    routes,
});

const app = new Vue(Vue.util.extend(Main, {
    store,
    router,
})).$mount('#main-content');
