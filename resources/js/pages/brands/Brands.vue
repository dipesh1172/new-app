<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Brands', url: '/brands', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <brands-nav />
      <div class="tab-content mb-4">
        <div 
          role="tabpanel" 
          class="tab-pane active p-0"
        >
          <div class="animated fadeIn">
            <div class="card mb-0">
              <div class="card-header brands-header">
                <a 
                  :class="{ 'active mt-1': !isFavoritesPage, 'brand-tab': true }" 
                  href="/brands?list=all"
                >
                  <i class="fa fa-th-large" /> All brands
                </a>
                <a 
                  :class="{ 'active mt-1': isFavoritesPage, 'brand-tab': true }"
                  href="/brands?list=favorites"
                >
                  <i class="fa fa-bookmark" /> Favorite brands
                </a>
            
                <a 
                  :href="createUrl" 
                  class="btn btn-success mr-0 mt-1 pull-right"
                ><i
                  class="fa fa-plus"
                  aria-hidden="true"
                /> Add Brand</a>

                <button 
                  class="btn btn-primary pull-right mt-1 mr-2" 
                  @click="searchData"
                >
                  <i class="fa fa-search" />
                </button>
            
                <custom-input
                  :value="searchValue"
                  placeholder="Search"
                  name="search"
                  class="pull-right m-0 mt-1"
                  @onChange="updateSearchValue"
                  @onKeyUpEnter="searchData"
                />
              </div>
              <div class="card-body p-0">
                <div 
                  v-if="hasFlashMessage" 
                  class="alert alert-success"
                >
                  <span class="fa fa-check-circle" /> <em>{{ flashMessage }}</em>
                </div>
                <custom-table
                  :headers="headers"
                  :data-grid="this.brands"
                  :data-is-loaded="dataIsLoaded"
                  show-action-buttons
                  has-action-buttons
                  empty-table-message="No brands were found."
                  class=""
                  :no-bottom-padding="numberPages <= 1"
                  @sortedByColumn="sortData"
                  @updatedBrandFavorites="updateBrandFavorite($event)"
                />
                <pagination
                  v-if="dataIsLoaded"
                  :active-page="activePage"
                  :number-pages="numberPages"
                  class="ml-auto mr-auto mb-0"
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
import { status, statusLabel, actions } from 'utils/constants';
import CustomTable from 'components/CustomTable';
import CustomInput from 'components/CustomInput';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';
import BrandsNav from '../clients/BrandsNav';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';
const FAVORITES_ENDPOINT = '/list/favorites?';
const BRANDS_ENDPOINT = '/brands/list?';

export default {
    name: 'Brands',
    components: {
        CustomTable,
        CustomInput,
        Pagination,
        Breadcrumb,
        BrandsNav,
    },
    props: {
        createUrl: {
            type: String,
            required: true,
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
            brands: [],
            headers: [{
                label: 'Status',
                key: 'statusLabel',
                serviceKey: 'status',
                width: '20%',
                sorted: NO_SORTED,
            }, {
                label: 'Brand Name',
                key: 'brandName',
                serviceKey: 'name',
                width: '35%',
                sorted: NO_SORTED,
            }, {
                label: 'Client Name',
                key: 'clientName',
                serviceKey: 'client_name',
                width: '35%',
                sorted: NO_SORTED,
            }],
            searchValue: this.searchParameter,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            isFavoritesPage: false,
        };
    },
    computed: {
        dataGrid() {
            return this.brands;
        },
    },
    mounted() {
        this.init();
    },
    methods: {

        init() {
            const searchParam = this.searchParameter ? `&search=${this.searchParameter}` : '';
            const sortParams = !!this.columnParameter && !!this.directionParameter
                ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
            const pageParam = this.pageParameter ? `&page=${this.pageParameter}` : '';

            this.isFavoritesPage = location.href.indexOf('favorites') > 0;
            const favoritesParam = this.isFavoritesPage ? '&list=favorites' : '&list=all';
            const baseUrl = this.isFavoritesPage ? FAVORITES_ENDPOINT : BRANDS_ENDPOINT;

            if (!!this.columnParameter && !!this.directionParameter) {
                const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === this.columnParameter);
                this.headers[sortHeaderIndex].sorted = this.directionParameter;
            }

            this.fetch(baseUrl, `${favoritesParam}${pageParam}${searchParam}${sortParams}`);
        },

        updateBrandFavorite(response) {
        	console.log('update brand favorite');
        	this.init();
        },

        denormalizeBrands({ data: brandsData}) {
            const brandsResult = brandsData.map((brand) => {
                const isFavoriteBrand = brand.favorite;
                return {
                    id: brand.id,
                    brandName: brand.name,
                    clientName: brand.client_name,
                    status: brand.active,
                    statusLabel: statusLabel[brand.active],
                    buttons: [{
                        type: isFavoriteBrand ? actions.FAVORITE_ON : actions.FAVORITE_OFF,
                        url: `/brands/favorites?brand=${brand.id}`,
                    }, {
                        type: 'edit',
                        url: `/brands/${brand.id}/edit`,
                    }, {
                        type: 'login',
                        url: `/brands/login?brand=${brand.id}`,
                        icon: 'sign-in',
                    }],
                };
            });

            return brandsResult;
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `/brands?column=${serviceKey}
                    &direction=${labelSort}${this.getSearchParams()}${this.getPageParams()}`;
        },
        searchData() {
            window.location.href = `/brands?${this.getSearchParams()}${this.getSortParams()}`;
        },
        selectPage(page) {
            const baseUrl = this.isFavoritesPage ? '/brands?list=favorites&' : '/brands?list=all&';
            window.location.href = `${baseUrl}page=${page}${this.getSortParams()}${this.getSearchParams()}`;
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
        renderBrands(brandsData) {
            this.brands = this.denormalizeBrands(brandsData);
            this.dataIsLoaded = true;
            this.activePage = brandsData.current_page;
            this.numberPages = brandsData.last_page;
        },

        async fetch(baseUrl, params) {
            this.dataIsLoaded = false;
            const isInitialLoad = window.location.href.indexOf('?') === -1;
            let brands = [];

            try {
                if (isInitialLoad) {
                    const favoritesBrandsData = await axios.get(`${FAVORITES_ENDPOINT}${params}`);
                    const hasFavorites = favoritesBrandsData.data.data.length;

                    if (hasFavorites) {
                        brands = favoritesBrandsData;
                        this.isFavoritesPage = true;
                    }
                    else {
                        brands = await axios.get(`${BRANDS_ENDPOINT}${params}`);
                    }
                }
                else {
                    brands = await axios.get(`${baseUrl}${params}`);
                }

                this.renderBrands(brands.data);
                return brands;
            }
            catch (err) {
                return err;
            }

        },
    },
};
</script>

<style lang="scss" scoped>
    .brands-header {
        clear: none;
        float: left;
        padding-top: 0;
        padding-bottom: 0;
    }

    .brand-tab {
        color: #000;
        display: inline-block;
        padding: 10px;
        text-decoration: none;

        &.active {
            background-color: #fff;
            position: relative;
            top: 1px;
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.2);
        }
    }
</style>
