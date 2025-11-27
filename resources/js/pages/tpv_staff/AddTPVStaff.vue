<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: getParams().isAgent ? 'Agents' : `TPV Staff`, url: getParams().isAgent ? '/agents' : '/tpv_staff'},
        {name: `Add ${getParams().isAgent ? 'Agent' : 'TPV Staff'}`, active: true}
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Add new {{ getParams().isAgent ? 'agent' : 'staff' }}
          </div>
          <div class="card-body">
            <div
              v-if="errors.length"
              class="alert alert-danger"
            >
              <ul>
                <li
                  v-for="(error, index) in errors"
                  :key="index"
                >
                  {{ error }}
                </li>
              </ul>
            </div>
            <ValidationObserver ref="validationObserver">
              <form
                method="POST"
                action="/tpv_staff"
                autocomplete="off"
                ref="formObject"
              >
                <input
                  name="_token"
                  type="hidden"
                  :value="csrf_token"
                >
                <div class="form-row">
                  <div class="col-md-4">
                    <ValidationProvider name="first_name" rules="required|max:64" v-slot="{ errors }">
                      <div class="form-group">
                        <label for="first_name">First Name<span class="text-danger">*</span></label>
                        <input
                          id="first_name"
                          name="first_name"
                          class="form-control form-control-lg"
                          autocomplete="off"
                          placeholder="Enter a First Name"
                          v-model="old.first_name"
                        >
                        <span class="text-danger">{{ errors[0] }}</span>
                      </div>
                    </ValidationProvider>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label for="middle_name">Middle Name</label>
                      <input
                        id="middle_name"
                        name="middle_name"
                        :value="old.middle_name"
                        class="form-control form-control-lg"
                        autocomplete="off"
                        placeholder="Enter a Middle Name"
                      >
                    </div>
                  </div>
                  <div class="col-md-4">
                    <ValidationProvider name="last_name" rules="required|max:64" v-slot="{ errors }">
                      <div class="form-group">
                        <label for="last_name">Last Name<span class="text-danger">*</span></label>
                        <input
                          id="last_name"
                          name="last_name"
                          class="form-control form-control-lg"
                          autocomplete="off"
                          placeholder="Enter a Last Name"
                          v-model="old.last_name"
                        >
                        <span class="text-danger">{{ errors[0] }}</span>
                      </div>
                    </ValidationProvider>
                  </div>
                </div>
                <div class="form-row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="phone">Phone</label>
                      <the-mask
                        id="phone"
                        :mask="['(###) ###-####']"
                        class="form-control form-control-lg"
                        placeholder="Contact Phone Number"
                        name="phone"
                        :value="old.phone"
                      />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="email">Email</label>
                      <input
                        id="email"
                        name="email"
                        :value="old.email"
                        class="form-control form-control-lg"
                        autocomplete="off"
                        placeholder="Email Address"
                      >
                    </div>
                  </div>
                </div>
                <div class="form-row">
                  <div class="col-md-6">
                    <ValidationProvider name="username" rules="required|max:64" v-slot="{ errors }">
                      <div class="form-group">
                        <label for="username">Username<span class="text-danger">*</span></label>
                        <input
                          id="username"
                          name="username"
                          class="form-control form-control-lg"
                          autocomplete="off"
                          placeholder="Enter a Username"
                          v-model="old.username"
                        >
                        <span class="text-danger">{{ errors[0] }}</span>
                      </div>
                    </ValidationProvider>
                  </div>
                  <div class="col-md-6">
                    <ValidationProvider name="password" rules="required|max:64" v-slot="{ errors }">
                      <div class="form-group">
                        <label for="password">Password<span class="text-danger">*</span></label>
                        <input
                          id="password"
                          type="password"
                          name="password"
                          class="form-control form-control-lg"
                          autocomplete="off"
                          placeholder="Enter a Password"
                          v-model="old.password"
                        >
                        <span class="text-danger">{{ errors[0] }}</span>
                      </div>
                    </ValidationProvider>
                  </div>
                </div>
                <div class="form-row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="call_center_id">Payroll ID</label>
                      <input
                        id="tpv_staff_payroll_id"
                        name="tpv_staff_payroll_id"
                        class="form-control form-control-lg"
                        :value="old.payroll_id"
                      >
                    </div>
                  </div>
                </div>

                <div class="form-row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="dept_id">Department</label>
                      <select
                        id="dept_id"
                        name="dept_id"
                        class="form-control form-control-lg"
                      >
                        <option value>
                          Select a Role
                        </option>
                        <option
                          v-for="dept in depts"
                          :key="dept.id"
                          :value="dept.id"
                          :selected="old.dept_id == dept.id"
                        >
                          {{ dept.name }}
                        </option>
                      </select>
                      <span class="text-danger">{{ errors[0] }}</span>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <ValidationProvider name="role_id" rules="required|min:1" v-slot="{ errors }">
                      <div class="form-group">
                        <label for="role_id">Role<span class="text-danger">*</span></label>
                        <select
                          id="role_id"
                          name="role_id"
                          class="form-control form-control-lg"
                          v-model="role_id"
                        >
                          <option
                            data-dept="*"
                            value
                          >
                            Select a Role
                          </option>
                          <option
                            v-for="role in roles"
                            :key="role.id"
                            :data-dept="role.dept_id"
                            :value="role.id"
                            :selected="old.role_id == role.id"
                          >
                            {{ role.name }}
                          </option>
                        </select>
                        <span class="text-danger">{{ errors[0] }}</span>
                      </div>
                    </ValidationProvider>
                  </div>
                </div>

                <div class="form-row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="supervisor_id">Supervisor</label>
                      <select
                        id="supervisor_id"
                        name="supervisor_id"
                        class="form-control form-control-lg"
                      >
                        <option value>
                          Select a Supervisor
                        </option>
                        <option
                          v-for="supervisor in supervisors"
                          :key="supervisor.id"
                          :value="supervisor.id"
                          :selected="old.supervisor_id == supervisor.id"
                        >
                          {{ supervisor.name }}
                        </option>
                      </select>
                      <span class="text-danger">{{ errors[0] }}</span>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="manager_id">Manager</label>
                      <select
                        id="manager_id"
                        name="manager_id"
                        class="form-control form-control-lg"
                      >
                        <option
                          data-dept="*"
                          value
                        >
                          Select a Manager
                        </option>
                        <option
                          v-for="manager in supervisors"
                          :key="manager.id"
                          :value="manager.id"
                          :selected="old.manager_id == manager.id"
                        >
                          {{ manager.name }}
                        </option>
                      </select>
                      <span class="text-danger">{{ errors[0] }}</span>
                    </div>
                  </div>
                </div>

                <div class="form-row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="call_center_id">Call Center</label>
                      <select
                        id="call_center_id"
                        name="call_center_id"
                        class="form-control form-control-lg"
                      >
                        <option value>
                          Select a Call Center
                        </option>
                        <option
                          v-for="callCenter in callCenters"
                          :key="callCenter.id"
                          :value="callCenter.id"
                          :selected="old.call_center_id == callCenter.id"
                        >
                          {{ callCenter.call_center }}
                        </option>
                      </select>
                      <span class="text-danger">{{ errors[0] }}</span>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="language_id">Language</label>
                      <select
                        id="language_id"
                        name="language_id"
                        class="form-control form-control-lg"
                      >
                        <option value>
                          Select a Language
                        </option>
                        <option
                          v-for="language in languages"
                          :key="language.id"
                          :value="language.id"
                          :selected="old.language_id == language.id"
                        >
                          {{ language.language }}
                        </option>
                      </select>
                      <span class="text-danger">{{ errors[0] }}</span>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <ValidationProvider name="timezone_id" rules="required|min:1" v-slot="{ errors }">
                      <div class="form-group">
                        <label
                          for="timezone_id"
                        >Timezone<span class="text-danger">*</span></label>
                        <select
                          id="timezone_id"
                          name="timezone_id"
                          class="form-control form-control-lg"
                          v-model="timezone_id"
                        >
                          <option value>
                            Select a Timezone
                          </option>
                          <option
                            v-for="timezone in timezones"
                            :key="timezone.id"
                            :value="timezone.id"
                            :selected="old.timezone_id == timezone.id"
                          >
                            {{ timezone.timezone }}
                          </option>
                        </select>
                        <span class="text-danger">{{ errors[0] }}</span>
                      </div>
                    </ValidationProvider>
                  </div>
                </div>
                <button
                  type="button"
                  class="btn btn-lg btn-primary pull-right"
                  @click="onClick"
                >
                  <i
                    class="fa fa-floppy-o"
                    aria-hidden="true"
                  /> 
                  Submit
                </button>
              </form>
            </ValidationObserver>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import {TheMask} from 'vue-the-mask';
