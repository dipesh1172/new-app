import Vue from 'vue';
import Vuex from 'vuex';
import VueChartKick from 'vue-chartkick';
import Chart from 'chart.js';
import * as VueGoogleMaps from 'vue2-google-maps';
import VueI18n from 'vue-i18n';
import VueMq from 'vue-mq';
import Moment from 'moment';
import Debug from 'debug';
import VueMoment from 'vue-moment';
import VueLogr from 'vuelogr';
import CommonMessages from './i18n-common';

require('./bootstrap');
require('./coreui');

window.Vue = Vue;

window.Vue.use(Vuex);
window.Vue.use(VueMoment);
window.Vue.use(VueChartKick, Chart);
window.Vue.use(VueGoogleMaps, {
    load: {
        key: 'AIzaSyDy5TT2nXryphrixfocnA3jMzXtBWN4PGY',
        libraries: 'places, geocoding',
    },
});

// window.Vue.use(VueRouter);
window.Vue.use(VueLogr);

window.moment = Moment;
window.LogBuilder = Debug;

let userLang = navigator.language || navigator.userLanguage;
userLang = (userLang) ? userLang.split('-')[0] : 'en';

const i18n = new VueI18n({
    locale: userLang,
    fallbackLocale: userLang,
    messages: CommonMessages,
    silentTranslationWarn: true,
});

window.i18n = i18n;

Vue.use(VueMq, {
    breakpoints: {
        sm: 850,
        md: 1250,
        lg: Infinity,
    },
});
Vue.use(window.i18n);
Vue.config.lang = 'en';

