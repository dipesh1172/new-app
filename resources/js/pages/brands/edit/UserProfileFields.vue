<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: 'User Profile Fields', active: true}
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active"
    >
          <div v-if="validationErrors.length" class="alert alert-danger">
        <ul>
          <li v-for="(error, index) in validationErrors" :key="index">
              {{ error }}
          </li>
        </ul>
      </div>
      <button
        type="button"
        class="btn btn-default"
        @click="toggleSections"
      >
        Sections <i :class="{'fa': true, 'fa-chevron-up': sectionEditorShown, 'fa-chevron-down': !sectionEditorShown}" />
      </button>
      <div
        v-if="sectionListShown"
        class="card"
      >
        <div class="card-body">
          <div class="row">
            <div
              v-for="(section, si) in rsections"
              :key="si"
              class="col-4"
            >
              <div class="card">
                <div class="card-body">
                  {{ section.name }}
                  <button
                    v-if="section.sort > 0"
                    type="button"
                    class="btn btn-primary pull-right"
                    @click="editSection(si)"
                  >
                    <i class="fa fa-edit" />
                  </button>
                  <span
                    v-else
                    class="badge badge-secondary pull-right"
                  >Not Editable</span>
                </div>
              </div>
            </div>
            <div class="col-4">
              <div class="card">
                <div class="card-body">
                  <button
                    type="button"
                    class="btn btn-success"
                    @click="addSection"
                  >
                    New Section
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div
            v-if="sectionEditorShown && sectionEditing !== null"
            class="row"
          >
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  Name:
                  <input
                    v-model="sectionEditing.name"
                    type="text"
                    placeholder="Section Name"
                    :disabled="working"
                  >
                  Sort:
                  <select
                    v-model="sectionEditing.sort"
                    :disabled="working"
                  >
                    <option
                      v-for="n in 10"
                      :key="n"
                      :value="n"
                    >
                      {{ n }}
                    </option>
                  </select>
                  <button
                    v-if="sectionEditing.id !== null"
                    type="button"
                    :disabled="working"
                    class="btn btn-danger"
                    @click="removeSection(sectionEditing.id)"
                  >
                    Disable
                  </button>
                  <button
                    type="button"
                    :disabled="working"
                    class="btn btn-primary"
                    @click="saveSection"
                  >
                    <i class="fa fa-save" /> Save
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <hr>
      </div>
      <hr>
      <div class="row">
        <div
          v-for="(field, i) in rfields"
          :key="i"
          class="col-4"
        >
          <div class="card">
            <div class="card-body">
              {{ field.name }}
              <button
                type="button"
                class="btn btn-primary pull-right"
                @click="editField(i)"
              >
                Edit
              </button>
            </div>
          </div>
        </div>
        <div class="col-4">
          <div class="card">
            <div class="card-body">
              <button
                type="button"
                class="btn btn-success"
                @click="addField"
              >
                Add Field
              </button>
            </div>
          </div>
        </div>
      </div>
      <div
        v-if="fieldEditing !== null && fieldEditorShown"
        class="row"
      >
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div class="form-group">
                <label>Section</label>
                <select
                  v-model="fieldEditing.section_id"
                  :disabled="working"
                  class="form-control"
                >
                  <option
                    v-for="(section, i) in rsections"
                    :key="i"
                    :value="section.id"
                  >
                    {{ section.name }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Field Name</label>
                <input
                  v-model="fieldEditing.name"
                  type="text"
                  class="form-control"
                  :disabled="working"
                >
              </div>
              <div class="form-group">
                <label>Sort</label>
                <select
                  v-model="fieldEditing.sort"
                  :disabled="working"
                  class="form-control"
                >
                  <option
                    v-for="n in 10"
                    :key="n"
                    :value="n"
                  >
                    {{ n }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Description</label>
                <input
                  v-model="fieldEditing.desc"
                  :disabled="working"
                  class="form-control"
                >
              </div>
              <div class="form-group">
                <label>Field Type</label>
                <select
                  v-model="fieldEditing.type"
                  class="form-control"
                  :disabled="working"
                >
                  <option value="checkbox">
                    Checkbox
                  </option>
                  <option value="text">
                    Text Input
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Is this field required?</label>
                <select
                  v-model="fieldEditing.required"
                  class="form-control"
                  :disabled="working"
                >
                  <option :value="0">
                    No
                  </option>
                  <option :value="1">
                    Yes
                  </option>
                </select>
              </div>
              <div class="form-row">
                <div class="col-12">
                  <button
                    v-if="fieldEditing.id !== null"
                    type="button"
                    :disabled="working"
                    class="btn btn-danger pull-left"
                    @click="removeField(fieldEditing.id)"
                  >
                    Disable
                  </button>
                  <button
                    type="button"
                    :disabled="working"
                    class="btn btn-primary pull-right"
                    @click="saveField"
                  >
                    <i class="fa fa-save" /> Save
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </layout>
</template>
<script>
import Layout from './Layout';

export default {
    name: 'UserProfileFields',
    components: {
        Layout,
    },
    props: {
        brand: {
            type: Object,
            required: true,
        },
        sections: {
            type: Array,
            required: true,
        },
        fields: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            sectionListShown: false,
            sectionEditorShown: false,
            sectionEditing: null,
            rsections: null,
            rfields: null,
            working: false,
            fieldEditorShown: false,
            fieldEditing: null,
            validationErrors: []
        };
    },
    computed: {
        csrf_token() {
            return window.csrf_token;
        },
    },
    mounted() {
        this.rsections = JSON.parse(JSON.stringify(this.sections));
        this.rfields = JSON.parse(JSON.stringify(this.fields));
    },
    methods: {
        toggleSections() {
            this.sectionListShown = !this.sectionListShown;
        },
        toggleSectionEditor() {
            this.sectionEditorShown = !this.sectionEditorShown;
        },
        editSection(index) {
            this.sectionEditing = this.rsections[index];
            this.sectionEditorShown = true;
        },
        addSection() {
            this.sectionEditing = {
                id: null,
                created_at: null,
                updated_at: null,
                deleted_at: null,
                name: null,
                sort: 1,
                brand_id: this.brand.id,
            };
            this.sectionEditorShown = true;
        },
        saveSection() {
            const toSave = JSON.parse(JSON.stringify(this.sectionEditing));
            this.working = true;
            this.validationErrors = [];
            if (!toSave.name) {
                this.validationErrors.push('The Section is required.');
            }
            if (this.validationErrors.length > 0) {
                this.working = false;
                return;
            }
            axios.post('/brands/save_brand_user_profile_section', {
                _token: this.csrf_token,
                id: toSave.id,
                brand_id: this.brand.id,
                name: toSave.name,
                sort: toSave.sort,
            }).then((res) => {
                if (res.data.error !== false) {
                    throw new Error(res.data.error);
                }
                const isNew = toSave.id === null;
                toSave.id = res.data.id;
                this.sectionEditorShown = false;
                this.sectionEditing = null;
                if (isNew) {
                    this.rsections.push(toSave);
                }
                else {
                    for (let i = 0, len = this.rsections.length; i < len; i += 1) {
                        if (this.rsections[i].id === toSave.id) {
                            this.rsections[i].name = toSave.name;
                            this.rsections[i].sort = toSave.sort;
                        }
                    }
                }
                this.sortSections();
            }).catch((e) => {
                alert('Could not save Section');
                console.log(e);
            }).finally(() => {
                this.working = false;
            });
        },
        sortSections() {
            this.rsections.sort((lhs, rhs) => {
                if (lhs.sort === rhs.sort) {
                    return 0;
                }
                if (lhs.sort > rhs.sort) {
                    return 1;
                }
                return -1;
            });
        },
        addField() {
            this.fieldEditorShown = true;
            this.fieldEditing = {
                id: null,
                created_at: null,
                updated_at: null,
                deleted_at: null,
                section_id: null,
                name: null,
                sort: 1,
                desc: null,
                type: null,
                properties: null,
            };
        },
        editField(index) {
            this.fieldEditorShown = true;
            this.fieldEditing = JSON.parse(JSON.stringify(this.rfields[index]));
        },
        saveField() {
            this.working = true;
            const toSave = JSON.parse(JSON.stringify(this.fieldEditing));
            this.validationErrors = [];
            if (!toSave.section_id) {
                this.validationErrors.push('The field Section is required.');
            }
            if (!toSave.name) {
                this.validationErrors.push('The field Field Name is required.');
            }
            if (!toSave.desc) {
                this.validationErrors.push('The field Description is required.');
            }
            if (!toSave.type) {
                this.validationErrors.push('The field Field Type is required.');
            }
            if (this.validationErrors.length > 0) {
                this.working = false;
                return;
            }
            axios.post('/brands/save_brand_user_profile_field', {
                _token: this.csrf_token,
                ...toSave,
            })
                .then((res) => {
                    if (res.data.error !== false) {
                        throw new Error(res.data.error);
                    }
                    const isNew = toSave.id === null;
                    toSave.id = res.data.id;
                    this.fieldEditorShown = false;
                    this.fieldEditing = null;
                    if (isNew) {
                        this.rfields.push(toSave);
                    }
                    else {
                        for (let i = 0, len = this.rfields.length; i < len; i += 1) {
                            if (this.rfields[i].id === toSave.id) {
                                this.rfields[i] = toSave;
                            }
                        }
                    }
                })
                .catch((e) => {
                    alert('Could not save field');
                    console.log(e);
                })
                .finally(() => {
                    this.working = false;
                });
        },
        removeField(id) {
            let index = null;
            for (let i = 0, len = this.rfields.length; i < len; i += 1) {
                if (this.rfields[i].id === id) {
                    index = i;
                }
            }
            if (index === null) {
                return;
            }
            if (confirm('Are you sure you want to disable this field?')) {
                this.working = true;
                axios.post('/brands/save_brand_user_profile_field', {
                    _token: this.csrf_token,
                    id,
                    command: 'disable',
                }).then((res) => {
                    if (res.data.error !== false) {
                        throw new Error(res.data.error);
                    }
                    this.rfields.splice(index, 1);
                    this.fieldEditorShown = false;
                    this.fieldEditing = null;
                }).catch((e) => {
                    alert('Could not remove field');
                    console.log(e);
                })
                    .finally(() => {
                        this.working = false;
                    });
            }
        },
        removeSection(id) {
            let index = null;
            for (let i = 0, len = this.rsections.length; i < len; i += 1) {
                if (this.rsections[i].id === id) {
                    index = i;
                }
            }
            for (let i = 0, len = this.rfields.length; i < len; i += 1) {
                if (this.rfields[i].section_id === id) {
                    alert(`Cannot remove this section as it is in use by the field: ${this.rfields[i].name}`);
                    return;
                }
            }
            if (index === null) {
                return;
            }
            if (confirm('Are you sure you want to disable this section?')) {
                this.working = true;
                axios.post('/brands/save_brand_user_profile_section', {
                    _token: this.csrf_token,
                    id,
                    command: 'disable',
                }).then((res) => {
                    if (res.data.error !== false) {
                        throw new Error(res.data.error);
                    }
                    this.rsections.splice(index, 1);
                    this.sectionEditing = null;
                    this.sectionEditorShown = false;
                }).catch((e) => {
                    alert('Could not remove section');
                    console.log(e);
                })
                    .finally(() => {
                        this.working = false;
                    });
            }
        },
    },
};
</script>
