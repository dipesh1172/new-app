<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: `Edit Fee Schedule for ${brand.name}`, active: true}
    ]"
  >
    <div class="tab-content">
      <div
        role="tabpanel"
        class="tab-pane active"
      >
        <div
          v-if="flashMessage"
          class="alert alert-success"
        >
          <span class="fa fa-check-circle" />
          <em>{{ flashMessage }}</em>
        </div>

        <div
          v-if="errors.length"
          class="alert alert-danger"
        >
          <strong>Errors</strong>
          <br>
          <ul>
            <li
              v-for="(error, i) in errors"
              :key="i"
            >
              {{ error }}
            </li>
          </ul>
        </div>

        <form
          method="POST"
          :action="`/brands/${brand.id}/feeschedule/update`"
          accept-charset="UTF-8"
          autocomplete="off"
        >
          <input
            name="_token"
            type="hidden"
            :value="csrfToken"
          >
          <h4>Basic Setup</h4>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="Billing Frequency"
                  class="heading"
                >Billing Frequency</label>
                <select
                  id="bill_frequency_id"
                  name="bill_frequency_id"
                  class="form-control form-control-lg"
                >
                  <option value>
                    Select a Billing Frequency
                  </option>
                  <option
                    v-for="bill_frequency in billFrequencies"
                    :key="bill_frequency.id"
                    :value="bill_frequency.id"
                    :selected="invoiceRateCard.bill_frequency_id !== null && invoiceRateCard.bill_frequency_id == bill_frequency.id"
                  >
                    {{ bill_frequency.frequency }}
                  </option>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="term_days"
                  class="heading"
                >Term Days</label>
                <input
                  id="term_days"
                  class="form-control form-control-lg"
                  placeholder="Term in days"
                  name="term_days"
                  type="text"
                  :value="invoiceRateCard.term_days"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="minimum"
                  class="heading"
                >Minimum Payment</label>
                <input
                  id="minimum"
                  class="form-control form-control-lg"
                  placeholder="Minimum Payment"
                  name="minimum"
                  type="text"
                  :value="invoiceRateCard.minimum"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>Digital TPV</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="digital_transaction"
                  class="heading"
                >Digital TPV</label>
                <input
                  id="digital_transaction"
                  class="form-control form-control-lg"
                  placeholder="Digital TPV"
                  name="digital_transaction"
                  type="text"
                  :value="number_format(invoiceRateCard.digital_transaction, 2)"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="ivr_voiceprint"
                  class="heading"
                >IVR Voiceprint</label>
                <input
                  id="ivr_voiceprint"
                  class="form-control form-control-lg"
                  placeholder="IVR Voiceprint Fee"
                  name="ivr_voiceprint"
                  type="text"
                  :value="number_format(invoiceRateCard.ivr_voiceprint, 2)"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>EZTPV</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="eztpv_rate"
                  class="heading"
                >EzTPV</label>
                <input
                  id="eztpv_rate"
                  class="form-control form-control-lg"
                  placeholder="EzTPV Rate"
                  name="eztpv_rate"
                  type="text"
                  :value="number_format(invoiceRateCard.eztpv_rate, 2)"
                >
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="eztpv_tm_rate"
                  class="heading"
                >EzTPV (TM)</label>
                <input
                  id="eztpv_tm_rate"
                  class="form-control form-control-lg"
                  placeholder="EzTPV (TM)"
                  name="eztpv_tm_rate"
                  type="text"
                  :value="number_format(invoiceRateCard.eztpv_tm_rate, 2)"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="eztpv_tm_monthly"
                  class="heading"
                >EzTPV (TM Monthly)</label>
                <input
                  id="eztpv_tm_monthly"
                  class="form-control form-control-lg"
                  placeholder="EzTPV (TM Monthly)"
                  name="eztpv_tm_monthly"
                  type="text"
                  :value="number_format(invoiceRateCard.eztpv_tm_monthly, 2)"
                >
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="eztpv_sms"
                  class="heading"
                >EzTPV (SMS)</label>
                <input
                  id="eztpv_sms"
                  class="form-control form-control-lg"
                  placeholder="EzTPV (SMS)"
                  name="eztpv_sms"
                  type="text"
                  :value="number_format(invoiceRateCard.eztpv_sms, 2)"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>HRTPV</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="hrtpv_transaction"
                  class="heading"
                >HRTPV (per hrtpv transaction)</label>
                <input
                  id="hrtpv_transaction"
                  class="form-control form-control-lg"
                  placeholder="HRTPV (per hrtpv transaction) Rate"
                  name="hrtpv_transaction"
                  type="text"
                  :value="invoiceRateCard.hrtpv_transaction"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="hrtpv_document"
                  class="heading"
                >HRTPV (per document)</label>
                <input
                  id="hrtpv_document"
                  class="form-control form-control-lg"
                  placeholder="HRTPV (per document) Rate"
                  name="hrtpv_document"
                  type="text"
                  :value="invoiceRateCard.hrtpv_document"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>DNIS</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="did_tollfree"
                  class="heading"
                >DID (Tollfree)</label>
                <input
                  id="did_tollfree"
                  class="form-control form-control-lg"
                  placeholder="DID (Tollfree) Rate"
                  name="did_tollfree"
                  type="text"
                  :value="invoiceRateCard.did_tollfree"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="did_local"
                  class="heading"
                >DID (Local)</label>
                <input
                  id="did_local"
                  class="form-control form-control-lg"
                  placeholder="DID (Local) Rate"
                  name="did_local"
                  type="text"
                  :value="invoiceRateCard.did_local"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>Live Rate</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="Billing Methodology"
                  class="heading"
                >Billing Methodology</label>
                <select
                  id="bill_methodology_id"
                  name="bill_methodology_id"
                  class="form-control form-control-lg"
                >
                  <option value>
                    Select a Billing Methodology
                  </option>
                  <option
                    v-for="bill_methodology in billMethodologies"
                    :key="bill_methodology.id"
                    :value="bill_methodology.id"
                    :selected="invoiceRateCard.bill_methodology_id == bill_methodology.id"
                  >
                    {{ bill_methodology.methodology }}
                  </option>
                </select>
              </div>

              <div class="form-group">
                <label
                  for="live_flat_rate"
                  class="heading"
                >Live Flat Rate (if applicable)</label>
                <input
                  id="live_flat_rate"
                  class="form-control form-control-lg"
                  placeholder="Live Flat Rate (if applicable)"
                  name="live_flat_rate"
                  type="text"
                  :value="invoiceRateCard.live_flat_rate"
                >
              </div>
            </div>
            <div class="col-md-3">
              <table
                v-if="invoiceRateCard.bill_methodology_id == 2"
                class="table table-bordered table-striped"
              >
                <tbody>
                  <tr
                    v-for="(level, index) in invoiceRateCard.levels"
                    :key="index"
                  >
                    <td>
                      <template v-if="level.level == 0">
                        0
                      </template>
                      <template v-else>
                        {{ number_format(level.level + 1, 0, ".", ",") }}
                      </template>

                      <template
                        v-if="(index+1) in invoiceRateCard.levels"
                      >
                        to {{ number_format(invoiceRateCard.levels[index+1]['level'], 0, ".", ",") }}
                      </template>
                      <template v-else>
                        +
                      </template>
                    </td>
                    <td>{{ number_format(invoiceRateCard.levels[index]['rate'], 2, ".", ",") }}</td>
                  </tr>
                </tbody>
              </table>

              <table
                v-if="invoiceRateCard.bill_methodology_id == 3"
                class="table table-bordered table-striped"
              >
                <tbody>
                  <tr
                    v-for="(level, index) in invoiceRateCard.levels"
                    :key="index"
                  >
                    <td>{{ number_format(invoiceRateCard.levels[index]['level'], 0, ".", ",") }} +</td>
                    <td>{{ number_format(invoiceRateCard.levels[index]['rate'], 2, ".", ",") }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="col-md-6">
              <textarea
                id="prettyJSON"
                v-model="prettyJSON"
                name="levels"
                class="form-control form-control-lg"
                style="height: 400px;"
              />
            </div>
          </div>

          <hr>

          <h4>IVR Rate</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="ivr_rate"
                  class="heading"
                >IVR Rate (per call)</label>
                <input
                  id="ivr_rate"
                  class="form-control form-control-lg"
                  placeholder="IVR Rate (per call)"
                  name="ivr_rate"
                  type="text"
                  :value="invoiceRateCard.ivr_rate"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="ivr_trans_rate"
                  class="heading"
                >IVR Trans Rate (per minute)</label>
                <input
                  id="ivr_trans_rate"
                  class="form-control form-control-lg"
                  placeholder="IVR Trans Rate (per minute)"
                  name="ivr_trans_rate"
                  type="text"
                  :value="invoiceRateCard.ivr_trans_rate"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>Staff Billable</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="it_billable"
                  class="heading"
                >IT Billable (per hour)</label>
                <input
                  id="it_billable"
                  class="form-control form-control-lg"
                  placeholder="IT Billable Rate"
                  name="it_billable"
                  type="text"
                  :value="invoiceRateCard.it_billable"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="qa_billable"
                  class="heading"
                >QA Billable (per hour)</label>
                <input
                  id="qa_billable"
                  class="form-control form-control-lg"
                  placeholder="QA Billable Rate"
                  name="qa_billable"
                  type="text"
                  :value="invoiceRateCard.qa_billable"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="cs_billable"
                  class="heading"
                >CS Billable (per hour)</label>
                <input
                  id="cs_billable"
                  class="form-control form-control-lg"
                  placeholder="CS Billable (per hour)"
                  name="cs_billable"
                  type="text"
                  :value="invoiceRateCard.cs_billable"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>Interconnect Fee</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="ld_billback_dom"
                  class="heading"
                >Interconnect Fee (domestic)</label>
                <input
                  id="ld_billback_dom"
                  class="form-control form-control-lg"
                  placeholder="Interconnect Fee (domestic)"
                  name="ld_billback_dom"
                  type="text"
                  :value="invoiceRateCard.ld_billback_dom"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="ld_billback_intl"
                  class="heading"
                >Interconnect Fee (international)</label>
                <input
                  id="ld_billback_intl"
                  class="form-control form-control-lg"
                  placeholder="Interconnect Fee (international)"
                  name="ld_billback_intl"
                  type="text"
                  :value="invoiceRateCard.ld_billback_intl"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>Document Service</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="eztpv_contract"
                  class="heading"
                >EZTPV (Contract)</label>
                <input
                  id="eztpv_contract"
                  class="form-control form-control-lg"
                  placeholder="Storage (minimum)"
                  name="eztpv_contract"
                  type="text"
                  :value="number_format(invoiceRateCard.eztpv_contract, 2)"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="eztpv_photo"
                  class="heading"
                >EZTPV (Photo)</label>
                <input
                  id="eztpv_photo"
                  class="form-control form-control-lg"
                  placeholder="EzTPV (Photo)"
                  name="eztpv_photo"
                  type="text"
                  :value="number_format(invoiceRateCard.eztpv_photo, 2)"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>Storage</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="storage_in_gb_min"
                  class="heading"
                >Storage (minimum)</label>
                <input
                  id="storage_in_gb_min"
                  class="form-control form-control-lg"
                  placeholder="Storage (minimum)"
                  name="storage_in_gb_min"
                  type="text"
                  :value="invoiceRateCard.storage_in_gb_min"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="storage_rate_in_gb"
                  class="heading"
                >Storage (per GB)</label>
                <input
                  id="storage_rate_in_gb"
                  class="form-control form-control-lg"
                  placeholder="Storage (per GB)"
                  name="storage_rate_in_gb"
                  type="text"
                  :value="invoiceRateCard.storage_rate_in_gb"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>API / HTTP Post</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="api_submission"
                  class="heading"
                >API Submission</label>
                <input
                  id="api_submission"
                  class="form-control form-control-lg"
                  placeholder="API Submission"
                  name="api_submission"
                  type="text"
                  :value="number_format(invoiceRateCard.api_submission, 2)"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="http_post"
                  class="heading"
                >HTTP Post</label>
                <input
                  id="http_post"
                  class="form-control form-control-lg"
                  placeholder="HTTP Post"
                  name="http_post"
                  type="text"
                  :value="number_format(invoiceRateCard.http_post, 2)"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>Web Enroll</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="web_enroll_submission"
                  class="heading"
                >Web Enroll Submissions</label>
                <input
                  id="web_enroll_submission"
                  class="form-control form-control-lg"
                  placeholder="Web Enroll Submissions"
                  name="web_enroll_submission"
                  type="text"
                  :value="number_format(invoiceRateCard.web_enroll_submission, 2)"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>Pay Link</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="pay_submission"
                  class="heading"
                >Pay Link</label>
                <input
                  id="pay_submission"
                  class="form-control form-control-lg"
                  placeholder="Pay Link"
                  name="pay_submission"
                  type="text"
                  :value="number_format(invoiceRateCard.pay_submission, 2)"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>Addon Services</h4>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="contract_review"
                  class="heading"
                >Contract Review</label>
                <input
                  id="contract_review"
                  class="form-control form-control-lg"
                  placeholder="Contract Review"
                  name="contract_review"
                  type="text"
                  :value="invoiceRateCard.contract_review"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="address_verification_rate"
                  class="heading"
                >Address Verification</label>
                <input
                  id="address_verification_rate"
                  class="form-control form-control-lg"
                  placeholder="Address Verification Rate"
                  name="address_verification_rate"
                  type="text"
                  :value="invoiceRateCard.address_verification_rate"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="cell_number_verification"
                  class="heading"
                >VOIP Lookup</label>
                <input
                  id="cell_number_verification"
                  class="form-control form-control-lg"
                  placeholder="VOIP Lookup Rate"
                  name="cell_number_verification"
                  type="text"
                  :value="invoiceRateCard.cell_number_verification"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="custom_report_fee"
                  class="heading"
                >Custom Reports</label>
                <input
                  id="custom_report_fee"
                  class="form-control form-control-lg"
                  placeholder="Custom Report Fee"
                  name="custom_report_fee"
                  type="text"
                  :value="invoiceRateCard.custom_report_fee"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="esiid_lookup"
                  class="heading"
                >ESI ID Lookup</label>
                <input
                  id="esiid_lookup"
                  class="form-control form-control-lg"
                  placeholder="ESI ID Lookup Rate"
                  name="esiid_lookup"
                  type="text"
                  :value="invoiceRateCard.esiid_lookup"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="sales_pitch"
                  class="heading"
                >Sales Pitch</label>
                <input
                  id="sales_pitch"
                  class="form-control form-control-lg"
                  placeholder="Sales Pitch Rate"
                  name="sales_pitch"
                  type="text"
                  :value="invoiceRateCard.sales_pitch"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="daily_questionnaire"
                  class="heading"
                >Daily Questionnaire</label>
                <input
                  id="daily_questionnaire"
                  class="form-control form-control-lg"
                  placeholder="Daily Questionnaire Rate"
                  name="daily_questionnaire"
                  type="text"
                  :value="invoiceRateCard.daily_questionnaire"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="gps_distance_cust_sa"
                  class="heading"
                >GPS Distance (Customer <> Sales Agent)</label>
                <input
                  id="gps_distance_cust_sa"
                  class="form-control form-control-lg"
                  placeholder="GPS Distance (Customer<>Sales Agent)"
                  name="gps_distance_cust_sa"
                  type="text"
                  :value="invoiceRateCard.gps_distance_cust_sa"
                >
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label
                  for="server_hosting"
                  class="heading"
                >Server Hosting (monthly)</label>
                <input
                  id="server_hosting"
                  class="form-control form-control-lg"
                  placeholder="Server Hosting (monthly)"
                  name="server_hosting"
                  type="text"
                  :value="invoiceRateCard.server_hosting"
                >
              </div>
            </div>
          </div>

          <hr>

          <h4>Supplemental Invoice</h4>
          <p>
            Client receieves supplemental invoices
          </p>

          <div class="row">
            <div class="col-md-3">
              <span
                style="position:relative;top:-8px;"
                class="its-a-no badge badge-danger font-lg"
              >
                Off
              </span>
              <label class="switch mb-0">
                <input
                  id="supplemental_invoice"
                  :checked="invoiceRateCard.supplemental_invoice"
                  type="checkbox"
                  name="supplemental_invoice"
                  value="1"
                >
                <span class="slider round" />
              </label>
              <span
                style="position:relative;top:-8px;"
                class="thats-a-yes badge badge-success font-lg"
              >
                On
              </span>
            </div>
          </div>

          <hr>

          <div class="row">
            <div class="col-12">
              <button
                class="btn btn-lg btn-primary pull-right"
                type="submit"
                name="submit"
              >
                <i
                  class="fa fa-floppy-o"
                  aria-hidden="true"
                /> Save
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </layout>
</template>
<script>
import Layout from './Layout';

export default {
    name: 'Feeschedule',
    components: {
        Layout,
    },
    props: {
        brand: {
            type: Object,
            default: () => ({}),
        },
        errors: {
            type: Array,
            default: () => [],
        },
        flashMessage: {
            type: String,
            default: '',
        },
        billFrequencies: {
            type: Array,
            default: () => [],
        },
        invoiceRateCard: {
            type: Object,
            default: () => {},
        },
        billMethodologies: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            csrfToken: window.csrf_token,
            // bill_frequencies: [],
            // invoiceRateCard: {},
            // billMethodologies: [],
        };
    },
    computed: {
        prettyJSON: {
            get() {
                return JSON.stringify(this.invoiceRateCard.levels || {}, undefined, 4);
            },
            set(val) {
                this.invoiceRateCard.levels = val;
            },
        },
    },
    methods: {
        number_format(number, decimals, decPoint, thousandsSep) {
            // eslint-disable-line camelcase
            if (!number) {
                return 0;
            }
            number = `${number}`.replace(/[^0-9+\-Ee.]/g, '');
            const n = !isFinite(+number) ? 0 : +number;
            const prec = !isFinite(+decimals) ? 0 : Math.abs(decimals);
            const sep = typeof thousandsSep === 'undefined' ? ',' : thousandsSep;
            const dec = typeof decPoint === 'undefined' ? '.' : decPoint;
            let s = '';

            const toFixedFix = function(n, prec) {
                if (`${n}`.indexOf('e') === -1) {
                    return +`${Math.round(`${n}e+${prec}`)}e-${prec}`;
                }
                const arr = `${n}`.split('e');
                let sig = '';
                if (+arr[1] + prec > 0) {
                    sig = '+';
                }
                return (+`${Math.round(
                    `${+arr[0]}e${sig}${+arr[1] + prec}`,
                )}e-${prec}`).toFixed(prec);
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