window.Vue.component('brands-index', require('./pages/brands/Brands.vue'));
window.Vue.component('brands-edit', require('./pages/brands/edit/EditBrand.vue'));
window.Vue.component('brands-services-index', require('./pages/brands/edit/Services.vue'));
window.Vue.component('brands-contacts', require('./pages/brands/edit/Contacts.vue'));
window.Vue.component('brands-contract', require('./pages/brands/edit/Contract.vue'));
window.Vue.component('brands-contracts', require('./pages/brands/BrandContracts.vue'));
window.Vue.component('brands-user-profile-fields', require('./pages/brands/edit/UserProfileFields.vue'));
window.Vue.component('rate-level-brands', require('./pages/brands/edit/RateLevelBrands.vue'));
window.Vue.component('brand-users-index', require('./pages/brands/BrandUsers.vue'));
window.Vue.component('vendors-index', require('./pages/vendors/Vendors.vue'));
window.Vue.component('brands-utilities-index', require('./pages/brands/Utilities.vue'));
window.Vue.component('dnis-index', require('./pages/dnis/Dnis.vue'));
window.Vue.component('interactions-index', require('./pages/qa/Interactions.vue'));
window.Vue.component('agents-index', require('./pages/tpv_staff/Agents.vue'));
window.Vue.component('tpv-staff-index', require('./pages/tpv_staff/TpvStaff.vue'));
window.Vue.component('utilities-index', require('./pages/utilities/Utilities.vue'));
window.Vue.component('events-index', require('./pages/qa/events/events/Events.vue'));
window.Vue.component('events-show', require('./pages/qa/events/show/Show.vue'));
window.Vue.component('create-brand-index', require('./pages/brands/CreateBrand.vue'));
window.Vue.component('create-utility-index', require('./pages/utilities/CreateForm.vue'));
window.Vue.component('create-client-index', require('./pages/clients/CreateClient.vue'));
window.Vue.component('create-dnis', require('./pages/dnis/CreateDnis.vue'));
window.Vue.component('create-dnis-external', require('./pages/dnis/CreateDnisExternal.vue'));
window.Vue.component('edit-dni-index', require('./pages/dnis/EditDnis.vue'));
window.Vue.component('lookup-dnis', require('./pages/dnis/LookupDnis.vue'));
window.Vue.component('choose-dnis', require('./pages/dnis/ChooseDnis.vue'));
window.Vue.component('choose-dnis-external', require('./pages/dnis/ChooseDnisExternal.vue'));
window.Vue.component('dashboard-index', require('./pages/dashboard/Dashboard.vue'));
window.Vue.component('sales-agent-dashboard', require('./pages/dashboard/SalesAgentDashboard.vue'));
window.Vue.component('tpv-agent-dashboard', require('./pages/dashboard/TpvAgentDashboard.vue'));
window.Vue.component('qa-review', require('./pages/qa/review/review/Review.vue'));
window.Vue.component('sales-index', require('./pages/eztpv/Sales.vue'));
window.Vue.component('billing-charges', require('./pages/billing/Charges.vue'));
// window.Vue.component('chat-app', require('./pages/chat/ChatApp.vue'));
window.Vue.component('call-c-dashboard', require('./pages/reports/CallCDashboard.vue'));
window.Vue.component('brand-vendors', require('./pages/brands/vendors/BrandVendors.vue'));
window.Vue.component('call-times-by-interval', require('./pages/reports/CallTimesByInterval.vue'));
window.Vue.component('call-validation', require('./pages/reports/CallValidation.vue'));
window.Vue.component('call-validation-dxc', require('./pages/reports/CallValidationDXC.vue'));
window.Vue.component('contract-generations', require('./pages/contract-generation/ContractGen.vue'));
window.Vue.component('invoices-index', require('./pages/billing/Invoices.vue'));
window.Vue.component('create-invoices-index', require('./pages/billing/CreateInvoices.vue'));
window.Vue.component('daily-stats', require('./pages/reports/DailyStats.vue'));
window.Vue.component('raw-billing', require('./pages/reports/RawBilling.vue'));
window.Vue.component('sla-report', require('./pages/reports/SlaReport.vue'));
window.Vue.component('inbound-call-volume', require('./pages/reports/InboundCallVolume.vue'));
window.Vue.component('invoice', require('./pages/billing/Invoice.vue'));
window.Vue.component('invoice-live-minutes-index', require('./pages/billing/LiveMinutes.vue'));
window.Vue.component('agent-summary', require('./pages/reports/AgentSummary.vue'));
window.Vue.component('agent-status-detail', require('./pages/reports/AgentStatusDetail.vue'));
window.Vue.component('agent-statuses', require('./pages/reports/AgentStatuses.vue'));
window.Vue.component('qc-callbacks', require('./pages/reports/QCCallbacks.vue'));
window.Vue.component('call-followups', require('./pages/reports/CallFollowups.vue'));
window.Vue.component('cph', require('./pages/reports/CPH.vue'));
window.Vue.component('no-sales-by-agent', require('./pages/reports/NoSalesByAgent.vue'));
window.Vue.component('legacy-portal', require('./pages/reports/LegacyPortal.vue'));
window.Vue.component('daily-call-count-by-channel', require('./pages/reports/DailyCallCountByChannel.vue'));
window.Vue.component('contracts-index', require('./pages/contracts/Contracts.vue'));
window.Vue.component('contract-add-edit-index', require('./pages/contracts/ContractAddEdit.vue'));
window.Vue.component('contract-versions', require('./pages/contracts/ContractVersions.vue'));
window.Vue.component('contract-pages', require('./pages/contracts/ContractPages.vue'));
window.Vue.component('contract-editor', require('./pages/contracts/ContractEditor.vue'));
window.Vue.component('contract-terms-and-conditions', require('./pages/contracts/ContractTCS.vue'));
window.Vue.component('contract-cancellations', require('./pages/contracts/ContractCancellations.vue'));
window.Vue.component('errors', require('./pages/issues/Errors.vue'));
window.Vue.component('error', require('./pages/issues/Error.vue'));
window.Vue.component('edit-utility', require('./pages/utilities/EditUtility.vue'));
window.Vue.component('issues-list', require('./pages/issues/IssuesList.vue'));
window.Vue.component('new-issue', require('./pages/issues/NewIssue.vue'));
window.Vue.component('sales-by-channel', require('./pages/reports/SalesAndCallsByChannel.vue'));
window.Vue.component('add-tpv-staff', require('./pages/tpv_staff/AddTPVStaff.vue'));
window.Vue.component('time-clock-mgmt', require('./pages/tpv_staff/TimeClock.vue'));
window.Vue.component('kb-show', require('./pages/kb/KBShow.vue'));
window.Vue.component('kb-index', require('./pages/kb/KBIndex.vue'));
window.Vue.component('video-index', require('./pages/kb/video/VideoIndex.vue'));
window.Vue.component('kb-create', require('./pages/kb/KBCreate.vue'));
window.Vue.component('issues-dashboard', require('./pages/dashboard/IssuesDashboard.vue'));
window.Vue.component('kb-edit', require('./pages/kb/KBEdit.vue'));
window.Vue.component('kb-version-history', require('./pages/kb/VersionHistory.vue'));
window.Vue.component('stats-product-validation', require('./pages/reports/SPValidation.vue'));
window.Vue.component('digital-report', require('./pages/reports/DigitalR.vue'));
window.Vue.component('sms-report', require('./pages/reports/SmsReport.vue'));
window.Vue.component('ivr-report', require('./pages/reports/IvrReport.vue'));
window.Vue.component('questionnaire-report', require('./pages/reports/QuestionnaireReport.vue'));
window.Vue.component('finalized-sales-for-digital-customers-report', require('./pages/reports/FinalizedSalesForDigitalCustomersReport.vue'));
window.Vue.component('call-validation-dxc-legacy', require('./pages/reports/CallValidationDXCLegacy.vue'));
window.Vue.component('report-contract', require('./pages/reports/ReportContract.vue'));
window.Vue.component('missing-recording-report', require('./pages/reports/MissingRecordingReport.vue'));
window.Vue.component('generic-report', require('./pages/reports/GenericReport.vue'));
window.Vue.component('audit-lookup', require('./pages/audits/AuditLookup.vue'));
window.Vue.component('edit-tpvstaff', require('./pages/tpv_staff/EditTPVStaff.vue'));
window.Vue.component('eztpv-by-channel', require('./pages/reports/EZTPVByChannel.vue'));
window.Vue.component('api-submissions', require('./pages/reports/ApiSubmissions.vue'));
window.Vue.component('web-enroll-submissions', require('./pages/reports/WebEnrollSubmissions.vue'));
window.Vue.component('search-results', require('./pages/search/SearchResults.vue'));
window.Vue.component('config-index', require('./pages/config/ConfigIndex.vue'));
window.Vue.component('runtime-settings', require('./pages/config/RuntimeSettings.vue'));
window.Vue.component('list-departments', require('./pages/config/departments/ListDepartments.vue'));
window.Vue.component('list-roles', require('./pages/config/departments/ListRoles.vue'));
window.Vue.component('search-user-info', require('./pages/reports/SearchUserInfo.vue'));
window.Vue.component('feeschedule', require('./pages/brands/edit/Feeschedule.vue'));
window.Vue.component('taskqueues', require('./pages/brands/edit/Taskqueues.vue'));
window.Vue.component('bgcheck', require('./pages/brands/edit/BgCheck.vue'));
window.Vue.component('edit-brand-vendor', require('./pages/brands/vendors/EditBrandVendor.vue'));
window.Vue.component('login-landing', require('./pages/brands/vendors/LoginLanding.vue'));
window.Vue.component('enrollments', require('./pages/brands/edit/Enrollments.vue'));
window.Vue.component('recordings', require('./pages/brands/edit/Recordings.vue'));
window.Vue.component('dispo-shortcuts', require('./pages/brands/edit/DispoShortcuts.vue'));
window.Vue.component('brand-api-token-mgmt', require('./pages/brands/edit/ApiTokens.vue'));
window.Vue.component('create-brand-utility', require('./pages/brands/utilities/CreateBrandUtility.vue'));
window.Vue.component('edit-brand-utility', require('./pages/brands/utilities/EditBrandUtility.vue'));
window.Vue.component('add-brand-vendor', require('./pages/brands/vendors/AddBrandVendor.vue'));
window.Vue.component('clients', require('./pages/clients/Clients.vue'));
window.Vue.component('edit-client', require('./pages/clients/EditClient.vue'));
window.Vue.component('main-page', require('./apps/dashboard/pages/Main.vue'));
window.Vue.component('agents', require('./apps/dashboard/pages/Agents.vue'));
window.Vue.component('queues', require('./apps/dashboard/pages/Queues.vue'));
window.Vue.component('queues2', require('./apps/dashboard/pages/Queues_new.vue'));
window.Vue.component('control-center', require('./apps/dashboard/pages/ControlCenter.vue'));
window.Vue.component('control-center2', require('./apps/dashboard/pages/ControlCenter_new.vue'));
window.Vue.component('ccdash', require('./apps/dashboard/pages/CCdash.vue'));
window.Vue.component('client-data', require('./apps/dashboard/pages/ClientData.vue'));
window.Vue.component('pending-status', require('./apps/dashboard/pages/PendingStatus.vue'));
window.Vue.component('television', require('./apps/dashboard/pages/Television.vue'));
window.Vue.component('alerts', require('./apps/dashboard/pages/Alerts.vue'));
window.Vue.component('surveys', require('./apps/dashboard/pages/Surveys.vue'));
window.Vue.component('live-agent', require('./apps/dashboard/pages/LiveAgent.vue'));
window.Vue.component('not-ready', require('./apps/dashboard/pages/NotReady.vue'));
window.Vue.component('missing-contracts', require('./pages/reports/MissingContracts.vue'));
window.Vue.component('multiple-sale-events', require('./pages/reports/MultipleSaleEvents.vue'));
window.Vue.component('report-list', require('./pages/reports/ReportList.vue'));
window.Vue.component('clear-test-calls', require('./pages/support/ClearTestCalls.vue'));
window.Vue.component('indra-audit', require('./pages/support/IndraAudit.vue'));
// window.Vue.component('chat-settings', require('./pages/chat/ChatSettings.vue'));
window.Vue.component('sidebar', require('./pages/sidebar/Sidebar.vue'));
window.Vue.component('manage-menu', require('./pages/menu/ManageMenu.vue'));
window.Vue.component('pay-link', require('./pages/brands/edit/Pay.vue'));
window.Vue.component('contract-brand-edit', require('./pages/contracts/ContractBrandEdit.vue'));
window.Vue.component('contract-brand-add', require('./pages/contracts/ContractBrandAdd.vue'));
window.Vue.component('api-errors', require('./pages/api/ApiErrors.vue'));
window.Vue.component('product-disassociated-contracts', require('./pages/reports/ProductDisassociatedContracts.vue'));
window.Vue.component('json-documents', require('./pages/jsondocs/JsonDocuments.vue'));
window.Vue.component('invoice-details', require('./pages/reports/InvoiceDetails.vue'));
window.Vue.component('motion-skills', require('./pages/motion/skills/Show.vue'));
window.Vue.component('motion-skills-create', require('./pages/motion/skills/Create.vue'));
window.Vue.component('motion-skills-edit', require('./pages/motion/skills/Edit.vue'));
window.Vue.component('motion-skill-maps', require('./pages/motion/skill_maps/Show.vue'));
window.Vue.component('motion-skill-maps-create', require('./pages/motion/skill_maps/Create.vue'));
window.Vue.component('motion-skill-maps-edit', require('./pages/motion/skill_maps/Edit.vue'));
window.Vue.component('motion-clear-test-calls', require('./pages/motion/clear_test_calls/ClearTestCalls.vue'));

