<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Company Configuration', url: '/config'},
        {name: 'Departments', url: '/config/departments'},
        {name: `${dept.name} Roles`, active: true},
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="row">
        <div class="col-md-12">
          <button
            id="add_role"
            class="btn btn-success pull-right mb-4"
            @click="addRole($event)"
          >
            <i
              class="fa fa-plus"
              aria-hidden="true"
            /> 
            New Role
          </button>
          <div class="pull-right">
            <form
              id="role_form"
              class="form-inline mr-2"
              style="display:none;"
              method="POST"
              :action="`/config/departments/${dept.id}/role`"
            >
              <input
                type="hidden"
                name="_token"
                :value="csrfToken"
              >
              <div class="form-group">
                <input
                  id="new_role"
                  name="rolename"
                  type="text"
                  class="form-control"
                  :value="old.rolename"
                  placeholder="Enter Role Name"
                >
              </div>
              <button
                type="submit"
                class="btn btn-primary ml-2"
              >
                <i
                  class="fa fa-floppy-o"
                  aria-hidden="true"
                /> 
                Submit
              </button>
            </form>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <div class="card">
            <div class="card-header">
              <i class="fa fa-th-large" />
              {{ dept.name }} Roles
            </div>
            <div class="card-body mt-3">
              <div
                v-if="hasFlashMessage"
                class="alert alert-success"
              >
                <span class="fa fa-check-circle" />
                <em>{{ flashMessage }}</em>
              </div>
              <div
                v-if="errors.length"
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
              <div class="table-responsive">
                <custom-table
                  :headers="headers"
                  :data-grid="roles"
                  :data-is-loaded="dataIsLoaded"
                  :show-action-buttons="true"
                  :total-records="totalRecords"
                  :has-action-buttons="true"
                  empty-table-message="No roles were found."
                  @sortedByColumn="sortData"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import CustomTable from 'components/CustomTable';
import Breadcrumb from 'components/Breadcrumb';
import { arraySort } from 'utils/arrayManipulation';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'ListRoles',
    components: {CustomTable, Breadcrumb},
    props: {
        errors: {
            type: Array,
            default: () => ([]),
        },
        old: {
            type: Object,
            default: () => ({}),
        },
        hasFlashMessage: {
            type: Boolean,
            default: false,
        },
        flashMessage: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            dept: '',
            roles: [],
            csrfToken: window.csrf_token,
            totalRecords: 0,
            dataIsLoaded: false,
            headers: [
                {
                    align: 'left',
                    label: 'Role Name',
                    key: 'name',
                    serviceKey: 'name',
                    width: '33%',
                    canSort: true,
                    sorted: ASC_SORTED,
                    type: 'string',
                }, {
                    align: 'center',
                    label: 'Description',
                    key: 'description',
                    serviceKey: 'description',
                    width: '33%',
                    canSort: true,
                    type: 'string',
                    sorted: NO_SORTED,
                },
                {
                    align: 'center',
                    label: 'Members',
                    key: 'members',
                    serviceKey: 'members',
                    width: '33%',
                    canSort: true,
                    type: 'number',
                    sorted: NO_SORTED,
                },
            ],
        };
    },
    mounted() {
        const dept = window.location.href.split('/').pop();

        axios.get(`/config/departments/${dept}/list_roles_by_dept`).then((response) => {
            this.dept = response.data.dept;
            this.roles = response.data.roles.map((r) => {
                r.buttons = [
                    {
                        type: 'edit',
                        url: `/config/departments/${this.dept.id}/role/${r.id}`,
                        buttonSize: 'medium',
                    },
                    
                ];
                if (!r.members) {
                    r.buttons.push({
                        type: 'delete',
                        url: `/config/departments/${this.dept.id}/role/${r.id}/del_dept`,
                        buttonSize: 'medium',
                        messageAlert: 'Are you sure you want to delete this role, it cannot be undone.',
                    },);
                }
                return r;
            });

            if (this.dept) {
                document.title += ` ${this.dept.name} Roles`;
            }
            this.dataIsLoaded = true;
        }).catch(console.log);

    },
    methods: {
        addRole(e) {
            if ($(e.target).hasClass('btn-success')) {
                $(e.target).removeClass('btn-success').addClass('btn-danger').html('<i class="fa fa-ban" aria-hidden="true"></i> Cancel');
            }
            else {
                $(e.target).removeClass('btn-danger').addClass('btn-success').html('<i class="fa fa-plus" aria-hidden="true"></i> New Role');
                $('#new_role').val('');
            }
            $('#role_form').toggle();
        },
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.roles = arraySort(
                this.headers,
                this.roles,
                serviceKey,
                index,
            );
        },
    },

};
</script>
