<template>
  <tr
    :key="interaction.id + '-row'"
    :class="{'bg-light': interaction_index %2 == 1 && !highlight, 'text-dark bg-warning-light': highlight}"
  >
    <td>
        &nbsp;
    </td>
    <td>{{ interaction.created_at }}</td>
    <td v-if="!isAC && interaction.source">
      {{ interaction.source.source }}
    </td>
    <td v-else-if="!isAC">
      &nbsp;
    </td>
    <td v-if="!isAC">
      <template v-if="interaction.interaction_type_id == 22">
        <span v-if="interaction.notes && typeof(interaction.notes) != 'string' && 'source' in interaction.notes">
          {{ interaction.notes.source }}
        </span>
        <span v-else>
          Unknown Tracking
        </span>
      </template>
      <template v-else>
        {{ interaction.interaction_type.name }}
        <small
          v-if="is_sales_browser"
          class="badge badge-warning"
        >Completed on Sales Agent Device</small>
        <span v-if="interaction.notes && typeof(interaction.notes) != 'string' && 'dnis' in interaction.notes">
          <br><strong>DNIS: </strong> {{ formatPhone(interaction.notes.dnis) }}
        </span>
        <span v-if="interaction.notes && typeof(interaction.notes) != 'string' && 'ani' in interaction.notes">
          <br><strong>ANI: </strong> {{ formatPhone(interaction.notes.ani) }}
        </span>
      </template>
    </td>
    <td v-if="interaction.tpv_agent">
      {{ interaction.tpv_agent.first_name }} {{ interaction.tpv_agent.last_name }}
      <span
        class="badge badge-info"
        title="Agent ID"
      >{{ interaction.tpv_agent.username }}</span>
      <button
        v-if="interaction.agent_statuses.length > 0"
        type="button"
        data-toggle="modal"
        data-target="#agentStatusModal"
        class="btn btn-sm btn-info"
        title="Agent Log"
        @click="setupStatusModal(interaction.agent_statuses)"
      >
        <em class="fa fa-eye" />
      </button>
    </td>
    <td v-else>
                &nbsp;
    </td>
    <td v-if="!isAC && interaction.result">
      <span
        v-if="isEmulated"
        class="badge badge-danger"
      >Emulated Call</span>
      <span
        v-if="interaction.result.result === 'Sale'"
        class="badge badge-success"
      >
        {{ interaction.result.result }}
      </span>
      <span v-else-if="interaction.result.result === 'No Sale'">
        <span class="badge badge-warning">
          {{ interaction.result.result }}
        </span>

        <span v-if="interaction.disposition">
          <br><br>
          <small class="text-muted">
            {{ interaction.disposition.reason }}
          </small>
        </span>
      </span>
      <span
        v-else-if="interaction.result.result === 'Closed'"
        class="badge badge-danger"
      >
        {{ interaction.result.result }}
      </span>
      <span v-else>--</span>

      <span v-if="interaction.event_flags.length > 0">
        <br><br>

        <div
          v-for="(flag, i) in interaction.event_flags"
          :key="i"
          class="mb-1"
        >
          <div
            v-if="typeof(flag.notes) !== 'string' && flag.notes && (flag.notes['tpv-comment'] || flag.notes['tpv-indicated-issue-start'])"
            class="card p-0"
          >
            <div class="card-header p-1">
              <em class="fa fa-flag-checkered" /> {{ flag.flag_reason.description }}
            </div>
            <div class="card-body p-1 alert-warning">

              <div class="card-text text-sm">
                <blockquote class="blockquote text-sm mb-0">
                  <span v-if="flag.notes['tpv-comment']">
                    {{ flag.notes['tpv-comment'] }}
                  </span>

                  <footer
                    v-if="flag.notes['tpv-indicated-issue-start']"
                    class="blockquote-footer"
                  >
                    Reported At {{ flag.notes['tpv-indicated-issue-start'] }} seconds
                    <template v-if="flag.reviewed_by != null">
                      <br>
                      <template v-if="flag.reviewed_by != null && flag.reviewer != null && flag.reviewer != undefined">
                        <strong>Reviewed by: {{ flag.reviewer.first_name }} {{ flag.reviewer.middle_name }} {{ flag.reviewer.last_name }}</strong>
                      </template>
                      <template v-else>
                        Unknown reviewer
                      </template>
                      {{ flag.updated_at }}
                    </template>
                    <template v-else>
                      <br>
                      <a
                      
                        class="btn btn-info reviewbtn"
                        :href="`/events/mark_as_reviewed/${event.id}?flag=${flag.id}`"
                      ><span class="fa fa-check" /> Mark as Reviewed</a>
                    </template>
                  </footer>
                </blockquote>
              </div>
            </div>
          </div>
          <div
            v-if="typeof(flag.notes) === 'string'"
            class="card p-0"
          >
            <div class="card-header p-1"><em class="fa fa-flag" /> {{ flag.flag_reason.description }}</div>
            <div class="card-body p-1 alert-warning">
              <div class="card-text text-sm">
                <blockquote class="blockquote text-sm mb-0">
                  {{ flag.notes }}
                  <footer class="blockquote-footer">
                    <template v-if="flag.reviewed_by != null">
                      <br>
                      <template v-if="flag.reviewed_by != null && flag.reviewer != null && flag.reviewer != undefined">
                        <strong>Reviewed by: {{ flag.reviewer.first_name }} {{ flag.reviewer.middle_name }} {{ flag.reviewer.last_name }}</strong>
                      </template>
                      <template v-else>
                        Unknown reviewer
                      </template>
                      {{ flag.updated_at }}
                    </template>
                    <template v-else>
                      <br>
                      <a
                      
                        class="btn btn-info reviewbtn"
                        :href="`/events/mark_as_reviewed/${event.id}?flag=${flag.id}`"
                      ><span class="fa fa-check" /> Mark as Reviewed</a>
                    </template>
                  </footer>
                </blockquote>
              </div>
            </div>
          </div>
        </div>
      </span>
    </td>
    <td v-else-if="!isAC">
                &nbsp;
      <span
        v-if="isEmulated"
        class="badge badge-danger"
      >Emulated Call</span>
    </td>
    <td class="text-center">
      <template v-if="interaction.interaction_type.name === 'digital' && digitalCompletionTime !== false">
        {{ digitalCompletionTime }}
      </template>
      <template v-else>
        {{ secondsToMinutes(parseInt(interaction.interaction_time * 60, 10)) }}
      </template>
    </td>
    <td class="text-center">
      <template v-if="interaction.interaction_type.name == 'ivr_script' && recording !== null">
        <div
          class="card"
        >
          <div class="card-header">
            Call Recording
          </div>
          <div class="card-body">
            <audio
              controls
            >
              <source :src="`${cloudfront}/${recording.recording}`">
              Your browser does not support the audio element. <a :href="`${cloudfront}/${recording.recording}`">Download</a>
            </audio>
          </div>
        </div>
      </template>
      <template v-else>
        <audio
          v-if="interaction.recordings && interaction.recordings[0] && interaction.recordings[0].recording"
          controls
        >
          <source v-for="asrc in audio_sources" :src="asrc.src" :name="asrc.name">
          Your browser does not support the audio element. <a :href="`${cloudfront}/${interaction.recordings[0].recording}`">Download</a>
        </audio>
        <template v-else-if="interaction.recordings && interaction.recordings[0] && interaction.recordings[0].remote_status">
          {{ interaction.recordings[0].remote_status }} - {{ interaction.recordings[0].remote_error_code }}
        </template>
        <template v-else>
          <span
            v-if="isEmulated"
            class="badge badge-warning"
          >Emulated calls are<br>not recorded</span>
          <template v-else>
            <a
              v-if="interaction.tpv_agent !== null && interaction.source !== null"
              class="btn btn-info"
              target="_blank"
              :href="`/qa/${interaction.interaction_type_id == 1 ? 'in-' : ''}recording-search/${interaction.tpv_agent.username}/${getDate(interaction.created_at)}?interaction=${interaction.id}&strict=true`"
            ><em class="fa fa-search" /></a>
            <template v-else>
              --
            </template>
          </template>
        </template>
      </template>
      <template v-if="interaction.notes && interaction.notes.logrocket && interaction.notes.logrocket !== 'Session quota exceeded. Please upgrade your plan.'">
        <br>
        <a
          class="btn btn-success"
          :href="interaction.notes.logrocket"
          target="_blank"
        >View LR <span class="fa fa-play" /></a>
      </template>
    </td>
    <td>
      <qa-toolbar
        :is-emulated="isEmulated == true"
        :dispositions="dispositions"
        :call-review-types="callReviewTypes"
        :interaction="interaction"
        :event="event"
        action="/events/qaupdate"
        method="POST"
      />
    </td>
  </tr>