// Zip code mgmt
window.Vue.component('zcm-index', require('./pages/zcm/Index.vue'));
window.Vue.component('zcm-state', require('./pages/zcm/State.vue'));
window.Vue.component('zcm-edit', require('./pages/zcm/Edit.vue'));

const initializeStore = require('./utils/vuex/store').default;

const store = initializeStore(window.baseContent);

delete window.baseContent;

const vm = new window.Vue({
    el: '#app',
    store,
    i18n,
});

$(() => {
    $('#app').tooltip({
        selector: '[data-toggle="tooltip"]',
    });
});

// window.addEventListener('load', setup);

// const get = document.getElementById.bind(document);
// if ('user' in window && window.user !== null) {
//     window.user.hasPermission = function hasPermission(permissionName) {
//         if (!('permissions' in window.user)) {
//             return false;
//         }
//         return window.user.permissions.includes(permissionName);
//     };

//     window.user.hasAnyPermission = function anyPermission(permissionNames) {
//         if (!('permissions' in window.user)) {
//             return false;
//         }
//         if (typeof (permissionNames) === 'string') {
//             return window.user.permissions.includes(permissionNames);
//         }
//         for (let i = 0, len = permissionNames.length; i < len; i += 1) {
//             if (window.user.permissions.includes(permissionNames[i])) {
//                 return true;
//             }
//         }
//         return false;
//     };

