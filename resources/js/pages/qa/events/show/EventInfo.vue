<template>
  <div class="card mb-0">
    <div class="card-body p-0 table-responsive">
      <table class="table table-sm table-bordered table-striped mb-0">
        <tbody>
          <tr>
            <th
              width="12%"
            >
              Created At
            </th>
            <td>{{ event.created_at }}</td>
            <th
              width="12%"
            >
              Confirmation Code
              <template v-if="event.external_id != null">
                <hr>
                External Ref ID
              </template>
            </th>
            <td>
              {{ event.confirmation_code || 'Not Set' }}
              <template v-if="event.external_id != null">
                <hr>
                {{ event.external_id }}
              </template>
            </td>
            <th
              width="12%"
            >
              Channel
            </th>
            <td>{{ event.channel && event.channel.channel.toUpperCase() }}</td>
          </tr>
          <tr>
            <th>
              Brand
            </th>
            <td>
              {{ event.brand.name }}
            </td>
            <th>
              Market
            </th>
            <td>
              <span
                v-if="event.event_category_id == 1"
              >
                {{ event.products.length > 0 && event.products[0].market_id == 1 ? 'Residential' : 'Commercial' }}
              </span>
              <span
                v-else-if="event.event_category_id == 2 && formData !== null"
              >
                {{ formData.market_id == 1 ? 'Residential' : 'Commercial' }}
              </span>
              <span
                v-else
              >
                Unknown
              </span>
            </td>
            <th>
              TSR ID
            </th>
            <td>
              {{ event.sales_agent ? event.sales_agent.tsr_id : 'Not Set' }}
            </td>
          </tr>
          <tr>
            <th>
              Vendor
            </th>
            <td>{{ event.vendor ? event.vendor.name : 'Not Set' }}</td>
            <th>
              Event Category
            </th>
            <td>{{ event.event_category_id == 2 ? 'Custom' : 'Energy' }}</td>
            <th>
              Sales Agent
            </th>
            <td>
              {{ event.sales_agent ? `${event.sales_agent.user.first_name} ${event.sales_agent.user.last_name}` : 'Not Set' }}
              <template v-if="event.sales_agent == null">
                <button
                  class="btn btn-sm btn-warning pull-right"
                  type="button"
                  @click="editSalesAgent"
                >
                  <span class="fa fa-pencil" />
                </button>
              </template>
            </td>
          </tr>
          <tr>
            <th>Live Script</th>
            <td>{{ event.script != null ? event.script.title : 'n/a' }}</td>
            <th>Digital Script</th>
            <td>{{ event.digital_script != null ? event.digital_script.title : 'n/a' }}</td>
            <th>Lead</th>
            <td><pre class="mb-0">{{ event.lead != null ? event.lead.external_lead_id : 'n/a' }}</pre></td>
          </tr>
          <tr>
            <th>
              Customer Phone
            </th>
            <td>{{ event.phone && event.phone.phone_number && event.phone.phone_number.phone_number ? formatPhone(event.phone.phone_number.phone_number) : 'Not Set' }}</td>
            <th>
              Customer Email
            </th>
            <td>{{ event.email && event.email.email_address.email_address ? event.email.email_address.email_address : 'None Provided' }}</td>
            <td colspan="2">
              <template v-if="!gatherReTPVInfo">
                <span
                  v-if="!showMarkReviewed"
                  class="pull-left"
                >
                  <strong>Reviewed:</strong> {{ tracking.completed_at }}, {{ tracking.first_name }} {{ tracking.last_name }}
                </span>
                <template v-else>
                  <form
                    :action="`/events/mark_as_reviewed/${event.id}`"
                    @submit.prevent="markReviewed"
                  >
                    <button
                      type="submit"
                      class="btn btn-sm btn-info pull-left"
                    >
                      <i
                        class="fa fa-check"
                        aria-hidden="true"
                      /> Mark as Reviewed
                    </button>
                  </form>
                </template>
              
                <button
                  v-if="roleId == 1"
                  type="button"
                  class="btn btn-sm btn-warning pull-right"
                  @click="setupReTPV"
                >
                  <i class="fa fa-refresh" /> ReTPV
                </button>
              </template>
              <template v-if="gatherReTPVInfo && !busy">
                <div class="border-info">
                  <label class="font-weight-bold">Select ReTPV Script</label>
                  <select
                    v-model="reTPVscript"
                    class="form-control"
                  >
                    <option
                      v-for="(s, s_i) in liveCallScripts"
                      :key="`script_${s_i}`"
                      :selected="s.id === event.script_id"
                      :value="s.id"
                    >
                      {{ s.id === event.script_id ? '(Current Script) ' : '' }}{{ s.title }}
                    </option>
                  </select>
                  <button
                    type="button"
                    class="btn btn-sm btn-warning mt-1 pull-right"
                    @click="initiateReTPV"
                  >
                    <span class="fa fa-refresh" /> Send ReTPV <span class="fa fa-send" />
                  </button>
                </div>
              </template>
              <template v-if="busy">
                <span class="fa fa-spinner fa-spin fa-2x" /> Loading Scripts...
              </template>
            </td>
          </tr>
          
          <tr>
            <td
              colspan="6"
              class="pt-2 pb-2 pr-4 pl-4 bg-dark text-white"
            >
              Quality Assurance <span class="badge badge-light">{{ event.notes.length }} note<template v-if="event.notes.length !== 1">s</template></span>
              <button
                type="button"
                class="btn btn-sm btn-info pull-right"
                @click="toggleQaNotesSection"
              >
                <span v-if="qanoteHidden"><i class="fa fa-eye" /> Show</span>
                <span v-else><i class="fa fa-eye-slash" /> Hide</span>
              </button>
            </td>
          </tr>
          <tr v-show="!qanoteHidden">
            <td colspan="6">
              <template
                v-if="event.notes !== null && event.notes instanceof Array && event.notes.length > 0"
              >
                <div
                  v-for="(en, i) in event.notes"
                  :key="i"
                  class="card"
                >
                  <div class="card-header">
                    <strong>{{ en.tpv_staff.first_name }} {{ en.tpv_staff.last_name }}</strong> said:
                  </div>
                  <div class="card-body">
                    {{ en.notes }}
                  </div>
                  <div class="card-footer font-sm">
                    <span class="pull-right">{{ en.created_at }}</span>
                  </div>
                </div>
              </template>
              <div class="card">
                <div class="card-body">
                  <select
                    v-model="qaAction"
                    class="form-control"
                  >
                    <option value="">
                      Choose QA Action
                    </option>
                    <option value="update">
                      Change Event Result
                    </option>
                    <option value="note">
                      Add Note to Event
                    </option>
                  </select>
                  <hr>
                  <form
                    v-if="qaAction == 'update'"
                    method="POST"
                    action="/events/qaupdate"
                  >
                    <input
                      type="hidden"
                      name="_token"
                      :value="csrf_token"
                    >
                    <input
                      type="hidden"
                      name="interaction_id"
                      :value="event.interactions[event.interactions.length - 1].id"
                    >
                    <input
                      type="hidden"
                      name="event_id"
                      :value="event.id"
                    >
                    <label>Change Event Result</label>
                    <select
                      ref="result"
                      v-model="tempResult"
                      name="result"
                      class="form-control"
                    >
                      <option :value="0">
                        Select New Result
                      </option>
                      <option
                        v-for="result in results"
                        :key="result.id"
                        :value="result.id"
                      >
                        {{ result.name }}
                      </option>
                    </select>
                    <div
                      v-if="tempResult === 2"
                    >
                      <select
                        name="disposition"
                        class="form-control"
                      >
                        <option :value="null">
                          Choose Disposition
                        </option>
                        <option
                          v-for="disposition in dispositions"
                          :key="disposition.id"
                          :value="disposition.id"
                        >
                          {{ (disposition.fraud_indicator == 1 ? '!! ' : '') + disposition.reason + (disposition.fraud_indicator == 1 ? ' !!' : '') }}
                        </option>
                      </select>
                    </div>
                    <select
                      name="call_review_type"
                      class="form-control"
                    >
                      <option value>
                        Select a Call Review Type
                      </option>
                      <optgroup
                        v-for="category in Object.keys(callReviewTypes)"
                        :key="category"
                        :label="category"
                      >
                        <option
                          v-for="callReviewType in callReviewTypes[category]"
                          :key="callReviewType.id"
                          :value="callReviewType.id"
                        >
                          {{ callReviewType.call_review_type }}
                        </option>
                      </optgroup>
                    </select>
                    <label>Reason for Change</label>
                    <textarea
                      name="notes"
                      class="form-control"
                    />
                    <button
                      type="submit"
                      class="btn btn-primary pull-right mt-1"
                    >
                      <i class="fa fa-save" /> Change Event Result
                    </button>
                  </form>
                  
                  <form
                    v-if="qaAction == 'note'"
                    :action="`/events/${event.id}/add_notes`"
                    method="POST"
                  >
                    <input
                      type="hidden"
                      name="_token"
                      :value="csrf_token"
                    >
                    <label>Notes</label>
                    <textarea
                      name="notes"
                      class="form-control"
                    />
                    <button
                      type="submit"
                      class="btn btn-primary pull-right mt-1"
                    >
                      <i
                        class="fa fa-plus"
                        aria-hidden="true"
                      /> Add Notes
                    </button>
                  </form>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div
        v-if="showAgentSelector"
        class="card"
      >
        <div class="card-header">
          Select Sales Agent
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-12">
              <input
                id="agent_search"
                v-model="agentSearch"
                class="form-control"
                type="text"
                placeholder="TSR ID"
                @keyup.enter="doAgentSearch"
              >
              <button
                type="button"
                class="btn btn-primary"
                :disabled="this.busy"
                @click="doAgentSearch"
              >
                <span class="fa fa-search" /> Search
                <span
                  v-if="busy"
                  class="fa fa-spinner fa-spin"
                />
              </button>
            </div>
          </div>
          <div
            v-if="possibleAgents.length > 0"
            class="row mt-4"
          >
            <div
              v-for="(agent, agent_i) in possibleAgents"
              :key="`agent-${agent_i}`"
              class="col-md-4"
            >
              <div class="card">
                <div class="card-header">
                  {{ agent.employer }}
                </div>
                <div class="card-body">
                  {{ agent.first_name }} {{ agent.last_name }}
                  <hr>
                  <button
                    class="btn btn-primary"
                    type="button"
                    :disabled="this.busy"
                    @click="selectAgent(agent.id, agent.employee_of_id, agent.office_id)"
                  >
                    Select this agent
                    <span
                      v-if="busy"
                      class="fa fa-spinner fa-spin"
                    />
                  </button>
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

