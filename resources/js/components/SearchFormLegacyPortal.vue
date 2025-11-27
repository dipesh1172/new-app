<template>
  <div :class="`search-form form-inline pull-${position}`">
    <div
      v-show="includeDateRange"
      class="form-group ml-2"
    >
      <label>Date Range</label>

      <DateRangePicker
        ref="rangedatePicker"
        v-model="dateRange"
        :start-date="startDate"
        :end-date="endDate"
        :locale-data="locale"
        :show-dropdowns="true"
        :ranges="false"
        :click-cancel="clearDate"
        opens="right"
        @update="updateValues"
      >
        <!--Optional scope for the input displaying the dates -->

        <template
          #input="rangedatePicker"
          style="min-width: 350px;"
        >
          {{ rangedatePicker.startDate | date }} - {{ rangedatePicker.endDate | date }}
        </template>
      </DateRangePicker>
      <button
        class="btn btn-info ml-2"
        type="button"
        @click="clearDate()"
      >
        Clear
      </button>
      &nbsp;&nbsp;
    </div>

    <div
      v-show="includeChannel"
      class="form-group"
    >
      <multiselect
        v-model="channel.value"
        :options="channels"
        :multiple="true"
        :close-on-select="false"
        :clear-on-select="false"
        :preserve-search="true"
        open-direction="below"
        placeholder="Select channel(s)"
        label="name"
        track-by="id"
      >
        <template
          slot="selection"
          slot-scope="{ values, search, isOpen }"
        >
          <span
            v-if="values.length && !isOpen"
            class="multiselect__single"
          >
            {{ values.length === channels.length ? 'All channels' : (values.length === 1 ? values[0].name : `${values.length} channels selected`) }}
          </span>
        </template>
      </multiselect>
    </div>

    <div
      v-show="includeMarket"
      class="form-group"
    >
      <multiselect
        v-model="market.value"
        :options="markets"
        :multiple="true"
        :close-on-select="false"
        :clear-on-select="false"
        :preserve-search="true"
        open-direction="below"
        placeholder="Select market(s)"
        label="name"
        track-by="id"
      >
        <template
          slot="selection"
          slot-scope="{ values, search, isOpen }"
        >
          <span
            v-if="values.length && !isOpen"
            class="multiselect__single"
          >
            {{ values.length === markets.length ? 'All markets' : (values.length === 1 ? values[0].name : `${values.length} markets selected`) }}
          </span>
        </template>
      </multiselect>
    </div>

    <div
      v-show="includeLanguage && languages.length > 1"
      class="form-group"
    >
      <multiselect
        v-model="language.value"
        :options="languages"
        :multiple="true"
        :close-on-select="false"
        :clear-on-select="false"
        :preserve-search="true"
        open-direction="below"
        placeholder="Trusted"
        label="name"
        track-by="id"
      >
        <template
          slot="selection"
          slot-scope="{ values, search, isOpen }"
        >
          <span
            v-if="values.length && !isOpen"
            class="multiselect__single"
          >
            {{ values.length === languages.length ? 'All Collections' : (values.length === 1 ? values[0].name : `${values.length} collections selected`) }}
          </span>
        </template>
      </multiselect>
    </div>

    <div
      v-show="includeCommodity"
      class="form-group"
    >
      <multiselect
        v-model="commodity.value"
        :options="commodities"
        :multiple="true"
        :close-on-select="false"
        :clear-on-select="false"
        :preserve-search="true"
        open-direction="below"
        placeholder="Select commodity(s)"
        label="name"
        track-by="id"
      >
        <template
          slot="selection"
          slot-scope="{ values, search, isOpen }"
        >
          <span
            v-if="values.length && !isOpen"
            class="multiselect__single"
          >
            {{ values.length === commodities.length ? 'All commodities' : (values.length === 1 ? values[0].name : `${values.length} commodities selected`) }}
          </span>
        </template>
      </multiselect>
    </div>

    <div
      v-show="includeBrand"
      class="form-group"
    >
      <multiselect
        v-model="brand.value"
        :options="brands"
        :multiple="true"
        :close-on-select="false"
        :clear-on-select="false"
        :preserve-search="true"
        open-direction="below"
        placeholder="DXC brand(s)"
        label="name"
        track-by="id"
      >
        <template
          slot="selection"
          slot-scope="{ values, search, isOpen }"
        >
          <span
            v-if="values.length && !isOpen"
            class="multiselect__single"
          >
            {{ values.length === brands.length ? 'All brands' : `${values.length} brands selected` }}
          </span>
        </template>
      </multiselect>
    </div>

    <div
      v-show="includeVendor"
      class="form-group"
    >
      <multiselect
        v-model="vendor.value"
        :options="vendors"
        :multiple="true"
        :close-on-select="false"
        :clear-on-select="false"
        :preserve-search="true"
        open-direction="below"
        placeholder="Calibrus / TPV"
        label="name"
        track-by="id"
      >
        <template
          slot="selection"
          slot-scope="{ values, search, isOpen }"
        >
          <span
            v-if="values.length && !isOpen"
            class="multiselect__single"
          >
            {{ values.length === vendors.length ? 'All collections' : `${values.length} collections selected` }}
          </span>
        </template>
      </multiselect>
    </div>

    <div
      v-show="includeSaleType"
      class="form-group"
    >
      <multiselect
        v-model="saleType.value"
        :options="saleTypes"
        :multiple="true"
        :close-on-select="false"
        :clear-on-select="false"
        :preserve-search="true"
        open-direction="below"
        placeholder="Select sale type(s)"
        label="name"
        track-by="id"
      >
        <template
          slot="selection"
          slot-scope="{ values, search, isOpen }"
        >
          <span
            v-if="values.length && !isOpen"
            class="multiselect__single"
          >
            {{ values.length === saleTypes.length ? 'All sale types' : (values.length === 1 ? values[0].name : `${values.length} sale types selected`) }}
          </span>
        </template>
      </multiselect>
    </div>

    <div
      v-show="includeState"
      class="form-group"
    >
      <multiselect
        v-model="state.value"
        :options="states"
        :multiple="true"
        :close-on-select="false"
        :clear-on-select="false"
        :preserve-search="true"
        open-direction="below"
        placeholder="Select state(s)"
        label="name"
        track-by="id"
      >
        <template
          slot="selection"
          slot-scope="{ values, search, isOpen }"
        >
          <span
            v-if="values.length && !isOpen"
            class="multiselect__single"
          >
            {{ values.length === states.length ? 'All states' : (values.length === 1 ? values[0].name : `${values.length} states selected`) }}
          </span>
        </template>
      </multiselect>
    </div>

    <slot name="extraSelect" />

    <div class="form-group">
      <select
        v-if="!hideSearchBox && searchFields.length"
        v-model="searchField.value"
        name="searchField"
        class="form-control"
        @blur="handleInputBlur($event)"
      >
        <option
          v-for="searchField in searchFields"
          :key="searchField.id"
          :value="searchField.id"
        >
          {{ searchField.name }}
        </option>
      </select>
      <input
        v-if="!hideSearchBox"
        v-model="search.value"
        name="search"
        type="text"
        :placeholder="searchLabel"
        class="form-control"
        @keyup.enter="handleSubmit"
        @blur="handleInputBlur($event)"
      >
      <button
        :disabled="invalid"
        class="btn btn-success ml-2"
        type="submit"
        @click="handleSubmit"
      >
        <i
          v-if="btnLabel === null"
          class="fa fa-search"
        />
        <i
          v-if="btnIcon"
          :class="`fa fa-${btnIcon}`"
        />
        <span
          v-if="btnLabel"
          v-html="btnLabel"
        />
      </button>
    </div>

    <slot />
  </div>
