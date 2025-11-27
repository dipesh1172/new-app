<template>
  <div class="tab-content">
    <div
      role="tabpanel"
      class="tab-pane active"
    >
      <div class="animated fadeIn">
        <div class="row page-buttons">
          <div class="col-md-12">
            <div class=" form-group form-inline pull-right">
              <custom-input
                :value="searchValue"
                placeholder="Search"
                name="search"
                :style="{ marginRight: 0 }"
                @onChange="updateSearchValue"
                @onKeyUpEnter="handleSearch"
              />
              <button
                class="btn btn-primary"
                @click="handleSearch"
              >
                <i class="fa fa-search" />
              </button>
            </div>
          </div>
        </div>            
        <div class="card">
          <div class="card-header">
            <a
              href="/eztpv/contracts"
              class="sale-event-tab"
            >
              <i class="fa fa-th-large" /> EZTPV Contracts
            </a>
            <a
              href="/eztpv/sale_events"
              class="sale-event-tab active"
            >
              <i class="fa fa-file-text-o" /> EZTPV Sale events
            </a>
          </div>
          <div class="card-body">
            <custom-table
              :headers="headers"
              :data-grid="dataGrid"
              :data-is-loaded="dataIsLoaded"
              show-action-buttons
              has-action-buttons
              empty-table-message="No brands were found."
            />
            <pagination
              v-if="dataIsLoaded"
              :active-page="activePage"
              :number-pages="numberPages"
              @onSelectPage="handleSelectPage"
            />
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
import { getObjParamsFromStr, getStrParamsFromObj } from 'utils/stringHelpers';

const SALE_EVENTS_ENDPOINT = '/list/sale_events';

export default {
    name: 'Sales',
    components: {
        CustomTable,
        CustomInput,
        Pagination,
    },
    props: {
        searchParameter: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            brands: [],
            headers: [{
                label: 'Date',
                key: 'date',
                serviceKey: 'created_at',
                width: '20%',
            }, {
                label: 'Confirmation code',
                key: 'confirmationCode',
                serviceKey: 'confirmation_code',
                width: '35%',
            }, {
                label: 'Brand',
                key: 'brandName',
                serviceKey: 'brand_name',
                width: '35%',
            }],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            paramsObj: null,
            searchValue: this.searchParameter,
        };
    },
    computed: {
        dataGrid() {
            return this.brands;
        },
        getBaseUrl() {
            return location.href.split('?')[0];
        },
    },
    mounted() {
        const paramsStr = location.href.split('?')[1] || '';
        this.fetch(`${SALE_EVENTS_ENDPOINT}?${paramsStr}`);

        if (paramsStr) {
            this.paramsObj = getObjParamsFromStr(paramsStr);
        }
    },
    methods: {
        updateSearchValue(newValue) {
            this.searchValue = newValue;
        },
        handleSearch() {
            const search = this.searchValue ? this.searchValue : '';
            const currentParamsObj = { ...this.paramsObj, search, page: 1 };
            const paramsStr = getStrParamsFromObj(currentParamsObj);
            window.location.href = `${this.getBaseUrl}?${paramsStr}`;
        },
        handleSelectPage(page) {
            const currentParamsObj = { ...this.paramsObj, page };
            const paramsStr = getStrParamsFromObj(currentParamsObj);
            window.location.href = `${this.getBaseUrl}?${paramsStr}`;
        },
        denormalizeSales({ data: salesData}) {
            const brandsResult = salesData.map((sale) => ({
                date: sale.created_at,
                confirmationCode: sale.confirmation_code,
                brandName: sale.brand_name,
            }));

            return brandsResult;
        },
        renderSales(salesData) {
            this.brands = this.denormalizeSales(salesData);
            this.dataIsLoaded = true;
            this.activePage = salesData.current_page;
            this.numberPages = salesData.last_page;
        },
        async fetch(baseUrl) {
            this.dataIsLoaded = false;
            let brands = [];

            try {
                brands = await axios.get(baseUrl);
                this.renderSales(brands.data);
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
    .card-header {
        clear: none;
        float: left;
        padding-top: 0;
        padding-bottom: 0;
    }

    .sale-event-tab {
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
