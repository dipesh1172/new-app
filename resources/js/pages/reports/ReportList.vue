<template>
  <div>
    <breadcrumb
      :items="[
        {name: 'Home', url: '/'},
        {name: 'Reports', url: '/reports', active: true},
      ]"
    />

    <div class="container-fluid mt-5">
      <div class="row animated fadeIn">
        <div
          v-for="(item, index) in items.filter(item => item.isVisible)"
          :key="index"
          class="col-md-4"
        >
          <div class="card">
            <div class="card-body">
              <a
                :href="item.url"
                :title="item.title || ''"
              >{{ item.text }}</a>
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
    name: 'ReportList',
    components: {
        Breadcrumb,
    },
    data() {
        const rol = this.$store.state.user.role_id;
        return {
            items: [
                {
                    isVisible: [1, 7, 8, 9].includes(rol),
                    url: '/agent-status-summary',
                    text: 'Agent Status Detail',
                },
                {
                    isVisible: [1, 7, 8, 9].includes(rol),
                    url: '/agent-summary-report',
                    text: 'Agent Summary Report',
                },
                {
                    isVisible: [1, 7, 8, 9].includes(rol),
                    url: '/reports/report_cph',
                    text: 'AHT Report',
                    title: 'AHT and CPH by TPV Agent',
                },
                {
                    isVisible: true,
                    url: '/reports/api_submissions',
                    text: 'API Submissions',
                },
                {
                    isVisible: true,
                    url: '/reports/call_center_dashboard',
                    text: 'Call Center',
                },
                {
                    isVisible: true,
                    url: '/reports/report_call_followups',
                    text: 'Call Followups',
                },
                {
                    isVisible: true,
                    url: '/reports/call_times_by_interval',
                    text: 'Call Times By Interval'
                },
                {
                    isVisible: true,
                    url: '/reports/call_validation',
                    title: 'Call level report used for validation',
                    text: 'Call Validation',
                },
                {
                    isVisible: true,
                    url: '/reports/call_validation_dxc',
                    title: 'DXC Call level report used for validation',
                    text: 'Call Validation DXC',
                },
                {
                    isVisible: true,
                    url: '/reports/call_validation_dxc_legacy',
                    title: 'Legacy Call level report used for validation',
                    text: 'Call Validation Legacy',
                },
                {
                    isVisible: true,
                    url: '/reports/report_contracts?upload_type_id=3',
                    text: 'Contracts Report',
                },
                {
                    isVisible: true,
                    url: '/reports/report_daily_call_count_by_channel',
                    title: 'Call volume information by channel, state, and brand',
                    text: 'Daily Call Count by Channel',
                },
                {
                    isVisible: true,
                    url: '/reports/daily_stats',
                    text: 'Daily Stats',
                },
                {
                    isVisible: true,
                    url: '/reports/digital_report',
                    text: 'Digital Report',
                },
                {
                    isVisible: true,
                    url: '/reports/eztpv_by_channel',
                    text: 'EZTPV By Channel',
                },
                {
                    isVisible: true,
                    url: '/reports/finalized_sales_for_digital_customers_report',
                    text: 'Finalized Sales for Digital Customers Report',
                },
                {
                    isVisible: true,
                    url: '/reports/report_inbound_call_volume',
                    title: 'Call Volume by brand and Interval',
                    text: 'Inbound Call Volume',
                },
                {
                    isVisible: true,
                    url: '/reports/invoice_details',
                    title: 'Invoice Details for SA Billing',
                    text: 'Invoice Details',
                },
                {
                    isVisible: true,
                    url: '/reports/ivr_report',
                    text: 'IVR Report',
                },
                {
                    isVisible: true,
                    url: '/reports/missing_contracts',
                    text: 'Missing Contracts',
                },
                {
                    isVisible: true,
                    url: '/reports/missing_recordings',
                    text: 'Missing Recordings',
                },
                {
                    isVisible: true,
                    url: '/reports/report_no_sales_by_agent',
                    title: 'No Sale Results by TPV agent',
                    text: 'No Sales by Agent',
                },
                {
                    isVisible: true,
                    url: '/reports/legacy_portal',
                    title: 'Legacy Portal for Calibrus, Trusted and DXC',
                    text: 'Legacy Portal',
                },
                {
                    isVisible: true,
                    url: '/reports/report_contracts?upload_type_id=4',
                    text: 'Photos Report',
                },   
                {
                    isVisible: true,
                    url: '/reports/product_disassociated_contracts',
                    text: 'Product-Disassociated Contracts',
                },         
                {
                    isVisible: [1, 8, 3, 9, 4, 11].includes(rol),
                    url: '/reports/report_qc_callbacks',
                    title: 'Call data for Survey calls',
                    text: 'QA - QC Callbacks',
                },    
                {
                    isVisible: true,
                    url: '/reports/questionnaire_report',
                    text: 'Questionnaire Report',
                },                
                {
                    isVisible: true,
                    url: '/reports/raw_billing',
                    text: 'Raw Billing',
                },
                {
                    isVisible: true,
                    url: '/reports/sales_by_channel',
                    text: 'Sales and Calls by Channel',
                },
                {
                    isVisible: true,
                    url: '/reports/search_user_info_from_audits',
                    text: 'Search User Info',
                },
                {
                    isVisible: true,
                    url: '/reports/sla_report',
                    text: 'SLA Report'
                },
                {
                    isVisible: true,
                    url: '/reports/stats_product_validation',
                    text: 'Stats Product Validation',
                },                                
                {
                    isVisible: true,
                    url: '/reports/web_enroll_submissions',
                    text: 'Web Enroll Submissions',
                },
                {
                    isVisible: [1],
                    url: '/reports/sms',
                    text: 'SMS Report',
                },
                {
                    isVisible: true,
                    url: '/reports/on_the_clock',
                    text: 'On The Clock',
                },
                {
                    isVisible: [1, 3, 5, 6, 7, 8, 10].includes(rol),
                    url: '/reports/daily_agent_stats',
                    text: 'Daily Agent Stats',
                },
            ],
        };
    },
};
</script>
