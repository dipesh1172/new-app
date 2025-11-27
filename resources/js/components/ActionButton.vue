<template>
  <span>
    <span v-if="!isLoading">
      <a
        v-if="!disabled"
        :href="url"
        :class="[
          'btn ml-1',
          { 'btn-sm': buttonSize === 'small' },
          { 'btn-info': type === 'update' },
          { 'btn-primary': type === 'edit' },
          { 'btn-success': type === 'monitor' },
          { 'btn-primary': type === 'counter' && extraValue },
          { 'btn-warning': type === 'counter' && !extraValue },
          { 'btn-danger': type === 'status' && extraValue === status.ACTIVE },
          { 'btn-success': type === 'status' && extraValue === status.INACTIVE },
          { 'btn-danger': type === 'delete' },
          { 'btn-success': type === 'active' },
          { 'btn-success': type === 'login' },
          { 'btn-warning': type === 'view' },
          { 'btn-warning': type === 'disable' },
          { 'btn-success': type === 'enable' },
          { 'fa fa-star favorite': type === actions.FAVORITE_ON },
          { 'fa fa-star-o favorite': type === actions.FAVORITE_OFF },
          { [classNames]: type === 'custom' },
        ]"
        :target="target || newTarget"
        @click="onClickButton"
      >
        <span
          v-if="extraValue && type === 'counter'"
          class="badge badge-light mr-2"
        >
          {{ extraValue }}
        </span>
        <i
          v-if="icon"
          :class="`fa fa-${icon}`"
        />
        {{ labelAction }}
      </a>
    </span>
    <span
      v-else
      class="text-center mr-4 ml-4"
    >
      <span class="fa fa-spinner fa-spin" />
    </span>
  </span>
</template>

<script>
import { status, statusLabel, actions } from 'utils/constants';

export default {
    name: 'ActionButton',
    props: {
        type: {
            type: String,
            required: true,
        },
        label: {
            type: String,
            required: false,
        },
        target: {
            type: String,
            required: false,
        },
        classNames: {
            type: String,
            required: false,
        },
        onClick: {
            type: Function,
            required: false,
        },
        url: {
            type: String,
            required: true,
        },
        extraValue: {
            type: String | Number,
            default: null,
        },
        buttonSize: {
            type: String,
            default: 'small',
        },
        counterName: {
            type: String,
            default: 'View',
        },
        messageAlert: {
            type: String,
            default: null,
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        icon: {
            type: String,
            default() {
                let icon;
                switch (this.type) {
                    case 'edit':
                        icon = 'pencil';
                        break;
                    case 'view':
                        icon = 'eye';
                        break;
                    case 'delete':
                        icon = 'trash';
                        break;
                    case 'disable':
                        icon = 'ban';
                        break;
                    case 'status':                        
                        icon = this.extraValue === status.ACTIVE ? 'ban' : 'thumbs-up';
                        break;
                    default:
                        icon = '';
                        break;
                }
                return icon;
            },
        },
    },
    data() {
        return {
            status: status,
            statusLabel: statusLabel,
            isLoading: false,
            actions,
        };
    },
    computed: {
        newTarget() {
            let target = '';

            switch (this.type) {
                case 'login':
                    target = '_blank';
                    break;
            }

            return target;
        },
        labelAction() {
            let label = null;

            if (this.label !== null && this.label !== undefined && this.label !== '') {
                return this.label;
            }

            switch (this.type) {
                case 'edit':
                    label = 'Edit';
                    break;
                case 'status':
                    label = this.extraValue === status.ACTIVE
                        ? 'Disable'
                        : 'Enable';
                    break;
                case 'counter':
                    label = this.counterName;
                    break;
                case 'delete':
                    label = 'Delete';
                    break;
                case 'active':
                    label = 'Enable';
                    break;
                case 'login':
                    label = 'Login';
                    break;
                case 'update':
                    label = 'Update';
                    break;
                case 'monitor':
                    label = 'Monitor';
                    break;
                case 'custom':
                    label = this.label;
                    break;
                case 'disable':
                    label = 'Disable';
                    break;
                default:
                    label = '';
                    break;
            }

            return label;
        },
    },
    methods: {
        onClickButton(e) {
            console.log(this.type);
            switch (this.type) {
                case 'status':
                    const c = confirm(this.messageAlert);
                    if (!c) { e.preventDefault(); }
                    break;
                case 'delete':
                    return confirm(this.messageAlert);
                    break;
                case 'active':
                    return confirm(this.messageAlert);
                    break;
                case 'monitor':
                    return confirm(this.messageAlert);
                    break;
                case 'favorite_on':
                case 'favorite_off':
                    e.preventDefault();
                    const id = e.target.href.split('=').pop();
                    this.updateBrandStatus(id);
                    break;
                case 'update':
                    e.preventDefault();
                    this.isLoading = true;
                    const select = document.getElementById(`select-${this.extraValue}`);
                    const value = select.options[select.selectedIndex].value;

                    axios.get(`${this.url}${value}`)
                        .then((response) => {
                            this.$emit('onUpdate', value);
                            this.isLoading = false;
                        })
                        .catch((error) => {
                            console.log(error);
                        });
                    break;
                case 'custom':
                    if (this.onClick) {
                        e.preventDefault();
                        this.onClick(e);
                    }
                    break;
            }
        },

        updateBrandStatus(id) {
            //const el = this.$el.querySelector('a');
            axios.get(`/list/update_favorites/${id}`)
                .then((response) => {
                    this.$emit('updatedBrandFavorites', response);
                })
                .catch((error) => error);
        },
    },
};
</script>

<style lang="scss" scoped>
   .btn:focus, .btn.focus {
    
    box-shadow: none;

   }
</style>