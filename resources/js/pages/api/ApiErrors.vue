<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'API Errors', active: true},
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="tab-content">
        <div
          role="tabpanel"
          class="tab-pane active"
        >
          <div class="animated fadeIn">
            <div class="row page-buttons">
              <div class="col-md-12">
                <div class="form-group pull-right m-0 ml-1" />
                <div class="form-group form-inline pull-right">
                  <custom-input
                    :value="searchValue"
                    placeholder="Search"
                    name="search"
                    :style="{ marginRight: 0 }"
                    @onChange="updateSearchValue"
                    @onKeyUpEnter="searchData"
                  />
                  <button
                    class="btn btn-primary"
                    @click="searchData"
                  >
                    <i class="fa fa-search" />
                  </button>
                </div>
              </div>
            </div>
            <div class="card">
              <div class="card-header agents-header">
                <i class="fa fa-th-large" /> API Errors
              </div>
              <div class="card-body">
                <div
                  v-if="hasFlashMessage"
                  class="alert alert-success"
                >
                  <span class="fa fa-check-circle" />
                  <em>{{ flashMessage }}</em>
                </div>
                <custom-table
                  :headers="headers"
                  :data-grid="dataGrid"
                  :data-is-loaded="dataIsLoaded"
                  show-action-buttons
                  has-action-buttons
                  empty-table-message="No results were found."
                />
                <pagination
                  v-if="dataIsLoaded"
                  :active-page="activePage"
                  :number-pages="numberPages"
                  @onSelectPage="selectPage"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { status, statusLabel } from 'utils/constants';
import CustomTable from 'components/CustomTable';
import CustomInput from 'components/CustomInput';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';
import { mapState } from 'vuex';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'APIErrors',
    components: {
        CustomTable,
        CustomInput,
        Pagination,
        Breadcrumb,
    },
    data() {
        return {
            results: [],
            headers: [{
                label: 'Brand',
                key: 'name',
                serviceKey: 'name',
                width: '15%',
                sorted: NO_SORTED,
            }, {
                label: 'Created',
                key: 'created_at',
                serviceKey: 'created_at',
                width: '15%',
                sorted: NO_SORTED,
            }, {
                label: 'Message',
                key: 'message',
                serviceKey: 'message',
                width: '10%',
                sorted: NO_SORTED,
            }, {
                label: 'Body',
                key: 'body',
                serviceKey: 'body',
                width: '50%',
                sorted: NO_SORTED,
            }],
            searchValue: this.getParams().search,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
        };
    },
    computed: {
        dataGrid() {
            return this.results;
        },
        ...mapState({
            hasFlashMessage: (state) => state.session && Object.keys(state.session).length && state.session.hasOwnProperty('flash_message') && state.session.flash_message,
            flashMessage: (state) => state.session.flash_message,
        }),
    },
    mounted() {
        const searchParam = this.getParams().search ? `&search=${this.getParams().search}` : '';
        const pageParam = this.getParams().page ? `&page=${this.getParams().page}` : '';

        axios.get(`/api/errors/list?${pageParam}${searchParam}`)
            .then((response) => {
                this.results = response.data.data.map((result) => this.getObject(result));
                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
            })
            .catch((error) => {
                console.log(error);
            });
    },
    methods: {
        getObject(result) {
            return {
                name: result.name,
                created_at: result.created_at,
                message: result.message,
                body: `<pre>${JSON.stringify(result.body, null, '\t')}</pre>`,
            };
        },
        searchData() {
            window.location.href = `/api/errors?${this.getSearchParams()}`;
        },
        selectPage(page) {
            window.location.href = `/api/errors?page=${page}${this.getSearchParams()}`;
        },
        getSearchParams() {
            return this.searchValue ? `&search=${this.searchValue}` : '';
        },
        getPageParams() {
            return this.activePage ? `&page=${this.activePage}` : '';
        },
        updateSearchValue(newValue) {
            this.searchValue = newValue;
        },
        getParams() {
            const url = new URL(window.location.href);
            const search = url.searchParams.get('search');
            const page = url.searchParams.get('page') || '';

            return {
                page,
                search,
            };
        },
    },
};
</script>
