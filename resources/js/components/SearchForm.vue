<template>
  <div :class="`search-form form-inline pull-${position}`">
    <div 
      v-show="includeDateRange" 
      class="form-group ml-2"
    >
      <label>Date Range</label>
      <vue-rangedate-picker
        ref="rangedatePicker"
        :preset-ranges="presetRanges"
        i18n="EN"
      />
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
        placeholder="Select language(s)"
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
            {{ values.length === languages.length ? 'All languages' : (values.length === 1 ? values[0].name : `${values.length} languages selected`) }} 
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
        placeholder="Select brand(s)"
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
        placeholder="Select vendor(s)"
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
            {{ values.length === vendors.length ? 'All vendors' : `${values.length} vendors selected` }} 
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
        class="btn btn-success ml-2 mt-1"
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
import VueRangedatePicker from 'vue-rangedate-picker';

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
        VueRangedatePicker,
        Multiselect,
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
    data() {
        const data = {
            startDate: {
                value: '',
                touched: false,
            },
            endDate: {
                value: '',
                touched: false,
            },
            searchField: {
                value: this.initialValues.searchField || (this.searchFields.length ? this.searchFields[0].id : ''),
                touched: false,
            },
            search: {
                value: this.initialValues.search || '',
                touched: false,
            },
            presetRanges: {
                today: presetRangeHelper(
                    (n) => new Date(n.getFullYear(), n.getMonth(), n.getDate() + 1, 0, 0),
                    (n) => new Date(n.getFullYear(), n.getMonth(), n.getDate() + 1, 23, 59),
                    'Today',
                ),
                yesterday: presetRangeHelper(
                    (n) => new Date(n.getFullYear(), n.getMonth(), n.getDate(), 0, 0),
                    (n) => new Date(n.getFullYear(), n.getMonth(), n.getDate(), 23, 59),
                    'Yesterday',
                ),
                thisWeek: presetRangeHelper(
                    (n) => new Date(n.getFullYear(), n.getMonth(), n.getDate() - n.getDay() + 1),
                    (n) => new Date(n.getFullYear(), n.getMonth(), n.getDate() + 1),
                    'This Week',
                ),
                lastWeek: presetRangeHelper(
                    (n) => new Date(n.getFullYear(), n.getMonth(), n.getDate() - n.getDay() - 6),
                    (n) => new Date(n.getFullYear(), n.getMonth(), n.getDate() - n.getDay()),
                    'Last Week',
                ),
                thisMonth: presetRangeHelper(
                    (n) => new Date(n.getFullYear(), n.getMonth(), 2),
                    (n) => new Date(n.getFullYear(), n.getMonth() + 1, 1),
                    'This Month',
                ),
                lastMonth: presetRangeHelper(
                    (n) => new Date(n.getFullYear(), n.getMonth() - 1, 2),
                    (n) => new Date(n.getFullYear(), n.getMonth(), 1),
                    'Last Month',
                ),      
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
        handleInputBlur({
            target,
        }) {
            this[target.name].touched = true;
        },
        handleSubmit() {
            const payload = {
                startDate: this.startDate.value,
                endDate: this.endDate.value,
                searchField: this.searchField.value,
                search: this.search.value,
            };

            Object.keys(selectableFields).forEach((field) => {
                const selected = this[field].value;
                const options = this[selectableFields[field]];
                payload[field] = selected.length === options.length ? [] : selected.map((item) => item.id);
            });

            this.onSubmit(payload);
        },
        setupDateRangePicker() {
            /**
             * Initialize values
             */
            const { rangedatePicker } = this.$refs;
            const { startDate, endDate } = this.initialValues;
            const parsedStart = this.$moment(startDate, 'YYYY-MM-DD', true);
            const parsedEnd = this.$moment(endDate, 'YYYY-MM-DD', true);

            // For some reason, VueRangedatePicker uses date + 1
            if (parsedStart.isValid()) {
                rangedatePicker.dateRange.start = parsedStart.add(1, 'd').toDate();
                this.startDate.value = startDate;
                this.startDate.touched = true;
            }

            if (parsedEnd.isValid()) {
                rangedatePicker.dateRange.end = parsedEnd.add(1, 'd').toDate();
                this.endDate.value = endDate;
                this.endDate.touched = true;
            }

            // trick to rerender rangedatePicker and populate its input
            rangedatePicker.toggleCalendar();
            rangedatePicker.toggleCalendar();

            /**
             * Add click outside event listener
             */
            document.addEventListener('mousedown', this.handleClickOutside);

            /**
             * Set up watchers for ref's inner data
             */
            this.$watch(
                function() {
                    return this.$refs.rangedatePicker.dateRange.start;
                },
                function(newStart) {
                    const parsedStart = this.$moment(newStart);
                    this.startDate.value = parsedStart.isValid() ? parsedStart.subtract(1, 'd').format('YYYY-MM-DD') : '';
                },
            );

            this.$watch(
                function() {
                    return this.$refs.rangedatePicker.dateRange.end;
                },
                function(newEnd) {
                    const parsedEnd = this.$moment(newEnd);
                    this.endDate.value = parsedEnd.isValid() ? parsedEnd.subtract(1, 'd').format('YYYY-MM-DD') : '';
                },
            );
        },
        handleClickOutside(event) {
            const rangedatePicker = this.$refs.rangedatePicker;
            if (rangedatePicker) {
                const calendar = rangedatePicker.$el.getElementsByClassName('calendar')[0];
                if (calendar && !calendar.contains(event.target)) {
                    rangedatePicker.toggleCalendar();
                }
            }
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
