<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: agent ? 'Agent' : `TPV Staff`, url: agent ? '/agents' : '/tpv_staff'},
        {name: `Edit ${agent ? 'Agent' : 'TPV Staff'}`, active: true}
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <a
              :href="`/tpv_staff/${tpv_staff.id}/permissions`"
              class="pull-right btn btn-light"
            >
              <i class="mt-1 fa fa-gears" /> Permissions
            </a>
            <i class="fa fa-th-large" />
            Edit {{ agent ? 'Agent' : 'TPV Staff' }}
          </div>
          <div class="card-body">
            <div
              v-if="!dataIsLoaded"
              class="text-center mb-3"
            >
              <span class="fa fa-spinner fa-spin fa-2x" />
            </div>
            <form
              method="POST"
              :action="`/tpv_staff/${tpv_staff.id}${agent ? `?agent=true` : `?tpvStaff=true`}`"
              accept-charset="UTF-8"
              autocomplete="off"
            >
              <input
                autocomplete="false"
                name="_method"
                type="hidden"
                value="PUT"
              >
              <input
                autocomplete="false"
                name="_token"
                type="hidden"
                :value="csrf_token"
              >
              <div
                v-if="hasFlashMessage"
                class="alert alert-success"
              >
                <span class="fa fa-check-circle" />
                <em>{{ flashMessage }}</em>
              </div>

              <div
                v-if="errors.length > 0"
                class="text-danger"
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

              <div class="row">
                <div class="col-md-8">
                  <div class="form-row">
                    <div class="col-md-4">
                      <div class="form-group">
                        <label
                          for="first_name"
                          class="heading"
                        >First Name</label>
                        <input
                          id="first_name"
                          class="form-control form-control-lg"
                          placeholder="Enter a First Name"
                          autocomplete="false"
                          name="first_name"
                          type="text"
                          :value="tpv_staff.first_name"
                        >
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label
                          for="middle_name"
                          class="heading"
                        >Middle Name</label>
                        <input
                          id="middle_name"
                          class="form-control form-control-lg"
                          placeholder="Enter a Middle Name"
                          autocomplete="off"
                          name="middle_name"
                          type="text"
                          :value="tpv_staff.middle_name"
                        >
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label
                          for="last_name"
                          class="heading"
                        >Last Name</label>
                        <input
                          id="last_name"
                          class="form-control form-control-lg"
                          placeholder="Enter a Last Name"
                          autocomplete="off"
                          name="last_name"
                          type="text"
                          :value="tpv_staff.last_name"
                        >
                      </div>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="phone_number"
                          class="heading"
                        >Phone Numbers</label>
                        <div class="table-responsive">
                          <table
                            id="phones"
                            class="table table-hover"
                          >
                            <tr v-if="!tpv_staff.phones">
                              <td>No phone numbers found</td>
                            </tr>
                            <tr
                              v-for="phone in tpv_staff.phones"
                              v-else
                              :key="phone.id"
                            >
                              <td>
                                <span class="phone_text">{{ phoneNFormatting(phone.phone_number) }}</span>
                              </td>
                              <td class="text-right">
                                <a
                                  class="btn btn-danger btn-sm"
                                  :href="`/tpv_staff/removePhone/${tpv_staff.id}/${phone.id}`"
                                  @click="removePhoneN"
                                >delete</a>
                              </td>
                            </tr>
                            <tr>
                              <td colspan="2">
                                <input
                                  id="phone_number"
                                  class="form-control form-control-lg"
                                  placeholder="Add phone number"
                                  name="phone_number"
                                  autocomplete="off"
                                  type="text"
                                  :value="old['phone_number']"
                                >
                              </td>
                            </tr>
                          </table>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="email_address"
                          class="heading"
                        >Email Addresses</label>
                        <div class="table-responsive">
                          <table
                            id="emails"
                            class="table table-hover"
                          >
                            <tr v-if="!tpv_staff.emails">
                              <td>No email addresses found</td>
                            </tr>
                            <tr
                              v-for="email in tpv_staff.emails"
                              v-else
                              :key="email.id"
                            >
                              <td>{{ email.email_address }}</td>
                              <td class="text-right">
                                <a
                                  class="btn btn-danger btn-sm"
                                  onclick="return confirm('Are you sure you want to remove this email address?');"
                                  :href="`/tpv_staff/removeEmail/${tpv_staff.id}/${email.id}`"
                                >delete</a>
                              </td>
                            </tr>
                            <tr>
                              <td colspan="2">
                                <input
                                  id="email_address"
                                  class="form-control form-control-lg"
                                  placeholder="Add email address"
                                  name="email_address"
                                  autocomplete="off"
                                  type="text"
                                  :value="old['email_address']"
                                >
                              </td>
                            </tr>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="username"
                          class="heading"
                        >Username</label>
                        <input
                          id="username"
                          class="form-control form-control-lg"
                          placeholder="Enter an Username"
                          autocomplete="off"
                          disabled="disabled"
                          name="username"
                          type="text"
                          :value="tpv_staff.username"
                        >
                      </div>
                    </div>

                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="password"
                          class="heading"
                        >Password</label>
                        <button
                          v-if="!passwordFieldShown"
                          id="change_pass"
                          type="button"
                          class="btn btn-lg form-control btn-warning"
                          @click="showPasswordField"
                        >
                          <i
                            class="fa fa-key"
                            aria-hidden="true"
                          />
                          Change Password
                        </button>
                        <input
                          v-if="passwordFieldShown"
                          id="password"
                          class="form-control form-control-lg"
                          placeholder="Enter New Password"
                          autocomplete="new-password"
                          name="password"
                          type="password"
                          :value="old['password']"
                        >
                      </div>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="dept_id"
                          class="heading"
                        >Department</label>
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
                            :selected="dept.id == tpv_staff.dept_id"
                          >
                            {{ dept.name }}
                          </option>
                        </select>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="role_id"
                          class="heading"
                        >Role</label>
                        <select
                          id="role_id"
                          name="role_id"
                          class="form-control form-control-lg"
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
                            :selected="role.id == tpv_staff.role_id"
                          >
                            {{ role.name }}
                          </option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="supervisor_id"
                          class="heading"
                        >Supervisor</label>
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
                            :selected="old['supervisor_id'] == supervisor.id"
                          >
                            {{ supervisor.name }}
                          </option>
                        </select>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="manager_id"
                          class="heading"
                        >Manager</label>
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
                            :selected="old['manager_id'] == manager.id"
                          >
                            {{ manager.name }}
                          </option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="call_center_id"
                          class="heading"
                        >Call Center</label>
                        <select
                          id="call_center_id"
                          name="call_center_id"
                          class="form-control form-control-lg"
                        >
                          <option value>
                            Select a Call Center
                          </option>
                          <option
                            v-for="call_center in call_centers"
                            :key="call_center.id"
                            :value="call_center.id"
                            :selected="call_center.id == tpv_staff.call_center_id"
                          >
                            {{ call_center.call_center }}
                          </option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="language_id"
                          class="heading"
                        >Language</label>
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
                            :selected="language.id == tpv_staff.language_id"
                          >
                            {{ language.language }}
                          </option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="tpv_staff_group_id"
                          class="heading"
                        >Group</label>
                        <select
                          id="tpv_staff_group_id"
                          name="tpv_staff_group_id"
                          class="form-control form-control-lg"
                        >
                          <option value>
                            Select an Agent Group
                          </option>
                          <option
                            v-for="group in groups"
                            :key="group.id"
                            :value="group.id"
                            :selected="group.id == tpv_staff.tpv_staff_group_id"
                          >
                            {{ group.group }}
                          </option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="tpv_staff_group_id"
                          class="heading"
                        >Payroll ID</label>
                        <input
                          id="tpv_staff_payroll_id"
                          name="tpv_staff_payroll_id"
                          class="form-control form-control-lg"
                          :value="tpv_staff.payroll_id"
                        >
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label
                          for="timezone_id"
                          class="heading"
                        >Timezone</label>
                        <select
                          id="timezone_id"
                          name="timezone_id"
                          class="form-control form-control-lg"
                        >
                          <option value>
                            Select a Timezone
                          </option>
                          <option
                            v-for="timezone in timezones"
                            :key="timezone.id"
                            :value="timezone.id"
                            :selected="tpv_staff.timezone_id == timezone.id || old.timezone_id == timezone.id"
                          >
                            {{ timezone.timezone }}
                          </option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <template v-if="service_login">
                    <hr>

                    <div class="row">
                      <div class="col-md-12">
                        <label
                          for="taskqueues"
                          class="heading"
                        >Task Queues</label>
                        <div class="row">
                          <div
                            v-for="tq in taskqueue"
                            :key="tq.id"
                            class="col-md-3"
                          >
                            {{ tq.task_queue }}
                            <br>

                            <label class="switch">
                              <input
                                type="checkbox"
                                name="taskqueues[]"
                                :value="tq.id"
                                :checked="workerAttr.indexOf(tq.task_queue) >= 0"
                              >
                              <span class="slider round" />
                            </label>
                          </div>
                        </div>
                      </div>
                    </div>
                  </template>

                  <hr>

                  <button
                    type="submit"
                    class="btn btn-primary pull-right"
                  >
                    <i class="fa fa-save" /> Save
                  </button>
                </div>
                <div
                  class="col-md-4"
                  style="border-left: 1px solid #CCCCCC;"
                >
                  <template v-if="tpv_staff.status == 0">
                    <div class="alert alert-danger">
                      Cannot add service or client logins for a disabled user.
                      <br>
                      <br>

                      <a
                        :href="`/tpv_staff/agents/${tpv_staff.id}/active`"
                        class="btn btn-success"
                      >Enable User</a>
                    </div>
                  </template>
                  <template v-else>
                    <h4>Linked Client User</h4>

                    <br>

                    <template v-if="linked_user">
                      <strong>ID:</strong>
                      {{ linked_user.id }}
                      <br>
                      <strong>Name:</strong>
                      {{ linked_user.first_name }} {{ linked_user.last_name }}
                      <br>
                      <strong>Username:</strong>
                      {{ linked_user.username }}
                      <br>
                    </template>
                    <template v-else>
                      <a
                        class="btn btn-success"
                        :href="`/tpv_staff/${tpv_staff.id}/addclient`"
                        onclick="return confirm('Are you sure?')"
                      ><i
                        class="fa fa-plus"
                        aria-hidden="true"
                      /> Add Client User</a>
                    </template>

                    <hr>

                    <h4>Service Login</h4>

                    <br>
                    <template v-if="service_login">
                      <strong>ID:</strong>
                      {{ service_login.id }}
                      <br>
                      <strong>Username:</strong>
                      {{ service_login.username }}
                      <br>
                      <strong>Password:</strong>
                      {{ service_login.password }}
                      <br>
                      <strong>Motion Username:</strong>
                      {{ service_login.motion_username }}
                      <br>

                      <br>

                      <textarea
                        style="width: 100%; height: 100px;"
                        disabled
                        v-html="worker"
                      />
                    </template>

                    <template v-else>
                      <a
                        class="btn btn-success"
                        :href="`/tpv_staff/${tpv_staff.id}/addservicelogin`"
                        onclick="return confirm('Are you sure?')"
                      >Add Service Login</a>
                    </template>
                  </template>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
      <!--/.col-->
    </div>
  </div>
