<template>
  <div>
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="/">Home</a>
      </li>
      <li class="breadcrumb-item active">Issues</li>
    </ol>
    <div class="container-fluid">
      <div class="row"></div>
      <div class="row">
        <div class="card col-12">
          <div class="card-body">
            <div v-if="hasFlashMessage" class="alert alert-success">
              <span class="fa fa-check-circle" />
              <em>{{ flashMessage }}</em>
            </div>
            <h2 class="pull-left">Reported Issues</h2>
            <a class="btn btn-primary btn-lg mb-2 pull-right" href="/issue/new">Report New Issue</a>
            <div class="table-responsive">
              <custom-table
                :headers="headers"
                :data-grid="issues"
                :data-is-loaded="dataIsLoaded"
                :total-records="totalRecords"
                empty-table-message="No issues were found."
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import CustomTable from 'components/CustomTable';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
  name: 'IssuesList',
  components: {
    CustomTable,
  },
  props: {
    hasFlashMessage: {
      type: Boolean,
      default: false,
    },
    flashMessage: {
      type: String,
      default: null,
    },
  },
  data() {
    return {
      issues: [],
      dataIsLoaded: false,
      totalRecords: 0,
      headers: [
        {
          align: 'left',
          label: 'Issue Name',
          key: 'name',
          serviceKey: 'name',
          width: '11%',
        },
        {
          align: 'left',
          label: 'Details',
          key: 'desc',
          serviceKey: 'desc',
          width: '60%',
        },
        {
          align: 'left',
          label: 'Status',
          key: 'status',
          serviceKey: 'status',
          width: '12%',
        },
        {
          align: 'left',
          label: 'Actions',
          key: 'actions',
          serviceKey: 'actions',
          width: '12%',
        },
      ],
    };
  },
  mounted() {
    document.title += ' Reported Issues';
    axios
      .get(`/issues`)
      .then(response => {
        console.log(response);

        for (const element in response.data) {
          this.issues.push(this.getObject(response.data[element]));
        }

        this.dataIsLoaded = true;
        this.totalRecords = this.issues.length;
      })
      .catch();
  },
  methods: {
    getObject(elem) {
      return {
        name: elem.name,
        desc: this.nl2br(this.htmlEntities(elem.desc), false),
        status: elem.list.name,
        actions: `<a class="btn btn-secondary" href="/issue/new?add=${elem.id}">Add Instance</a>`,
      };
    },
    nl2br(str, isXhtml) {
      // Some latest browsers when str is null return and unexpected null value
      if (typeof str === 'undefined' || str === null) {
        return '';
      }

      // Adjust comment to avoid issue on locutus.io display
      var breakTag =
        isXhtml || typeof isXhtml === 'undefined' ? '<br ' + '/>' : '<br>';

      return (str + '').replace(/(\r\n|\n\r|\r|\n)/g, breakTag + '$1');
    },
    htmlEntities(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    },
  },
};
</script>