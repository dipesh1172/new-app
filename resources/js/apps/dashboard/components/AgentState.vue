<template>
  <div class="card mb-3">
    <div class="card-header">
      <h5 class="card-title mb-0 pull-left">
        Agents
      </h5>
    </div>
    <div class="card-body p-3 m-0">
      <div>
        <h5 class="mb-3">
          Filters / Search
        </h5>
        <div class="row mb-4 page-buttons">
          <div class="col-md-4">
            <div class=" form-group form-inline pull-left">
              <custom-input
                label="By ID:"
                class-style="small"
                :value="searchValue.id"
                placeholder="Search"
                name="search-id"
                :style="{ marginRight: 0 }"
                @onChange="(newValue) => { updateSearchValue(newValue, 'id'); }"
                @onKeyUpEnter="searchData"
              />
              <button
                class="btn btn-sm btn-primary"
                @click="cleanSearchValue('id')"
              >
                Clear
              </button>
            </div>
          </div>
          <div class="col-md-4">
            <div class=" form-group form-inline pull-right">
              <custom-input
                label="By Agent:"
                class-style="small"
                :value="searchValue.agentName"
                placeholder="Search"
                name="search-agent"
                :style="{ marginRight: 0 }"
                @onChange="(newValue) => { updateSearchValue(newValue, 'agentName'); }"
                @onKeyUpEnter="searchData"
              />
              <button
                class="btn btn-sm btn-primary"
                @click="cleanSearchValue('agentName')"
              >
                Clear
              </button>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group form-inline pull-right">
              <custom-select
                label="By Status:"
                :items="filterStatusList"
                class-style="small"
                :value="searchValue.status"
                name="search-status"
                :style="{ marginRight: 0 }"
                @onChange="(newValue) => { updateSearchValue(newValue, 'status'); }"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import CustomTable from 'components/CustomTable';
import CustomInput from 'components/CustomInput';
import CustomSelect from 'components/CustomSelect';
import Pagination from 'components/Pagination';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'AgentState',
    components: {
        CustomInput,
        CustomSelect,
        CustomTable,
        Pagination,
    },
    data() {
        return {
            activities: [],
            agents: [],
            headers: [{
                label: 'ID',
                key: 'id',
                serviceKey: 'id',
                width: '30%',
                sorted: NO_SORTED,
            }, {
                label: 'Agent Name',
                key: 'agentName',
                serviceKey: 'name',
                width: '30%',
                sorted: NO_SORTED,
            }, {
                label: 'Status',
                key: 'status',
                serviceKey: 'status',
                width: '30%',
                innerHtml: true,
                sorted: NO_SORTED,
            }],
            dataIsLoaded: false,
            searchValue: {
                id: '',
                agentName: '',
                status: 'all',
            },
            filterStatusList: [{
                value: 'all',
                label: 'All',
            }],
        };
    },
    computed: {
        dataGrid() {
            const filterByID = this.agents.filter((element) => element.id.toUpperCase().includes(this.searchValue.id.toUpperCase()));
            const filterByAgent = filterByID.filter((element) => element.agentName.toUpperCase().includes(this.searchValue.agentName.toUpperCase()));

            let filterByStatus = filterByAgent;

            if (this.searchValue.status !== 'all') {
                filterByStatus = filterByAgent.filter((element) => element.statusValue === this.searchValue.status);
            }

            return filterByStatus;
        },
    },
    mounted() {
        axios.get('/api/twilio/activities')
            .then((response) => {
                this.activities = response.data.map((item) => ({
                    value: item.id,
                    label: item.activity,
                }));

                this.filterStatusList = this.activities.slice(0);
                this.filterStatusList.unshift({
                    value: 'all',
                    label: 'All',
                });

                axios.get('/api/twilio/workers')
                    .then((response) => {
                        this.agents = response.data.map((agent) => this.getAgentObject(agent));
                        this.dataIsLoaded = true;
                    });
            });
    },
    methods: {
        renderActivities(idAgent, status) {
            let select = `<select id="select-${idAgent}" class="form-control">`;

            const options = this.activities.map((activity) => (
                `<option value="${activity.value}" ${activity.value === status ? 'selected' : ''}>
                    ${activity.label}
                </option>`
            ));
            select += options;
            select += '</select>';

            return select;
        },
        getAgentObject(agent) {
            return {
                id: agent.id,
                agentName: agent.name,
                status: this.renderActivities(agent.id, agent.status),
                statusValue: agent.status,
                update: agent.id,
                buttons: [{
                    type: 'update',
                    url: `/api/twilio/update_worker?worker_id=${agent.worker_id}&activity_sid=`,
                }],
            };
        },
        compareElements(element1, element2, key) {
            let parseElement1 = element1[key];
            let parseElement2 = element2[key];

            if (key === 'status') {
                const select1 = document.getElementById(`select-${element1.id}`);
                const select2 = document.getElementById(`select-${element2.id}`);
                parseElement1 = select1.options[select1.selectedIndex].text;
                parseElement2 = select2.options[select2.selectedIndex].text;
            }

            if (typeof parseElement1 === String) {
                parseElement1 = parseElement1.toUpperCase();
                parseElement2 = parseElement2.toUpperCase();
            }

            if (parseElement1 < parseElement2) {
                return -1;
            }

            if (parseElement1 > parseElement2) {
                return 1;
            }

            return 0;
        },
        sortData(serviceKey, index) {
            const labelSort = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;
            this.headers[index].sorted = labelSort;

            this.agents = this.agents.sort((el1, el2) => (
                this.compareElements(el1, el2, this.headers[index].key)
            ));

            if (labelSort === DESC_SORTED) {
                this.agents = this.agents.reverse();
            }
        },
        searchData() {

        },
        cleanSearchValue(key) {
            this.searchValue[key] = '';
        },
        updateSearchValue(newValue, key) {
            this.searchValue[key] = newValue;
        },
        updateStatus(newValue, agentId) {
            const index = this.agents.findIndex((element) => element.id === agentId);
            this.agents[index].status = this.renderActivities(agentId, newValue);
            this.agents[index].statusValue = newValue;
        },
    },
};
</script>

<style>
h5 {
    margin-bottom: 0;
}

.form-group {
    margin-bottom: 0;
}

select {
    margin-left: 1rem;
}
</style>
