<template>
  <nav aria-label="page navigation" v-if="navLinks.next !== null || navLinks.last !== null">
    <ul class="pagination justify-content-center">
      <li class="page-item" :class="{'disabled': navLinks.last === null}">
        <a
          class="page-link"
          :href="navLinks.last ?  navLinks.last + filterParams + sortParams : ''"
        >Previous</a>
      </li>
      <li class="page-item" :class="{'disabled': navLinks.next === null}">
        <a
          class="page-link"
          :href="navLinks.next ? navLinks.next + filterParams + sortParams : ''"
        >Next</a>
      </li>
    </ul>
  </nav>
</template>
<script>
export default {
  name: 'SimplePagination',
  props: {
    filterParams: {
      type: String,
      default: '',
    },
    sortParams: {
      type: String,
      default: '',
    },
    pageNav: {
      type: Object,
      default: function() {
        return { next: null, last: null };
      },
    },
  },
  computed:{
    navLinks(){
      return {next: this.returnNavLinks(this.pageNav.next), last: this.returnNavLinks(this.pageNav.last)};
    }
  },
  methods:{
    returnNavLinks(link){
      if(link && window.location.protocol === 'https:'){
        if(link.includes('http://')){
          return (link).replace('http://', 'https://');
        }
      }
      return link;
    }
  }
};
</script>