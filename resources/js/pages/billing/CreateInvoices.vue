<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Billing', url: '/billing'},
        {name: 'Create Invoices', active: true}
      ]"
    />

    <div class="container-fluid mt-3">
      <ul class="nav nav-tabs">
        <li class="nav-item">
          <a
            class="nav-link"
            href="/billing"
          ><i class="fa fa-list" /> Invoices</a>
        </li>
        <li class="nav-item">
          <a
            class="nav-link "
            href="/billing/charges"
          ><i class="fa fa-usd" /> Charges and Credits</a>
        </li>
        <li class="nav-item">
          <a
            class="nav-link active"
            href="/billing/create"
          ><i class="fa fa-plus-square" /> Generate Invoices</a>
        </li>
      </ul>
      <div class="tab-content mb-3">
        <div
          role="tabpanel"
          class="tab-pane active"
        >
          <div class="animated fadeIn">
            <div
              v-if="hasFlashMessage"
              class="alert alert-danger"
            >
              <span class="fa fa-check-circle" />
              <em>{{ flashMessage }}</em>
            </div>

            <form
              method="post"
              action="/billing/run"
            >
              <input
                type="hidden"
                name="_token"
                :value="csrf_token"
              >
              <input
                type="hidden"
                name="invoice_start_date"
                :value="invoice_start_date_formatted"
              >
              <input
                type="hidden"
                name="invoice_end_date"
                :value="invoice_end_date_formatted"
              >
              <div class="row page-buttons">
                <div class="col-md-4">
                  <label>Billing Frequency</label>
                  <select
                    v-model="periodSelect"
                    class="form-control"
                  >
                    <option :value="null">
                      Choose Billing Frequency
                    </option>
                    <option value="monthly">
                      Monthly
                    </option>
                    <option value="bi-weekly">
                      Bi-Weekly
                    </option>
                    <option value="weekly">
                      Weekly
                    </option>
                  </select>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4">
                  <label>Invoice Start Date</label>
                  <datepicker
                    v-model="invoice_start_date"
                    :inline="true"
                    :format="dateFormat"
                    :highlighted="{dates:[$moment().toDate()]}"
                  />
                </div>
                <div class="col-md-4">
                  <label>Invoice End Date</label>
                  <datepicker
                    v-model="invoice_end_date"
                    :inline="true"
                    :format="dateFormat"
                    :highlighted="{dates:[$moment().toDate()]}"
                  />
                </div>
              </div>

              <template v-if="brands.length === 0">
                <div class="row mt-4">
                  <div class="col-md-12">
                    <span class="fa fa-spinner fa-spin" /> Loading Brand Information
                  </div>
                </div>
              </template>

              <template v-if="filteredBrands.length > 0">
                <div class="row mt-4 pl-4">
                  <div
                    v-for="brand in filteredBrands"
                    :key="brand.id"
                    class="col-md-3"
                  >
                    <div class="form-check">
                      <input
                        :id="brand.id"
                        class="form-check-input"
                        type="checkbox"
                        name="brands[]"
                        :value="brand.id"
                        :checked="brand.billing_enabled == 1"
                        :disabled="brand.billing_enabled != 1"
                      >
                      <label
                        :class="{'form-check-label': true, 'billing-disabled text-danger': brand.billing_enabled != 1}"
                        :title="brand.billing_enabled != 1 ? 'Billing is disabled for this brand' : ''"
                        :for="brand.id"
                      >{{ brand.name }}</label>
                    </div>
                  </div>
                </div>
              </template>
              <template v-if="periodSelect != null && filteredBrands.length === 0">
                <div class="row mt-4">
                  <div class="col-md-12">
                    <div class="alert alert-warning">
                      The selected Billing Frequency does not include any brands.
                    </div>
                  </div>
                </div>
              </template>

              <div class="row mt-4">
                <div class="col-md-12">
                  <span class="pull-right">
                    <button
                      :disabled="!readyToContinue"
                      class="btn btn-success"
                    >
                      <span
                        class="fa fa-cogs"
                        aria-hidden="true"
                      /> Run Invoices
                    </button>
                  </span>
                </div>
              </div>
            </form>
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
    name: 'CreateInvoice',
    components: {
        Datepicker,
        Breadcrumb,
    },
    data() {
        return {
            brands: [],
            filteredBrands: [],
            dateFormat: 'yyyy-MM-dd',
            invoice_start_date: null,
            invoice_end_date: null,
            periodSelect: null,
        };
    },
    
    computed: {
        readyToContinue() {
            return this.periodSelect != null && this.filteredBrands.length > 0 && this.invoice_start_date != null && this.invoice_end_date != null;
        },
        csrf_token() {
            return csrf_token;
        },
        invoice_start_date_formatted() {
            return this.$moment(this.invoice_start_date).format('YYYY-MM-DD');
        },
        invoice_end_date_formatted() {
            return this.$moment(this.invoice_end_date).format('YYYY-MM-DD');
        },
        ...mapState({
            hasFlashMessage: (state) => state.session && Object.keys(state.session).length && state.session.hasOwnProperty('flash_message') && state.session.flash_message,
            flashMessage: (state) => state.session.flash_message,
        }),
    },
    watch: {
        periodSelect(v) {
            this.filteredBrands = [];
            if (v != null) {
                this.filteredBrands = this.brands.filter((i) => i.billing_frequency === v);
                switch (v) {
                    case 'monthly':
                        this.invoice_start_date = this.$moment().startOf('month').subtract(1, 'month').format('YYYY-MM-DD 00:00:00');
                        this.invoice_end_date = this.$moment().subtract(1, 'month').endOf('month').format('YYYY-MM-DD 00:00:00');
                        break;
                    case 'bi-weekly':
                        this.invoice_start_date = this.$moment().startOf('week').subtract(2, 'week').format('YYYY-MM-DD 00:00:00');
                        this.invoice_end_date = this.$moment().subtract(1, 'week').endOf('week').format('YYYY-MM-DD 00:00:00');
                        break;
                    case 'weekly':
                        this.invoice_start_date = this.$moment().startOf('week').subtract(1, 'week').format('YYYY-MM-DD 00:00:00');
                        this.invoice_end_date = this.$moment().subtract(1, 'week').endOf('week').format('YYYY-MM-DD 00:00:00');
                        break;
                }
            }
        },
    },
    mounted() {
        axios.get('/list/brands?active=true')
            .then((response) => {
                this.brands = response.data;
            })
            .catch((error) => {
                console.log(error);
            });

        const currentDate = new Date();
        const day = currentDate.getDate();
        const month = currentDate.getMonth() + 1;
        const year = currentDate.getFullYear();

        if (day > 15) {
            this.invoice_start_date = new Date(year, month, 1);
            this.invoice_end_date = new Date(year, month, 15);
        }
        else {
            this.invoice_start_date = new Date(year, month - 2, 16);
            this.invoice_end_date = new Date(year, month - 1, 0);
        }
    },
    methods: {
        continue() {

        },
    },
};
</script>

<style scoped>
.billing-disabled {
  text-decoration: none;
  font-style: italic;
}
</style>
