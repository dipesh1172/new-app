<template>
  <div>
    <div>
      <div class="container-fluid">
        <div class="card mb-0">
          <div class="card-header">
            <i class="fa fa-th-large" /> Agent Groups
            <button
              type="button"
              class="btn btn-success btn-sm pull-right"
              title="Add New Agent Group"
              @click="addProfile"
            >
              <i class="fa fa-plus" /> 
            </button>
          </div>
          <div class="card-body p-0">
            <div class="container-fluid p-0">
              <div class="row">
                <div class="col-12">
                  <div class="card mb-0">
                    <div class="card-body no-gutters p-0">
                      <table class="main-table table table-bordered mb-0">
                        <thead>
                          <tr>
                            <th>Name</th>
                            <th class="w-15">
                              # Skills
                            </th>
                            <th class="w-15">
                              # Agents
                            </th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-if="updating && !loading">
                            <td
                              colspan="3"
                              class="text-center"
                            >
                              One moment while we update the results ...
                            </td>
                          </tr>
                          <tr v-if="loading && !updating">
                            <td
                              colspan="3"
                              class="text-center"
                            >
                              Loading ...
                            </td>
                          </tr>
                          <tr
                            v-cloak
                            v-if="profiles.length == 0 && !loading && !updating"
                          >
                            <td colspan="3">
                              <div class="alert alert-info">
                                There are no profiles to display at this time.
                              </div>
                            </td>
                          </tr>
                          <tr
                            v-for="profile in profiles"
                            v-else
                            :key="profile.id"
                          >
                            <td
                              :data-id="profile.id"
                              data-type="edit"
                              @click="clickedCell"
                            >
                              {{ profile.group }}
                            </td>
                            <td
                              :data-id="profile.id"
                              data-type="skills"
                            >
                              <span v-if="profile.config && profile.config.skills && profile.config.skills.length">
                                {{ profile.config.skills.length }}
                              </span>
                              <span v-else>
                                0
                              </span>
                            </td>
                            <td
                              :data-id="profile.id"
                              data-type="agents"
                              @click="clickedCell"
                            >
                              {{ profile.agent_count }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div
      id="profileModal"
      class="modal fade"
      tabindex="-1"
      role="dialog"
      aria-labelledby="profileModalLabel"
      aria-hidden="true"
    >
      <div
        class="modal-dialog modal-lg"
        role="document"
      >
        <div class="modal-content">
          <div class="modal-header">
            <h5
              id="profileModalLabel"
              class="modal-title"
            >
              {{ modalTitle }}
            </h5>
            <button
              type="button"
              class="close"
              data-dismiss="modal"
              aria-label="Close"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div v-if="type == 'edit'">
              <tpv-skill-edit
                :profile="profileToEdit"
                @input="handleInput"
              />
            </div>
            <div v-if="type == 'agents'">
              <table class="table table-shaded">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Name</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="agentLoading">
                    <td
                      colspan="2"
                      class="text-center"
                    >
                      Loading ...
                    </td>
                  </tr>
                  <tr 
                    v-for="(agent, index) in agentsComputed" 
                    v-cloak 
                    :key="index"
                  >
                    <td>{{ agent.username }}</td>
                    <td>
                      {{ agent.first_name }} {{ agent.last_name }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button 
              type="button" 
              class="btn btn-secondary" 
              data-dismiss="modal"
            >
              <i
                class="fa fa-times-circle"
                aria-hidden="true"
              /> 
              Close
            </button>
            <button
              v-if="type == 'edit'"
              type="button"
              class="btn btn-primary"
              @click="saveChanges()"
            >
              <i
                class="fa fa-floppy-o"
                aria-hidden="true"
              /> 
              Save changes
            </button>
          </div>
        </div>
      </div>
    </div>
    <!-- End Modal -->
  </div>
</template>
<script>
import SkillProfileEditor from '../components/SkillProfileEditor.vue';

export default {
    name: 'SkillList',
    components: {
        'tpv-skill-edit': SkillProfileEditor,
    },
    data() {
        return {
            type: null,
            profileToEdit: null,
            modalTitle: '',
            loading: true,
            updating: false,
            agents: null,
            agentLoading: true,
        };
    },
    computed: {
        skills() {
            return this.$store.state.skills;
        },
        profiles() {
            if (this.$store.state.profiles && this.$store.state.profiles.length > 0) {
                this.loading = false;
            }

            return this.$store.state.profiles;
        },
        agentsComputed() {
            if (this.agents && this.agents.length > 0) {
                this.agentLoading = false;
            }

            return this.agents;
        },
    },
    methods: {
        handleInput(e) {
            this.profileToEdit.config.skills = e;
        },
        saveChanges() {
            const context = this;
            context.updating = true;
            console.log('Profiles: ', this.profiles);
            console.log('Saving: ', context.profileToEdit);
            axios.post('/skill-profiles/save', {
                id: context.profileToEdit.id,
                group: context.profileToEdit.group,
                skills: context.profileToEdit.config,    
            }).then((response) => {
                if (response.data.error === null) {
                    context.refreshProfiles();
                    context.updating = false;
                }
                else {
                    context.updating = false;
                }
            }).catch((error) => {
                context.updating = false;
                console.log(error);
            });

            $('.modal').modal('hide');
        },
        refreshSkills() {
            return this.$store.dispatch('loadSkills');
        },
        refreshProfiles() {
            return this.$store.dispatch('loadProfiles');
        },
        getAgents(id) {
            const context = this;
            axios.get(`/skill-profiles/${id}/list-agents`).then((response) => {
                if (response.data) {
                    context.agents = response.data.skill_profile_agents;
                }
            }).catch((error) => {
                console.log(error);
            });
        },
        addProfile() {
            const context = this;
            context.updating = true;

            const r = Math.random() * 1000;
            const group = `New Group ${r.toString(16).replace('.', '').toUpperCase()}`;
            this.$store.commit('addProfile', {
                id: r,
                group: group,
                config: {
                    skills: [],
                },
                agent_count: 0,                
            });

            axios.post('/skill-profiles/add', {
                group: group, 
            }).then((response) => {
                if (response.data.error === null) {
                    context.refreshProfiles()
                        .then(() => {
                            this.profileToEdit = this.getProfile(r);
                            this.type = 'edit';
                            this.modalTitle = 'Editing Group';
                            $('#profileModal').modal('toggle');
                        }).catch((e) => console.log(e));
                    context.updating = false;
                }
                else {
                    context.updating = false;
                }
            }).catch((error) => {
                context.updating = false;
                console.log(error);
            });
        },
        getProfile(id) {
            for (let i = 0, len = this.profiles.length; i < len; i += 1) {
                if (this.profiles[i].id == id) {
                    return { ...this.profiles[i]};
                }
            }
        },
        clickedCell(e) {
            const target = $(e.target);
            this.type = target.data('type');
            this.profileToEdit = this.getProfile(target.data('id'));

            switch (this.type) {
                case 'edit':
                    this.modalTitle = 'Editing Group';
                    break;

                case 'skills':
                    this.modalTitle = 'Skills in Group';
                    break;

                case 'agents':
                    this.modalTitle = 'Agents with Group';
                    this.getAgents(target.data('id'));
                    break;
            }

            $('#profileModal').modal('toggle');
        },
    },
};
</script>
<style scoped>
.w-15 {
    width: 15%;
}
.main-table td {
    cursor: pointer;
}
tbody > tr:hover {
    background-color: #e7e7e7;
}
</style>
