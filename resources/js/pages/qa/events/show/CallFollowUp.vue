<template>
  <div class="row">
    <div class="col-12">
      <event-info
        :event="event"
        :is-ac="isAC"
        :tracking="tracking"
        :role-id="roleId"
        :dispositions="dispositions"
        :call-review-types="callReviewTypes"
      />
    </div>

    <interaction-timeline
      :raw-interactions="event.interactions"
      :is-ac="isAC"
      :event="event"
      :dispositions="dispositions"
      :call-review-types="callReviewTypes"
      :review-interaction="reviewInteraction"
      @clear-statuses="clearStatuses"
    />
  </div>
</template>
<style scoped>
.text-sm {
  font-size: 0.875rem;
}
.modal {
  position: absolute;
  z-index: 2000;
}
</style>

<script>
import { mapState } from 'vuex';
import EventInfo from './EventInfo.vue';
import InteractionTimeline from './InteractionTimeline.vue';

export default {
    name: 'QaReviewCallFollowUp',
    components: {
        'event-info': EventInfo,
        'interaction-timeline': InteractionTimeline,
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
        event: {
            type: Object,
            default: () => ({}),
        },
        tracking: {
            type: Object,
            default: () => null,
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
    data() {
        return {
            active_row: 0,
            agentStatuses: [],
        };
    },
    computed: {
        ...mapState(['AWS_CLOUDFRONT']),
        isAC() {
            return this.event.agent_confirmation === 1;
        },
    },
    methods: {
        clearStatuses() {
            this.agentStatuses = [];
        },
        
    },
    
};
</script>
