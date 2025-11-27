<template>
  <ul
    v-if="numberPages > 1"
    class="pagination justify-content-center pb-1"
  >
    <li
      :class="[
        'active page',
        { 'is-disabled': activePage === 1 },
      ]"
      @click="onClickPrev"
    >
      <span>&lsaquo;&lsaquo;</span>
    </li>
    <li
      v-for="(page, index) in pagesList"
      :key="index"
      :class="[
        'active page',
        { 'is-disabled': activePage === page },
      ]"
      @click="onClickPage(page, index)"
    >
      <span>{{ page }}</span>
    </li>
    <li
      :class="[
        'active page',
        { 'is-disabled': activePage === numberPages },
      ]"
      @click="onClickNext"
    >
      <span>&rsaquo;&rsaquo;</span>
    </li>
  </ul>
</template>

<script>
const PAGINATION_LENGTH = 10;
const NUM_ITEMS_VARIABLES = PAGINATION_LENGTH - 2;

export default {
    name: 'Pagination',
    props: {
        numberPages: {
            type: Number,
            required: true,
        },
        activePage: {
            type: Number,
            required: true,
        },

        displayedPages: {
            type: Number,
        },
    },
    computed: {
        pagesList() {
            const pages = Array.from(new Array(this.numberPages), (value, index) => (index + 1));
            const activePage = this.activePage;
            const numberPages = this.numberPages;
            let paginationItems = [];

            const paginationLength = this.displayedPages ? this.displayedPages : PAGINATION_LENGTH;
            const itemVariables = paginationLength - 2;

            if (numberPages <= paginationLength) {
                paginationItems = pages;
            }
            else if (activePage < 1 + itemVariables) {
                paginationItems.push(
                    ...pages.slice(0, itemVariables),
                    '...',
                    pages[pages.length - 1],
                );
            }
            else if (1 + itemVariables <= activePage && activePage <= numberPages - itemVariables) {
                paginationItems.push(
                    pages[0],
                    '...',
                    ...pages.slice(activePage - 1, activePage + itemVariables - 3),
                    '....',
                    pages[pages.length - 1],
                );
            }
            else if (numberPages - itemVariables < activePage) {
                paginationItems.push(
                    pages[0],
                    '...',
                    ...pages.slice(numberPages - itemVariables),
                );
            }

            return paginationItems;
        },
    },
    methods: {
        onClickPrev() {
            if (this.activePage !== 1) {
                this.$emit('onSelectPage', this.activePage - 1);
            }
        },
        onClickPage(page, index) {
            let selectedPage = page;

            if (page === '...' || page === '....') {
                const prev = this.pagesList[index - 1];
                const next = this.pagesList[index + 1];
                selectedPage = this.activePage <= prev ? prev + 1 : selectedPage;
                selectedPage = next <= this.activePage ? next - 1 : selectedPage;
            }

            this.$emit('onSelectPage', selectedPage);
        },
        onClickNext() {
            if (this.activePage !== this.numberPages) {
                this.$emit('onSelectPage', this.activePage + 1);
            }
        },
    },
};
</script>

<style lang="scss" scoped>
    .page {
        cursor: pointer;

        &.is-disabled {
            cursor: default;

            span {
                color: #000000 !important;
            }
        }

        span {
            color: #20a8d8 !important;
        }
    }
</style>
