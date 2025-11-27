<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Contracts', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <nav
          class="nav nav-tabs"
          role="tablist"
        >
          <a
            :class="{ 'nav-item': true, 'nav-link': true, 'active': url === '/contracts' }"
            href="/contracts"
          >Summary Filters</a>
          <a
            :class="{ 'nav-item': true, 'nav-link': true, 'active': url === '/contracts/tcs' }"
            href="/contracts/tcs"
          >Terms & Conditions</a>
          <!-- <a :class="{ 'nav-item': true, 'nav-link': true, 'active': url === '/contracts/cancellations' }" href="/contracts/cancellations">Cancellations</a> -->
        </nav>
        <div class="card tab-content">
          <div class="card-body">
            <form
              method="POST"
              action="/contracts/uploadPdf"
              accept-charset="UTF-8"
              autocomplete="off"
              enctype="multipart/form-data"
            >
              <input
                type="hidden"
                name="_token"
                :value="csrf_token"
              >
              <div class="row">
                <div class="col-md-4">
                  <label for="brands">Brands</label><br>
                  <select
                    id="brand"
                    name="brand"
                    class="form-control form-control-lg"
                    required
                  >
                    <option value="">
                      Select a Brand
                    </option>
                    <option
                      v-for="brand in brands"
                      :key="brand.id"
                      :value="brand.id"
                    >
                      {{ brand.name }}
                    </option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="terms_conditions">Document</label><br>
                  <input
                    id="terms_conditions"
                    type="file"
                    name="terms_conditions"
                  >
                </div>
                <div class="col-md-3">
                  <label for="language">Language</label><br>
                  <select
                    id="language"
                    name="language"
                    class="form-control form-control-lg"
                    required
                  >
                    <option
                      value="1"
                      selected
                    >
                      English
                    </option>
                    <option value="2">
                      Spanish
                    </option>
                  </select>
                </div>
                <div class="col-md-2">
                  <br>
                  <button class="btn btn-success">
                    <i
                      class="fa fa-upload"
                      aria-hidden="true"
                    /> Upload
                  </button>
                </div>
              </div>
            </form>

            <br><hr><br>

            <div
              v-if="hasFlashMessage"
              class="alert alert-success"
            >
              <span class="fa fa-check-circle" /> <em>{{ flashMessage }}</em>
            </div>
            <custom-table
              :headers="headers"
              :data-grid="dataGrid"
              :data-is-loaded="dataIsLoaded"
              show-action-buttons
              has-action-buttons
              empty-table-message="No terms & conditions were found."
              @sortedByColumn="sortData"
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
</template>

<script>
import { status, statusLabel } from 'utils/constants';
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'ContractsTCS',
    components: {
        CustomTable,
        Pagination,
        Breadcrumb,
    },
    props: {
        brands: {
            type: Array,
        },
        searchParameter: {
            type: String,
            default: '',
        },
        columnParameter: {
            type: String,
            default: '',
        },
        directionParameter: {
            type: String,
            default: '',
        },
        pageParameter: {
            type: String,
            default: '',
        },
        hasFlashMessage: {
            type: Boolean,
            default: false,
        },
        flashMessage: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            url: null,
            object: [],
            headers: [{
                label: 'Brand',
                key: 'brand_name',
                serviceKey: 'brand_name',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Language',
                key: 'language',
                serviceKey: 'language',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Name',
                key: 'terms_and_conditions_name',
                serviceKey: 'terms_and_conditions_name',
                width: '15%',
                sorted: NO_SORTED,
                canSort: true,
            }],
            searchValue: this.searchParameter,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
        };
    },
    computed: {
        dataGrid() {
            return this.object;
        },
        csrf_token() {
            return csrf_token;
        },
    },
    mounted() {
        const searchParam = this.searchParameter ? `&search=${this.searchParameter}` : '';
        const sortParams = !!this.columnParameter && !!this.directionParameter
            ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        const pageParam = this.pageParameter ? `&page=${this.pageParameter}` : '';

        if (!!this.columnParameter && !!this.directionParameter) {
            const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === this.columnParameter);
            this.headers[sortHeaderIndex].sorted = this.directionParameter;
        }

        axios.get(`/contracts/tcs/list?${pageParam}${searchParam}${sortParams}`)
            .then((response) => {
                this.object = response.data.data.map((object) => this.getObject(object));
                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
            })
            .catch((error) => {
                console.log(error);
            });

        this.url = window.location.pathname;
    },
    methods: {
        getObject(object) {
            return {
                brand_name: object.brand_name,
                language: object.language,
                terms_and_conditions_name: object.terms_and_conditions_name ? object.terms_and_conditions_name : '--',
            };
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `/contracts?column=${serviceKey}&direction=${labelSort}${this.getSearchParams()}${this.getPageParams()}`;
        },
        searchData() {
            window.location.href = `/contracts?${this.getSearchParams()}${this.getSortParams()}`;
        },
        selectPage(page) {
            window.location.href = `/contracts?page=${page}${this.getSortParams()}${this.getSearchParams()}`;
        },
        getSearchParams() {
            return this.searchValue ? `&search=${this.searchValue}` : '';
        },
        getSortParams() {
            return !!this.columnParameter && !!this.directionParameter
                ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        },
        getPageParams() {
            return this.activePage ? `&page=${this.activePage}` : '';
        },
        updateSearchValue(newValue) {
            this.searchValue = newValue;
        },
    },
};
</script>

<style lang="scss" scoped>

</style>
