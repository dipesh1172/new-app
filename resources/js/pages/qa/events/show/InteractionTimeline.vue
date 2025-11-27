<template>
  <div class="col-12">
    <div class="card mb-0">
      <div class="card-header bg-dark text-white">
        Interaction Timeline
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table  table-bordered mb-0">
          <thead>
            <tr class="table-active">
              <th>Flagged</th>
              <th>Date</th>
              <th v-if="!isAC">Type</th>
              <th v-if="!isAC">Direction</th>
              <th>TPV Agent</th>
              <th v-if="!isAC">Result</th>
              <th class="text-center" title="Includes all time from screen pop to disposition">Duration&nbsp;<i class="fa fa-info-circle" /></th>
              <th class="text-center">Recording</th>
              <th class="text-center">Tools</th>
            </tr>
          </thead>
          <tbody v-if="interactions.length">
            <template v-for="(interaction, i_i) in interactions">
              <tr
                v-if="i_i > 0 && interaction.source && interaction.source.source == 'Live'"
                :key="interaction.id + '-row1'"
              >
                <td colspan="9" class="p-1"></td>
              </tr>
              <interaction
                :key="`interaction-${i_i}`"
                :interaction="interaction"
                :child-interactions="subInteractions(interaction.id)"
                :index="i_i"
                :event="event"
                :is-ac="isAC"
                :highlight="includesInteractionToReview(interaction) && interactions.length > 1"
                :dispositions="dispositions"
                :call-review-types="callReviewTypes"
                @setupStatusModal="setupStatusModal"
              />
              <template v-for="(sinteraction, si_i) in subInteractions(interaction.id)">
                <interaction
                  :key="`child-interaction-${si_i + i_i}`"
                  :interaction="sinteraction"
                  :index="si_i + i_i + 1"
                  :event="event"
                  :is-ac="isAC"
                  :highlight="includesInteractionToReview(interaction) && interactions.length > 1"
                  :dispositions="dispositions"
                  :call-review-types="callReviewTypes"
                  @setupStatusModal="setupStatusModal"
                />
              </template>
              <tr
                v-if="i_i > 0 && interaction.source && interaction.source.source == 'Live' && i_i < (subInteractions(interaction.id).length - 1)"
                :key="interaction.id + '-row2'"
              >
                <td
                  colspan="9"
                  class="p-1"
                >
                  <hr class="pt-0 pb-0 mt-1 mb-1">
                </td>
              </tr>
            </template>
          </tbody>
          <tbody v-else>
            <tr>
              <td 
                colspan="8" 
                class="text-center"
              >
                No interactions were found.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div 
      id="agentStatusModal"
      class="modal fade"
      data-backdrop="false"
      tabindex="-1" 
      role="dialog"
      style="background-color: rgba(0, 0, 0, 0.5);" 
    >
      <div 
        class="modal-dialog" 
        role="document"
        style="margin: 60px auto;" 
      >
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              Agent Status Log
            </h5>
            <button 
              type="button" 
              class="close" 
              data-dismiss="modal"
              aria-label="Close" 
              @click="clearStatuses"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <ul>
              <li 
                v-for="(item, i) in agentStatuses" 
                :key="i"
              >
                {{ item.created_at }} {{ item.event }}
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Interaction from './Interaction.vue';

