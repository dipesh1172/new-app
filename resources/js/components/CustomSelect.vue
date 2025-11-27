<template>
  <div class="form-group">
    <label 
      v-if="label" 
      :for="`input-${name}`" 
      class="mr-2">
      {{ label }}
    </label>
    <select
      :name="name"
      :id="`input-${name}`"
      :class="[
        'form-control',
        extraClass,
        { 'form-control-lg': classStyle === 'large' },
        { 'form-control-sm': classStyle === 'small' },
      ]"
      v-model="valueInput"
    >
      <option 
        v-for="item in items" 
        :value="item.value" 
        :key="item.value">{{ item.label }}</option>
    </select>
  </div>
</template>

<script>
export default {
    name: 'CustomSelect',
    props: {
        value: {
            type: String | Number,
            required: true,
        },
        items: {
            type: Array,
            required: true,
        },
        label: {
            type: String,
            default: null,
        },
        name: {
            type: String,
            required: true,
        },
        classStyle: {
            type: String,
            default: '',
        },
        extraClass: {
            type: String,
            default: '',
        },
        type: {
            type: String,
            default: 'text',
        },
    },
    computed: {
        valueInput: {
            get() {
                return this.value;
            },
            set(newValue) {
                this.$emit('onChange', newValue);
                this.$emit('input', newValue);
            },
        },
    },
};
</script>
