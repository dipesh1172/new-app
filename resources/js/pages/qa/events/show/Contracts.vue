<template>
  <div class="card mb-0">
    <div class="card-header bg-dark text-white">
      Attachments
      <div class="alert alert-warning p-0 mb-0 pl-1 pr-1 pull-right">
        <strong>NOTICE:</strong> Contracts are not processed and delivered until the result is a Good Sale.
      </div>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-striped table-hover table-bordered mb-0">
        <thead>
          <tr class="table-active">
            <th>Date</th>
            <th>Type</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-if="contracts.length === 0">
            <td colspan="3" class="text-center">No contracts were found.</td>
          </tr>
          <tr
            v-for="(contract, i) in contracts.filter(c => c.uploads && c.uploads.deleted_at == null && c.uploads.filename)"
            v-else
            :key="i"
          >
            <td>{{ contract.created_at }}</td>
            <td v-if="contract.uploads.type && contract.uploads.type.upload_type">
              {{ contract.uploads.type.upload_type }}
              <template v-if="contract.uploads.deleted_at != null">
                (deleted)
              </template>
            </td>
            <td v-else>
              --
            </td>
            <td width="150">
              <a
                :href="`${AWS_CLOUDFRONT}/${contract.uploads.filename}`"
                :class="{'btn': true, 'btn-success': contract.uploads.deleted_at == null, 'btn-danger': contract.uploads.deleted_at != null}"
                target="_blank"
              >
                <i
                  class="fa fa-download"
                  aria-hidden="true"
                /> Download
              </a>
            </td>
          </tr>
          <tr v-if="contracts.length > 0 && hasDigital">
            <td>{{ contracts[0].created_at }}</td>
            <td>Electronic Transcription Summary</td>
            <td>
              <a
                :href="`${clients_url}/summary/${eztpvid}`"
                class="btn btn-success"
                target="_blank"
              ><i
                class="fa fa-eye"
                aria-hidden="true"
              /> View</a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { mapState } from 'vuex';

export default {
    name: 'QaEventsContracts',
    props: {
        contracts: {
            type: Array,
            default: () => [],
        },
        eztpvid: {
            type: String,
            default: '',
        },
        hasDigital: {
            type: Boolean,
            default: false,
        },
    },
    computed: {
        ...mapState(['AWS_CLOUDFRONT']),
        clients_url() {
            const currentUrl = window.location.href;
            if (currentUrl.includes('staging')) {
                return 'https://clients.staging.tpvhub.com';
            }
            return 'https://clients.tpvhub.com';
        },
    },
};
</script>