//     window.user.hasAllPermissions = function allPermissionsIn(permissionNames) {
//         if (!('permissions' in window.user)) {
//             return false;
//         }
//         const toCheck = [];
//         for (let i = 0, len = permissionNames.length; i < len; i += 1) {
//             if (window.user.permissions.includes(permissionNames[i])) {
//                 toCheck.push(permissionNames[i]);
//             }
//         }
//         const missing = permissionNames.filter((x) => !toCheck.includes(x));
//         console.log('Missing permissions: ', missing);
//         return toCheck.length === permissionNames.length;
//     };
// }

// function setup() {
//     const modalRoot = get('app');
//     const button = get('mail_icon');
//     const modal = get('chat-app');

//     if (modalRoot !== null) {
//         modalRoot.addEventListener('click', rootClick);
//     }
//     if (button !== null) {
//         button.addEventListener('click', openModal);
//     }
//     if (modal !== null) {
//         modal.addEventListener('click', modalClick);
//     }

//     rootClick();

//     function rootClick() {
//         if (modal) {
//             modal.style.display = 'none';
//         }
//     }

//     function openModal(e) {
//         e.preventDefault();
//         e.stopPropagation();
//         e.stopImmediatePropagation();
//         modal.style.display = 'flex';
//         return false;
//     }

//     function modalClick(e) {
//         e.preventDefault();
//         e.stopPropagation();
//         e.stopImmediatePropagation();
//         return false;
//     }
// }

window.formatRate = function rateFormatter(x) {
    if (x == null) {
        return 0;
    }
    const ret = x.toFixed(6);
    let toRemove = 0;
    let rindex = ret.length - 1;
    while (ret[rindex] == '0') {
        toRemove += 1;
        rindex -= 1;
    }
    if (toRemove > 0) {
        return ret.slice(0, -(toRemove));
    }
    const decimalIndex = ret.indexOf('.');
    const decimalPlaces = (ret.length - decimalIndex) - 1;
    if (decimalPlaces == 6) {
        return ret.slice(0, -1);
    }
    return ret;
};

window.formatPhoneNumber = (number) => {
    if (number === undefined || number.length === 0) {
        console.log('formatPhoneNumber number was undefined.');
        return '';
    }

    const formatted = number.replace(
        /^\+?[1]?\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/,
        '($1) $2-$3',
    );

    return formatted;
};
