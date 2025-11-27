<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Support', url: '/support', active: false},
        {name: 'Indra Audit', url: '/support/indra_audit', active: true},
      ]"
    />
    <div class="container-fluid mt-4">
      <div class="animated fadeIn">
        <div class="row page-buttons filter-bar-row">
          <div class="col-md-12">
            <nav class="navbar navbar-light bg-light filter-navbar">
              <search-form 
                :on-submit="searchData"
                :initial-values="initialSearchValues"
                :search-fields="[
                  {
                    id: 'confirmation_code',
                    name: 'Confirmation Code',
                  },
                  {
                    id: 'event_id',
                    name: 'Event ID',
                  },
                  {
                    id: 'reviewed',
                    name: 'Reviewed? (y/n)',
                  },
                  {
                    id: 'sent_to_queue',
                    name: 'Sent to Queue? (y/n)',
                  },
                  {
                    id: 'good_or_bad',
                    name: 'Good or Bad? (g/b)',
                  }
                ]"
                btn-label="Update Page"
                btn-icon="refresh"
                include-brand
              >
                <a
                  v-if="showExportButton"
                  :href="exportUrl"
                  class="btn btn-primary m-0"
                  :class="{'disabled': !events.length}"
                >
                  <i
                    class="fa fa-download"
                    aria-hidden="true"
                  /> 
                  Data Export
                </a>
              </search-form>
            </nav>
          </div>
        </div>
        <br class="clearfix"><br>

        <div class="card events-card">
          <div class="card-header d-flex flex-row align-items-center">
            <i class="fa fa-th-large" /> Audit Logs
            <div class="col"></div>
            <!-- <input class="mr-2" type="checkbox" v-model="checkFileSize">
                <span>Check FileSize</span>
            </input> -->
            <button class="btn btn-primary mt-1" @click="exportCSV()"><i class="fa fa-download" /> Export CSV</button>
          </div>
          <div class="card-body p-1">
            <div 
              v-if="hasFlashMessage" 
              class="alert alert-success"
            >
              <span class="fa" :class="flashIcon"/>
              <em>{{ flashMessage }}</em>
            </div>
            <div 
              v-if="hasErrorMessage" 
              class="alert alert-danger"
            >
              <span class="fa fa-times" />
              <em>{{ errorMessage }}</em>
            </div>
            <custom-table
              :headers="headers"
              :data-grid="events"
              :data-is-loaded="dataIsLoaded"
              :total-records="totalRecords"
              has-action-buttons
              show-action-buttons
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

        <div
            id="updateEvent"
            class="modal fade"
            data-backdrop="false"
            tabindex="-1"
            role="dialog"
            aria-labelledby="updateEventLabel"
            aria-hidden="true"
        >
            <div
                class="modal-dialog modal-lg"
                role="document"
            >
                <div class="modal-content">
                    <div class="modal-header">
                        <h5
                            id="updateEventLabel"
                            class="modal-title"
                        >
                            Update Status
                        </h5>
                        <button
                            type="button"
                            class="close"
                            data-dismiss="modal"
                            aria-label="Close"
                        >
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form>
                            <div>
                                <label style="width: 100px">Good or bad?</label>
                                <input type='radio' name="good_or_bad" v-model="modalGoodOrBad" value='1'>Good</input>
                                <input type='radio' name="good_or_bad" v-model="modalGoodOrBad" value='0'>Bad</input>
                            </div>
                            <div>
                                <label style="width: 100px">Comment</label>
                                <textarea style="width: 100%; height: 200px" v-model="modalComment"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button 
                            type="button" 
                            class="btn btn-secondary" 
                            @click="saveEvent()"
                        >
                            <i
                                class="fa fa-save"
                                aria-hidden="true"
                            /> 
                            Save
                        </button>
                        <button 
                            type="button" 
                            class="btn btn-secondary" 
                            data-dismiss="modal"
                        >
                            <i
                                class="fa fa-times-circle"
                                aria-hidden="true"
                            /> 
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>

import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Pagination from 'components/Pagination';
import Breadcrumb from 'components/Breadcrumb';