</template>

<script>
import { mapState } from 'vuex';
import Multiselect from 'vue-multiselect';
import DateRangePicker from 'vue2-daterange-picker'; // https://innologica.github.io/vue2-daterange-picker/advanced/#slots-demo
// you need to import the CSS manually
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css';

const presetRangeHelper = (start, end, label) => () => {
    const now = new Date();
    return {
        label,
        active: false,
        dateRange: {
            start: start(now),
            end: end(now),
        },
    };
};

const selectableFields = {
    channel: 'channels',
    market: 'markets',
    state: 'states',
    vendor: 'vendors',
    brand: 'brands',
    language: 'languages',
    commodity: 'commodities',
    saleType: 'saleTypes',
};

export default {
    name: 'SearchForm',
    components: {
        DateRangePicker,
        Multiselect,
    },
    filters: {
        dateCell(value) {
            const dt = new Date(value);
    
            return dt.getDate();
        },
        date(val) {

            try {
                // Specifying options for formatting
                const options = {

                    year: 'numeric', // 'numeric' or '2-digit'
                    month: '2-digit', // 'numeric', '2-digit', or 'long', 'short', or 'narrow'
                    day: '2-digit', // 'numeric' or '2-digit'

                };
                return val ? val.toLocaleString('en-US', options) : '';
            }
            catch (err) {
                console.log(err);
                return null;
            }
        },
    },
    props: {
        onSubmit: {
            type: Function,
            required: true,
        },
        initialValues: {
            type: Object,
            default: {},
        },
        includeDateRange: {
            type: Boolean,
            default: true,
        },
        includeChannel: {
            type: Boolean,
            default: false,
        },
        includeMarket: {
            type: Boolean,
            default: false,
        },
        includeSaleType: {
            type: Boolean,
            default: false,
        },
        includeBrand: {
            type: Boolean,
            default: false,
        },
        includeLanguage: {
            type: Boolean,
            default: false,
        },
        includeCommodity: {
            type: Boolean,
            default: false,
        },
        includeVendor: {
            type: Boolean,
            default: false,
        },
        includeState: {
            type: Boolean,
            default: false,
        },
        searchFields: {
            type: Array,
            default: () => [],
        },
        hideSearchBox: {
            type: Boolean,
            default: false,
        },
        position: {
            type: String,
            default: 'right',
        },
        searchLabel: {
            type: String,
            default: 'Search',
        },
        btnLabel: {
            type: String,
            default: null,
        },
        btnIcon: {
            type: String,
            default: null,
        },
    },
    data: function() {
        const data = {
            startDate: new Date(),
            endDate: new Date(),

            dateRange: {
                startDate: null, // Start date: February 1, 2024
                endDate: null, // End date: February 29, 2024
            },

            locale: {
                direction: 'ltr', // direction of text
                format: 'mm-dd-yyyy', // fomart of the dates displayed
                separator: ' - ', // separator between the two ranges
                applyLabel: 'Apply',
                cancelLabel: 'Cancel',
                weekLabel: 'W',
                customRangeLabel: 'Custom Range',
                daysOfWeek: this.$moment.weekdaysMin(), // array of days - see moment documenations for details
                monthNames: this.$moment.monthsShort(), // array of month names - see moment documenations for details
                firstDay: 1, // ISO first day of week - see moment documenations for details
            },
        };

        const mapIds = (ids, options) => ids.map((id) => options.find((item) => item.id == id));

        Object.keys(selectableFields).forEach((field) => {
            const selected = this.initialValues[field];
            const options = this.$store.state[selectableFields[field]];

            data[field] = {
                value: selected ? mapIds(selected, options) : [/* ...options*/],
                touched: false,
            };
        });

        return data;
    },
  
    computed: {
        ...mapState(Object.values(selectableFields)),
        errors() {
            const errors = {};
            // due to the date range picker component, this might be unnecessary
            if (this.startDate.value && this.endDate.value && this.startDate.value > this.endDate.value) {
                errors.dates = 'End date cannot be before start date';
            }
            return errors;
        },
        datesTouched() {
            return this.startDate.touched && this.endDate.touched;
        },
        invalid() {
            return !!Object.keys(this.errors).length;
        },
    },
    mounted() {
        this.setupDateRangePicker();
    },
    beforeDestroy() {
        document.removeEventListener('mousedown', this.handleClickOutside);
    },
    methods: {

        updateValues(newRange) {
            try {
                console.log('Update values');
                this.dateRange = newRange;
            }
            catch (err) {
                console.log(err);
            } 
         
        },
        isDateValid(dateStr) {
            try {
                return !Number.isNaN(new Date(dateStr).getTime());
            }
            catch (err) {
                console.log(err);
            }
        },
        clearDate() {

            try {
                if (this.dateRange) {
                    this.dateRange.startDate = null;
                    this.dateRange.endDate = null;
                    console.log('Success clearing date range values');
                }
            }
            catch (error) {
                console.log('Error clearing date range values');
                console.error(error);
            }

        },
        handleInputBlur({
            target,
        }) {
            this[target.name].touched = true;
        },
        handleSubmit() {
            try {
          
                const payload = {
                    startDate: this.$moment(this.dateRange.startDate).format('YYYY-MM-DD'),
                    endDate: this.$moment(this.dateRange.endDate).format('YYYY-MM-DD'),
                    searchField: null, // this.searchField.value,
                    search: null, // this.search.value,
                };

                Object.keys(selectableFields).forEach((field) => {
                    const selected = this[field].value;
                    const options = this[selectableFields[field]];
                    payload[field] = selected.length === options.length ? [] : selected.map((item) => item.id);
                });
              this.onSubmit(payload);
            }
            catch (err) { console.log(err); }

        },
        setupDateRangePicker() {
            /**
       * Initialize values
       */
            try {
                const url = new URL(window.location.href);
                const { rangedatePicker } = this.$refs;
                const { startDate, endDate } = this.initialValues;
                const parsedStart = this.$moment(startDate, 'mm-dd-yyyy', true);
                const parsedEnd = this.$moment(endDate, 'mm-dd-yyyy', true);
                
                if (url.searchParams.get('startDate') && url.searchParams.get('endDate')) {
                    this.dateRange.startDate = this.$moment(url.searchParams.get('startDate'), 'YYYY-MM-DD', true);
                    this.dateRange.endDate = this.$moment(url.searchParams.get('endDate'), 'YYYY-MM-DD', true);
                }
                else {
                    this.dateRange.startDate = null; // this.$moment(url.searchParams.get('startDate'), 'YYYY-MM-DD', true);
                    this.dateRange.endDate = null; // this.$moment(url.searchParams.get('endDate'), 'YYYY-MM-DD', true);
                }
                
            }
            catch (err) {
                console.log(err);
            }
          
            // console.log('Fecha: ', this.startDate);

        },
        handleClickOutside(event) {
            try {
                const rangedatePicker = this.$refs.rangedatePicker;
            }
            catch (err) {
                console.log(err);
            }

            /* if (rangedatePicker) {
                const calendar = rangedatePicker.$el.getElementsByClassName('calendar')[0];
                if (calendar && !calendar.contains(event.target)) {
                    rangedatePicker.toggleCalendar();
                }
            }*/
        },
    },
};

</script>

<style src="vue-multiselect/dist/vue-multiselect.min.css"></style>
<style>
.search-form input{
  margin-right: 0;
}
.search-form label{
  margin-right: 10px;
}
.search-form .input-date{
  background-color: white;
}

.multiselect__option--selected {
  background: white;
}

.multiselect__option--selected:after {
  color: #35495e;
}

.multiselect__option--highlight,
.multiselect__option--highlight:after,
.multiselect__option--selected.multiselect__option--highlight,
.multiselect__option--selected.multiselect__option--highlight:after,
.multiselect__option--group-selected.multiselect__option--highlight,
.multiselect__option--group-selected.multiselect__option--highlight:after {
  background: #f3f3f3;
  color: #35495e;
}

.multiselect__option--highlight:after {
  content: '';
}

.multiselect__option:after,
.multiselect__option.multiselect__option--highlight:after {
  content: 'Off';
}

.multiselect__option--selected:after,
.multiselect__option--selected.multiselect__option--highlight:after,
.multiselect__option--group-selected.multiselect__option--highlight:after {
  content: 'On';
}

.calendar-btn-apply {
  display: none;
}

.multiselect__option span{
  display: inline-block;
  width: 170px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.multiselect--active {
  min-width: 220px;
}

</style>
