<template>
  <div class="card">
    <form 
      class="card-body" 
      @submit.prevent="$emit('submit', values)"
    >
      <fieldset 
        :disabled="submitting" 
        :aria-busy="submitting"
      >
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="name">Name</label>
              <input
                v-model="values.name"
                :class="['form-control', { 'is-invalid': errors.name && touched.name }]"
                type="text"
                name="name"
                placeholder="Name"
                @blur="handleInputBlur"
              >
              <div 
                v-if="errors.name && touched.name" 
                class="invalid-feedback"
              >
                <ul class="list-unstyled">
                  <li 
                    v-for="(error, i) in errors.name" 
                    :key="i"
                  >
                    {{ error }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group">
              <label for="title">Title</label>
              <input
                v-model="values.title"
                type="text"
                name="title"
                class="form-control"
                placeholder="Title"
                @blur="handleInputBlur"
              >
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="email">Email</label>
              <input
                v-model="values.email"
                :class="['form-control', { 'is-invalid': errors.email && touched.email }]"
                type="text"
                name="email"
                placeholder="Email"
                @blur="handleInputBlur"
              >
              <div 
                v-if="errors.email && touched.email" 
                class="invalid-feedback"
              >
                <ul class="list-unstyled">
                  <li 
                    v-for="(error, i) in errors.email" 
                    :key="i"
                  >
                    {{ error }}
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="contact_type">Contact Type</label>
              <select
                v-model="values.brand_contact_type_id"
                name="contact_type"
                class="form-control"
                @blur="handleInputBlur"
              >
                <option 
                  :value="null" 
                  disabled 
                  hidden
                >
                  Select Contact Type
                </option>
                <option
                  v-for="contact_type in contact_types"
                  :key="contact_type.id"
                  :value="contact_type.id"
                >
                  {{ contact_type.name }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            Phone Numbers 
            <button 
              class="btn btn-sm btn-primary pull-right" 
              style="margin-bottom: -0.25rem;"
              type="button"
              @click="addPhone"
            >
              <i class="fa fa-plus" />
            </button>
          </div>
          <div class="card-body">
            <div 
              v-for="(phone, i) in values.phones" 
              :key="'phone-' + i"
              :class="{'row mb-2': true, 'border-bottom':i < values.phones.length - 1}"
            >
              <div class="col-md-6">
                <div class="form-group">
                  <label for="phone">Phone</label>
                  <input
                    v-model="values.phones[i].phone_number.phone_number"
                    :class="['form-control', { 'is-invalid': errors.phone && touched.phone }]"
                    type="text"
                    name="phone[]"
                    mask="(###) ###-####"
                    :disabled="values.phones[i].id != null"
                    placeholder="Phone"
                    @blur="handleInputBlur"
                  >
                  <div 
                    v-if="errors.phone && touched.phone" 
                    class="invalid-feedback"
                  >
                    <ul class="list-unstyled">
                      <li 
                        v-for="(error, i) in errors.phone" 
                        :key="i"
                      >
                        {{ error }}
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="phone_ext">Extension</label>
                  <input 
                    v-model="values.phones[i].phone_number.extension" 
                    type="text" 
                    class="form-control" 
                    name="extension[]" 
                    :disabled="values.phones[i].id != null"
                    @blur="handleInputBlur"
                  >
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="phone_type">Phone Type</label>
                  <select
                    v-model="values.phones[i].phone_number.label_id"
                    name="phone_type[]"
                    class="form-control"
                    :disabled="values.phones[i].id != null"
                    @blur="handleInputBlur"
                  >
                    <option 
                      :value="null" 
                      disabled 
                      hidden
                    >
                      Select Phone Type
                    </option>
                    <option
                      v-for="phone_type in phone_types"
                      :key="phone_type.id"
                      :value="phone_type.id"
                    >
                      {{ phone_type.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="col-md-1">
                <div class="form-group">
                  <label>&nbsp;</label>
                  <button 
                    type="button" 
                    class="btn btn-danger" 
                    title="Remove"
                    @click="removeRow(i)"
                  >
                    <i class="fa fa-remove" />
                  </button>
                </div>
              </div>
            </div>
            <template v-if="toRemove.length > 0">
              <hr>
              <h4>Will be removed:</h4>
              <div 
                v-for="(phone, i) in toRemove" 
                :key="'remove-' + i"
                :class="{'row mb-2 bg-danger': true, 'border-bottom':i < toRemove.length - 1}"
              >
                <input
                  type="hidden"
                  :name="`remove[${i}]`"
                  :value="toRemove[i].id"
                >
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="phone">Phone</label>
                    <input
                      v-model="toRemove[i].phone_number.phone_number"
                      :class="['form-control', { 'is-invalid': errors.phone && touched.phone }]"
                      type="text"
                      mask="(###) ###-####"
                      :disabled="true"
                      placeholder="Phone"
                    >
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-group">
                    <label for="phone_ext">Extension</label>
                    <input 
                      v-model="toRemove[i].phone_number.extension" 
                      type="text" 
                      class="form-control" 
                      :disabled="true"
                    >
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="phone_type">Phone Type</label>
                    <select
                      v-model="toRemove[i].phone_number.label_id"
                      
                      class="form-control"
                      :disabled="true"
                    >
                      <option 
                        :value="null" 
                        disabled 
                        hidden
                      >
                        Select Phone Type
                      </option>
                      <option
                        v-for="phone_type in phone_types"
                        :key="phone_type.id"
                        :value="phone_type.id"
                      >
                        {{ phone_type.name }}
                      </option>
                    </select>
                  </div>
                </div>
                <div class="col-md-1">
                  <div class="form-group">
                    <label>&nbsp;</label>
                    <button 
                      type="button" 
                      class="btn btn-danger" 
                      title="Unremove"
                      @click="unremoveRow(i)"
                    >
                      <i class="fa fa-remove" />
                    </button>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>

        <button
          :disabled="submitting"
          type="button"
          class="btn btn-danger pull-left"
          @click="$emit('cancel')"
        >
          <i
            class="fa fa-ban"
            aria-hidden="true"
          /> 
          Cancel
        </button>
        <button
          :disabled="disabled"
          type="submit"
          class="btn btn-primary pull-right"
        >
          <i
            class="fa fa-floppy-o"
            aria-hidden="true"
          /> 
          Save
        </button>
      </fieldset>
    </form>
  </div>
</template>

<script>
import { TheMask } from 'vue-the-mask';
import { mapState } from 'vuex';

const emailRegex = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

export default {
    name: 'BrandContactForm',
    components: {
        TheMask,
    },
    props: {
        initialValues: {
            type: Object,
            default: () => ({}),
        },
        submitting: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            values: this.initialValues || {},
            touched: {},
            toRemove: [],
        };
    },
    watch: {
        initialValues() {
            this.values = this.initialValues;
            this.touched = {};
        },
    },
    computed: {
        ...mapState(['contact_types', 'phone_types']),
        errors() {
            const [errors, addError] = this.useErrors();
            const { phone, email, name } = this.values;
            if (phone && phone.length !== 10) { addError('phone', 'Phone is not valid'); }
            if (email && !emailRegex.test(email)) { addError('email', 'Email is not valid'); }
            if (!name) { addError('name', 'Name is required'); }
            return errors;
        },
        disabled() {
            return Object.keys(this.errors).length || this.submitting;
        },
    },
    methods: {
        addPhone() {
            this.values.phones.push({
                id: null,
                phone_number: {
                    id: null,
                    phone_number: null,
                    extension: null,
                    label_id: null,
                },
            });
        },
        handleInputBlur({target: { name }}) {
            this.touched = { ...this.touched, [name]: true };
        },
        useErrors() {
            const errors = {};
            const addError = (key, error) => errors[key] = [...(errors[key] || []), error];
            return [errors, addError];
        },
        removeRow(i) {
            this.toRemove.push(this.values.phones[i]);
            this.values.remove.push(this.values.phones[i]);
            this.values.phones.splice(i, 1);
        },
        unremoveRow(i) {
            this.values.phones.push(this.toRemove[i]);
            this.values.remove.splice(i, 1);
            this.toRemove.splice(i, 1);
        },
    },
};
</script>

<style scoped>
.border-bottom {
  border-bottom: 1px solid #ccc;
}
</style>