</template>

<script>
import QaToolbar from './QaToolbar.vue';

export default {
    name: 'Interaction',
    components: {
        'qa-toolbar': QaToolbar,
    },
    props: {
        interaction: {
            type: Object,
            required: true,
        },
        index: {
            type: Number,
            default: 0,
        },
        childInteractions: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },
        event: {
            type: Object,
            required: true,
        },
        highlight: {
            type: Boolean,
            default: false,
        },
        isAC: {
            type: Boolean,
            default: false,
        },
        dispositions: {
            type: Array,
            default: () => [],
        },
        callReviewTypes: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
      return { 
        audio_sources: [],
        signed_url: ''
      }
    },
    async mounted() {
      this.audio_sources.push({ name: "focus", src:`${this.cloudfront}/${this.interaction.recordings[0].recording}`})
      this.audio_sources.push({ name: "motionServer", src:`${this.motionFile}${this.interaction.recordings[0].recording}`})
      this.audio_sources.push({ name: "motions3Bucket", src:`${this.motionS3Bucket}${this.interaction.recordings[0].recording}`})
       
      await this.motionS3Path();
      this.audio_sources.push({ name: "motionss3BucketSigned", src: this.signed_url })
     
    },
    computed: {
        recording() {
            return this.getIvrWholeCallRecording(this.interaction.recordings);
        },
        isEmulated() {
            if (this.interaction.session_id !== null
                && this.interaction.session_id.startsWith('DEV')) {
                return true;
            }
            if ('notes' in this.interaction && this.interaction.notes !== null) {
                let notes = null;
                if (typeof(this.interaction.notes) === 'string') {
                    notes = JSON.parse(this.interaction.notes);
                }
                else {
                    notes = this.interaction.notes;
                }
                if (notes !== null) {
                    if ('devSession' in notes) {
                        return notes.devSession === 'true' || notes.devSession === true;
                    }
                }

            }
            return false;
        },
        interaction_index() {
            return this.index;
        },

        is_sales_browser() {

            // Revert back the old code logic on Sammi's request 07/03/2024

            if ('notes' in this.interaction && this.interaction.notes !== null) {
                let notes = null;
                if (typeof(this.interaction.notes) === 'string') {
                    notes = JSON.parse(this.interaction.notes);
                }
                else {
                    notes = this.interaction.notes;
                }
                if (notes !== null) {
                    if ('is_sales_browser' in notes) {
                        return notes.is_sales_browser === 'true' || notes.is_sales_browser === true;
                    }
                }

            }
            return false;
            
            // New code logic - 2023/11/14 Verica N - We are using the old logic for now.
            // if(this.event.eztpv && this.event.eztpv.ip_addr && this.event.digital_submissions[0] && this.event.digital_submissions[0].ip_addr)
            // {
            //   return (this.interaction.interaction_type_id === 6 && this.event.eztpv.ip_addr === this.int2ip(this.event.digital_submissions[0].ip_addr));
            // }
            // return false;
        },
        digitalCompletionTime() {
            if ('notes' in this.interaction && this.interaction.notes !== null) {
                let notes = null;
                if (typeof(this.interaction.notes) === 'string') {
                    notes = JSON.parse(this.interaction.notes);
                }
                else {
                    notes = this.interaction.notes;
                }
                if (notes !== null) {
                    if ('completion_time' in notes) {
                        if (notes.completion_time > 0) {
                            return this.secondsToMinutes(notes.completion_time);
                        }
                    }
                }

            }
            return false;
        },
        cloudfront() {
            if ('AWS_CLOUDFRONT' in window) {
                return window.AWS_CLOUDFRONT;
            }
            return null;
        },
        motionFile(){
            if ('MOTION_FILE_URL' in window) {
                return window.MOTION_FILE_URL;
            }
            return null;
        },
        motionS3Bucket(){
            if ('MOTION_S3_URL' in window) {
              return window.MOTION_S3_BUCKET;
            }
            return null;
        },
        


    },
    methods: {
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

        async motionS3Path(){
           let url = '';
            if ('MOTION_SIGNED_URL' in window) {
                try {
                    const response = await axios.get(`${window.MOTION_SIGNED_URL}/${this.interaction.session_call_id}.wav`);

                    this.signed_url = response.data;
                }
                catch(e) {
                    console.error('motionS3Path Error', e);
                }
            }
        }, 

        setupStatusModal(x) {
            this.$emit('setupStatusModal', x);
        },
        getDate(i) {
            return i.split(' ')[0];
        },
        secondsToMinutes(secs) {
            if (secs != null && secs !== undefined && secs > 0) {
                if (secs < 60) {
                    return `0m ${secs}s`;
                }
                let minutes = 1;
                let seconds = secs - 60;
                while (seconds > 59) {
                    seconds -= 60;
                    minutes += 1;
                }
                return `${minutes}m ${seconds}s`;
            }
            return '';
        },
        getIvrWholeCallRecording(recordings) {
            if (recordings === null || recordings == undefined || recordings.length === 0) {
                return null;
            }
            if (recordings.length === 1) {
                return recordings[0];
            }
            const copy = JSON.parse(JSON.stringify(recordings)).sort((a, b) => {
                if (a.duration < b.duration) {
                    return -1;
                }
                if (a.duration > b.duration) {
                    return 1;
                }
                return 0;
            }).reverse();
            return copy[0];
        },
        // Converts long int to IP.
        // from: https://gist.github.com/jppommet/5708697
        // int2ip (ipInt) {
        //     if (!ipInt || isNaN(ipInt)) { return '--'; }
        //     ipInt = parseInt(ipInt);
        //     return ( (ipInt>>>24) +'.' + (ipInt>>16 & 255) +'.' + (ipInt>>8 & 255) +'.' + (ipInt & 255) );
        // },
    },
};
</script>

<style scoped>
.bg-warning-light {
  background-color: #fff3cd;
}
</style>