export default {
    name: 'InteractionTimeline',
    components: {
        'interaction': Interaction,
    },
    props: {
        rawInteractions: {
            type: Array,
            default() {
                return [];
            },
        },
        isAC: {
            type: Boolean,
            default: false,
        },
        event: {
            type: Object,
            required: true,
        },
        dispositions: {
            type: Array,
            default: () => [],
        },
        callReviewTypes: {
            type: Object,
            default: () => ({}),
        },
        reviewInteraction: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            agentStatuses: [],
        };
    },
    computed: {
        interactions() {
            const firstStage = this.rawInteractions.filter((i) => i.parent_interaction_id == null);
            const inverse = this.rawInteractions.filter((i) => i.parent_interaction_id !== null);
            for (let i = 0, len = inverse.length; i < len; i += 1) {
                const interactionA = inverse[i];
                let pass = false;
                for (let m = 0, mlen = firstStage.length; m < mlen; m += 1) {
                    if (interactionA.parent_interaction_id === firstStage[m].id) {
                        pass = true;
                    }
                }
                if (!pass) {
                    firstStage.push(interactionA);
                }
            }
            const outInteractions = Array.from(firstStage);

            const trackingInteractions = [];

            if (this.event.digital_submissions instanceof Array) {
                for (let i = 0, len = this.event.digital_submissions.length; i < len; i += 1) {
                    
                    trackingInteractions.push({
                        id: 'digital-complete',
                        created_at: this.event.digital_submissions[i].created_at,
                        interaction_type: {
                            name: 'Digital Completed',
                        },
                        session_id: '',
                        tpv_agent: null,
                        parent_interaction_id: null,
                        interaction_time: null,
                        source: {
                            source: 'tracking',
                        },
                    });
                    
                }
            }

            if (this.event.eztpv != null && this.event.eztpv != undefined) {
                
                if (this.event.eztpv.landing_accessed != null) {
                    trackingInteractions.push({
                        id: 'landing-page-1',
                        created_at: this.event.eztpv.landing_accessed,
                        interaction_type: {
                            name: 'Contract Landing Page Accessed',
                        },
                        session_id: '',
                        tpv_agent: null,
                        parent_interaction_id: null,
                        interaction_time: null,
                        source: {
                            source: 'tracking',
                        },
                    });
                }

                if (this.event.eztpv.signature_landing_accessed != null) {
                    trackingInteractions.push({
                        id: 'landing-page-2',
                        created_at: this.event.eztpv.signature_landing_accessed,
                        interaction_type: {
                            name: 'Customer Signature Landing Page Accessed',
                        },
                        session_id: '',
                        tpv_agent: null,
                        parent_interaction_id: null,
                        interaction_time: null,
                        source: {
                            source: 'tracking',
                        },
                    });
                }

                if (this.event.eztpv.signature_date != null) {
                    trackingInteractions.push({
                        id: 'landing-page-3',
                        created_at: this.event.eztpv.signature_date,
                        interaction_type: {
                            name: 'Customer Signature Saved',
                        },
                        session_id: '',
                        tpv_agent: null,
                        parent_interaction_id: null,
                        interaction_time: null,
                        source: {
                            source: 'tracking',
                        },
                    });
                }

                if (this.event.eztpv.signature_customer != null) {
                    trackingInteractions.push({
                        id: 'landing-page-3',
                        created_at: this.event.eztpv.signature_customer.created_at,
                        interaction_type: {
                            name: 'Customer Signature Saved',
                        },
                        session_id: '',
                        tpv_agent: null,
                        parent_interaction_id: null,
                        interaction_time: null,
                        source: {
                            source: 'tracking',
                        },
                    });
                }

                if (this.event.eztpv.signature2_date != null) {
                    trackingInteractions.push({
                        id: 'landing-page-4',
                        created_at: this.event.eztpv.signature2_date,
                        interaction_type: {
                            name: 'Agent Signature Saved',
                        },
                        session_id: '',
                        tpv_agent: null,
                        parent_interaction_id: null,
                        interaction_time: null,
                        source: {
                            source: 'tracking',
                        },
                    });
                }

                if (this.event.eztpv.signature_agent != null) {
                    trackingInteractions.push({
                        id: 'landing-page-4',
                        created_at: this.event.eztpv.signature_agent.created_at,
                        interaction_type: {
                            name: 'Agent Signature Saved',
                        },
                        session_id: '',
                        tpv_agent: null,
                        parent_interaction_id: null,
                        interaction_time: null,
                        source: {
                            source: 'tracking',
                        },
                    });
                }

                for (let i = 0, len = trackingInteractions.length; i < len; i += 1) {
                    outInteractions.push(trackingInteractions[i]);
                }
            }
            return outInteractions.sort((a, b) => {
                
                if (b.source && b.source.source === 'tracking' && a.source && a.source.source === 'Digital' && a.created_at === b.created_at) {
                    if (b.interaction_type.name === 'Customer Signature Landing Page Accessed') {
                        return 1;
                    }
                }
                if (a.source && a.source.source === 'tracking' && b.source && b.source.source === 'Digital' && a.created_at === b.created_at) {
                    if (a.interaction_type.name === 'Customer Signature Landing Page Accessed') {
                        return -1;
                    }
                }
                if (a.created_at < b.created_at) {
                    return -1;
                }
                if (a.created_at > b.created_at) {
                    return 1;
                }
                
                return 0;
            });
        },
    },
    methods: {
        includesInteractionToReview(interaction) {
            if (interaction.id === this.reviewInteraction) {
                return true;
            }
            const children = this.subInteractions(interaction.id);
            if (children !== null && children.length > 0) {
                for (let i = 0, len = children.length; i < len; i += 1) {
                    if (children[i].id === this.reviewInteraction) {
                        return true;
                    }
                }
            }
            return false;
        },
        subInteractions(parent) {
            return this.rawInteractions.filter((i) => i.parent_interaction_id == parent);
        },
        clearStatuses() {
            this.$emit('clear-statuses');
        },
        setupStatusModal(x) {
            setTimeout(() => {
                // $('.modal')[0].scrollIntoView();
                $('#agentStatusModal').modal('show');
            }, 100);
            this.agentStatuses = x;
        },
    },
};
</script>
