<template>
    <div>
        <breadcrumb
        :items="[
            {name: 'Home', url: '/'},
            {name: 'Quality Assurance', active: true},
            {name: 'Events', url: '/events', active: true},
        ]"
        />

        <div class="container-fluid mt-5">
            <div class="animated fadeIn">
                <div class="card">
                    <div class="card-header">
                        <span class="mt-1 pull-left"><i class="fa fa-th-large" /> Events</span>
                        <div class="pull-right">
                            <div class="form-inline">
                                <search-form 
                                    :on-submit="searchData"
                                    :initial-values="initialSearchValues"
                                    :search-fields="[
                                    {
                                        id: 'confirmation_code',
                                        name: 'Confirmation Code',
                                    },
                                    {
                                        id: 'phone_number',
                                        name: 'BTN',
                                    },
                                    {
                                        id: 'account_number',
                                        name: 'Account Number',
                                    },
                                    {
                                        id: 'tpv_name',
                                        name: 'TPV Name'
                                    },
                                    {
                                        id: 'sales_agent',
                                        name: 'Sales Agent'                                
                                    },
                                    {
                                        id: 'lead_record_id',
                                        name: 'Lead Record ID'                                
                                    },
                                    {
                                        id: 'monitored',
                                        name: 'Monitored (Y/N)'
                                    }
                                    ]"
                                    btn-label="Update Page"
                                    btn-icon="refresh"
                                    include-vendor
                                    include-brand
                                    include-channel
                                    include-language
                                    include-sale-type
                                >
                                <a
                                v-if="showExportButton"
                                :href="exportUrl"
                                class="btn btn-primary ml-2 mt-1"
                                :class="{'disabled': !events.length}"
                                >
                                    <i class="fa fa-download" aria-hidden="true" />
                                    Data Export
                                </a>
                                </search-form>
                            </div>
                        </div>
                        <br><br><br><br><br>
                        <div class="card events-card">
                            <div class="card-body p-1">
                                <div 
                                    v-if="hasFlashMessage" 
                                    class="alert alert-success"
                                    >
                                    <span class="fa fa-check-circle" />
                                    <em>{{ flashMessage }}</em>
                                </div>
                                <custom-table
                                    :headers="headers"
                                    :data-grid="events"
                                    :data-is-loaded="dataIsLoaded"
                                    :show-action-buttons="tableHasActions"
                                    :total-records="totalRecords"
                                    :has-action-buttons="hasActionButtons"
                                    empty-table-message="No events were found."
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
        </div>
    </div>
</template>

<script>

import { formArrayQueryParam } from 'utils/stringHelpers';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

