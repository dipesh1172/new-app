<template>
  <div
    v-if="fields.length > 0"
    class="card mb-0"
  >
    <div class="card-header bg-dark text-white">
      Custom Fields
    </div>
    <div class="card-body p-0">
      <table class="table table-striped mb-0 table-bordered">
        <thead>
          <tr class="table-active">
            <th>Field Name</th>
            <th>Value</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(field, i) in fields"
            :key="`field-${i}`"
          >
            <td>{{ field.custom_field.name }} ({{ field.custom_field.output_name }})</td>
            <td>{{ field.value }}</td>
            <td>{{ field.custom_field.description }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
export default {
    name: 'CustomFields',
    props: {
        fields: {
            type: Array,
            required: true,
        },
    },
};
</script>