</template>
<script>
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'EditTPVStaff',
    components: {
        Breadcrumb,
    },
    props: {
        hasFlashMessage: {
            type: Boolean,
            default: false,
        },
        flashMessage: {
            type: String,
            default: null,
        },
        old: {
            type: [Object, Array],
        },
        errors: {
            type: Array,
        },
    },
    data() {
        return {
            agent: this.getParams().agent,
            call_centers: [],
            depts: [],
            languages: [],
            linked_user: {},
            groups: [],
            roles: [],
            service_login: {},
            taskqueue: [],
            tpv_staff: {},
            worker: {},
            supervisors: [],
            timezones: [],
            csrf_token: window.csrf_token,
            dataIsLoaded: false,
            passwordFieldShown: false,
        };
    },
    computed: {
        workerAttr() {
            return this.worker && this.worker.skills ? this.worker.skills : [];
        },
    },
    mounted() {
        document.title += ` Edit ${this.agent ? 'Agent' : 'TPV Staff'}`;
        // let tpvStaffID = window.location.pathname.split('/')[2];
        axios
            .get(window.location.href)
            .then((response) => {
                const res = response.data;
                console.log(res);

                this.agent = res.agent;
                this.call_centers = res.call_centers;
                this.depts = res.depts;
                this.groups = res.groups;
                this.roles = res.roles;
                this.languages = res.languages;
                this.linked_user = res.linked_user;
                this.service_login = res.service_login;
                this.supervisors = res.supervisors;
                this.taskqueue = res.taskqueue;
                this.tpv_staff = res.tpv_staff;
                this.worker = res.worker;
                this.dataIsLoaded = true;
                this.timezones = res.timezones;
            })
            .catch(console.log);
    },
    updated() {
        this.updateRoles();
        $('#dept_id').on('change', this.updateRoles);
    },
    methods: {
        showPasswordField() {
            console.log('Clicked showPasswordField');
            this.passwordFieldShown = true;
        },
        phoneNFormatting(phoneN) {
            return window.formatPhoneNumber(phoneN);
        },
        removePhoneN(phoneN) {
            const confirm = confirm(
                'Are you sure you want to remove this phone number?',
            );
            if (confirm) {
                return true;
            }
            return false;
        },
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
            const agent = url.searchParams.get('agent') || false;

            return {
                agent,
            };
        },
    },
};
</script>
<style>
.heading {
  font-weight: bold;
  font-size: 14pt;
}
</style>