import { mapState } from 'vuex';
import { formArrayQueryParam } from 'utils/stringHelpers';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'IndraAudit',
    components: {
        CustomTable,
        Pagination,
        SearchForm,
        Breadcrumb,
    },
    props: {
        brandParameter: {
            type: Array,
            default: () => null,
        },
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
        // languageParameter: {
        //     type: Array,
        //     default: () => null,
        // },
        // vendorParameter: {
        //     type: Array,
        //     default: () => null,
        // },
        // saleTypeParameter: {
        //     type: Array,
        //     default: () => null,
        // },
        tableHasActions: {
            type: Boolean,
            default: false,
        },
        hasErrorMessage: {
            type: Boolean,
            default: false,
        },
        errorMessage: {
            type: String,
            default: null,
        },
        // hasFlashMessage: {
        //     type: Boolean,
        //     default: false,
        // },
        // flashMessage: {
        //     type: String,
        //     default: null,
        // },
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
                label: 'Reviewed?',
                // icon: 'check',
                key: 'reviewed',
                serviceKey: 'reviewed',
                width: '20px',
                sorted: NO_SORTED,
                // canSort: true
            }, {
                label: 'Brand',
                key: 'brand_name',
                serviceKey: 'brand_name',
                width: '160px',
                sorted: NO_SORTED,
                // canSort: true
            }, {
                label: 'Date',
                key: 'event_created_at',
                serviceKey: 'event_created_at',
                width: '160px',
                sorted: NO_SORTED,
                // canSort: true
            }, {
                align: 'left',
                label: 'Event ID',
                key: 'event_id',
                serviceKey: 'event_id',
                width: '160px',
                sorted: NO_SORTED,
                // canSort: true
            }, {
                align: 'center',
                label: 'Confirmation',
                key: 'confirmation_code',
                serviceKey: 'confirmation_code',
                width: '120px',
                sorted: NO_SORTED,
                // canSort: true
            }, {
                align: 'left',
                label: 'Contracts',
                key: 'contracts',
                serviceKey: 'contracts',
                width: '200px',
                sorted: NO_SORTED,
            }, {
                label: 'Source',
                key: 'source',
                serviceKey: 'source',
                width: '100px',
                sorted: NO_SORTED,
                // canSort: true
            }, {
                label: 'Commodity Type',
                key: 'commodity_type',
                serviceKey: 'commodity_type',
                width: '120px',
                sorted: NO_SORTED,
                // canSort: true
            }, {
                label: 'Commodity',
                key: 'commodity',
                serviceKey: 'commodity',
                width: '120px',
                sorted: NO_SORTED,
            },/* {
                label: 'File Size',
                key: 'filesize',
                serviceKey: 'filesize',
                width: '120px',
                sorted: NO_SORTED,
            }, {
                label: 'Contracts Valid?',
                key: 'contracts_valid',
                serviceKey: 'contracts_valid',
                width: '40px',
                sorted: NO_SORTED,
                // canSort: true
            },*/ {
                label: 'Sent to Queue?',
                key: 'sent_to_queue',
                serviceKey: 'sent_to_queue',
                width: '40px',
                sorted: NO_SORTED,
                // canSort: true
            }],
            searchValue: this.searchParameter,
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            checkFileSize: 1,
            flashIcon: "fa-check-circle",
            flashMessage: "",
            flashMessageClass: "alert-warning",

            showUpdateModal: false,
            currentEventId: '',
            modalGoodOrBad: null,
            modalComment: ''
        };
    },
    computed: {
        hasFlashMessage() {
          return this.flashMessage.trim() != "";
        },
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
                this.brandParameter ? formArrayQueryParam('brandId', this.brandParameter) : '',
                this.startDateParameter ? `&startDate=${this.startDateParameter}` : '',
                this.endDateParameter ? `&endDate=${this.endDateParameter}` : '',
                this.searchFieldParameter ? `&searchField=${this.searchFieldParameter}` : '',
                this.searchParameter ? `&search=${this.searchParameter}` : '',
                // this.checkFileSize ? `&checkFileSize=${1}` : '',
            ].join('');
        },
        initialSearchValues() {
            return {
                brand: this.brandParameter,
                search: this.searchParameter,
                startDate: this.startDateParameter,
                endDate: this.endDateParameter,
                searchField: this.searchFieldParameter,
            };
        },
    },
    mounted() {
        const pageParam = this.pageParameter ? `&page=${this.pageParameter}` : '';

        if (!!this.columnParameter && !!this.directionParameter) {
            const sortHeaderIndex = this.headers.findIndex((header) => header.serviceKey === this.columnParameter);
            this.headers[sortHeaderIndex].sorted = this.directionParameter;
        }

        axios.get(`/support/indra_audit/list?${pageParam}${this.filterParams}${this.sortParams}`)
            .then((response) => {
                this.events = Object.values(response.data.data).map(e => ({
                    ...e,
                    _event_id: e.event_id,
                    event_id: `<a href="https://clients.tpvhub.com/events/${e.event_id}" target="_blank">${e.event_id}</a>`,
                    reviewed: e.reviewed ? (e.good_or_bad ? '<span class=\'badge tpv-blue\'>GOOD</span>' : '<span class=\'badge tpv-orange\'>BAD</span>') : '',
                    contracts: e.contracts ? e.contracts.split(",").map(c => `<a href="https://tpv-live.s3.amazonaws.com/${c}">${c.split('/').at(-1)}</a>`).join('<br/>') : '',
                    // filesize: e.filesize.replaceAll(",", "<br/>"),
                    // contracts_valid: e.contracts_valid ? '<span class=\'badge tpv-blue\'>YES</span>' : '<span class=\'badge tpv-orange\'>NO</span>',
                    sent_to_queue: e.sent_to_queue ? '<span class=\'badge tpv-blue\'>YES</span>' : '<span class=\'badge tpv-orange\'>NO</span>',
                    buttons: [
                        {
                            type: 'custom',
                            classNames: 'btn btn-success pointer-cursor',
                            label: 'Update',
                            url: '',
                            onClick: () => this.checkRow(e.event_id)
                        },
                    ],
                }));

                this.dataIsLoaded = true;
                this.activePage = response.data.current_page;
                this.numberPages = response.data.last_page;
                this.totalRecords = response.data.total;
            })
            .catch(console.log);
    },
    methods: {
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            window.location.href = `/support/indra_audit?column=${serviceKey}&direction=${labelSort}${this.filterParams}${this.pageParams}`;
        },
        clearMessages() {
            this.flashIcon = "fa-check-circle";
            this.flashMessage = "";
            this.flashMessageClass = "alert-warning";
        },
        showMessage(msg, type) {
            // set defaults, for 'success' type and in case of invalid 'type' values.
            this.flashIcon = "fa-check-circle";
            this.flashMessageClass = "alert-success";

            if(type === "warning") {
                this.flashIcon = "fa-exclamation-triangle";
                this.flashMessageClass = "alert-warning";
            } else if(type === "error") {
                this.flashIcon = "fa-exclamation-circle";
                this.flashMessageClass = "alert-danger";
            }

            this.flashMessage = msg;

            setTimeout(() => this.clearMessages(), 3000);
        },
        checkRow(event_id) {
            const event = this.events[this.events.findIndex(e => e._event_id == event_id)];
            this.currentEventId = event._event_id || event.event_id;
            this.modalGoodOrBad = event.reviewed ? event.good_or_bad : null;
            this.modalComment = event.comment || '';

            $('#updateEvent').modal('toggle');
        },
        saveEvent() {
            axios.post(`/support/indra_audit/reviewed/${this.currentEventId}`, {
                good_or_bad: this.modalGoodOrBad,
                comment: this.modalComment || ''
            })
                .then(res => {
                    this.events = this.events.map(e => (e._event_id != this.currentEventId ? e : {
                        ...e,
                        reviewed: (this.modalGoodOrBad == '1' ? '<span class=\'badge tpv-blue\'>GOOD</span>' : '<span class=\'badge tpv-orange\'>BAD</span>'),
                        good_or_bad: this.modalGoodOrBad,
                        comment: this.modalComment
                    }));

                    this.showMessage("Successfully reviewed the event.");
                })
                .catch(() => this.showMessage("Failed to check the event.", "error"))
                .finally(() => {
                    $('#updateEvent').modal('hide');
                    this.currentEventId = '';
                    this.comment = '';
                    this.modalGoodOrBad = null;
                })
        },
        searchData({
            search, startDate, endDate, brand, searchField
        }) {
            const filterParams = [
                brand ? formArrayQueryParam('brandId', brand) : '',
                searchField ? `&searchField=${searchField}` : '',
                search ? `&search=${search}` : '',
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
                // this.checkFileSize ? `&checkFileSize=${1}` : '',
            ].join('');
            window.location.href = `/support/indra_audit?${filterParams}${this.sortParams}`;
        },
        selectPage(page) {
            window.location.href = `/support/indra_audit?page=${page}${this.sortParams}${this.filterParams}`;
        },
        exportCSV() {
            window.location.href = `/support/indra_audit/list?export=true${this.sortParams}${this.filterParams}`;
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

.pointer-cursor {
    cursor: pointer;
}

.modal {
    margin-top: 100px;
}
</style>
