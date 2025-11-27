<template>
  <layout
    :brand="brand"
    :breadcrumb="[
      {name: 'Home', url: '/'},
      {name: 'Brands', url: '/brands'},
      {name: brand.name, url: `/brands/${brand.id}/edit`},
      {name: `Task Queues for ${brand.name}`, active: true}
    ]"
  >
    <div
      role="tabpanel"
      class="tab-pane active"
    >
      <div
        v-if="flashMessage"
        class="alert alert-success"
      >
        <span class="fa fa-check-circle" />
        <em> {{ flashMessage }}</em>
      </div>

      <table class="table table-striped">
        <!-- <thead>
          <tr>
            <th>Client Name</th>
          </tr>
        </thead>
        -->
        <tbody>
          <tr v-if="!taskqueues.length">
            <td
              colspan="1"
              class="text-center"
            >
              No task queues were found.
            </td>
          </tr>
          <tr
            v-for="(taskqueue, index) in taskqueues"
            v-else
            :key="index"
          >
            <td>{{ taskqueue.task_queue }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </layout>
</template>
<script>
import Layout from './Layout';

export default {
    name: 'Taskqueues',
    components: {
        Layout,
    },
    props: {
        brand: {
            type: Object,
            default: () => ({}),
        },
        flashMessage: {
            type: String,
            default: '',
        },
        taskqueues: {
            type: Array,
            default: () => [],
        },
    },
};
</script>
