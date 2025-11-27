<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Legacy Portal', url: '/reports/legacy_portal', active: true}
      ]"
    />

    <div class="page-buttons filter-bar-row">
      <nav class="navbar navbar-light bg-light filter-navbar">
        <div class="search-form form-inline pull-left">
          <div class="form-group">
            <label for="confirmation_number_search" />
            <input
              id="confirmation_number_search"
              ref="confirmation_number_search"
              class="form-control"
              placeholder="Confirmation Number"
              autocomplete="off"
              name="confirmation_number_search"
              type="text"
              :value="getParams().confirmation_number_search"
            >
          </div>
          <div class="form-group">
            <label for="btn_search" />
            <input
              id="btn_search"
              ref="btn_search"
              class="form-control"
              placeholder="BTN Only Digits"
              autocomplete="off"
              name="btn_search"
              type="text"
              :value="getParams().btn_search"
            >
          </div>
          <div class="form-group">
            <label for="account_search" />
            <input
              id="account_search"
              ref="account_search"
              class="form-control"
              placeholder="Account Number"
              autocomplete="off"
              name="account_search"
              type="text"
              :value="getParams().account_search"
            >
          </div>
          <div class="form-group">
            <label for="firstname_search" />
            <input
              id="firstname_search"
              ref="firstname_search"
              class="form-control"
              placeholder="First Name"
              autocomplete="off"
              name="firstname_search"
              type="text"
              :value="getParams().firstname_search"
            >
          </div>
          <div class="form-group">
            <label for="lastname_search" />
            <input
              id="lastname_search"
              ref="lastname_search"
              class="form-control"
              placeholder="Last Name"
              autocomplete="off"
              name="lastname_search"
              type="text"
              :value="getParams().lastname_search"
            >
          </div>
          <div class="form-group">
            <label for="postalcode_search" />
            <input
              id="postalcode_search"
              ref="postalcode_search"
              class="form-control"
              placeholder="Service Zip"
              autocomplete="off"
              name="postalcode_search"
              type="text"
              :value="getParams().postalcode_search"
            >
          </div>
          <search-form
            :on-submit="searchData"
            :initial-values="initialSearchValues"
            :hide-search-box="true"
            include-date-range
            include-brand
            include-vendor
            include-language
          />
        </div>
      </nav>
    </div>

    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <br class="clearfix">
        <br>
        <div class="card mt-4">
          <div class="card-header">
            <i class="fa fa-th-large" /> Recordings
          </div>
          <div class="row mt-5 p-3">
            <div class="col-md-12">
              <div class="table-responsive">
                <p
                  v-if="totalRecords"
                  align="right"
                >
                  Total Records: {{ totalRecords }}
                </p>
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th
                        v-for="h in headers"
                        :key="h.name"
                      >
                        {{ h.name }}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!dataIsLoaded">
                      <td
                        colspan="11"
                        class="text-center"
                      >
                        <span class="fa fa-spinner fa-spin fa-2x" />
                      </td>
                    </tr>
                    <template v-if="data.length === 0 && dataIsLoaded">
                      <tr>
                        <td
                          colspan="11"
                          class="text-center"
                        >
                          No recordings were found.
                        </td>
                      </tr>
                    </template>
                    <template
                      v-for="row in data"
                      v-else
                    >
                      <tr>

                        <td>{{ row.Company }}</td>

                        <td>

                          <p v-if="row.Source.includes('WDNas02') || row.Source.includes('WDNas01') || isTooOld(row)">
                            Audio doesn't exist.
                          </p>
                          <i
                            v-else-if="row.Filename && !loadingRecording && !row.recording && !row.recordingError"
                            :id="row.Filename"
                            class="fa fa-play"
                            name="row"
                            @click="openAudio($event, row)"
                          />

                          <span
                            v-if="loadingRecording"
                            class="fa fa-spinner fa-spin fa-2x"
                          />

                          <audio
                            v-if="row.recording && !row.recordingError"
                            controls
                            autoplay

                            onerror="console.log('err')"
                            onabort="console.log('abort')"
                            @canplay="setCanPlay(true)"
                          >
                            
                            <source
                              :src="row.recording"
                              type="audio/mpeg"
                            >
                          </audio>

                          <a
                            v-if="row.DownloadAudio && !row.recordingError && !canPlayRecording"
                            :href="row.DownloadAudio"
                            class="btn btn-primary"
                            role="button"
                          >
                            <i
                              class="fa fa-floppy-o"
                              aria-hidden="true"
                            /> Save
                          </a>

                          <span
                            v-if="row.recordingError"
                            class="label label-warning"
                          >Not Found</span>
                        </td>

                        <td>{{ row.ConfirmationNumber }}</td>
                        <td>{{ row.Fullname }}</td>
                        <td>{{ row.BTN }}</td>
                        <td>{{ formatDate(row.Date) }}</td>
                        <td>{{ row.AccountNumber }}</td>
                        <td>{{ row.ServiceAddress }}</td>
                        <td>{{ row.BillingAddress }}</td>
                        <td>{{ row.Source }}</td>
                        <td>{{ row.Email }}</td>
                      </tr>
                    </template>
                  </tbody>
                </table>
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
import { replaceFilterBar } from 'utils/domManipulation';
import SearchForm from 'components/SearchFormLegacyPortal';
import Breadcrumb from 'components/Breadcrumb';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'LegacyPortal',
    components: {
        SearchForm,
        Breadcrumb,
    },
    props: {

        brands: {
            type: Array,
            default: () => [],
        },

        vendors: {
            type: Array,
            default: () => [],
        },
        languages: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            loadingRecording: false,
            canPlayRecording: false,
            gtSales: 0,
            gtNoSales: 0,
            gtPercentNoSales: 0,
            totalRecords: 0,
            data: [],
            headers: [

                {
                    name: 'Company', sorted: NO_SORTED, key: 'name', canSort: false, type: 'string',
                },
                { name: 'Audio', sorted: NO_SORTED, key: 'total_sales' },
                { name: 'ConfirmationNumber', sorted: NO_SORTED, key: 'total_no_sales' },
                { name: 'Fullname', sorted: NO_SORTED, key: 'total_pct_no_sales' },
                { name: 'BTN', sorted: NO_SORTED, key: 'total_pct_no_sales' },
                { name: 'Date', sorted: NO_SORTED, key: 'total_pct_no_sales' },
                { name: 'AccountNumber', sorted: NO_SORTED, key: 'total_pct_no_sales' },
                { name: 'ServiceAddress', sorted: NO_SORTED, key: 'total_pct_no_sales' },
                { name: 'Billing Address', sorted: NO_SORTED, key: 'total_pct_no_sales' },
                { name: 'Source', sorted: NO_SORTED, key: 'total_pct_no_sales' },
                { name: 'Email', sorted: NO_SORTED, key: 'total_pct_no_sales' },
            ],
            exportUrl: '/reports/legacy_portal?&csv=true',
            dataIsLoaded: false,
        };
    },
    computed: {
        filterParams() {
            const params = this.getParams();
            return [
                params.startDate ? `&startDate=${params.startDate}` : '',
                params.endDate ? `&endDate=${params.endDate}` : '',
                params.brand ? formArrayQueryParam('brand', params.brand) : '',
                params.vendor ? formArrayQueryParam('vendor', params.vendor) : '',
                params.language ? formArrayQueryParam('language', params.language) : '',
                params.confirmation_number_search
                    ? `&confirmation_number_search=${params.confirmation_number_search}`
                    : '',
                params.btn_search
                    ? `&btn_search=${params.btn_search}`
                    : '',
                params.account_search
                    ? `&account_search=${params.account_search}`
                    : '',
                params.firstname_search
                    ? `&firstname_search=${params.firstname_search}`
                    : '',
                params.lastname_search
                    ? `&lastname_search=${params.lastname_search}`
                    : '',
                params.postalcode_search
                    ? `&postalcode_search=${params.postalcode_search}`
                    : '',
            ].join('');
        },
        initialSearchValues() {
            const params = this.getParams();
            return {
                startDate: params.startDate,
                endDate: params.endDate,
                brand: params.brand,
                vendor: params.vendor,
                language: params.language,

            };
        },
        sortParams() {
            const params = this.getParams();
            return !!params.column && !!params.direction
                ? `&column=${params.column}&direction=${params.direction}`
                : '';
        },
    },
    created() {
        this.$store.commit('setBrands', this.brands);
        this.$store.commit('setVendors', this.vendors);
        this.$store.commit('setLanguages', this.languages);

    },
    mounted() {
        const params = this.getParams();

        if (!!params.column && !!params.direction) {
            const sortHeaderIndex = this.headers.findIndex(
                (header) => header.key === params.column,
            );
            this.headers[sortHeaderIndex].sorted = params.direction;
        }

        this.exportUrl = `/reports/legacy_portal?${this.filterParams}${this.sortParams}&csv=true`;
        axios.get(
            `/reports/legacy_portal?${this.filterParams}${this.sortParams}`,
        )
            .then((response) => {
                this.dataIsLoaded = true;
                const res = response.data;

                console.log(res);

                this.data = res[0];

            })
            .catch(console.log);
    },
    beforeDestroy() {
    },
    methods: {
        isTooOld(row) {
          var date1 = new Date(row.Date);
          var date2 = new Date('2010-01-01');
          return (row.Platform == "DXC" && (date1 < date2));
        },
        setCanPlay(value) {
            this.canPlayRecording = value;
        },
        isDateValid(dateStr) {
            try {
                return !Number.isNaN(new Date(dateStr).getTime());
            }
            catch (err) {
                console.log(err);
                return false;
            }
            
        },
        formatDate(dateStr) {
            try {
                return  moment(dateStr).format('MM/DD/YYYY HH:mm:ss');

            }
            catch (e) {
            // Handle the error
                console.error(`Error formatting: ${dateStr}${e.message}`);
                return dateStr;
            
            }
        },
        hideElement(element) {
            $('element').hide();
        },
        isValidHttpUrl(string) {
            try {
                new URL(string);
                return true;
            }
            catch (_) {
                return false;
            }
        },

        openAudio(e, row) {
            this.canPlayRecording = false;
            if (!this.loadingRecording) {

                this.loadingRecording = true;

                const SourceArray = row.Source.split('AWS-LINK: ');

                if (SourceArray.length > 0 && this.isValidHttpUrl(SourceArray[1])) {
                    row.recordingError = false;
                    row.recording = SourceArray[1];
                    row.DownloadAudio = SourceArray[1];
                    this.loadingRecording = false;
                    console.log('The AWS Link was already in the row data');
                }
                else {

                    const ApiURL = 'https://apiv2.tpvhub.com';
                    //'http://apiv2.staging.tpvhub.com'; //'http://mgmt-3-staging.tpvhub.com:6500'; //https://apiv2.tpvhub.com

                    let StepId = '';

                    //Some Calibrus recordings have the step id at the end of the filename in AWS S3.
                    if (row.StepId && row.StepId.length > 0) { StepId = row.StepId; }

                    axios.get(`${ApiURL}/api/recordingsportal/getrecording?Filename=${row.Filename}&StepId=${StepId}`,
                        { cache: false}, {timeout: 120000 })
                        .then((response) => {
                            const res = response.data;

                            if (res.Error) {
                                row.recordingError = true;
                            }
                            else {
                                row.recordingError = false;
                                row.recording = res.Audio;
                                row.DownloadAudio = res.DownloadAudio;

                            }
                            console.log(row.recording);

                            //  console.log(res);
                            this.loadingRecording = false;

                        }).catch((error) => {
                            row.recordingError = true;
                            this.loadingRecording = false;

                            if (error.code === 'ECONNABORTED') {

                                console.log('Request timed out');

                            }
                            else {
                                console.log('An error occurred', error);
                            }
                        });
                }
            }
           
        },
        searchData({
            startDate, endDate, brand, vendor, language, 
        }) {
            const confirmation_number_search = this.$refs.confirmation_number_search.value;
            const btn_search = this.$refs.btn_search.value;
            const account_search = this.$refs.account_search.value;
            const firstname_search = this.$refs.firstname_search.value;
            const lastname_search = this.$refs.lastname_search.value;
            const postalcode_search = this.$refs.postalcode_search.value;
            const filterParams = [
                this.isDateValid(startDate) ? `&startDate=${startDate}` : '',
                this.isDateValid(endDate) ? `&endDate=${endDate}` : '',
                brand ? formArrayQueryParam('brand', brand) : '',
                vendor ? formArrayQueryParam('vendor', vendor) : '',
                language ? formArrayQueryParam('language', language) : '',
                confirmation_number_search
                    ? `&confirmation_number_search=${confirmation_number_search}`
                    : '',
                btn_search
                    ? `&btn_search=${btn_search}`
                    : '',
                account_search
                    ? `&account_search=${account_search}`
                    : '',
                firstname_search
                    ? `&firstname_search=${firstname_search}`
                    : '',
                lastname_search
                    ? `&lastname_search=${lastname_search}`
                    : '',
                postalcode_search
                    ? `&postalcode_search=${postalcode_search}`
                    : '',
            ].join('');

            window.location.href = `/reports/legacy_portal?${filterParams}${this.sortParams}`;
        },
        sortData(h) {
            const labelSort = this.headers[this.headers.indexOf(h)].sorted === ASC_SORTED
                ? DESC_SORTED
                : ASC_SORTED;
            window.location.href = `/reports/legacy_portal?column=${h.key}&direction=${labelSort}${this.filterParams}`;
        },
        getParams() {
            const url = new URL(window.location.href);
            const brand = url.searchParams.getAll('brand[]').length > 0
                ? url.searchParams.getAll('brand[]')
                : null;
            const vendor = url.searchParams.getAll('vendor[]').length > 0
                ? url.searchParams.getAll('vendor[]')
                : null;
            const language = url.searchParams.getAll('language[]').length > 0
                ? url.searchParams.getAll('language[]')
                : null;
            const startDate = url.searchParams.get('startDate') || '';
            const endDate = url.searchParams.get('endDate') || '';
            const column = url.searchParams.get('column') || '';
            const direction = url.searchParams.get('direction') || '';
            const confirmation_number_search = url.searchParams.get('confirmation_number_search');
            const btn_search = url.searchParams.get('btn_search');
            const account_search = url.searchParams.get('account_search');
            const firstname_search = url.searchParams.get('firstname_search');
            const lastname_search = url.searchParams.get('lastname_search');
            const postalcode_search = url.searchParams.get('postalcode_search');

            return {
                brand,
                vendor,
                language,
                startDate,
                endDate,
                column,
                direction,
                confirmation_number_search,
                btn_search,
                account_search,
                firstname_search,
                lastname_search,
                postalcode_search,
            };
        },
        number_format(number, decimals, decPoint, thousandsSep) {
            number = (`${number}`).replace(/[^0-9+\-Ee.]/g, '');
            const n = !isFinite(+number) ? 0 : +number;
            const prec = !isFinite(+decimals) ? 0 : Math.abs(decimals);
            const sep = typeof thousandsSep === 'undefined' ? ',' : thousandsSep;
            const dec = typeof decPoint === 'undefined' ? '.' : decPoint;
            let s = '';

            const toFixedFix = function(n, prec) {
                if ((`${n}`).indexOf('e') === -1) {
                    return +(`${Math.round(`${n}e+${prec}`)}e-${prec}`);
                } 
                const arr = (`${n}`).split('e');
                let sig = '';
                if (+arr[1] + prec > 0) {
                    sig = '+';
                }
                return (+(
                    `${Math.round(`${+arr[0]}e${sig}${+arr[1] + prec}`) 
                    }e-${ 
                        prec}`
                )).toFixed(prec);
        
            };

            // @todo: for IE parseFloat(0.55).toFixed(0) = 0;
            s = (prec ? toFixedFix(n, prec).toString() : `${Math.round(n)}`).split(
                '.',
            );
            if (s[0].length > 3) {
                s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
            }
            if ((s[1] || '').length < prec) {
                s[1] = s[1] || '';
                s[1] += new Array(prec - s[1].length + 1).join('0');
            }

            return s.join(dec);
        },
    },
};
</script>
