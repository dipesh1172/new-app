<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Company Configuration', url: '/config'},
        {name: 'Departments', active: true},
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="row">
        <div class="col-md-12">
          <div class="card">
            <div class="card-header">
              <i class="fa fa-th-large" /> Departments
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <custom-table
                  :headers="headers"
                  :data-grid="depts"
                  :data-is-loaded="dataIsLoaded"
                  :total-records="totalRecords"
                  empty-table-message="No depts were found."
                  :has-action-buttons="true"
                  :show-action-buttons="true" 
                  @sortedByColumn="sortData"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import CustomTable from 'components/CustomTable';
import Breadcrumb from 'components/Breadcrumb';
import { arraySort } from 'utils/arrayManipulation';

const NO_SORTED = '';
const ASC_SORTED = 'asc';
const DESC_SORTED = 'desc';

export default {
    name: 'ListDeparments',
    components: {
        CustomTable,
        Breadcrumb,
    },
    data() {
        return {
            depts: [],
            dataIsLoaded: false,
            totalRecords: 0,
            headers: [
                {
                    align: 'left',
                    label: 'Department Name',
                    key: 'name',
                    serviceKey: 'name',
                    width: '20%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'string',
                },
                {
                    align: 'center',
                    label: 'Roles',
                    key: 'roles',
                    serviceKey: 'roles',
                    width: '20%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
                {
                    align: 'center',
                    label: 'Members',
                    key: 'members',
                    serviceKey: 'members',
                    width: '20%',
                    canSort: true,
                    sorted: NO_SORTED,
                    type: 'number',
                },
                {
                    align: 'left',
                    label: 'Head',
                    key: 'head_name',
                    serviceKey: 'head_name',
                    width: '20%',
                },
            ],
        };
    },
    mounted() {
        window.title = `${window.title} Departments`;
        axios.get('/config/list_departments').then((response) => {
            this.depts = response.data.map((d) => {
                d.head_name = d.head_name ? `<a href class="btn btn-default">${d.head_name}</a>` : 'Not Set';
                d.buttons = [{
                    type: 'edit',
                    url: `departments/${d.id}`,
                    buttonSize: 'medium',
                    label: 'Edit Roles',
                }];
                return d;
            });
            this.dataIsLoaded = true;
        }).catch(console.log);
    },
    methods: {
        sortData(serviceKey, index) {
            this.headers[index].sorted = this.headers[index].sorted === ASC_SORTED ? DESC_SORTED : ASC_SORTED;

            this.depts = arraySort(
                this.headers,
                this.depts,
                serviceKey,
                index,
            );
        },
    },
};
</script>