import { mapState } from 'vuex';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'Events',
    components: {
        CustomTable,
        Pagination,
        SearchForm,
        Breadcrumb,
    },
    props: {
        searchParameter: {
            type: String,
            default: '',
        },
        searchFieldParameter: {
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
        channelParameter: {
            type: Array,
            default: () => null,
        },
        brandParameter: {
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
        saleTypeParameter: {
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
    },
    data() {
        return {
            totalRecords: 0,
            events: [],
            headers: [{
                align: 'center',
                label: 'Monitored',
                icon: 'headphones',
                key: 'monitored',
                serviceKey: 'monitored',
                width: '20px',
                sorted: NO_SORTED,
            }, {
                label: 'Date',
                key: 'date',
                serviceKey: 'created_at',
                width: '160px',
                sorted: NO_SORTED,
            }, {
                align: 'center',
                label: 'Confirmation',
                key: 'confirmation',
                serviceKey: 'confirmation_code',
                width: '120px',
                sorted: NO_SORTED,
            }, {
                align: 'center',
                label: 'Channel',
                key: 'channel',
                serviceKey: 'channel',
                width: '110px',
                sorted: NO_SORTED,
            }, {
                label: 'Brand',
                key: 'brand',
                serviceKey: 'brand_name',
                width: '115px',
                sorted: NO_SORTED,
            }, {
                label: 'Vendor',
                key: 'vendor',
                serviceKey: 'vendor_name',
                width: '115px',
                sorted: NO_SORTED,
            }, {
                label: 'Sales Agent',
                key: 'agent',
                serviceKey: 'last_name',
                width: '115px',
                sorted: NO_SORTED,
            }, {
                label: 'TPV Name',
                key: 'tpv_name',
                serviceKey: 'tpv_agent_name',
                width: '115px',
                sorted: NO_SORTED,
            }, {
                align: 'center',
                label: 'EzTPV',
                key: 'eztpv',
                serviceKey: 'eztpv',
                width: '20px',
                sorted: NO_SORTED,
            }, {
                label: 'BTN',
                key: 'btn',
                serviceKey: 'phone_number',
                width: '125px',
                sorted: NO_SORTED,
            }, {
                align: 'center',
                label: 'Latest Result',
                key: 'latest_result',
                serviceKey: 'latest_result',
                width: '15%',
                sorted: NO_SORTED,
            }],
            searchValue: this.searchParameter,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
        };
    },
    computed: {
        exportUrl() {
            return `/list/events?${this.filterParams}${this.sortParams}&export=csv`;
        },
        sortParams() {
            return !!this.columnParameter && !!this.directionParameter
                ? `&column=${this.columnParameter}&direction=${this.directionParameter}` : '';
        },
        pageParams() {
            return this.activePage ? `&page=${this.activePage}` : '';
        },
        filterParams() {
            return [
                this.startDateParameter ? `&startDate=${this.startDateParameter}` : '',
                this.endDateParameter ? `&endDate=${this.endDateParameter}` : '',
                this.searchFieldParameter ? `&searchField=${this.searchFieldParameter}` : '',
                this.searchParameter ? `&search=${this.searchParameter}` : '',
                this.channelParameter ? formArrayQueryParam('channel', this.channelParameter) : '',
                this.brandParameter ? formArrayQueryParam('brandId', this.brandParameter) : '',
                this.languageParameter ? formArrayQueryParam('language', this.languageParameter) : '',
                this.commodityParameter ? formArrayQueryParam('commodity', this.commodityParameter) : '',
                this.vendorParameter ? formArrayQueryParam('vendor', this.vendorParameter) : '',
                this.saleTypeParameter ? formArrayQueryParam('saleType', this.saleTypeParameter) : '',
            ].join('');
        },
        initialSearchValues() {
            return {
                search: this.searchParameter,
                startDate: this.startDateParameter,
                endDate: this.endDateParameter,
                channel: this.channelParameter,
                brand: this.brandParameter,
                searchField: this.searchFieldParameter,
                language: this.languageParameter,
                commodity: this.commodityParameter,
                vendor: this.vendorParameter,
                saleType: this.saleTypeParameter,
            };
        },
    },
    mounted() {
        const pageParam = this.pageParameter ? `&page=${this.pageParameter}` : '';

        if (!!this.columnParameter && !!this.directionParameter) {
            const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === this.columnParameter);
            this.headers[sortHeaderIndex].sorted = this.directionParameter;
        }

        axios.get(`/list/events?${pageParam}${this.filterParams}${this.sortParams}`)
            .then((response) => {
                this.events = response.data.data.map(this.getEventObject);
                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
                this.totalRecords = response.data.total;
            })
            .catch(console.log);
    },
    methods: {
        getEventObject(event) {
            const phoneArray = !!event.btn 
                        && event.btn.replace('+1', '').split('');
            phoneArray && phoneArray.splice(3, 0, '-') && phoneArray.splice(7, 0, '-');
            const btn = phoneArray ? phoneArray.join('') : '--';

            switch (event.result) {
                case 'Sale':
                    var result = '<span class=\'badge tpv-blue\'>Good Sale</span>';
                    break;
                case 'No Sale':
                    var result = '<span class=\'badge tpv-orange\'>No Sale</span>';
                    break;
                case 'Closed':
                    var result = '<span class=\'badge badge-secondary\'>Closed</span>';
                    break;
                default:
                    var result = '<span class=\'badge badge-light\'>In Progress</span>';
            }

            return {
                id: event.event_id,
                monitored: event.monitored,
                date: event.event_created_at,
                confirmation: event.confirmation_code || '--',
                channel: event.channel || '--',
                brand: event.brand_name || '--',
                vendor: event.vendor_name || '--',
                agent: event.sales_agent_name || '--',
                tpv_name: event.tpv_agent_name || '--',
                btn: btn,
                latest_result: result,
                eztpv: `
                        <i
                            class="fa fa-${event.eztpv_initiated ? 'check' : 'times'} text-${event.eztpv_initiated ? 'success' : 'secondary'}"
                            aria-hidden="true"
                        ></i>
                    `,
                buttons: [{
                    type: 'view',
                    label: 'View',
                    url: `/events/${event.event_id}`,
                    buttonSize: 'medium',
                }],
            };
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `/events?column=${serviceKey}&direction=${labelSort}${this.filterParams}${this.pageParams}`;
        },
        searchData({
            search, startDate, endDate, channel, brand, searchField, language, commodity, vendor, saleType,
        }) {
            const filterParams = [
                searchField ? `&searchField=${searchField}` : '',
                search ? `&search=${search}` : '',
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                channel ? formArrayQueryParam('channel', channel) : '',
                brand ? formArrayQueryParam('brandId', brand) : '',
                language ? formArrayQueryParam('language', language) : '',
                commodity ? formArrayQueryParam('commodity', commodity) : '', 
                vendor ? formArrayQueryParam('vendor', vendor) : '',
                saleType ? formArrayQueryParam('saleType', saleType) : '',
            ].join('');
            window.location.href = `/events?${filterParams}${this.sortParams}`;
        },
        selectPage(page) {
            window.location.href = `/events?page=${page}${this.sortParams}${this.filterParams}`;
        },
    },
};
</script>

<style>
.breadcrumb{
    margin-bottom: 0.5rem;
}

.filter-navbar{
    box-shadow: 10px 10px 5px -5px rgba(184,173,184,1);
}

.filter-bar-row {
    z-index: 1000;
}

.tpv-blue {
    background-color: #0077c8;
    color: #FFFFFF;
}

.tpv-orange {
    background-color: #ed8b00;
    color: #FFFFFF;
}
</style>