export default {
    name: 'EventInfo',
    
    props: {
        event: {
            type: Object,
            required: true,
        },
        isAC: {
            type: Boolean,
            default: false,
        },
        tracking: {
            type: Object,
            default: () => null,
        },
        roleId: {
            type: Number,
            default: 0,
        },
        dispositions: {
            type: Array,
            default: () => [],
        },
        callReviewTypes: {
            type: Object,
            default: () => {},
        },
    },
    data() {
        return {
            liveCallScripts: [],
            reTPVscript: null,
            gatherReTPVInfo: false,
            busy: false,
            agentSearch: '',
            showAgentSelector: false,
            possibleAgents: [],
            qanoteHidden: true,
            tempResult: 0,
            qaAction: '',
            results: [
                {
                    id: 1,
                    name: 'Sale',
                },
                {
                    id: 2,
                    name: 'No Sale',
                },
                {
                    id: 3,
                    name: 'Closed',
                },
            ],
        };
    },
    computed: {
        showMarkReviewed() {
            let unreviewed = false;
            if (this.tracking !== null) {
                const reviewDate = this.tracking.completed_at;
                let latestFlag = null;
                for (let i = 0, len = this.event.interactions.length; i < len; i += 1) {
                    if (this.event.interactions[i].event_flags !== null && this.event.interactions[i].event_flags.length > 0) {
                        for (let n = 0, nlen = this.event.interactions[i].event_flags.length; n < nlen; n += 1) {
                            if (this.event.interactions[i].event_flags[n].reviewed_by == null) {
                                unreviewed = true;
                            }
                            if (this.event.interactions[i].event_flags[n].created_at > latestFlag) {
                                latestFlag = this.event.interactions[i].event_flags[n].created_at;
                            }
                        }
                    }
                }
                if (unreviewed) {
                    return true;
                }
                if (latestFlag !== null) {
                    if (latestFlag > reviewDate) {
                        return true;
                    } 
                    return false;
                } 
                return false;
            }
            return true;
        },
        csrf_token() {
            return window.csrf_token;
        },
        formData() {
            if (this.event.eztpv_id !== null) {
                if (this.event.eztpv !== null) {
                    if (this.event.eztpv.form_data !== null) {
                        return JSON.parse(this.event.eztpv.form_data);
                    }
                }
            }
            return null;
        },
    },
    
    methods: {
        getBrandScripts() {
            this.busy = true;
            axios.get(`/brands/${this.event.brand_id}/scripts`).then((res) => {
                this.busy = false;
                this.liveCallScripts = res.data;
            }).catch((e) => {
                this.busy = false;
                console.log('unable to get scripts for brand', this.event.brand_id);
            });
        },
        toggleQaNotesSection() {
            this.qanoteHidden = !this.qanoteHidden;
        },
        formatPhone(x) {
            if ('formatPhoneNumber' in window) {
                return window.formatPhoneNumber(x);
            }
            const number = x;
            if (number === undefined || number.length === 0) {
                return '';
            }

            const formatted = number.replace(
                /^\+?[1]?\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/,
                '($1) $2-$3',
            );

            return formatted;
        },
        markReviewed(e) {
            const toreview = window.document.getElementsByClassName('reviewbtn');
            if (toreview.length == 0) {
                if (confirm('Are you sure you want to mark this event as reviewed?')) {
                    this.$nextTick(() => {
                        $(e.target).submit();
                    });
                }
            }
            else {
                alert('There are Flagged Interactions that have not been marked as resolved');
                if ('scrollIntoViewIfNeeded' in toreview[0]) {
                    toreview[0].scrollIntoViewIfNeeded();
                }
            }
            return false;
        },
        setupReTPV() {
            this.gatherReTPVInfo = true;
            this.getBrandScripts();
            if (this.event.script_id != null) {
                this.reTPVscript = this.event.script_id;
            }
        },
        initiateReTPV() {
            if (this.reTPVscript == null) {
                window.alert('Please select ReTPV script first');
                return;
            }
            if (window.confirm('Are you sure you want to submit this record for ReTPV?')) {
                window.axios.post(`/events/${this.event.id}/ReTPV`, {
                    script: this.reTPVscript,
                })
                .then(() => {
                    window.alert('The record has been submitted for ReTPV');
                    window.location.href = `/events/${this.event.id}`;
                    return true;
                })
                .catch((e) => {
                    window.alert(`There was an issue submitting the record for ReTPV: ${e}`);
                });
            }
        },
        editSalesAgent() {
            this.showAgentSelector = true;
            this.$nextTick(() => {
                setTimeout(() => {
                    const searchb = document.getElementById('agent_search');
                    if (searchb) {
                        searchb.focus();
                    }
                }, 50);
            });
        },
        async doAgentSearch() {
            if (this.busy) {
                return;
            }
            this.busy = true;
            this.possibleAgents = [];

            const results = await axios.post('/qa/tsr-id-lookup', {
                brand: this.event.brand_id,
                search: this.agentSearch,
            });

            if (results.data instanceof Array) {
                if (results.data.length === 1) {
                    this.busy = false;
                    await this.selectAgent(results.data[0].id, results.data[0].employee_of_id, results.data[0].office_id);
                }
                else {
                    if (results.data.length === 0) {
                        alert('No Agents for this brand found using that TSR ID');
                    }
                    else {
                        this.possibleAgents = results.data;
                    }
                    this.busy = false;
                }
            }
            else {
                this.busy = false;
            }
            
        },
        async selectAgent(agent_id, vendor_id, office_id) {
            if (this.busy) {
                return;
            }
            this.busy = true;
            const results = await axios.post(`/events/${this.event.id}/update-sales-rep`, {
                agent_id,
                vendor_id,
                office_id,
            });

            if (results.data.errors !== false) {
                alert(`Error during update: ${results.data.errors}`);
            }
            else {
                window.location.reload();
            }
            this.busy = false;
        },
    },
};
</script>

<style scoped>
.border-bottom {
  border-bottom: 2px solid #ccc;
  width: 100%;
  display: block;
}

pre {
  font-size: 100%;
}
</style>
