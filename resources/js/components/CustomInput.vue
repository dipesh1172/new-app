<template>
  <div
    :class="[
      'form-group',
      { 'd-none': type === 'hidden' },
    ]"
  >
    <label 
      v-if="label" 
      :for="`input-${name}`" 
      class="mr-2"
    >
      {{ label }}
    </label>
    <input
      :id="`input-${name}`"
      v-model="valueInput"
      :type="type"
      :class="[
        'form-control',
        extraClass,
        { 'form-control-lg': classStyle === 'large' },
        { 'form-control-sm': classStyle === 'small' },
      ]"
      :placeholder="placeholder"
      :name="name"
      :min="min"
      :step="step"
      @keyup.enter="$emit('onKeyUpEnter')"
    >
  </div>
</template>

<script>
export default {
    name: 'CustomInput',
    props: {
        value: {
            type: String | Number,
            required: true,
        },
        label: {
            type: String,
            default: null,
        },
        placeholder: {
            type: String,
            default: '',
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
        min: {
            type: String,
            required: false,
        },
        step: {
            type: String | Number,
            required: false,
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
