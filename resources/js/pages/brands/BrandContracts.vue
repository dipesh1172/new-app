<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: `${brand.name ? brand.name : '...Loading'}`, url: `/brands/${brand.id}/edit`},
      {name: 'Contracts', active: true}
    ]"
  >
    <div class="tab-content">
      <div
        role="tabpanel"
        class="tab-pane active p-0"
      >
        <div class="animated fadeIn">
          <div class="card mb-0">
            <div class="card-header">
              <i class="fa fa-th-large" /> Brand Contracts
              <div class="pull-right">
                <a
                  :href="`/brand_contracts/add_contract?brand_id=${brand.id}`"
                  class="btn btn-success btn-sm"
                >
                  <i
                    class="fa fa-plus"
                    aria-hidden="true"
                  /> Add Contract
                </a>
              </div>
            </div>
            <div class="card-body p-1">
              <div
                v-if="hasFlashMessage"
                class="alert alert-success"
              >
                <span class="fa fa-check-circle" /> <em>{{ flashMessage }}</em>
              </div>
              <div
                slot="filter-bar"
                class="filter-bar-row"
              >
                <nav class="navbar navbar-light bg-light filter-navbar">
                  <search-form
                    :on-submit="searchData"
                    :initial-values="initialSearchValues"
                    :hide-search-box="true"
                    :include-date-range="false"
                    include-market
                    include-channel
                    include-state
                    include-language
                    include-commodity
                    btn-label="Filter"
                    btn-icon="filter"
                  >
                    <template slot="extraSelect">
                      <div class="form-group">
                        <select
                          v-model="selectedRateType"
                          name="rtype"
                          class="form-control filter-select"
                        >
                          <option :value="null">
                            Select a Rate Type
                          </option>
                          <option :value="1">
                            Fixed
                          </option>
                          <option :value="2">
                            Variable
                          </option>
                          <option :value="4">
                            Tiered / Fixed
                          </option>
                          <option :value="5">
                            Tiered / Variable
                          </option>
                        </select>
                      </div>
                      <div class="form-group">
                        <select
                          v-model="selectedDtype"
                          name="dtype"
                          class="form-control filter-select"
                        >
                          <option :value="null">
                            Select a Contract Type
                          </option>
                          <option :value="1">
                            Contract Only
                          </option>
                          <option :value="3">
                            Sigpage + Contract
                          </option>
                        </select>
                      </div>
                      <div
                        class="form-group"
                      >
                        <select
                          v-model="selectedProduct"
                          name="promo_id"
                          class="form-control filter-select"
                        >
                          <option :value="null">
                            Select a product
                          </option>
                          <option
                            v-for="product in products"
                            :key="product.id"
                            :value="product.id"
                          >
                            {{ product.name }}
                          </option>
                        </select>
                      </div>
                      <div
                        class="form-group"
                      >
                        <select
                          v-model="selectedRate"
                          name="rate_id"
                          class="form-control filter-select"
                          @change="filterProducts(selectedRate)"
                        >
                          <option :value="null">
                            Select a rate
                          </option>
                          <option
                            v-for="rate in (!selectedProduct ? rates: rates.filter(r => r.product_id === selectedProduct))"
                            :key="rate.id"
                            :value="rate.id"
                          >
                            {{ rate.program_code }}
                          </option>
                        </select>
                      </div>
                    </template>
                  </search-form>
                </nav>
              </div>
              <div
                v-if="column === 'rate_type_id'"
                class="alert alert-warning"
              >
                Because of the way Tiered contract info is stored it is not possible currently to seperate Tiered / Fixed from Tiered / Variable in the sorting process.
              </div>
              <custom-table
                :headers="headers"
                :data-grid="contracts"
                :data-is-loaded="dataIsLoaded"
                :show-action-buttons="true"
                :has-action-buttons="true"
                no-bottom-padding
                empty-table-message="No contracts were found."
                @sortedByColumn="sortData"
              >
                <template
                  slot="rtype"
                  slot-scope="slotProps"
                >
                  <span
                    v-if="slotProps.row.rate_type_id == 1"
                    class="badge badge-primary"
                  >
                    Fixed
                  </span>
                  <span
                    v-else-if="slotProps.row.rate_type_id == 2"
                    class="badge badge-secondary"
                  >
                    Variable
                  </span>
                  <template v-else-if="slotProps.row.rate_type_id == 3">
                    <span
                      v-if="slotProps.row.contract_pdf.includes('fixed-tiered')"
                      class="badge badge-info"
                    >
                      Tiered / Fixed
                    </span>
                    <span
                      v-else-if="slotProps.row.contract_pdf.includes('tiered-variable')"
                      class="badge badge-success"
                    >
                      Tiered / Variable
                    </span>
                    <span
                      v-else
                      class="badge badge-warning"
                    >
                      Unknown Rate type
                    </span>
                  </template>
                  <span
                    v-else
                    class="badge badge-warning"
                  >
                    Unknown Rate Type {{ slotProps.row.rate_type_id }}
                  </span>
                </template>
                <template
                  slot="dtype"
                  slot-scope="slotProps"
                >
                  <span
                    v-if="slotProps.row.document_type_id == 1"
                    class="badge badge-primary"
                  >
                    Contract Only
                  </span>
                  <span
                    v-else-if="slotProps.row.document_type_id == 3"
                    class="badge badge-secondary"
                  >
                    Sigpage + Contract
                  </span>
                  <span
                    v-else
                    class="badge badge-warning"
                  >
                    unknown type
                  </span>
                </template>
              </custom-table>
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
  </layout>
