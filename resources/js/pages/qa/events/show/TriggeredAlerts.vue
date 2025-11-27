<template>
  <div class="card mb-0">
    <div class="card-header bg-dark text-white">
      Triggered Alerts
      <div class="alert alert-warning p-0 mb-0 pl-1 pr-1 pull-right">
        This list contains all possible alerts, only alerts requested by the client are sent.
      </div>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-striped table-bordered mb-0">
        <thead>
          <tr class="table-active">
            <th>Times Triggered</th>
            <th>When</th>
            <th>Alert Type</th>
            <th>Raw Data</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="alerts.length == 0">
            <td 
              colspan="4" 
              class="text-center"
            >
              No Alerts were found.
            </td>
          </tr>
          <tr
            v-for="(alert, i) in alerts"
            v-if="alerts.length > 0"
            :key="i"
          >
            <td class="text-center">
              {{ alertData(i).data.length }}
            </td>
            <td>
              <div 
                v-for="(item, n) in alertData(i).data" 
                :key="n"
              >
                <span v-if="item.when">
                  {{ typeof(item.when) === 'string' ? item.when : item.when.date }}
                </span>
                <span v-else>
                  <span class="badge badge-warning">
                    Unknown, on/after {{ alert.created_at }}
                  </span>
                </span>
              </div>
            </td>
            <td>{{ alert.client_alert.title }}</td>
            <td>
              <button
                :data-target="`#alert-${alert.id}`"
                class="btn btn-warning"
                data-toggle="collapse"
                type="button"
              >
                <i class="fa fa-eye" /> Hide / Show Data
              </button>
              <pre
                :id="`alert-${alert.id}`"
                style="border: 1px solid;"
                class="collapse p-1"
              >{{ JSON.stringify(alertData(i), null, 4) }}
            </pre>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
export default {
    name: 'QaEventsTriggeredAlerts',
    props: {
        alerts: {
            type: Array,
            default: () => [],
        },
    },
    methods: {
        alertData(i) {
            return this.alerts[i].data;
        },
    },
};
</script>
