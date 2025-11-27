<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Manage Menu', active: true}
      ]"
    />
    
    <div class="container-fluid mt-5">
      <div class="tab-content">
        <div
          role="tabpanel"
          class="tab-pane active"
        >
          <div class="animated fadeIn">
            <div class="row page-buttons">
              <div class="col-md-12" />
            </div>
            <div class="card">
              <div class="card-header">
                <i class="fa fa-th-large" /> Manage Menu
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-3">
                    <h3>Menu to edit</h3><hr>
                    <div 
                      v-if="!dataIsLoaded"
                      class="text-center"
                    >
                      <span class="fa fa-spinner fa-spin fa-2x" />
                    </div>
                    
                    <ul style="list-style-type: none; padding-left: 0;">
                      <li v-for="m in menuTree" class="nav-item dropdown border border-light">
                        <a
                          class="nav-link dropdown-toggle"
                          data-toggle="dropdown"
                          href="#"
                          role="button"
                          aria-haspopup="true"
                          aria-expanded="false"
                        >{{ m.name }}</a>
                        <div class="dropdown-menu">
                          <a
                            class="btn btn-primary"
                            style="min-width: 100%; display: block; color: #fff"
                            v-on:click="editLink(m)"
                          ><i class="fa fa-pencil" /> Edit</a>
                          <a
                            class="btn btn-danger"
                            style="min-width: 100%; display: block; color: #fff"
                            v-on:click="this.deleteLink"
                            :href="'/menus/destroy?id=' + m.id"
                          ><i class="fa fa-trash" /> Delete</a>
                        </div>
                      </li>
                    </ul>


                  </div>
                  <div class="col-9">
                    <form
                      action="/menus/store"
                      method="POST"
                    >
                      <input
                        type="hidden"
                        name="_token"
                        :value="csrfToken"
                      >
                      <input
                        type="hidden"
                        name="selected_roles"
                        :value="selectedRoles.length ? selectedRoles.map(r => r.id).join(',') : ''"
                      >
                      <input
                        v-if="action === 'Edit'"
                        type="hidden"
                        name="id"
                        :value="currentData.id"
                      >
                      <h3>{{ action }} link</h3>
                      <hr>
                      <div class="form-group">
                        <label for="name">Name <span
                          title="Required"
                          class="text-danger"
                        >*</span></label>
                        <input
                          id="name"
                          v-model="currentData.name"
                          name="name"
                          type="text"
                          class="form-control"
                          placeholder="Enter name"
                          required
                        >
                      </div>
                      <div class="form-group">
                        <label for="url">URL (please enter partial URL like: /brand/users) <span
                          title="Required"
                          class="text-danger"
                        >*</span></label>
                        <input
                          id="url"
                          v-model="currentData.url"
                          name="url"
                          type="text"
                          class="form-control"
                          placeholder="Enter partial URL (pathname)"
                          :required="action==='Add'"
                        >
                      </div>
                      <div class="form-group">
                        <label for="icon">Icon (icon class like: fa-tags)</label>
                        <input
                          id="icon"
                          v-model="currentData.icon"
                          name="icon"
                          type="text"
                          class="form-control"
                          placeholder="Empty if no icon"
                        >
                      </div>
                      <div class="form-group">
                        <label for="parent_id">Parent id</label>
                        <select
                          id="parent_id"
                          class="form-control"
                          name="parent_id"
                          @change="addToPositionArray($event)"
                        >
                          <option value="">
                            Root
                          </option>
                          <option
                            v-for="m in menu"
                            :key="m.id"
                            :value="m.id"
                            :selected="currentData.parent_id == m.id"
                          >
                            {{ m.name }}
                          </option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label for="position">Position</label>
                        <select
                          id="position"
                          name="position"
                          class="form-control"
                        >
                          <option
                            v-for="pos in position"
                            :key="pos"
                            :value="pos"
                            :selected="currentData.position == pos"
                          >
                            {{ pos }}
                          </option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label for="roles">Role Permissions</label>
                        <select
                          id="roles"
                          name="roles"
                          class="form-control"
                        >
                          <option value="">
                            Select Roles
                          </option>
                          <option
                            v-for="role in roles"
                            :key="role.id"
                            :value="role.id"
                          >
                            {{ role.name }}
                          </option>
                        </select>
                        <button
                          id="add_role"
                          class="btn btn-success mt-1"
                          type="button"
                          @click="addRole"
                        >
                          <i
                            class="fa fa-plus"
                            aria-hidden="true"
                          /> Add Role
                        </button>
                        <div class="pull-right mt-1">
                          <span
                            v-for="(sr, index) in selectedRoles"
                            :key="index"
                            class="mr-2"
                          ><i
                             class="fa fa-times alert alert-danger"
                             role="alert"
                             aria-hidden="true"
                             @click="deleteRol(sr)"
                           />
                            {{ sr.name }}
                          </span>
                        </div>
                      </div>
                      <button
                        v-if="action === 'Edit'"
                        type="button"
                        class="btn btn-danger mr-1 mb-1"
                        @click="reset"
                      >
                        <i
                          class="fa fa-circle-o"
                          aria-hidden="true"
                        /> 
                        Reset
                      </button>
                      <button
                        type="submit"
                        class="btn btn-primary"
                      >
                        <i class="fa fa-save" />
                        Submit
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import Breadcrumb from 'components/Breadcrumb';
import {arrayToTree} from 'utils/arrayManipulation';

const initialValues = {
    name: '',
    url: '',
    icon: '',
    parent_id: null,
    position: 1,
    role_permissions: '',
};