</template>
<script>
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';
import SearchForm from 'components/SearchForm';
import { formArrayQueryParam } from 'utils/stringHelpers';
import { hasProperties } from 'utils/helpers';
import { mapState } from 'vuex';
import Layout from './edit/Layout';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'BrandContract',
    components: {
        CustomTable,
        Pagination,
        SearchForm,
        Layout,
    },
    props: {
        brand: {
            type: Object,
            default: () => ({}),
        },
        states: {
            type: Array,
            default: () => [],
        },
        channels: {
            type: Array,
            default: () => [],
        },
        languages: {
            type: Array,
            default: () => [],
        },
        commodities: {
            type: Array,
            default: () => [],
        },
        productsAndRates: {
            type: Array,
            default: () => [],
        },
        awsCloudFront: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            contracts: [],
            markets: [
                {
                    id: 1,
                    name: 'Residential',
                },
                {
                    id: 2,
                    name: 'Commercial',
                },
            ],
            dataIsLoaded: false,
            totalRecords: 0,
            exportUrl: '',
            activePage: 1,
            numberPages: 1,
            column: this.getParams().column,
            direction: this.getParams().direction,
            headers: [{
                label: 'Date',
                key: 'created_at',
                serviceKey: 'created_at',
                sorted: DESC_SORTED,
                canSort: true,
            }, {
                label: 'State',
                key: 'state_abbrev',
                serviceKey: 'state_abbrev',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Channel',
                key: 'channel',
                align: 'center',
                serviceKey: 'channel',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Market',
                key: 'market',
                serviceKey: 'market',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Language',
                align: 'center',
                key: 'language',
                serviceKey: 'language',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Commodity',
                align: 'center',
                key: 'commodity',
                serviceKey: 'commodity',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Product',
                align: 'center',
                key: 'product_name',
                serviceKey: 'product_name',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Green',
                align: 'center',
                key: 'product_type',
                serviceKey: 'product_type',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Rate Type',
                align: 'center',
                slot: 'rtype',
                key: 'rate_type_id',
                serviceKey: 'rate_type_id',
                sorted: NO_SORTED,
                canSort: true,
            }, {
                label: 'Type',
                align: 'center',
                key: 'document_type_id',
                serviceKey: 'document_type_id',
                sorted: NO_SORTED,
                canSort: true,
                slot: 'dtype',
            }],
            searchValue: this.getParams().search,
            products: [],
            rates: [],
            selectedDtype: this.getParams().dtype,
            selectedProduct: this.getParams().product_id,
            selectedRateType: this.getParams().rtype,
            selectedRate: this.getParams().rate_id,
            
        };
    },
    computed: {
        ...mapState({
            hasFlashMessage: (state) => hasProperties(state, 'session.flash_message') && state.session.flash_message,
            flashMessage: (state) => state.session.flash_message,
        }),
        filterParams() {
            const params = this.getParams();
            return [
                params.channel
                    ? formArrayQueryParam('channel', params.channel)
                    : '',
                params.market
                    ? formArrayQueryParam('market', params.market)
                    : '',
                params.state
                    ? formArrayQueryParam('state', params.state)
                    : '',
                params.language
                    ? formArrayQueryParam('language', params.language)
                    : '',
                params.commodity
                    ? formArrayQueryParam('commodity', params.commodity)
                    : '',
                this.selectedProduct ? `&product_id=${this.selectedProduct}` : '',
                this.selectedRate ? `&rate_id=${this.selectedRate}` : '',
                this.selectedDtype ? `&dtype=${this.selectedDtype}` : '',
                this.selectedRateType ? `&rtype=${this.selectedRateType}` : '',
            ].join('');
        },
        initialSearchValues() {
            const params = this.getParams();
            return {
                channel: params.channel,
                market: params.market,
                state: params.state,
                language: params.language,
                commodity: params.commodity,
                dtype: params.dtype,
                rtype: params.rtype,
            };
        },
    },
    created() {
        this.$store.commit('setStates', this.states);
        this.$store.commit('setMarkets', this.markets);
        this.$store.commit('setChannels', this.channels);
        this.$store.commit('setLanguages', this.languages);
        this.$store.commit('setCommodities', this.commodities);

        this.mapProductsAndRates();
    },
    mounted() {
        const params = this.getParams();
        const searchParam = params.search ? `&search=${params.search}` : '';
        const pageParam = params.page ? `&page=${params.page}` : '';

        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === params.column);
            this.headers[sortHeaderIndex].sorted = params.direction;
        }
        const url = `/brands/${this.brand.id}/list_contracts`;
        this.exportUrl = `${url}?&csv=true${searchParam}${this.getSortParams()}${this.filterParams}`;

        axios.get(`${url}?${pageParam}${searchParam}${this.getSortParams()}${this.filterParams}`)
            .then(({data}) => {
                this.contracts = data.data.map((contract) => this.mapContract(contract));
                this.dataIsLoaded = true;
                this.activePage = data.current_page;
                this.numberPages = data.last_page;
            })
            .catch((error) => {
                console.log(error);
            });
    },
    methods: {
        
        filterProducts(rate_id) {
            const rate = this.rates.find((r) => r.id === rate_id);
            const product = this.products.find((p) => rate.product_id === p.id);
            if (product) {
                this.selectedProduct = product.id;
            }
        },
        mapProductsAndRates() {
            this.productsAndRates.forEach((pr) => {
                if (!this.products.find((p) => p.id === pr.product_id)) {
                    this.products.push({name: pr.product_name, id: pr.product_id});
                }
                if (!this.rates.find((r) => r.id === pr.rate_id)) {
                    this.rates.push({program_code: pr.program_code, product_id: pr.product_id, id: pr.rate_id});
                }
                this.rates.sort((lhs, rhs) => {
                    if (lhs.program_code < rhs.program_code) {
                        return -1;
                    }
                    if (lhs.program_code > rhs.program_code) {
                        return 1;
                    }
                    return 0;
                });
            });
        },
        mapContract(contract) {
            const url = (this.$moment(contract.created_at, 'YYYY-MM-DD').isSameOrAfter('2020-02-27'))
                ? `${this.awsCloudFront}/contracts/`
                : '/storage/contracts?file_name=';

            if (contract.product_name !== null && contract.product_deleted !== null) {
                contract.product_name = `<strike style="color: red;">${contract.product_name}</strike>`;
            }

            contract.product_type = (contract.product_type === 0) ? 'N' : 'Y';

            Object.keys(contract).forEach((key) => {
                contract[key] = contract[key] || '--';
            });

            contract.buttons = [{
                type: 'edit',
                url: `/brand_contracts/${contract.id}/edit`,
                buttonSize: 'medium',
            }, {
                type: 'view',
                url: url + contract.file_name,
                buttonSize: 'medium',
                label: 'View',
            }];

            return contract;
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;
            window.location.href = `/brands/${this.brand.id}/get_contracts?column=${serviceKey}&direction=${labelSort}${this.getSearchParams()}${this.getPageParams()}${this.filterParams}`;
        },
        searchData(
            {
                channel,
                market,
                state,
                language,
                commodity,
            },
        ) {
            const params = [
                channel ? formArrayQueryParam('channel', channel) : '',
                market ? formArrayQueryParam('market', market) : '',
                state ? formArrayQueryParam('state', state) : '',
                language ? formArrayQueryParam('language', language) : '',
                commodity ? formArrayQueryParam('commodity', commodity) : '',
                this.selectedProduct ? `&product_id=${this.selectedProduct}` : '',
                this.selectedRate ? `&rate_id=${this.selectedRate}` : '',
                this.selectedDtype ? `&dtype=${this.selectedDtype}` : '',
                this.selectedRateType ? `&rtype=${this.selectedRateType}` : '',
            ].join('');
            window.location.href = `/brands/${this.brand.id}/get_contracts?${params}${this.getSearchParams()}${this.getSortParams()}`;
        },
        selectPage(page) {
            window.location.href = `/brands/${this.brand.id}/get_contracts?page=${page}${this.filterParams}${this.getSortParams()}${this.getSearchParams()}`;
        },
        getSearchParams() {
            return this.searchValue ? `&search=${this.searchValue}` : '';
        },
        getSortParams() {
            return !!this.getParams().column && !!this.getParams().direction
                ? `&column=${this.getParams().column}&direction=${this.getParams().direction}` : '';
        },
        getPageParams() {
            return this.activePage ? `&page=${this.activePage}` : '';
        },
        updateSearchValue(newValue) {
            this.searchValue = newValue;
        },
        getParams() {
            const url = new URL(window.location.href);
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const page = url.searchParams.get('page') || '';
            const search = url.searchParams.get('search') || '';
            const market = url.searchParams.getAll('market[]');
            const state = url.searchParams.getAll('state[]');
            const channel = url.searchParams.getAll('channel[]');
            const language = url.searchParams.getAll('language[]');
            const commodity = url.searchParams.getAll('commodity[]');
            const product_id = url.searchParams.get('product_id');
            const rate_id = url.searchParams.get('rate_id');
            const dtype = url.searchParams.get('dtype');
            const rtype = url.searchParams.get('rtype');

            return {
                column,
                direction,
                page,
                search,
                market,
                state,
                channel,
                language,
                commodity,
                product_id,
                rate_id,
                dtype,
                rtype,
            };
        },
    },
};
</script>
<style>
  .filter-select{
    min-height: 40px;
    border: 1px solid rgb(232, 232, 232);
    border-radius: 5px;
  }
</style>
