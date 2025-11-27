<template>
  <div>
    <breadcrumb :items="breadcrumbs" />
    <div class="container-fluid mt-3">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <em class="fa fa-th-large" /> Event (#{{ event.id }})
            <span
              v-if="event.brand.does_recording_transfer == 1"
              :class="{'pull-right badge': true, 'badge-success': event.synced == 1, 'badge-danger': event.synced != 1}"
            >
              {{ event.synced == 1 ? 'Files Synced' : 'Files Not Synced' }}
            </span>
          </div>
          <div class="card-body p-0">
            <div
              v-if="flashMessage"
              class="alert alert-success"
            >
              <span class="fa fa-check-circle" />
              <em>{{ flashMessage }}</em>
            </div>

            <div
              v-if="flashErrorMessage"
              class="alert alert-danger"
            >
              <span class="fa fa-check-circle" />
              <em>{{ flashErrorMessage }}</em>
            </div>
            <div
              v-if="isAC"
              class="alert alert-info"
            >
              This record is an Agent Confirmation
            </div>

            <call-follow-up
              :event="event"
              :tracking="tracking"
              :dispositions="dispositions"
              :call-review-types="callReviewTypes"
              :qa-review="qaReview"
              :role-id="roleId"
              :review-interaction="reviewInteraction"
            />

            <products
              v-if="!isAC && event.products"
              :products="event.products"
              :event="event"
            />

            <custom-fields
              v-if="event.custom_field_storage != null && event.custom_field_storage !== undefined"
              :fields="event.custom_field_storage"
            />

            <contracts
              v-if="event.eztpv"
              :contracts="contracts"
              :eztpvid="event.eztpv_id"
              :has-digital="hasDigitalInteraction"
            />

            <triggered-alerts :alerts="alerts" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Breadcrumb from 'components/Breadcrumb';
import CallFollowUp from './CallFollowUp';
import TriggeredAlerts from './TriggeredAlerts';
import Products from './Products';
import Contracts from './Contracts';
import CustomFields from './CustomFields.vue';

export default {
    name: 'EventsShow',
    components: {
        CallFollowUp,
        TriggeredAlerts,
        Products,
        Contracts,
        Breadcrumb,
        'custom-fields': CustomFields,
    },
    props: {
        flashErrorMessage: {
            type: String,
            default: '',
        },
        flashMessage: {
            type: String,
            default: '',
        },
        errors: {
            type: Array,
            default: () => [],
        },
        action: {
            type: String,
            default: '',
        },
        event: {
            type: Object,
            default: () => ({}),
        },
        tracking: {
            type: Object,
            default: () => null,
        },
        alerts: {
            type: Array,
            default: () => [],
        },
        dispositions: {
            type: Array,
            default: () => [],
        },
        callReviewTypes: {
            type: Object,
            default: () => ({}),
        },
        roleId: {
            type: Number,
            default: 0,
        },
        qaReview: {
            type: Boolean,
            default: false,
        },
        reviewInteraction: {
            type: String,
            default: '',
        },
    },
    computed: {
        breadcrumbs() {
            if (!this.qaReview) {
                return [
                    { name: 'Home', url: '/' },
                    { name: 'Events', url: '/events' },
                    { name: `Event #${this.event.id}`, active: true },
                ];
            }
            return [
                { name: 'Home', url: '/' },
                { name: 'Call Followups', url: '/qa_review' },
                { name: `Event #${this.event.id}`, active: true },
            ];
        },
        isAC() {
            return this.event.agent_confirmation === 1;
        },
        category() {
            return this.event.event_category_id;
        },
        hasDigitalInteraction() {
            for (let i = 0, len = this.event.interactions.length; i < len; i += 1) {
                if (
                    this.event.interactions[i].interaction_type.name === 'digital'
                    && this.event.eztpv.has_digital === 1
                ) {
                    return true;
                }
            }
            return false;
        },
        contracts() {
            if (this.event && this.event.eztpv && this.event.eztpv.eztpv_docs) {
                if (this.event.eztpv.eztpv_docs instanceof Array) {
                    return this.event.eztpv.eztpv_docs;
                }
                const keys = Object.keys(this.event.eztpv.eztpv_docs);
                const out = [];
                for (let i = 0, len = keys.length; i < len; i += 1) {
                    if ('eztpv_id' in this.event.eztpv.eztpv_docs[keys[i]]) {
                        out.push(this.event.eztpv.eztpv_docs[keys[i]]);
                    }
                }
                return out;
            }
            return [];
        },
    },
    methods: {
        rmQA(e) {
            if (
                confirm('Are you sure you want to remove this QA from Call Review.')
            ) {
                $(e.target).submit();
            }
            return false;
        },
    },
};
</script>
