<template>
  <div class="container-fluid">
    <tab-bar :active-item="3" />
    <div class="alerts-container">
      <div class="card mb-0">
        <div class="card-header">
          <h5 class="card-title mb-0 pull-left">
            Alerts
          </h5>
          <button
              class="btn btn-success btn-sm pull-right m-0"
              data-toggle="modal"
              data-target="#createAlertModal"
            >
            Add Alert
          </button>
        </div>
        <div
          v-if="flashMessage"
          class="alert alert-success"
        >
          <span class="fa fa-check-circle" />
          <em> {{ flashMessage }}</em>
        </div>
        <div
            v-if="errors.length"
            class="alert alert-danger"
          >
            <strong>Errors</strong><br>
            <ul>
              <li
                v-for="(error, i) in errors"
                :key="i"
              >
                {{ error }}
              </li>
            </ul>
        </div>
        <custom-table
          :headers="headers"
          :data-grid="alertsData"
          :data-is-loaded="dataIsLoaded"
          show-action-buttons
          has-action-buttons
          empty-table-message="No alerts were found."
        />
      </div>
    </div>
    <form
        method="POST"
        action="/alerts/create"
      >
      <div
        id="createAlertModal"
        class="modal fade"
        role="dialog"
        tabindex="-1"
      >
        <div
          class="modal-dialog"
          role="document"
        >
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title">
              Create Alert
            </h4>
          </div>
          <div class="modal-body">
              <input 
                type="hidden" 
                name="id" 
                id="idInput"
              >
              <input
                type="hidden"
                name="_token"
                :value="csrf"
              >
              <div class="row mb-2">
                  <div class="col-md-6">
                    <div class="form-group">
                      <custom-input
                          label="Start date"
                          :value="currentAlertData.startDate || ''"
                          type="date"
                          class-style="small"
                          placeholder="Start date"
                          name="start_date"
                        />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <custom-input
                          label="End date"
                          :value="currentAlertData.endDate || ''"
                          type="date"
                          class-style="small"
                          placeholder="End date"
                          name="end_date"
                        />
                    </div>
                  </div>
              </div>
              <div class="form-group">
                <custom-textarea
                    label="Message"
                    :value="currentAlertData.message || ''"
                    placeholder="Message"
                    name="message"
                  />
              </div>
          </div>
          <div class="modal-footer">
              <button
                  type="button"
                  class="btn btn-secondary"
                  data-dismiss="modal"
                >
                  <i
                    class="fa fa-times"
                    aria-hidden="true"
                  />
                  Cancel
              </button>
              <button
                  type="submit"
                  class="btn btn-primary"
                >
                  <i
                    class="fa fa-floppy-o"
                    aria-hidden="true"
                  />
                  Save
              </button>
          </div>
        </div>
      </div>
    </div>
    </form>
  </div>
</template>

<script>
import axios from 'axios';
import CustomInput from 'components/CustomInput';
import CustomTextarea from 'components/CustomTextarea';
import CustomTable from 'components/CustomTable';
import TabBar from '../components/TabBar.vue';

const ROUTES = {
    ALERTS_CREATE: '/alerts/create',
    ALERTS_DELETE: '/alerts/delete',
    ALERTS_LIST: '/alerts/list',
};

const spinnerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>';

export default {
    name: 'Alerts',
    components: {
        CustomInput,
        CustomTextarea,
        CustomTable,
        TabBar,
    },
    props: {
        errors: {
            type: Array,
            default: () => [],
        },
        flashMessage: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            alertsData: [],
            currentAlertData: {
                id: null,
                startDate: null,
                endDate: null,
                message: null,
            },
            headers: [
                {
                    label: 'Id',
                    key: 'id',
                    serviceKey: 'id',
                    width: '20%', 
                },
                {
                    label: 'Start date',
                    key: 'startDate',
                    serviceKey: 'start_date',
                    width: '20%', 
                },
                {
                    label: 'End date',
                    key: 'endDate',
                    serviceKey: 'end_date',
                    width: '20%',              
                },          
                {
                    label: 'Messages',
                    key: 'message',
                    serviceKey: 'message',
                    width: '20%',              
                },  
            ],
            dataIsLoaded: false,
        };
    },
    computed: {
        csrf() {
            return window.csrf_token;
        },
    },
    mounted() {
        this.fetch(ROUTES.ALERTS_LIST);
    },
    methods: {
        handleDelete(e) {
            const cta = e.target;
            const currentAlertID = cta.href.split('/').pop();
            cta.classList.add('disabled');
            cta.innerHTML = `${cta.innerHTML} ${spinnerHTML}`;

            this.deleteAlert(currentAlertID, () => {
                cta.classList.remove('disabled');
                cta.querySelector('.fa-spinner').remove();
            });
        },
        handleEdit(e) {
            const currentAlertID = e.target.href.split('/').pop();
            const currentAlertData = this.alertsData.find((alert) => +alert.id === +currentAlertID);
            this.currentAlertData = currentAlertData;
            $('#createAlertModal').modal('show');
            $('#idInput').val(currentAlertID);
        },
        denormalizeAlert({ data: alerts }) {
            return alerts.map((alert) => ({
                id: alert.id,
                startDate: alert.start_date,
                endDate: alert.end_date,
                message: alert.message,
                buttons: [
                    {
                        type: 'custom',
                        label: 'Edit',
                        url: `/alerts/edit/${alert.id}`,
                        classNames: 'btn-primary',
                        onClick: this.handleEdit,
                    },
                    {
                        type: 'custom',
                        label: 'Delete',
                        url: `/alerts/delete/${alert.id}`,
                        classNames: 'btn-danger',
                        onClick: this.handleDelete,
                    },
                ],
            }));
        },
        async deleteAlert(id, cb) {
            await axios.delete(ROUTES.ALERTS_DELETE, { data: { id } });
            this.fetch(ROUTES.ALERTS_LIST, cb);
        },
        async saveAlert(data, cb) {
            await axios.post(ROUTES.ALERTS_CREATE, data);
            this.fetch(ROUTES.ALERTS_LIST, cb);
        },
        async fetch(url, cb) {
            const alerts = await axios.get(url);
            this.alertsData = this.denormalizeAlert(alerts.data);
            this.dataIsLoaded = true;
            cb && cb();
        },
    },
};
</script>

<style scoped>
  .card-wrapper {
    background: #ffff;
    padding: 20px;
  }
  .card {
    margin-bottom: 0;
  }
</style>