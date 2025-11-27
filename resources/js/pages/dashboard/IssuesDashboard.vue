<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Dashboard', url: '/sales_dashboard'},
        {name: 'Issues Dashboard', active: true},
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="row">
          <div class="col-sm-3">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title">
                  <span class="fa fa-headphones" /> Last recording
                </h5>
                <div
                  v-if="!lr_dataIsLoaded"
                  class="card-text"
                >
                  <span class="fa fa-spinner fa-spin fa-2x" />
                </div>
                <p
                  v-if="lr_dataIsLoaded && lastRecording"
                  class="card-text"
                /><h2>{{ lastRecording }}</h2></p>
                <p
                  v-if="lr_dataIsLoaded && !lastRecording"
                  class="card-text"
                >
                  No data was found.
                </p>
              </div>
            </div>
          </div>
          <div class="col-sm-3">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title">
                  <span class="fa fa-id-badge" /> Last contract
                </h5>
                <div
                  v-if="!lc_dataIsLoaded"
                  class="card-text"
                >
                  <span class="fa fa-spinner fa-spin fa-2x" />
                </div>
                <p
                  v-if="lc_dataIsLoaded && lastContract"
                  class="card-text"
                /><h2>{{ lastContract }}</h2>
                </p>
                <p
                  v-if="lc_dataIsLoaded && !lastContract"
                  class="card-text"
                >
                  No data was found.
                </p>
              </div>
            </div>
          </div>
        </div>
        <div class="row bg-white">
          <div class="col-6 mt-4">
            <div class="card">
              <div class="card-header text-center">
                <strong>Calls Without Recordings</strong>
                <span class="badge badge-primary">{{ callsWithoutRecords.length }}</span>
              </div>
              <div class="card-body table-responsive issue-box p-0">
                <table class="table table-striped mb-0">
                  <thead>
                    <tr>
                      <th scope="col">
                        Created At
                      </th>
                      <th scope="col">
                        Result
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Interaction Time
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Interaction Id
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Interaction Type Id
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Confirmation Code
                      </th>
                    </tr>
                  </thead>
                  <tbody v-if="callsWithoutRecords.length">
                    <tr
                      v-for="call in callsWithoutRecords"
                      :key="call.id"
                    >
                      <td>{{ call.created_at }}</td>
                      <td>{{ call.event_result_id }}</td>
                      <td class="text-center">
                        {{ call.interaction_time }}
                      </td>
                      <td class="text-center">
                        {{ call.id }}
                      </td>
                      <td class="text-center">
                        {{ call.interaction_type_id }}
                      </td>
                      <td
                        class="text-center"
                        v-html="call.confirmation_code"
                      />
                    </tr>
                  </tbody>
                  <tbody v-else-if="callswc_dataIsLoaded">
                    <tr>
                      <td
                        class="text-center"
                        colspan="6"
                      >
                        No calls were found for today.
                      </td>
                    </tr>
                  </tbody>
                  <tbody v-else-if="!callswc_dataIsLoaded">
                    <tr>
                      <td
                        class="text-center"
                        colspan="6"
                      >
                        <span class="fa fa-spinner fa-spin fa-2x" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-6 mt-4">
            <div class="card">
              <div class="card-header text-center">
                <strong>Last Brand Enrollment File Record</strong>
                <span class="badge badge-primary">{{ lastResultBEFPerBrand.length }}</span>
              </div>
              <div class="card-body table-responsive issue-box p-0">
                <table class="table table-striped mb-0">
                  <thead>
                    <tr>
                      <th scope="col">
                        Name
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Next Run
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Last Run
                      </th>
                      <th scope="col">
                        Last History
                      </th>
                    </tr>
                  </thead>
                  <tbody v-if="lastResultBEFPerBrand.length">
                    <tr
                      v-for="(bef, index) in lastResultBEFPerBrand"
                      :key="index"
                    >
                      <td>{{ bef.name }}</td>
                      <td class="text-center">
                        {{ bef.next_run }}
                      </td>
                      <td class="text-center">
                        {{ bef.last_run }}
                      </td>
                      <td>{{ bef.last_history }}</td>
                    </tr>
                  </tbody>
                  <tbody v-else-if="lbef_dataIsLoaded">
                    <tr>
                      <td
                        class="text-center"
                        colspan="6"
                      >
                        No brand enrollment files were found.
                      </td>
                    </tr>
                  </tbody>
                  <tbody v-else-if="!lbef_dataIsLoaded">
                    <tr>
                      <td
                        class="text-center"
                        colspan="6"
                      >
                        <span class="fa fa-spinner fa-spin fa-2x" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="row bg-white mt-4">
          <div class="col-12 mt-4">
            <div class="card">
              <div class="card-header text-center">
                <strong>Eztpv Without Contracts</strong>
                <span class="badge badge-primary">{{ eztpvWithoutContracts.length }}</span>
              </div>
              <div class="card-body table-responsive issue-box p-0">
                <table class="table table-striped mb-0">
                  <thead>
                    <tr>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Created At
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Id
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Confirmation Code
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Brand User Id
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        Signature Date
                      </th>
                      <th
                        scope="col"
                        class="text-center"
                      >
                        IP Addr
                      </th>
                      <th scope="col">
                        Brand
                      </th>
                    </tr>
                  </thead>
                  <tbody v-if="eztpvWithoutContracts.length">
                    <tr
                      v-for="eztpv in eztpvWithoutContracts"
                      :key="eztpv.id"
                    >
                      <td class="text-center">
                        {{ eztpv.created_at }}
                      </td>
                      <td class="text-center">
                        {{ eztpv.id }}
                      </td>
                      <td
                        class="text-center"
                        v-html="eztpv.confirmation_code"
                      />
                      <td class="text-center">
                        {{ eztpv.user_id }}
                      </td>
                      <td class="text-center">
                        {{ eztpv.signature_date }}
                      </td>
                      <td class="text-center">
                        {{ eztpv.ip_addr }}
                      </td>
                      <td>{{ eztpv.name }}</td>
                    </tr>
                  </tbody>
                  <tbody v-else-if="eztpvwc_dataIsLoaded">
                    <tr>
                      <td
                        class="text-center"
                        colspan="7"
                      >
                        No eztpv were found for today.
                      </td>
                    </tr>
                  </tbody>
                  <tbody v-else-if="!eztpvwc_dataIsLoaded">
                    <tr>
                      <td
                        class="text-center"
                        colspan="7"
                      >
                        <span class="fa fa-spinner fa-spin fa-2x" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-12">
            <div class="card">
              <div class="card-header text-center">
                <strong>Text Message Failed Delivery</strong>
                <span class="badge badge-primary">{{ failed_sms.length }}</span>
              </div>
              <div class="card-body p-0 issue-box">
                <span v-if="failed_sms_loaded == false">
                  Loading <i class="fa fa-spiner fa-spin" />
                </span>
                <table
                  v-else
                  class="table table-striped mb-0"
                >
                  <thead>
                    <tr>
                      <th>Sent</th>
                      <th>Updated At</th>
                      <th>Brand</th>
                      <th>Sent To</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(fs, fs_id) in failed_sms"
                      :key="fs_id"
                    >
                      <td>{{ fs.created_at }}</td>
                      <td>{{ fs.updated_at }}</td>
                      <td>{{ fs.brand !== null ? fs.brand.name : '' }}</td>
                      <td>{{ fs.to_phone.phone_number }}</td>
                      <td>
                        {{ fs.status }} <a
                          href="#"
                          title="Check Failure Reason"
                          @click="doSmsStatusLookup(fs.message_sid)"
                        ><i class="fa fa-2x fa-question-circle" /></a>
                      </td>
                    </tr>
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
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'IssuesDashboard',
    components: {
        Breadcrumb,
    },
    data() {
        return {
            lastContract: '',
            lastRecording: '',
            lastResultBEFPerBrand: [],
            eztpvWithoutContracts: [],
            callsWithoutRecords: [],
            failed_sms: [],
            lc_dataIsLoaded: false,
            lr_dataIsLoaded: false,
            lbef_dataIsLoaded: false,
            eztpvwc_dataIsLoaded: false,
            callswc_dataIsLoaded: false,
            failed_sms_loaded: false,
        };
    },
    mounted() {
        document.title += ' Issues Dashboard';
        axios.post('/issues_dashboard/failed_sms').then((response) => {
            this.failed_sms = response.data;
            this.failed_sms_loaded = true;
        }).catch(console.log);

        axios
            .get('/issues_dashboard/last_result_bef_per_brand')
            .then((response) => {
                this.lastResultBEFPerBrand = response.data;
                this.lbef_dataIsLoaded = true;
            })
            .catch(console.log);

        axios
            .get('/issues_dashboard/calls_without_records')
            .then((response) => {
                this.callsWithoutRecords = response.data.map((call) => {
                    call.interaction_time = call.interaction_time.toFixed(2);
                    call.confirmation_code = `<a href="/events/${call.event_id}">${call.confirmation_code}</a>`;
                    return call;
                });
                this.callswc_dataIsLoaded = true;
            })
            .catch(console.log);

        axios
            .get('/issues_dashboard/last_recording')
            .then((response) => {
                this.lastRecording = response.data.created_at;
                this.lr_dataIsLoaded = true;
            })
            .catch(console.log);

        axios
            .get('/issues_dashboard/last_contract')
            .then((response) => {
                this.lastContract = response.data.created_at;
                this.lc_dataIsLoaded = true;
            })
            .catch(console.log);

        axios
            .get('/issues_dashboard/eztpv_without_contracts')
            .then((response) => {
                this.eztpvWithoutContracts = response.data.map((eztpv) => {
                    eztpv.confirmation_code = `<a href="/events/${eztpv.event_id}">${eztpv.confirmation_code}</a>`;
                    return eztpv;
                });
                this.eztpvwc_dataIsLoaded = true;
            })
            .catch(console.log);
    },
    methods: {
        doSmsStatusLookup(message_sid) {
            if (message_sid === null || message_sid === '' || message_sid === 'error') {
                alert('This message was rejected by Twilio and did not receive an identifier.');
                return false;
            }
            axios.post(`/issues_dashboard/failed_sms_error_lookup/${message_sid}`)
                .then((response) => {
                    alert(response.data.error);
                }).catch((e) => {
                    alert(`There was an issue getting the error reason: ${e}`);
                });
            return false;
        },
    },
};
</script>
<style scoped>
.issue-box {
  max-height: 500px;
  overflow-y: scroll;
}
</style>
