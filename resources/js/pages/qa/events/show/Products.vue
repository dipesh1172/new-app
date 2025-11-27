<template>
  <div class="card mb-0">
    <div class="card-header bg-dark text-white">
      Products
    </div>
    <div class="card-body table-responsive p-0">
      <table class="table table-striped mb-0 table-bordered">
        <thead>
          <tr class="table-active">
            <th>Type</th>
            <th>Identifier</th>
            <th>Auth Name</th>
            <th>Billing Name</th>
            <th>Address</th>
            <th>Product</th>
            <th>Custom Fields</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="products.length === 0">
            <td class="text-center" colspan="6">No products were found</td>
          </tr>
          <template
            v-for="(product, i) in products"
          >
            <tr :key="`view${i}`">
              <td>
                {{ product.event_type && product.event_type.event_type }}
                <br>
                <button
                  v-show="!editingProducts[i]"
                  href="#"
                  type="button"
                  class="btn btn-sm mt-2 btn-info"
                  title="Update Event Information"
                  @click="edit(i)"
                >
                  <span class="fa fa-pencil" /> Correct Info
                </button>
              </td>

              <td>
                <template
                  v-for="(identifier, i) in product.identifiers"
                >
                  <div
                    :key="i"
                    :class="{'pt-2': i > 0}"
                  >
                    <strong>{{ identifier.utility_account_type.account_type }}</strong><br>
                    <pre style="font-size:100%; letter-spacing:0.5px">{{ identifier.identifier }}</pre>
                  </div>
                </template>
              </td>

              <td>{{ product.auth_first_name }} {{ product.auth_middle_name !== null ? product.auth_middle_name : '' }} {{ product.auth_last_name }}</td>

              <td v-if="product.company_name">
                {{ product.company_name }}
              </td>
              <td v-else>
                {{ product.bill_first_name }} {{ product.bill_middle_name !== null ? product.bill_middle_name : '' }} {{ product.bill_last_name }}
              </td>

              <td>
                <span
                  v-for="(address, i) in product.addresses"
                  :key="i"
                >
                  <strong>{{ address.id_type === 'e_p:billing' ? 'Billing:' : 'Service:' }}</strong>
                  <span v-if="address.address.line_1">
                    {{ address.address.line_1 }} <em>{{ address.address.line_2 }}</em> {{ address.address.city }}, {{ address.address.state_province }} {{ address.address.zip }}
                  </span>
                  <span v-else>N/A</span>

                  <br>
                </span>
              </td>

              <td>
                <div v-if="product.rate">
                  <span v-if="product.rate.utility && product.rate.utility.utility">
                    <strong>Provider: </strong> {{ product.rate.utility.utility.name }}<br>
                  </span>

                  <span v-if="product.rate.product && product.rate.product.name">
                    <strong>Product: </strong> {{ product.rate.product.name }}<br>
                  </span>

                  <span v-if="product.rate.program_code">
                    <strong>Program Code:</strong> {{ product.rate.program_code }}<br>
                  </span>

                  <span v-if="product.rate.brand_name">
                    <strong>Brand Name:</strong> {{ product.rate.brand_name }}<br>
                  </span>

                  <span v-if="product.rate.rate_amount">
                    <strong>Rate Amount:</strong>
                    <span v-if="product.rate.rate_amount > 0 && product.rate.rate_currency.currency === 'cents'">
                      {{ formatRate(product.rate.rate_amount / 100) }}
                    </span>
                    <span v-else>
                      {{ formatRate(product.rate.rate_amount) }}
                    </span>

                    per {{ product.rate.rate_uom.uom }}<br>
                  </span>

                  <span v-if="product.rate.intro_rate_amount">
                    <strong>Intro Rate Amount</strong>
                    <span v-if="product.rate.intro_rate_amount > 0 && product.rate.rate_currency.currency === 'cents'">
                      {{ product.rate.intro_rate_amount / 100 }}
                    </span>
                    <span v-else>
                      {{ product.rate.intro_rate_amount }}
                    </span>

                    per {{ product.rate.rate_uom.uom }}<br>
                  </span>

                  <span v-if="product.product && product.product.term">
                    <strong>Term:</strong> {{ product.product.term }}<br>
                  </span>

                  <span v-if="product.product && product.product.intro_term">
                    <strong>Intro Term:</strong> {{ product.product.intro_term }}<br>
                  </span>
                </div>
              </td>
              <td>
                <template v-if="product.custom_fields != null">
                  <span
                    v-for="(field, fieldn) in product.custom_fields"
                    :key="`field-${product.id}-${fieldn}`"
                  >
                    <strong :title="`[${ field.custom_field.output_name }] ${field.custom_field.description}`">{{ field.custom_field.name }}:</strong>
                    {{ field.value }}
                    <br>
                  </span>
                </template>
              </td>
            </tr>
            <tr
              v-show="editingProducts[i]"
              :key="`edit${i}`"
            >
              <td colspan="7">
                <w-megalo
                  :product="product"
                  :event="event"
                  :qid="`${i}`"
                  :index="i"
                  @cancel="edit(i)"
                />
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import MegaloAccountEditor from './MegaloAccountEditor.vue';

export default {
    name: 'QaEventsProducts',
    components: {
        'w-megalo': MegaloAccountEditor,
    },
    props: {
        products: {
            type: Array,
            default: () => [],
        },
        event: {
            type: Object,
            default: () => {},
        },
    },
    data() {
        return {
            editingProducts: [],
        };
    },
    watch: {
        products() {
            this.updateEditingProducts();
        },
    },
    mounted() {
        this.updateEditingProducts();
    },
    methods: {
        updateEditingProducts() {
            const out = [];
            for (let i = 0, len = this.products.length; i < len; i += 1) {
                out.push(false);
            }
            this.editingProducts = out;
        },
        edit(i) {
            console.log(`editing ${i}`);
            const curVal = this.editingProducts[i];
            this.updateEditingProducts();
            if (!curVal) {
              
                this.editingProducts[i] = true;
            }
            return false;
        },
        formatRate(r) {
            if ('formatRate' in window) {
                return window.formatRate(r);
            }
            // none of this should be needed, the function lives in app.js
            const x = r;
            if (x == null) {
                return 0;
            }
            const ret = x.toFixed(6);
            let toRemove = 0;
            let rindex = ret.length - 1;
            while (ret[rindex] == '0') {
                toRemove += 1;
                rindex -= 1;
            }
            if (toRemove > 0) {
                return ret.slice(0, -(toRemove));
            }
            const decimalIndex = ret.indexOf('.');
            const decimalPlaces = (ret.length - decimalIndex) - 1;
            if (decimalPlaces == 6) {
                return ret.slice(0, -1);
            }
            return ret;
        },
    },
};
</script>
