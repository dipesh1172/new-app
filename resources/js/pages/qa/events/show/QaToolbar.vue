<template>
  <div
    v-if="isUpdatable"
    class="container"
  >
    <div class="row">
      <div
        v-if="errors.length"
        class="alert alert-danger"
      >
        <ul>
          <li
            v-for="(error, i) in errors"
            :key="i"
          >
            {{ error }}
          </li>
        </ul>
      </div>
    </div>
    <div class="row">
      <a
        :href="`/interactions/transcript/${interaction.id}/${event.id}`"
        class="btn btn-sm btn-info"
        target="_blank"
      >
        <i class="fa fa-list-ol" /> View Transcript
      </a>
    </div>
    <div
      v-if="hasEditor && !editorShown"
      class="row mt-1"
    >
      <button
        type="button"
        class="btn btn-info"
        @click="editorShown = true"
      >
        <i class="fa fa-pencil" /> Update Status
      </button>
    </div>
    <div
      v-if="hasEditor && editorShown"
      class="row mt-1"
    >
      <form
        v-if="!isEmulated && interaction.recordings.length > 0"
        method="post"
        action="/qa/interaction-update"
      >
        <input
          :value="csrf_token"
          type="hidden"
          name="_token"
        >
        <input
          type="hidden"
          name="interaction"
          :value="interaction.id"
        >
        <button
          type="submit"
          class="btn btn-danger mb-1"
        >
          Wrong Recording
        </button>
      </form>
      <form
        :method="method.toUpperCase() === 'PUT' ? 'POST' : method"
        :action="action"
        autocomplete="off"
      >
        <input
          v-if="method.toUpperCase() === 'PUT'"
          name="_method"
          type="hidden"
          value="PUT"
        >
        <input
          :value="csrf_token"
          type="hidden"
          name="_token"
        >
        <input
          type="hidden"
          name="command"
          value="update"
        >
        <input
          :value="interaction.id"
          type="hidden"
          name="interaction_id"
        >
        <input
          :value="interaction.event_id"
          type="hidden"
          name="event_id"
        >

        <div class="">
          <select
            ref="result"
            :disabled="interaction.event_result_id != 3"
            :value="tempResult"
            name="result"
            class="form-control"
            @change="updateResult"
          >
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
              :value="interaction.disposition_id"
            >
              <option
                v-for="disposition in dispositions"
                :key="disposition.id"
                :value="disposition.id"
              >
                {{ (disposition.fraud_indicator == 1 ? '!! ' : '') + disposition.reason + (disposition.fraud_indicator == 1 ? ' !!' : '') }}
              </option>
            </select>
          </div>

          <div
            v-if="tempResult > 1 && isCallReview"
          >
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
          </div>
          <textarea
            name="notes"
            placeholder="QA Notes"
            class="form-control"
          />
        </div>
        <div class="mt-1">
          <button
            type="button"
            class="btn btn-secondary pull-left"
            @click="editorShown = false"
          >
            <i class="fa fa-close" />
          </button>
          <button
            type="submit"
            class="btn btn-primary pull-right"
            @click="checkResult"
          >
            Update <i class="fa fa-arrow-right" />
          </button>
        </div>
      </form>
    </div>
    <div
      v-if="!isEmulated && interaction.recordings.length == 0"
      class="row mt-1"
    >
      <a
        v-if="interaction.tpv_agent !== null"
        class="btn btn-info"
        target="_blank"
        :href="`/qa/${interaction.interaction_type_id == 1 ? 'in-' : ''}recording-search/${interaction.tpv_agent.username}/${getDate(interaction.created_at)}?interaction=${interaction.id}&strict=true`"
      ><i class="fa fa-search" /> Search for Recording</a>
    </div>
  </div>
</template>

<script>
export default {
    name: 'QaReviewToolbar',
    props: {
        isEmulated: {
            type: Boolean,
            default: false,
        },
        errors: {
            type: Array,
            default: () => [],
        },
        action: {
            type: String,
            default: '',
        },
        method: {
            type: String,
            default: '',
        },
        dispositions: {
            type: Array,
            default: () => [],
        },
        callReviewTypes: {
            type: Object,
            default: () => ({}),
        },
        interaction: {
            type: Object,
            default: () => ({}),
        },
        event: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        return {
            hasEditor: true,
            editorShown: false,
            tempResult: null,
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
            result: '',
            updatableTypes: ['call_inbound', 'call_outbound', 'eztpv', 'digital', 'hrtpv', 'ivr_script'],
        };
    },
    computed: {
        isUpdatable() {
            if (this.interaction !== null 
                && 'interaction_type' in this.interaction 
                && this.interaction.interaction_type !== null 
                && 'name' in this.interaction.interaction_type
            ) {
                const name = this.interaction.interaction_type.name.toLowerCase();
                return this.updatableTypes.includes(name);
            }
            return false;
        },
        csrf_token() {
            return window.csrf_token;
        },
    },
    watch: {
        interaction(v) {
            if (v !== null && v !== undefined) {
                this.tempResult = v.event_result_id;
            }
        },
    },
    mounted() {
        if (this.interaction !== null && this.interaction !== undefined) {
            this.tempResult = this.interaction.event_result_id;
        }
        const name = this.interaction.interaction_type.name.toLowerCase();
        if (name === 'ivr_script') {
            this.hasEditor = false;
        }
    },
    methods: {
        getDate(i) {
            return i.split(' ')[0];
        },
        updateResult(e) {
            this.tempResult = parseInt(e.target.value, 10);
        },
        checkResult(e) {
            if (this.tempResult == '' || this.tempResult == null) {
                alert('You must select an option to change this interaction status.');
                e.preventDefault();
                return false;
            }
            return true;
        },
        isCallReview: function() {
            const eventFlagCount = this.interaction.event_flags.length;

            const flag_reason_id = this.interaction.event_flags[eventFlagCount - 1]
                .flag_reason_id;
            if (
                flag_reason_id == '5dcdd6fb-faa2-4f3a-8e87-cc98db16b8b0' // Closed Calls
                || flag_reason_id == '0afb2c0a-ffd1-4488-a258-eb628679e228' // Call Unusually Long
                || flag_reason_id == '00000000000000000000000000000000' // Final Disposition
            ) {
                return false;
            }

            return true;
        },
    },
};
</script>
