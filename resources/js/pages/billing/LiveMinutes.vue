<template>
  <div>
    <br><br>
    <div class="container-fluid">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <em class="fa fa-th-large" /> Live Minutes
          </div>
          <div class="card-body">
            <a :href="exportUrl" class="btn btn-primary m-0"><i class="fa fa-download" aria-hidden="true" /> Data Export</a>
            <custom-table
              :headers="headers"
              :data-grid="live_minutes"
              :total-records="totalRecords"
              data-is-loaded
              @sortedByColumn="sortData"
            />
            <pagination
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
import CustomTable from 'components/CustomTable';
import Pagination from 'components/Pagination';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'InvoiceLiveMinutes',
    components: {
        CustomTable,
        Pagination,
    },
    props: {
        items: {
            type: Array,
            default: () => [],
        },
        invoice: {
            type: Object,
            default: () => ({}),
        },
        activePage: {
            type: Number,
            default: 1,
        },
        numberPages: {
            type: Number,
            default: 1,
        },
        totalRecords: {
            type: Number,
            default: 0,
        },
        sortBy: {
            type: String,
            default: 'interaction_created_at',
        },
        sortDir: {
            type: String,
            default: 'desc',
        },
    },
    data() {
        return {
            headers: [
                {
                    label: 'Confirmation Code',
                    key: 'confirmation_code',
                    serviceKey: 'confirmation_code',
                    index: 0,
                    canSort: true,
                    width: '5%',
                },
                {
                    label: 'Created Date',
                    key: 'created_at',
                    serviceKey: 'created_at',
                    index: 1,
                    canSort: true,
                    width: '5%',
                },
                {
                    label: 'Channel',
                    key: 'channel',
                    serviceKey: 'channel',
                    index: 2,
                    canSort: true,
                    width: '5%',
                },
                {
                    label: 'Products',
                    key: 'commodity',
                    serviceKey: 'commodity',
                    index: 3,
                    canSort: true,
                    width: '5%',
                },
                {
                    label: 'Product Time',
                    key: 'product_time',
                    serviceKey: 'product_time',
                    index: 4,
                    canSort: true,
                    width: '5%',
                },
                {
                    label: 'Total Time',
                    key: 'interaction_time',
                    serviceKey: 'interaction_time',
                    index: 5,
                    canSort: true,
                    width: '5%',
                },
                {
                    label: 'Vendor',
                    key: 'vendor_name',
                    serviceKey: 'vendor_name',
                    index: 6,
                    canSort: true,
                    width: '5%',
                },
                {
                    label: 'Sales Rep',
                    key: 'sales_agent_name',
                    serviceKey: 'sales_agent_name',
                    index: 7,
                    canSort: true,
                    width: '5%',
                },
                {
                    label: 'Rep ID',
                    key: 'sales_agent_rep_id',
                    serviceKey: 'sales_agent_rep_id',
                    canSort: true,
                    index: 8,
                    width: '5%',
                },
                {
                    label: 'Interaction Type',
                    key: 'interaction_type',
                    serviceKey: 'interaction_type',
                    canSort: true,
                    index: 9,
                    width: '5%',
                },
                {
                    label: 'Result',
                    key: 'result',
                    serviceKey: 'result',
                    canSort: true,
                    index: 10,
                    width: '5%',
                },
            ],
        };
    },
    computed: {
        exportUrl() {
            return `/invoices/${this.invoice.id}/live_minutes_export`;
        },
        live_minutes() {
            return this.items.map((item) => ({
                ...item,
                confirmation_code: `<a href="/events/${item.event_id}" target="_blank">${item.confirmation_code}</a>`,
                result: item.result || '--',
                created_at: this.$moment(item.created_at).format('MM/DD/YYYY'),
                vendor_name: item.vendor_name || '--',
                product_time: item.product_time.toFixed(2),
                interaction_time: item.interaction_time.toFixed(2),
            }));
        },
    },
    methods: {
        selectPage(page) {
            window.location.href = `/invoices/${this.invoice.id}/live_minutes_view?page=${page}`;
        },
        sortData(serviceKey, index) {
            const labelSort = this.sortDir === ASC_SORTED ? DESC_SORTED : ASC_SORTED;
            window.location.href = `/invoices/${this.invoice.id}/live_minutes_view?column=${serviceKey}&direction=${labelSort}`;
        },
    },
};
</script>