import Breadcrumb from 'components/Breadcrumb';
import { ValidationProvider, ValidationObserver } from 'vee-validate/dist/vee-validate.full.esm';

export default {
    name: 'AddTPVStaff',
    components: {
        TheMask,
        Breadcrumb,
        ValidationObserver,
        ValidationProvider
    },
    props: {
        errors: {
            type: Array,
            default: function() {
                return [];
            },
        },
        old: {
            type: Object,
            default: function() {
                return {};
            },
        },
    },
    data() {
        return {
            depts: [],
            roles: [],
            supervisors: [],
            callCenters: [],
            languages: [],
            csrf_token: window.csrf_token,
            timezones: [],
            role_id: null,
            timezone_id: null
        };
    },
    mounted() {
        document.title += ' Add TPV Staff';
        axios
            .get('/tpv_staff/create')
            .then((response) => {
                const res = response.data;
                this.supervisors = res.supervisors;
                this.roles = res.roles;
                this.depts = res.depts;
                this.callCenters = res.call_centers;
                this.languages = res.languages;
                this.timezones = res.timezones;
            })
            .catch((error) => console.log(error));
    },
    updated() {
        this.updateRoles();
        $('#dept_id').on('change', this.updateRoles);
        $('#change_pass').on('click', () => {
            $('#change_pass').addClass('d-none');
            $('#password').removeClass('d-none');
        });
    },
    methods: {
        updateRoles() {
            const rid = $('#role_id');
            const ridv = $('#dept_id').val();

            rid.find('option').addClass('d-none');
            rid.find('option').each((index, item) => {
                const v = $(item).data('dept');
                if (v == ridv) {
                    $(item).removeClass('d-none');
                }
            });
            $(rid.children()[0]).removeClass('d-none');
        },
        getParams() {
            const url = new URL(window.location.href);
            const isAgent = url.searchParams.get('agents');

            return {
                isAgent,
            };
        },
        onClick() {
          this.$refs.validationObserver.validate().then(success => {
            if (!success) {
              return;
            }

            this.$refs.formObject.submit();
          });
        }
    },
};
</script>
