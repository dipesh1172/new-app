<template>
  <div>
    <select
      ref="mystate"
      :value="value"
      class="form-control"
      @change="sendChange"
      @input="sendInput"
    >
      <option>Choose...</option>
      <option
        v-for="state in states"
        :key="state.id"
        :value="state.abbreviation"
      >
        {{ state.name }}
      </option>
    </select>
  </div>
</template>

<script>
export default {
    name: 'StateSelect',
    props: {
        value: {
            type: String,
            default: '',
        },
    },
    computed: {
        states() {
            return this.$store.state.states;
        },
    },
    methods: {
        sendChange(v) {
            this.$emit('change', this.$refs.mystate.value);
        },
        sendInput(v) {
            this.$emit('input', this.$refs.mystate.value);
        },
    },
};
</script>
