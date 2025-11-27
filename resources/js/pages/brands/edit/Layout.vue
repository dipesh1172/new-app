<template>
  <div>
    <breadcrumb :items="breadcrumb" />

    <slot name="filter-bar" />

    <div class="container-fluid mt-4 mb-4">
      <div class="row">
        <div class="col-md-11">
          <h2>{{ brand.name }}</h2>
        </div>
        <div class="col-md-1 pull-right">
          <a
            :href="'/brands/login?brand=' + brand.id"
            class="btn btn-success"
            target="_blank"
          ><i class="fa fa-sign-in" /> Login</a>
        </div>
      </div>
      <br>
      <ul class="nav nav-tabs">
        <li
          v-for="(p, index) in parents"
          :key="index"
          class="nav-item"
        >
          <a
            href="#"
            class="nav-link"
            :class="{active: p.isActive}"
            @click="updateParents(p)"
          >
            {{ p.label }}
          </a>
        </li>
      </ul>

      <brand-nav :items="navItems.filter(item => item.parent === activeParent.id)" />

      <div class="tab-content p-0">
        <slot />
      </div>
    </div>
  </div>
</template>

<script>
import Breadcrumb from 'components/Breadcrumb';
import { requestIs } from 'utils/urlHelpers';
import Nav from './Nav';

export default {
    name: 'BrandLayout',
    components: {
        Breadcrumb,
        'brand-nav': Nav,
    },
    props: {
        brand: {
            type: Object,
            default: () => ({}),
        },
        breadcrumb: {
            type: Array,
            default: () => [],
        },
        forceSelection: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            parents: [
                {id: 1, isActive: false, label: 'Brand'},
                {id: 2, isActive: false, label: 'Services'},
            ],
            activeParent: null,
        };
    },
    computed: {
        navItems() {
            return [
                {
                    isActive: requestIs('/brands/*/edit'),
                    href: `/brands/${this.brand.id}/edit`,
                    label: 'Configuration',
                    parent: 1,
                },
                {
                    isActive: requestIs('/brands/*/services'),
                    href: `/brands/${this.brand.id}/services`,
                    label: 'Services Configuration',
                    parent: 2,
                },
                {
                    isActive: requestIs('/brands/*/contacts'),
                    href: `/brands/${this.brand.id}/contacts`,
                    label: 'Contacts',
                    parent: 1,
                },
                {
                    isActive: requestIs('/brands/*/feeschedule'),
                    href: `/brands/${this.brand.id}/feeschedule`,
                    label: 'Fee Schedule',
                    parent: 1,
                },
                {
                    isActive: requestIs('/brands/*/taskqueues'),
                    href: `/brands/${this.brand.id}/taskqueues`,
                    label: 'Task Queues',
                    parent: 1,
                },
                {
                    isActive: requestIs('/brands/*/bgchk'),
                    href: `/brands/${this.brand.id}/bgchk`,
                    label: 'Background Checks',
                    parent: 2,
                },
                {
                    isActive: requestIs('/brands/*/get_contracts'),
                    href: `/brands/${this.brand.id}/get_contracts`,
                    label: 'Contracts',
                    parent: 2,
                },
                {
                    isActive: requestIs('/brands/*/vendors'),
                    href: `/brands/${this.brand.id}/vendors`,
                    label: 'Vendors',
                    parent: 1,
                },
                {
                    isActive: requestIs('/brands/*/utilities'),
                    href: `/brands/${this.brand.id}/utilities`,
                    label: 'Utilities',
                    parent: 1,
                },
                {
                    isActive: requestIs('/brands/*/enrollments'),
                    href: `/brands/${this.brand.id}/enrollments`,
                    label: 'Enrollment Files',
                    parent: 2,
                },
                {
                    isActive: requestIs('/brands/*/recordings'),
                    href: `/brands/${this.brand.id}/recordings`,
                    label: 'File Transfer',
                    parent: 2,
                },
                {
                    isActive: requestIs('/brands/*/disposition-shortcuts'),
                    href: `/brands/${this.brand.id}/disposition-shortcuts`,
                    label: 'Disposition Shortcuts',
                    parent: 1,
                },
                {
                    isActive: requestIs('/brands/*/api_tokens'),
                    href: `/brands/${this.brand.id}/api_tokens`,
                    label: 'API Access',
                    parent: 2,
                },
                {
                    isActive: requestIs('/brands/*/pay'),
                    href: `/brands/${this.brand.id}/pay`,
                    label: 'Pay Link',
                    parent: 2,
                },
                {
                    isActive: requestIs('/brands/*/user_profile_fields'),
                    href: `/brands/${this.brand.id}/user_profile_fields`,
                    label: 'User Profile Fields',
                    parent: 1,
                },
                {
                    isActive: requestIs('/brands/*/rate_level_brands'),
                    href: `/brands/${this.brand.id}/rate_level_brands`,
                    label: 'Rate-Level Brands',
                    parent: 1,
                },
                // {
                //     pattern: /^\/brands\/.+\/contract$/g,
                //     href: `/brands/${this.brand.id}/contract`,
                //     label: 'Contracts',
                // },
            ];
        },
    },
    created() {
        this.forceSelectionM();
        for (let i = 0; i < this.navItems.length; i++) {
            if (this.navItems[i].isActive) {
                const activeParent = this.parents.find((p) => this.navItems[i].parent === p.id);
                activeParent.isActive = true;
                this.activeParent = activeParent;
                break;
            }
        }
    },
    methods: {
        updateParents(p) {
            p.isActive = true;
            this.activeParent = p;
            this.parents.forEach((parent) => {
                if (p.id !== parent.id) {
                    parent.isActive = false;
                }
            });
        },
        forceSelectionM() {
            if (this.forceSelection) {
                const [parentId, navILabel] = this.forceSelection.split('.');
                this.activeParent = this.parents.find((p) => parentId === p.id);
                this.navItems.forEach((nav) => {
                    if (nav.label === navILabel) {
                        nav.isActive = true;
                    }
                });
            }
        },
    },
};
</script>
<style scoped>
.nav-tabs{
  margin-bottom: 0px !important;
  border-bottom: 2px solid #ddd;
}
</style>
