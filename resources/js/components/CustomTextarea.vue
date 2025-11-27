<template>
    <div class='form-group'>
        <label v-if="label" :for="`input-${name}`" class="mr-2">
            {{ label }}
        </label>
        <textarea :name="name" v-model="textareaValue" :placeholder="placeholder" class="form-control">{{ value }}</textarea>
    </div>
</template>

<script>
    export default {
        name: 'custom-input',
        props: {
            value: {
                type: String|Number,
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
        },
        computed: {
            textareaValue: {
                get() {
                    return this.value;
                },
                set(val) {
                    this.$emit('onChange', val);
                },
            },
        },
    }
</script>