<template>
  <div>
    <div class="row">
      <div
        class="col-8"
        :style="{paddingRight: 0}"
      >
        <breadcrumb
          :items="[
            {name: $t('ui.home'), url: '/'},
            {name: $t('ui.reports'), url: '/reports'},
            {name: title, url: mainUrl, active: true}
          ]"
        />
      </div>
      <div class="col-4 breadcrumb-right">
        <button
          v-if="$mq === 'sm'"
          class="navbar-toggler sidebar-minimizer float-right"
          type="button"
          @click="displaySearchBar = !displaySearchBar"
        >
          <i class="fa fa-bars" />
        </button>
      </div>
    </div>

    <div
      v-if="$mq !== 'sm' || displaySearchBar"
      class="page-buttons filter-bar-row"
    >
      <nav class="navbar navbar-light bg-light filter-navbar">
        <search-form 
          :on-submit="searchData"
          :initial-values="initialSearchValues"
          :hide-search-box="!searchOptions.search"
          :include-vendor="searchOptions.vendors"
          :include-language="searchOptions.language"
          :include-commodity="searchOptions.commodity"
          :include-state="searchOptions.state"
          :include-brand="searchOptions.brand"
        >
          <div class="form-group pull-right m-0">
            <a 
              v-if="showExportButton" 
              :href="exportUrl" 
              class="btn btn-primary m-0"
              :class="{'disabled': !events.length}"
            ><i class="fa fa-download" /> 
              {{ $t('ui.data_export') }}
            </a>
          </div>
        </search-form>
      </nav>
    </div>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <br class="clearfix"><br>
        <div class="card mt-4">
          <div class="card-header">
            <i class="fa fa-th-large" /> {{ title }}
          </div>
          <div class="card-body p-1">
            <div 
              v-if="hasFlashMessage" 
              class="alert alert-success"
            >
              <span class="fa fa-check-circle" /> <em>{{ flashMessage }}</em>
            </div>
            <custom-table
              :headers="headers"
              :data-grid="events"
              :data-is-loaded="dataIsLoaded"
              :total-records="totalRecords"
              :empty-table-message="$t('ui.no_events')"
              :no-bottom-padding="true"
              :has-action-buttons="viewLink !== ''"
              :show-action-buttons="viewLink !== ''"
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

