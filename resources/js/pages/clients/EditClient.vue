<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Clients', url: '/clients'},
        {name: `Edit Client ${client.name}`, active: true}
      ]"
    />
    
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> Edit Client {{ client.name }}
          </div>
          <div class="card-body">
            <form
              method="POST"
              :action="`/clients/${client.id}`"
              accept-charset="UTF-8"
              autocomplete="off"
              enctype="multipart/form-data"
            >
              <input
                name="_method"
                type="hidden"
                value="PUT"
              >
              <input
                type="hidden"
                name="_token"
                :value="csrf_token"
              >
              <div class="row">
                <div class="col-md-3 text-center">
                  <img
                    :src="logoPreview"
                    class="img-avatar"
                    alt="No logo"
                    style="max-width: 100%; height: auto;"
                  >

                  <br><br>
                  <div class="form-group">
                    <input
                      name="logo_upload"
                      type="file"
                      :style="{
                        border: '1px solid #c2cfd6',
                        padding: '.5rem .75rem',
                        width: '100%'
                      }"
                      @change="handleLogoChange"
                    >
                  </div>
                </div>
                <div class="col-md-9">
                  <div
                    v-show="flashMessage"
                    class="alert alert-success"
                  >
                    <span class="fa fa-check-circle" /><em>{{ flashMessage }}</em>
                  </div>

                  <div
                    v-show="errors.length"
                    class="alert alert-danger"
                  >
                    <li
                      v-for="(error, index) in errors"
                      :key="index"
                    >
                      {{ error }}
                    </li>
                  </div>

                  <div class="form-group">
                    <label for="name">Client Name</label>
                    <input
                      v-model="client.name"
                      type="text"
                      name="name"
                      class="form-control form-control-lg"
                      placeholder="Enter a Client Name"
                    >
                  </div>

                  <div class="row">
                    <div class="col-md-8">
                      <div class="form-group">
                        <label for="address">Address</label>
                        <input
                          v-model="client.address"
                          type="text"
                          name="address"
                          class="form-control form-control-lg"
                          placeholder="Enter the Address"
                        >
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="city">City</label>
                        <input
                          v-model="client.city"
                          type="text"
                          name="city"
                          class="form-control form-control-lg"
                          placeholder="Enter a City"
                        >
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="state">State</label>
                        <select
                          v-model="client.state"
                          name="state"
                          class="form-control form-control-lg"
                        >
                          <option value="">
                            Select a state
                          </option>
                          <option
                            v-for="state in states"
                            :key="state.id"
                            :value="state.id"
                          >
                            {{ state.name }}
                          </option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="zip">Zip Code</label>
                        <input
                          v-model="client.zip"
                          type="text"
                          name="zip"
                          class="form-control form-control-lg"
                          :placeholder="`Enter a Zip Code`"
                        >
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="phone">Phone</label>
                        <the-mask
                          v-model="phoneC"
                          type="text"
                          name="phone"
                          mask="(###) ###-####"
                          class="form-control form-control-lg"
                          placeholder="Enter a Phone"
                        />
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label for="email">Email</label>
                        <input
                          v-model="client.email"
                          type="text"
                          name="email"
                          class="form-control form-control-lg"
                          placeholder="Enter a Email"
                        >
                      </div>
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea
                      v-model="client.notes"
                      name="notes"
                      class="form-control form-control-lg"
                      placeholder="Enter notes"
                    />
                  </div>

                  <button
                    type="submit"
                    class="btn btn-primary"
                  >
                    Submit
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { TheMask } from 'vue-the-mask';
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'EditClient',
    components: {
        TheMask,
        Breadcrumb,
    },
    props: {
        client: {
            type: Object,
            default: () => {},
        },
        states: {
            type: Array,
            default: () => [],
        },
        flashMessage: {
            type: String,
            default: '',
        },
        errors: {
            type: Array,
            default: () => [],
        },
        awsCloudFront: {
            type: String,
            default: '',
        },
    },
    data() {
        return {
            logoPreview: 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mm&s=300',
        };
    },
    computed: {
        csrf_token() {
            return csrf_token;
        },     
        phoneC: {
            get() {   
                if (this.client.phone) {
                    if (this.client.phone[0] === '+' && this.client.phone[1] === '1') {
                        return this.client.phone.substr(2);
                    }
                    if (this.client.phone[1] === '1') {
                        return this.client.phone.substr(1);
                    }            
                }
                return this.client.phone;
            },
            set(val) {
                this.client.phone = val;
            },
        },
    },
    created() {
        if (this.client.filename) {
            this.logoPreview = `${this.awsCloudFront}/${this.client.filename}`;
        }
    },
    methods: {
        handleLogoChange(e) {           
            const reader = new FileReader();
            reader.onload = ({target: { result }}) => this.logoPreview = result;
            reader.readAsDataURL(e.target.files[0]);

        },
    },
};
</script>
