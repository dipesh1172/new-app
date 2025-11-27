<template>
  <div>
    <div
      v-if="this.$parent.validationErrors.length > 0"
      class="alert alert-danger"
    >
      <ul class="mr-2">
        <li
          v-for="(error, index) in this.$parent.validationErrors"
          :key="index"
        >
          {{ error }}
        </li>
      </ul>
    </div>
    <form
      class="add-charge-form"
      @submit="handleSubmit"
    >
      <div class="row mb-2">
        <div class="col-md-6">
          <custom-input
            v-model="chargeData.owner"
            label="Owner"
            placeholder="Owner"
            name="owner"
          />
        </div>
        <div class="col-md-6">
          <template v-if="chargeData.date_of_work === today">
            <div class="form-group">
              <label>Date of Work</label>
              <div class="input-group">
                <input
                  readonly
                  class="form-control"
                  type="date"
                  name="date_of_work"
                  :value="today"
                >
                <div class="input-group-append">
                  <button
                    type="button"
                    class="btn btn-warning"
                    @click="changeDate"
                  >
                    <span class="fa fa-pencil p-1" />
                  </button>
                </div>
              </div>
            </div>
          </template>
          <custom-input
            v-else
            v-model="chargeData.date_of_work"
            label="Date of Work"
            type="date"
            placeholder="Date of Work"
            name="date_of_work"
          />
        </div>
      </div>
      <div class="row mb-2">
        <div class="col-md-12">
          <custom-select
            v-model="chargeData.brand_id"
            :items="brandsData"
            label="Brand"
            placeholder="Brand"
            name="brand"
          />
        </div>
        <div class="col-md-12">
          <custom-select
            v-model="chargeData.category_id"
            :items="categoriesData"
            label="Category"
            placeholder="Category"
            name="category"
          />
        </div>
      </div>
      <div class="row mb-2">
        <div class="col-md-12">
          <custom-input
            v-model="chargeData.ticket"
            label="Ticket"
            placeholder="Ticket"
            name="ticket"
          />
        </div>
      </div>

      <div class="row mb-2">
        <div class="col-md-6">
          <custom-input
            v-model.number="chargeData.duration"
            label="Duration / Qty"
            type="number"
            placeholder="Duration / Qty"
            name="duration"
            min="0"
            step="0.25"
          />
        </div>
        <div class="col-md-4">
          <custom-input
            v-if="selectedCategory !== null && selectedCategory.map_rate_to == null"
            v-model.number="chargeData.rate"
            label="Amount"
            type="number"
            placeholder="Amount"
            name="rate"
            min="0"
            step="0.01"
          />
          <div v-if="selectedCategory !== null && selectedCategory.map_rate_to !== null">
            <label>Amount</label><br>
            <input
              :value="chargeData.rate"
              type="hidden"
              name="rate"
            >
            {{ chargeData.rate }}
          </div>
        </div>
        <div
          v-if="selectedCategory !== null"
          class="col-md-2"
        >
          <label>Credit?</label>
          <input
            v-model="isCredit"
            type="checkbox"
            name="rate_is_credit"
          >
        </div>
      </div>

      <div class="row mb-2">
        <div class="col-md-12">
          <custom-textarea
            v-model="chargeData.description"
            label="Description"
            placeholder="Description"
            name="description"
          />
        </div>
      </div>

      <div class="row">
        <div class="col-md-12">
          <div
            v-if="!isCredit"
            class="pull-left"
          >
            <strong>Total: </strong>
            ${{ total }}
          </div>
          <div
            v-else
            calss="pull-left"
          >
            <strong>Total Credit: </strong>
            ${{ total }}
          </div>
          <input
            v-model="chargeData.id"
            type="hidden"
            name="id"
          >
          <button
            v-show="selectedCategory !== null"
            class="btn btn-primary pull-right"
            name="submit"
          >
            <i
              class="fa fa-floppy-o"
              aria-hidden="true"
            /> Save
          </button>
          <button type="button" data-dismiss="modal" class="btn btn-secondary pull-right" style="margin-right: 10px;"><i aria-hidden="true" class="fa fa-times"></i>
              Cancel
          </button>
        </div>
      </div>
    </form>
  </div>
</template>

<script>
import CustomInput from 'components/CustomInput';
import CustomSelect from 'components/CustomSelect';
import CustomTextarea from 'components/CustomTextarea';