import { formArrayQueryParam } from 'utils/stringHelpers';
import { replaceFilterBar } from 'utils/domManipulation';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'GenericReport',
    components: {
        CustomTable,
        Pagination,
        SearchForm,
        Breadcrumb,
    },
    props: {
        searchOptions: {
            type: Object,
            default() {
                return {
                    search: false,
                    vendors: false,
                    commodity: false,
                    language: false,
                    state: false,
                    brand: false,
                };
            },
        },
        title: {
            type: String,
            required: true,
        },
        mainUrl: {
            type: String,
            required: true,
        },
        reportUrl: {
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
        startDateParameter: {
            type: String,
            default: function() {
                return this.$moment().subtract(1, 'd').format('YYYY-MM-DD');
            },
        },
        endDateParameter: {
            type: String,
            default: function() {
                return this.$moment().format('YYYY-MM-DD');
            },
        },

        hiddenColumns: {
            type: Array,
            default: () => [],
        },

        directionParameter: {
            type: String,
            default: '',
        },
        pageParameter: {
            type: String,
            default: '',
        },
        vendorParameter: {
            type: Array,
            default: () => null,
        },
        languageParameter: {
            type: Array,
            default: () => null,
        },
        commodityParameter: {
            type: Array,
            default: () => null,
        },
        tableHasActions: {
            type: Boolean,
            default: false,
        },
        hasFlashMessage: {
            type: Boolean,
            default: false,
        },
        flashMessage: {
            type: String,
            default: null,
        },
        showExportButton: {
            type: Boolean,
            value: false,
        },
        hasActionButtons: {
            type: Boolean,
            default: true,
        },
        viewLink: {
            type: String,
            default() {
                return '';
            },
        },
    },
    data() {
        return {
            totalRecords: 0,
            events: [],
            headers: [],
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            displaySearchBar: false,
            
        };
    },
    computed: {
        realHiddenColumns() {
            if (this.viewLink === '') {
                return this.hiddenColumns;
            }
            const out = [];
            for (let i = 0, len = this.hiddenColumns.length; i < len; i += 1) {
                out.push(this.hiddenColumns[i]);
            }
            out.push('buttons');
            out.push('startDate');
            out.push('endDate');
            return out;
        },
        exportUrl() {
            return `${this.reportUrl}?${this.filterParams}&csv=true`;
        },
        sortParams() {
            return !!this.columnParameter && !!this.directionParameter
                ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        },
        filterParams() {
            return [
                this.startDateParameter ? `&startDate=${this.startDateParameter}` : '',
                this.endDateParameter ? `&endDate=${this.endDateParameter}` : '',
                this.searchParameter ? `&search=${this.searchParameter}` : '',
                this.languageParameter ? formArrayQueryParam('language', this.languageParameter) : '',
                this.commodityParameter ? formArrayQueryParam('commodity', this.commodityParameter) : '',
                this.vendorParameter ? formArrayQueryParam('vendor', this.vendorParameter) : '',
            ].join('');
        },
        initialSearchValues() {
            return {
                search: this.searchParameter,
                startDate: this.startDateParameter,
                endDate: this.endDateParameter,
                language: this.languageParameter,
                commodity: this.commodityParameter,
                vendor: this.vendorParameter,
            };
        },
    },
    mounted() {
        document.addEventListener('scroll', replaceFilterBar('breadcrumb', 'filter-bar-row', 'filter-bar-replaced'));

        const pageParam = this.pageParameter ? `&page=${this.pageParameter}` : '';

        axios.get(`${this.reportUrl}?${this.filterParams}${this.sortParams}${pageParam}`)
            .then((response) => {
                this.events = response.data.data.map((item) => {
                    if ('confirmation_code' in item && 'event_id' in item) {
                        item.confirmation_code = `<a href="/events/${item.event_id}">${item.confirmation_code}</a>`;
                    }
                    if (this.viewLink !== '') {
                        item.buttons = [];
                        item.buttons.push({
                            type: 'view',
                            url: this.getViewLink(item),
                        });
                    }
                    return item;
                });
                if (this.events.length > 0) {
                    const headers = Object.keys(this.events[0]);
                    
                    if (headers.includes('event_id')) {
                        headers.splice(headers.indexOf('event_id'), 1);
                    }
                    headers.forEach((element) => {
                        let type;
                        switch (element) {
                            case 'btn':
                            case 'account_number1':
                            case 'confirmation_code':
                                type = 'number';
                                break;
                            default:
                                if (element.endsWith('_at')) {
                                    type = 'date';
                                }
                                else {
                                    type = 'string';
                                }
                                break;
                        }
                        if (!this.realHiddenColumns.includes(element)) {
                            this.headers.push({
                                label: (element.charAt(0).toUpperCase() + element.slice(1)).replace(/_/g, ' '),
                                key: element,
                                serviceKey: element,
                                width: '10%',
                                sorted: NO_SORTED,
                                canSort: true,
                                type: type,
                            });
                        }
                    });

                    if (!!this.columnParameter && !!this.directionParameter) {
                        const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === this.columnParameter);
                        if (sortHeaderIndex >= 0 && sortHeaderIndex < this.headers.length) {
                            this.headers[sortHeaderIndex].sorted = this.directionParameter;
                        }
                    }
                }
                this.dataIsLoaded = true;
                this.numberPages = response.data.last_page,
                this.totalRecords = response.data.total;
            })
            .catch(console.log);
    },
    beforeDestroy() {
        document.removeEventListener('scroll', replaceFilterBar('breadcrumb', 'filter-bar-row', 'filter-bar-replaced'));
    },
    methods: {
        getViewLink(row) {
            row.startDate = this.startDateParameter;
            row.endDate = this.endDateParameter;
            const keys = Object.keys(row);
            let out = this.viewLink;
            for (let i = 0, len = keys.length; i < len; i += 1) {
                if (out.includes(`[${keys[i]}]`)) {
                    out = out.replace(`[${keys[i]}]`, row[keys[i]]);
                }
            }
            return out;
        },
        sortData(serviceKey, index) {
            console.log(this.headers[index].sorted);
            console.log(ASC_SORTED);
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `${this.mainUrl}?column=${serviceKey}&direction=${labelSort}${this.filterParams}`;
        },
        searchData({
            search, startDate, endDate, language, commodity, vendor,
        }) {
            const filterParams = [
                search ? `&search=${search}` : '',
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                language ? formArrayQueryParam('language', language) : '',
                commodity ? formArrayQueryParam('commodity', commodity) : '',
                vendor ? formArrayQueryParam('vendor', vendor) : '',
            ].join('');
            window.location.href = `${this.mainUrl}?${filterParams}${this.sortParams}`;
        },
        selectPage(page) {
            window.location.href = `${this.mainUrl}?page=${page}${this.sortParams}${this.filterParams}`;
        },
    },
};
</script>
