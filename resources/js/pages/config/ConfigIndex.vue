<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Company Configuration', active: true},
      ]"
    />
    <div class="container-fluid mt-5">
      <div class="row animated fadeIn">
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <a href="/config/departments">Departments and Roles</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <a href="/config/runtime">Runtime Settings</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <a href="/config/brand_service_types">Brand Service Types</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              Call Centers
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <a href="/config/permissions">Permissions</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <a href="/skill-profiles">Skill Profiles</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <a href="/config/site-alerts">Site Alerts</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <a href="/zcm">Zip Code Management</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import Breadcrumb from 'components/Breadcrumb';

export default {
    name: 'ConfigIndex',
    components: {
        Breadcrumb,
    },
};
</script>
