<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports'},
        {name: 'Agent Status Detail', active: true}
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="tab-content">
        <div
          role="tabpanel"
          class="tab-pane active"
        >
          <div class="animated fadeIn">
            <div class="row page-buttons">
              <div class="col-md-12" />
            </div>
            <div class="card">
              <div class="card-header">
                <i class="fa fa-th-large" /> Agent Status Detail
              </div>
              <div class="card-body">
                <div
                  v-if="flashMessage"
                  class="alert alert-success"
                >
                  <span class="fa fa-check-circle" />
                  <em>{{ flashMessage }}</em>
                </div>
                <div class="row p-3">
                  <form
                    method="POST"
                    action="/agent-status-summary"
                    accept-charset="UTF-8"
                    class="form-inline pull-right"
                  >
                    <input
                      name="_token"
                      type="hidden"
                      :value="csrfToken"
                    > 
                    <input
                      type="hidden"
                      name="startDate"
                      :value="startDateFormatted"
                    >
                    <input
                      type="hidden"
                      name="endDate"
                      :value="endDateFormatted"
                    >
                    <div class="row">
                      <div class="col-md-6">
                        <h3>Date From</h3><br>
                        <datepicker
                          v-model="startDate"
                          :inline="true"
                          :format="dateFormat"
                        />
                      </div>
                      <div class="col-md-6">
                        <h3>Date To</h3><br>
                        <datepicker
                          v-model="endDate"
                          :inline="true"
                          :format="dateFormat"
                        />
                      </div>
                      <div class="col-12 mt-3">
                        <button
                          name="export_csv"
                          value="export_csv"
                          type="submit"
                          class="btn btn-primary"
                        >
                          <i
                            class="fa fa-download"
                            aria-hidden="true"
                          /> Export CSV
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import Datepicker from 'vuejs-datepicker';
import Breadcrumb from 'components/Breadcrumb';
import { mapState } from 'vuex';

export default {
    name: 'AgentStatusDetail',
    components: {
        Datepicker,
        Breadcrumb,
    },
    data() {
        return {
            dateFormat: 'MM-DD-YYYY',
            startDate: this.$moment().format('MM-DD-YYYY'),
            endDate: this.$moment().format('MM-DD-YYYY'),
            csrfToken: window.csrf_token,
        };
    },
    computed: {
        ...mapState({
            flashMessage: (state) => state.session.flash_message || '',
        }),
        startDateFormatted() {
            return this.$moment(this.startDate).format('YYYY-MM-DD');
        },
        endDateFormatted() {
            return this.$moment(this.endDate).format('YYYY-MM-DD');
        },
    },
};
</script>