export default {
    name: 'ChargeForm',
    components: {
        CustomInput,
        CustomTextarea,
        CustomSelect,
    },
    props: {
        handleSubmit: {
            type: Function,
            required: true,
        },
        currentChargeData: {
            type: Object | null,
            required: true,
        },
        categoriesData: {
            type: Array,
            required: true,
        },
        brandsData: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            chargeData: {
                id: null,
                category: null,
                category_id: null,
                client: null,
                brand_id: null,
                description: null,
                date_of_work: this.today,
                duration: null,
                ticket: null,
                owner: null,
                rate: null,
            },
            selectedCategory: null,
            ratecard: null,
            isCredit: false,
        };
    },
    computed: {
        today() {
            return this.getTodayDate();
        },
        canSave() {
            return this.chargeData.category_id !== null 
              && this.chargeData.brand_id !== null 
              && this.chargeData.date_of_work !== null
              && this.chargeData.description !== null 
              && this.chargeData.ticket !== null
              && this.chargeData.description.length > 1
              && this.chargeData.duration !== null
              && this.chargeData.owner !== null
              && this.chargeData.rate !== null;
        },
        total() {
            let result = 0;
            if (Number.isFinite(this.chargeData.duration) && Number.isFinite(this.chargeData.rate)) {
                if (this.chargeData.duration > 0
                    && this.chargeData.rate !== null
                    && this.chargeData.rate !== ''
                    && this.chargeData.rate > 0) {
                    result = (this.chargeData.duration * this.chargeData.rate).toFixed(2);
                }
                else {
                    result = Math.abs(this.chargeData.rate).toFixed(2);
                }

                if (result === '0.00') {
                    result = (this.chargeData.duration * this.chargeData.rate).toFixed(4);
                }
                if (result === '0.0000') {
                    result = '0.00';
                }
            }
            return result;
        },
    },
    watch: {
        'chargeData.date_of_work'(v) {
            if (v === '') {
                this.$nextTick(() => {
                    this.chargeData.date_of_work = this.today;
                });
            }
        },
        'chargeData.category_id'(v) {
            if (!v) {
                this.selectedCategory = null;
            }
            else {
                for (let i = 0, len = this.categoriesData.length; i < len; i += 1) {
                    if (this.categoriesData[i].value == v) {
                        this.selectedCategory = this.categoriesData[i];
                    }
                }
                if (this.ratecard !== null && this.selectedCategory !== null && this.selectedCategory.map_rate_to !== null) {
                    this.chargeData.rate = this.ratecard[this.selectedCategory.map_rate_to];
                }
            }
        },
        'chargeData.brand_id'(v) {
            if (v !== null) {
                this.loadRates(v);
            }
        },
        currentChargeData(v) {
            this.isCredit = false;
            if (v && v !== {}) {
                this.setChargeData(v);
            }
            else {
                this.setChargeData({
                    id: null,
                    category: null,
                    category_id: null,
                    client: null,
                    client_id: null,
                    description: null,
                    date_of_work: this.today,
                    duration: null,
                    ticket: null,
                    owner: null,
                    rate: null,
                });
            }
            if (v && v.rate < 0) {
                this.isCredit = true;
            }
        },
    },
    mounted() {
        if (this.currentChargeData) {
            this.setChargeData(this.currentChargeData);
            if (this.chargeData.rate !== null && this.chargeData.rate < 0) {
                this.isCredit = true;
                this.chargeData.rate = Math.abs(this.chargeData.rate);
            }
        }
    },
    methods: {
        changeDate() {
            this.chargeData.date_of_work = null;
        },
        getTodayDate() {
            const today = new Date();
            const dd = String(today.getDate()).padStart(2, '0');
            const mm = String(today.getMonth() + 1).padStart(2, '0'); // January is 0!
            const yyyy = today.getFullYear();

            return `${yyyy}-${mm}-${dd}`;
        },
        setChargeData(v) {      
            if (v === {}) {
                this.setChargeData({
                    id: null,
                    category: null,
                    category_id: null,
                    client: null,
                    client_id: null,
                    description: null,
                    date_of_work: this.today,
                    duration: null,
                    ticket: null,
                    owner: null,
                    rate: null,
                });
                return;
            }   
            Object.keys(this.chargeData).forEach((i) => {
                if (v[i] !== undefined) { 
                    this.chargeData[i] = v[i];                
                }
            });
            
        },
        loadRates(brand_id) {
            if (brand_id !== null && brand_id !== undefined) {
                axios.get(`/billing/${brand_id}/ratecard`)
                    .then((result) => {
                        this.ratecard = result.data.card;
                        if (this.ratecard !== null && this.selectedCategory !== null && this.selectedCategory.map_rate_to !== null) {
                            console.log('Rate assign: ', this.selectedCategory.map_rate_to);
                          
                            this.chargeData.rate = this.ratecard[this.selectedCategory.map_rate_to];
                        }
                    }).catch(console.log);
            }
        },
    },
};
</script>
