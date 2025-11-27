<template>
  <div>
    <breadcrumb :items="breadcrumb" />

    <div class="container-fluid mt-4 mb-4">
      <h2>{{ brand.name }}</h2>
      <br>
      <ul class="nav nav-tabs">
        <li
          v-for="(item, i) in items"
          :key="i"
          class="nav-item"
        >
          <a
            :href="item.href"
            :class="['nav-link', { active: item.active }]"
          >
            {{ item.label }}
          </a>
        </li>
      </ul>
      <div class="tab-content">
        <slot />
      </div>
    </div>
  </div>
</template>

<script>
import Breadcrumb from 'components/Breadcrumb';
import { requestIs } from 'utils/urlHelpers';

export default {
    name: 'BrandVendorNav',
    components: {
        Breadcrumb,
    },
    props: {
        brand: {
            type: Object,
            default: () => ({}),
        },
        vendor: {
            type: Object,
            default: () => ({}),
        },
        breadcrumb: {
            type: Array,
            default: () => [],
        },
    },
    computed: {
        items() {
            return [
                {
                    active: requestIs('/brands/*/vendor/*/editVendor'),
                    href: `/brands/${this.brand.id}/vendor/${this.vendor.id}/editVendor`,
                    label: 'Vendors',
                },
                {
                    active: requestIs('/brands/*/vendor/*/loginLanding'),
                    href: `/brands/${this.brand.id}/vendor/${this.vendor.id}/loginLanding`,
                    label: 'Login Landing (TM Only)',
                },
            ];
        },
    },
};
</script>
