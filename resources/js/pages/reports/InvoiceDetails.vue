<template>
  <div>
    <div class="row">
      <div
        class="col-8"
        :style="{paddingRight: 0}"
      >
        <breadcrumb
          :items="[
            {name: 'Home', url: '/'},
            {name: 'Reports', url: '/reports'},
            {name: 'Invoice Details', url: '/reports/invoice_details', active: true}
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
          :hide-search-box="true"
        >
          <div class="form-group pull-right m-0">
            <a
              :href="exportUrl"
              class="btn btn-primary m-0"
              :class="{'disabled': !invoiceDetails.length}"
            ><i
              class="fa fa-download"
              aria-hidden="true"
            /> Export Data</a>
          </div>
        </search-form>
      </nav>
    </div>

    <div class="container-fluid">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card mt-4">
          <div class="card-header">
            <i class="fa fa-th-large" /> Invoice Details Report
          </div>
          <div class="card-body">
            <div
              v-if="hasFlashMessage"
              class="alert alert-success"
            >
              <span class="fa fa-check-circle" />
              <em>{{ flashMessage }}</em>
            </div>
            <div class="table-responsive">
              <custom-table
                :headers="headers"
                :data-grid="invoiceDetails"
                :data-is-loaded="dataIsLoaded"
                :total-records="totalRecords"
                empty-table-message="No invoice details were found."
                @sortedByColumn="sortData"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { hasProperties } from 'utils/helpers';
import { arraySort } from 'utils/arrayManipulation';
import CustomTable from 'components/CustomTable';
import SearchForm from 'components/SearchForm';
import Breadcrumb from 'components/Breadcrumb';

import { mapState } from 'vuex';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'InvoiceDetails',
    components: {
        CustomTable,
        SearchForm,
        Breadcrumb,
    },
    data() {
        return {
            totalRecords: 0,
            invoiceDetails: [],
            headers: (() => {
            const commoninvoiceHeader = {
                align: 'center',
                width: '12.5%',
                canSort: true,
                sorted: NO_SORTED,
                type: 'number'
            };
            return [
                  { label: 'Brand', key: 'brand_name', serviceKey: 'brand_name', ...commoninvoiceHeader },
                  { label: 'Bill Date', key: 'invoice_bill_date', serviceKey: 'invoice_bill_date', ...commoninvoiceHeader },
                  { label: 'Start Date', key: 'invoice_start_date', serviceKey: 'invoice_start_date', ...commoninvoiceHeader },
                  { label: 'End Date', key: 'invoice_end_date', serviceKey: 'invoice_end_date', ...commoninvoiceHeader },
                  { label: 'Due Date', key: 'invoice_due_date', serviceKey: 'invoice_due_date', ...commoninvoiceHeader },
                  { label: 'Account #', key: 'account_number', serviceKey: 'account_number', ...commoninvoiceHeader },
                  { label: 'Invoice #', key: 'invoice_number', serviceKey: 'invoice_number', ...commoninvoiceHeader },
                  { label: 'Quantity', key: 'quantity', serviceKey: 'quantity', ...commoninvoiceHeader },
                  { label: 'Invoice Desc ID', key: 'invoice_desc_id', serviceKey: 'invoice_desc_id', ...commoninvoiceHeader },
                  { label: 'Rate', key: 'rate', serviceKey: 'rate', ...commoninvoiceHeader },
                  { label: 'Note', key: 'note', serviceKey: 'note', ...commoninvoiceHeader },
                  { label: 'Total', key: 'total', serviceKey: 'total', ...commoninvoiceHeader }
            ];
            })(),
            dataIsLoaded: false,
            activePage: 1,
            numberPages: 1,
            displaySearchBar: false,
            column: this.getParams().column,
            direction: this.getParams().direction,
        };
    },
    computed: {
        ...mapState({
            hasFlashMessage: (state) => hasProperties(state, 'session.flash_message') && state.session.flash_message,
            flashMessage: (state) => state.session.flash_message,
        }),
        sortParams() {
            return !!this.column && !!this.direction
                ? `&column=${this.column}&direction=${this.direction}`
                : '';
        },
        filterParams() {
            return [
                this.getParams().startDate ? `&startDate=${this.getParams().startDate}` : '',
                this.getParams().endDate ? `&endDate=${this.getParams().endDate}` : '',
            ].join('');
        },
        initialSearchValues() {
            return {
                startDate: this.getParams().startDate,
                endDate: this.getParams().endDate,
            };
        },
        exportUrl() {
            return `/reports/list_invoice_details?${this.filterParams}${this.sortParams}&csv=true`;
        },
    },
    mounted() {
        const params = this.getParams(); 
        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.serviceKey === params.column,
            );
            this.headers[sortHeaderIndex].sorted = params.direction;
        }
        axios
            .get(`/reports/list_invoice_details?${this.filterParams}${this.sortParams}`)
            .then((response) => {
                const details = [];
                response.data.forEach((event) => {
                    details.push(this.getObject(event));
                });

                this.invoiceDetails = details;
                this.dataIsLoaded = true;
                this.totalRecords = details.length;
            })
            .catch(console.log);
    },
    methods: {
        parseFloat(x) {
            return (Number.parseFloat(x)) ? Number.parseFloat(x).toFixed(2) : x;
        },
        getObject(details) {
            return {
                id: details.id,
                brand_id: details.brand_id,
                brand_name: details.brand_name,
                invoice_bill_date: details.invoice_bill_date,
                invoice_start_date: details.invoice_start_date,
                invoice_end_date: details.invoice_end_date,
                invoice_due_date: details.invoice_due_date,
                account_number: details.account_number,
                invoice_number: details.invoice_number,
                quantity: details.quantity,
                invoice_desc_id: details.invoice_desc_id,
                rate: details.rate,
                note: details.note,
                total: details.total,
            };
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.invoiceDetails = arraySort(this.headers, this.invoiceDetails, serviceKey, index);

            // Updating values for page render and export
            this.column = serviceKey;
            this.direction = this.headers[index].sorted;
        },
        searchData({ startDate, endDate }) {
            const filterParams = [
                startDate ? `&startDate=${startDate}` : '',
                endDate ? `&endDate=${endDate}` : '',
            ].join('');
            window.location.href = `/reports/invoice_details?${filterParams}${this.sortParams}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const startDate = url.searchParams.get('startDate')
        || this.$moment().subtract(1, 'd').format('YYYY-MM-DD');
            const endDate = url.searchParams.get('endDate') || this.$moment().format('YYYY-MM-DD');
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';

            return {
                startDate,
                endDate,
                column,
                direction,
            };
        },
    },
};
</script>