export default {
    name: 'ManageMenu',
    components: {
        Breadcrumb,
    },
    props: {
        roles: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            menu: [],
            action: 'Add',
            menuTree: [], 
            selectedRoles: [],
            position: [1],
            csrfToken: window.csrf_token,
            currentData: { ...initialValues},
            dataIsLoaded: false,
            liTemplate: $(`<li class="nav-item dropdown border border-light">
                    <a
                      class="nav-link dropdown-toggle"
                      data-toggle="dropdown"
                      href="#"
                      role="button"
                      aria-haspopup="true"
                      aria-expanded="false"
                    >Dropdown</a>
                    <div class="dropdown-menu">
                      <a
                        class="btn btn-primary"
                        style="min-width: 100%; display: block; color: #fff"
                      ><i class="fa fa-pencil" /> Edit</a>
                      <a
                        class="btn btn-danger"
                        style="min-width: 100%; display: block; color: #fff"
                      ><i class="fa fa-trash" /> Delete</a>
                    </div>
                  </li>`),
        };
    },
    watch: {
        action(newAction) {
            if (newAction === 'Edit') {
                const val = this.currentData.parent_id || '';
                document.querySelector('#parent_id').value = val;
            }
            this.addToPositionArray(null, this.currentData.parent_id);
        },
        'currentData.role_permissions'(newRP) {
            this.selectedRoles = [];
            if (newRP) {
                const linkRoles = newRP.split(',');
                linkRoles.forEach((r) => {
                    const rol = this.roles.find((role) => role.id == r);
                    if (rol) {
                        this.selectedRoles.push(rol);
                    }
                });
            }
        },
    },
    created() {
        axios.get('/menus/get_sidebar_menu').then((res) => { 
            this.menu = res.data; 
            this.addToPositionArray(null);
            this.menuTree = arrayToTree(res.data);
            this.sortByPosition(this.menuTree);
            this.dataIsLoaded = true;
        }).catch(console.log);
    },
    methods: {
        sortByPosition(arr) {
            const _sort = (arrs) => arrs.sort((a, b) => a.position - b.position);            
            const searchAndSort = (arrsas) => arrsas.forEach((elemt) => {
                if (elemt.children.length) {
                    elemt.children = _sort(elemt.children);
                    searchAndSort(elemt.children);
                }
                _sort(arrsas);
            });
            searchAndSort(arr);
        },
        deleteRol(sr) {
            this.selectedRoles.splice(this.selectedRoles.indexOf(sr), 1);
        },
        addRole() {
            const e = document.getElementById('roles');
            if (e.options[e.selectedIndex].value) {
                const rol = {
                    name: e.options[e.selectedIndex].text,
                    id: e.options[e.selectedIndex].value,
                };
            
                if (!this.selectedRoles.filter((r) => r.id === rol.id && r.name === rol.name).length) {
                    this.selectedRoles.push(rol);
                }
            }
        },
        addToPositionArray(e = null, id = null) { 
            if (!id) {
                id = (e && e.target.options[e.target.selectedIndex].value) 
                    ? e.target.options[e.target.selectedIndex].value 
                    : null;                  
            }       
            const childrenArrLength = this.menu.filter((elemt) => elemt.parent_id == id).length;
            this.position = (this.action === 'Add') 
                ? [...Array.from(Array(childrenArrLength + 1), (x, index) => index + 1)] 
                : [...Array.from(Array(childrenArrLength || 1), (x, index) => index + 1)];
        },
        generateMenu(menuArr, container) {
            const menuHTML = document.createElement('UL');
            menuHTML.style.listStyleType = 'none';
            if (container.tagName === 'LI' && container.classList.contains('nav-dropdown')) {              
                menuHTML.style.overflowY = 'visible'; 
                menuHTML.classList.add('nav-dropdown-items');
            }
            else {
                menuHTML.classList.add('nav');
            }
            menuArr.forEach((m) => {
                const li = this.liTemplate.clone();
                li.css({'display': 'block', 'min-width': '100%'});
                if ($(container).prop('tagName') === 'LI' && $(container).hasClass('nav-dropdown')) {
                    li.css({'margin-left': '8px'});
                    li.addClass('indent-right'); 
                }

                li
                    .find('a.nav-link.dropdown-toggle')
                    .first()
                    .text(m.name); 
                const aArray = li.find('div.dropdown-menu a');
                aArray[0].onclick = () => this.editLink(m);
                aArray[1].onclick = this.deleteLink;
                aArray[1].href = `/menus/destroy?id=${m.id}`;

                menuHTML.appendChild(li.get(0));
                
                if (m.children.length) {
                    li.addClass('nav-dropdown');
                    this.generateMenu(m.children, li.get(0));
                }
            });
            container.appendChild(menuHTML);
        },
        deleteLink(e) {
            if (!confirm('Are you sure you want to delete this link?')) {
                e.preventDefault();
            }
        },
        editLink(link) {
            this.action = 'Edit';
            this.currentData = { ...link};
            this.addToPositionArray(null, this.currentData.parent_id);
        },
        reset() {
            this.action = 'Add';
            this.currentData = { ...initialValues};
            document.querySelector('#parent_id').value = '';
        },
    },
};
</script>
<style scoped>
#menu-container {
  color: #fff;
  overflow-y: hidden !important;
}
#menu-container ul{
  color: #fff;
  list-style: none !important;
}
#menu-container ul li a:hover{
  background: #20a8d8 !important;
}
#menu-container .nav .nav-item .nav-link:hover{
  color: #fff !important;
  background: #20a8d8 !important;
}
</style>
