<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'DNIS', url: '/dnis'},
        {name: 'DNIS Choose', active: true}
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="animated fadeIn">
        <div class="card">
          <div class="card-header">
            <i class="fa fa-th-large" /> DNIS Choose
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-12">
                <div
                  v-if="flashMessage"
                  class="alert alert-success"
                >
                  <span class="fa fa-check-circle" />
                  <em>{{ flashMessage }}</em>
                </div>

                <div
                  v-show="errorsMutated.length"
                  class="alert alert-danger"
                >
                  <li
                    v-for="(error, index) in errorsMutated"
                    :key="index"
                  >
                    {{ error }}
                  </li>
                </div>

                <form
                  id="chooseform"
                  method="POST"
                  action="/dnis"
                  accept-charset="UTF-8"
                  autocomplete="off"
                  @submit="submit"
                >
                  <input
                    name="_token"
                    type="hidden"
                    :value="csrfToken"
                  >                
                  <div class="form-group">
                    <label for="dnis">You Choose the number</label>
                    <input
                      type="hidden"
                      name="dnis"
                      :value="number"
                    >
                    <input
                      type="hidden"
                      name="service_type_id"
                      value="3"
                    >
                    <input
                      type="hidden"
                      name="dnis_type"
                      :value="getParams().type"
                    >
                    <strong>{{ numberDisplay }}</strong>
                  </div>

                  <div class="form-group">
                    <label for="brand_id">Brand</label>
                    <select
                      ref="brand_id"
                      name="brand_id"
                      class="form-control"
                    >
                      <option value="">
                        Select a Brand
                      </option>
                      <option
                        v-for="brand in brands"
                        :key="brand.id"
                        :value="brand.id"
                      >
                        {{ brand.name }}
                      </option>
                    </select>
                  </div>

                  <button
                    type="submit"
                    class="btn btn-primary"
                  >
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
</template>
<script>
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'ChooseDnis',
    components: {
        Breadcrumb,
    },
    props: {
        errors: {
            type: Array,
            default: () => [],
        },
        brands: {
            type: Array,
            default: () => [],
        },
        flashMessage: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            csrfToken: window.csrf_token,
            numberDisplay: this.getParams().numberDisplay,
            number: this.getParams().number,
            errorsMutated: this.errors,
        };
    },
    methods: {
        submit(e) {
            if (this.$refs.brand_id.value) {
                return true;
            }
            if (!this.errorsMutated.includes('Please select brand.')) {
                this.errorsMutated.push('Please select brand.');
            }
            e.preventDefault();
            return false;
        },
        formatPhoneNumber(number) {
            if (number === undefined || number.length === 0) {
                console.log('formatPhoneNumber number was undefined.');
                return '';
            }

            const formatted = number.replace(
                /^\+?[1]?\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/,
                '($1) $2-$3'
            );

            return formatted;
        },
        getParams() {
            const url = new URL(window.location.href);
            const type = url.searchParams.get('type') || 1;
            const number = (url.searchParams.get('number')) ? `+${url.searchParams.get('number').trim()}` : '';
            const numberDisplay = this.formatPhoneNumber(number);
            return {
                type,
                number,
                numberDisplay,
            };
        },
    },
};
</script>
