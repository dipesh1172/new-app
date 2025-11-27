<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Support', active: true},
        {name: 'Clear Test Calls', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="card">
        <div class="card-header">
          <i class="fa fa-th-large" /> Clear Test Calls
        </div>        
        <div class="card-body">
          <div class="row">
            <div class="col-md-12">
              <div
                v-if="hasFlashMessage"
                class="alert alert-success"
              >
                <span class="fa fa-check-circle" /><em> {{ flashMessage }}</em>
              </div>
            </div>
          </div>
          
          <form
            class="container"
            method="POST"
            @submit.prevent="requestCC"
          >
            <div class="row">
              <div class="col-md-6">
                <label for="confirmation_codes">Confirmation Codes (separate with commas)</label>                  
                <input
                  ref="confirmation_codes"
                  v-model="confirmation_codes"
                  class="form-control ml-2"
                  name="confirmation_codes"
                  type="text"
                >

                <button
                  ref="search_button"
                  name="search_button"
                  value="search"
                  class="btn btn-lg btn-success ml-2"
                  type="submit"
                  :class="{'disabled': !confirmation_codes.trim()}"
                >
                  <span
                    class="fa fa-search"
                    aria-hidden="true"
                  /> 
                  Search
                </button>
              </div>
                
              <div class="col-md-6">
                <a
                  href="/support/clear_test_calls?mode=testvendor"
                  class="btn btn-lg btn-warning pull-right"
                >
                  <span
                    class="fa fa-search"
                    aria-hidden="true"
                  /> 
                  All Test Vendor Records
                </a>
              </div>
            </div>
          </form>
        
          <div
            v-if="loading"
            class="row"
          >
            <div class="col-md-12">
              <span class="fa fa-5x fa-spinner fa-spin" /> Loading...
            </div>
          </div>
          <div class="row mt-4">
            <div class="col-md-12">
              <div
                v-if="events.length"
                class="table-responsive"
              >
                <custom-table
                  :headers="headers"
                  :data-grid="events"
                  :data-is-loaded="dataIsLoaded"
                  :total-records="totalRecords"
                  empty-table-message="No calls were found."
                />
                <form
                  action="/support/delete_test_calls"
                  method="POST"
                >
                  <input
                    type="hidden"
                    name="_token"
                    :value="csrfToken"
                  >
                  <input
                    :value="confirmation_codes"
                    type="hidden"
                    class="form-control ml-2"
                    name="confirmation_codes"
                  >                  
                  <button
                    name="delete_button"
                    value="delete"
                    type="submit"
                    class="btn btn-lg btn-danger delete_button"
                  >
                    <i
                      class="fa fa-trash-o"
                      aria-hidden="true"
                    /> 
                    Delete All
                  </button>   
                </form>
              </div>
            </div>
          </div>
          <div v-if="!events.length && dataIsLoaded">
            <div class="text-center">
              <p>No calls were found.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import CustomTable from 'components/CustomTable';
import Breadcrumb from 'components/Breadcrumb';
import { mapState } from 'vuex';

export default {
    name: 'ClearTestCalls',
    components: {
        CustomTable,
        Breadcrumb,
    },
    data() {
        return {
            loading: false,
            mode: 'search',
            csrfToken: window.csrf_token,
            events: [],
            dataIsLoaded: false,
            totalRecords: 0,
            headers: [{
                align: 'left',
                label: 'Date',
                key: 'created_at',
                serviceKey: 'created_at',
                width: '16%',
            }, {
                align: 'left',
                label: 'Confirmation Code',
                key: 'confirmation_code',
                serviceKey: 'confirmation_code',
                width: '16%',
            }, {
                align: 'left',
                label: 'Brand',
                key: 'brand_name',
                serviceKey: 'brand_name',
                width: '16%',
            }, {
                align: 'left',
                label: 'Vendor',
                key: 'vendor_name',
                serviceKey: 'vendor_name',
                width: '16%',
            }, {
                align: 'left',
                label: 'Authorizing Name',
                key: 'auth_name',
                serviceKey: 'auth_name',
                width: '16%',
            }, {
                align: 'left',
                label: 'Billing Name',
                key: 'billing_name',
                serviceKey: 'billing_name',
                width: '16%',
            }],
            confirmation_codes: '',
        };
    },
    computed: mapState({
        session: 'session',
        hasFlashMessage: (state) => Object.keys(state.session).length && state.session.hasOwnProperty('flash_message') && state.session.flash_message,
        flashMessage: (state) => state.session.flash_message,
    }),
    mounted() {
        const params = this.getParams();
        this.mode = params.mode;

        if (this.mode === 'testvendor') {
            this.requestCC(false);
        }
    },
    methods: {
        getParams() {
            const url = new URL(window.location.href);
            const mode = url.searchParams.get('mode') || 'search';
            return {mode};
        },
        requestCC(bb) {
            if (bb !== false) {
                this.mode = 'search';
            }
            let searchV = this.$refs.confirmation_codes.value;
            if (this.mode === 'search') {
                $(this.$refs.search_button)
                    .find('i')
                    .first()
                    .hide();

                $(this.$refs.search_button)
                    .prepend('<span class="fa fa-spinner fa-spin" />');
            }
            else {
                searchV = 'testvendor';
            }
            this.loading = true;
            axios.post('/support/list_clear_test_calls', {
                confirmation_codes: searchV,
                _token: this.csrfToken,
            }).then((response) => {
                const codes = [];
                this.events = response.data.map((event) => {
                    codes.push(event.confirmation_code);
                    return {
                        auth_name: `${event.auth_first_name ? event.auth_first_name : ''} ${event.auth_last_name ? event.auth_last_name : ''}`,
                        bill_name: `${event.bill_first_name ? event.bill_first_name : ''} ${event.bill_last_name ? event.bill_last_name : ''}`,
                        created_at: this.$moment(event.created_at).format('MM-DD-YYYY A'),
                        confirmation_code: `<a href="/events/${event.id}">${event.confirmation_code}</a>`,
                        brand_name: event.brand_name,
                        vendor_name: event.vendor != null ? event.vendor.name : '',
                    };
                });
                this.confirmation_codes = codes.join(',');
                this.dataIsLoaded = true;
                this.totalRecords = this.events.length;
                this.loading = false;
                if (this.mode === 'search') {
                    $(this.$refs.search_button)
                        .find('i')
                        .first()
                        .show();
                    $(this.$refs.search_button)
                        .find('span')
                        .first()
                        .remove();
                }
            }).catch((e) => {
                console.log(e);
                this.loading = false;
            });
        },
    },
};
</script>
