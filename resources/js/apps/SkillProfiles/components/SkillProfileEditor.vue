<template>
  <div class="container no-gutters">
    <div class="row">
      <div class="col-5 br-1">
        <div class="form-row">
          <div class="form-group">
            <label for="name">Name</label>
            <input
              v-model="profile.group"
              type="text"
              class="form-control form-control-lg"
              placeholder="Name"
            >
          </div>
        </div>
        <div class="form-row">
          <label>Available Skills</label>
        </div>
        <div class="row">
          <div class="col-8">
            <select
              v-model="skillSelected"
              class="form-control form-control-lg"
            >
              <option
                v-for="skill in allSkills"
                v-if="skill && !inArray(skill)"
                :key="skill"
              >
                {{ skill }}
              </option>
            </select>
          </div>
          <div class="col-4">
            <button
              type="button"
              class="btn btn-sm btn-success"
              :disabled="skillSelected == null"
              @click="addSkill"
            >
              <i class="fa fa-2x fa-plus" />
            </button>
          </div>
        </div>
        <div
          v-if="profile.agent_count == 0"
          class="row mt-4"
        >
          <div class="col-12">
            <form
              method="GET"
              :action="`/agent/groups/${profile.id}/delete`"
            >
              <button
                type="submit"
                class="btn btn-danger"
              >
                <i
                  class="fa fa-trash"
                  aria-hidden="true"
                /> 
                Remove Group
              </button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-7">
        <div class="form-row">
          <div class="col-12 p-0">
            <strong>Skill</strong>

            <hr>

            <div
              v-if="!profileToSave.config.skills || profileToSave.config.skills.length == 0"
            >
              No Skills Assigned
            </div>
            <div v-else>
              <ul class="list-group">
                <li
                  v-for="(skill, index) in profileToSave.config.skills"
                  :key="skill"
                  class="list-group-item"
                >
                  <div class="row">
                    <div class="col-10">
                      {{ skill }}
                    </div>
                    <div class="col-2">
                      <button
                        type="button"
                        class="btn btn-sm btn-danger ml-1"
                        @click="removeSkill(index)"
                      >
                        <i class="fa fa-minus" />
                      </button>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>

export default {
    name: 'SkillProfileEditor',

    props: ['profile'],
    data() {
        return {
            skillSelected: null,
            outSkills: [],
            profileToSave: {
                group: null,
                config: {
                    skills: null,
                    langs: null,
                },
            },
        };
    },
    computed: {
        allSkills() {
            return this.$store.state.skills;
        },
    },
    watch: {
        profile: function(v) {
            this.skillSelected = null;

            if (this.profile != null) {
                this.profileToSave.group = this.profile.group;
                this.profileToSave.config.skills = this.profile.config.skills.filter((i) => i != null);
            }
        },
    },
    created: function() {
        if (this.profile != null) {
            this.profileToSave.group = this.profile.group;

            if (this.profile.config && this.profile.config.skills) {
                this.profileToSave.config.skills = this.profile.config.skills.filter((i) => i != null);
            }
            else {
                this.profileToSave.config.skills = null;
            }
        }
    },
    methods: {
        inArray(skill) {
            if (this.profileToSave.config && this.profileToSave.config.skills) {
                for (
                    let i = 0, len = this.profileToSave.config.skills.length;
                    i < len;
                    i += 1
                ) {
                    if (this.profileToSave.config.skills[i] == skill) {
                        return true;
                    }
                }
            }

            return false;
        },
        removeSkill(index) {
            console.log('in remove skill');
            this.profileToSave.config.skills.splice(index, 1);
            this.$emit('input', this.profileToSave.config.skills);
        },
        addSkill() {
            if (this.skillSelected != null) {
                console.log(`Adding ${this.skillSelected}`);
                if (!this.profileToSave.config || !this.profileToSave.config.skills) {
                    this.profileToSave.config.skills.push(this.skillSelected);
                }
                else {
                    this.profileToSave.config.skills.push(this.skillSelected);
                }
                this.$emit('input', this.profileToSave.config.skills);
            }
        },
        hasOutSkill(skill) {
            console.log(skill.Sid);
            for (let i = 0, len = this.outSkills.length; i < len; i += 1) {
                if (skill.Sid == this.outSkills[i].Sid) {
                    console.log('skill.Sid ', skill.Sid);
                    console.log('outSkill[i].Sid ', this.outSkills[i].Sid);
                    return true;
                }
            }
            return false;
        },
    },
};
</script>

<style>
.br-1 {
  border-right: 1px solid #c9c9c9;
}

</style>

