<template>
  <div class="table-responsive">
    <p
      v-if="totalRecords"
      align="right"
    >
      Total Records: {{ totalRecords }}
    </p>
    <table :class="{'table table-striped':true, 'mb-0': noBottomPadding}">
      <thead>
        <tr>
          <th
            v-for="(column, index) in headers"
            :key="`headers-${index}`"
            :style="{ minWidth: column.width }"
            :class="`text-${column.align || 'left'}`"
          >
            <i
              v-if="column.icon !== undefined"
              :class="icon(column.icon)"
              :title="column.label"
            />
            <span v-else>{{ column.label }}</span>
            <span
              v-if="column.canSort"
              class="sort-button"
              @click="$emit('sortedByColumn', column.serviceKey, index)"
            >
              <i class="fa fa-sort" />
            </span>
          </th>
          <th v-if="hasActionButtons && showActionButtons" />
        </tr>
      </thead>
      <tbody>
        <tr v-if="!dataIsLoaded">
          <td
            :colspan="headers.length + 1"
            class="text-center"
          >
            <span class="fa fa-spinner fa-spin fa-2x" />
          </td>
        </tr>
        <tr v-if="!dataGrid.length && dataIsLoaded">
          <td
            :colspan="headers.length + 1"
            class="text-center"
          >
            {{ emptyTableMessage }}
          </td>
        </tr>
        <tr
          v-for="(row, rown) in dataGrid"
          :key="`data-${'id' in row ? row.id : 'row'}-${rown}`"
          :style="{backgroundColor: ($parent !== null && $parent.$options.name == 'InboundCallVolume' && row.time_slice == $parent.current_time)? '#FFF3D0' : 'none'}"
        >
          <td
            v-for="column in headers"
            :key="column.key"
            :class="`cell text-${column.align || 'left'}`"
          >
            <slot
              v-if="column.slot"
              :name="column.slot"
              :row="row"
            >
              Default
            </slot>
            <span
              v-else
              :class="[
                { 'badge': column.key === 'statusLabel' },
                { 'badge-danger': column.key === 'statusLabel' && row.status === status.INACTIVE },
                { 'badge-success': column.key === 'statusLabel' && row.status === status.ACTIVE },
                { 'badge': column.key === 'lastResult' },
                { 'badge-danger': column.key === 'lastResult' && row.lastResult === result.CLOSED },
                { 'badge-success': column.key === 'lastResult' && row.lastResult === result.SALE },
                { 'badge-warning': column.key === 'lastResult' && row.lastResult === result.NO_SALE },
              ]"
              v-html="row[column.key]"
            />
          </td>
          <td
            v-if="hasActionButtons && showActionButtons"
            class="text-right"
            nowrap="nowrap"
          >
            <action-button
              v-for="(button, index) in row.buttons"
              :key="index"
              :type="button.type"
              :label="button.label"
              :on-click="button.onClick"
              :target="button.target"
              :class-names="button.classNames"
              :url="button.url"
              :button-size="button.buttonSize"
              :extra-value="row[button.type]"
              :counter-name="button.counterName"
              :message-alert="button.messageAlert"
              :show-button="button.showButton"
              :disabled="button.disabled"
              :icon="button.icon"
              @updatedBrandFavorites="updateBrandFavorite($event)"
            />
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import { status, result } from 'utils/constants';
import ActionButton from 'components/ActionButton';

export default {
    name: 'CustomTable',
    components: {
        ActionButton,
    },
    props: {
        tableName: {
            type: String,
            default: 'table',
        },
        headers: {
            type: Array,
            default: [],
        },
        totalRecords: {
            type: Number,
            default: 0,
        },
        dataGrid: {
            type: Array,
            default: [],
        },
        dataIsLoaded: {
            type: Boolean,
            default: false,
        },
        hasActionButtons: {
            type: Boolean,
            default: false,
        },
        showActionButtons: {
            type: Boolean,
            default: false,
        },
        emptyTableMessage: {
            type: String,
            default: 'No data were found.',
        },
        noBottomPadding: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            status: status,
            result: result,
        };
    },
    methods: {
        icon(ico) {
            const x = {
                fa: true,
            };
            x[`fa-${ico}`] = true;
            return x;
        },

        updateBrandFavorite(response) {
            // emit to Brands from ActionButton
            this.$emit('updatedBrandFavorites', response);
        },
    },
};
</script>

<style lang="scss" scoped>
.sort-button {
  cursor: pointer;
  margin-left: 5px;
}
</style>
