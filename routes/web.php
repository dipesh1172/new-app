<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

use App\Models\EventAlert;


Route::get('/debug/mailable', 'HomeController@debug_mailable');

Route::get('/', 'HomeController@root');
Route::get('invoice/{id}/download', 'InvoiceController@invoiceTracking');
Route::get('invoice/{id}/generate', 'InvoiceController@generate');
Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('login', 'Auth\LoginController@login');
Route::post('logout', 'Auth\LoginController@logout')->name('logout')->middleware(['auth']);
Route::get('logout', 'Auth\LoginController@logout')->middleware(['auth']);
\App\Http\Controllers\ApiController::routes();
\App\Http\Controllers\ConfigurationController::routes();
\App\Http\Controllers\KnowledgeBaseController::routes();
\App\Http\Controllers\KbRedbookEntry::routes();
\App\Http\Controllers\LegacyRedbook::routes();
\App\Http\Controllers\AlertController::routes();
\App\Http\Controllers\ClientAlertController::routes();
\App\Http\Controllers\IssueTrackerController::routes();
\App\Http\Controllers\ContractGen::routes();
\App\Http\Controllers\ContractBuilder::routes();
\App\Http\Controllers\JsonDocController::routes();
\App\Http\Controllers\MotionSkillsController::routes();
\App\Http\Controllers\MotionSkillMapsController::routes();
\App\Http\Controllers\MotionClearTestCallsController::routes();

Route::get('word2pdf', 'EzTpvController@word2pdf')->name('word2pdf');


// Password Reset Routes...
Route::get('password/reset', 'Auth\ForgotPasswordController@showLinkRequestForm')->name('password.request');
Route::post('password/email', 'Auth\ForgotPasswordController@sendResetLinkEmail')->name('password.email');
Route::get('password/reset/{token}', 'Auth\ResetPasswordController@showResetForm')->name('password.reset');
Route::post('password/reset', 'Auth\ResetPasswordController@reset');
Route::group(
    ['middleware' => ['auth']],
    function () {
        /* temporary routes for testing */
        Route::view('/test-voice-imprint', 'testing.voice_imprint');
        Route::post('/test-voice-imprint', 'TestingController@voiceImprint');
        Route::get('/test-rpa-welcome-template', 'TestingController@rpaWelcomeEmail');
        /* end temporary routes */

        \App\Http\Controllers\ZipMgmtController::routes();

        //Global Search
        Route::match(['get', 'post'], '/search', 'HomeController@globo_search')->name('globo.search');

        Route::get('audits/lookup', 'AuditController@lookup')->name('audits.lookup');
        Route::get('audits/lookupData/{id}', 'AuditController@lookupData')->name('audits.lookupData');
        Route::get('audits/search_user_info', 'AuditController@search_user_info')->name('audits.search_user_info');

        \App\Http\Controllers\ApiManagementController::routes();
        // Lists
        Route::get('list/audits', 'AuditController@listAudits')->name('audits.list');
        Route::get('brands/list', 'BrandController@listBrands')->name('brand.list');
        Route::get('list/events', 'EventController@listEvents')->name('events.list');
        Route::get('list/vendors', 'VendorController@listVendors')->name('vendor.list');
        Route::get('list/utilities_by_brand', 'UtilityController@listUtilsByBrand')->name('utilities.listByBrand');
        Route::get('list/utilities', 'UtilityController@listUtilities')->name('utilities.index');
        Route::get('list/surveys', 'SurveyController@listSurveys')->name('surveys.list');
        Route::get('list/dnis', 'DnisController@listDnis')->name('dnis.listDnis');
        Route::get('dnis/{phone}/enableIt', 'DnisController@enableDnis')->name('dnis.enable');
        Route::get('dnis/{phone}/disable', 'DnisController@disableDnis')->name('dnis.disable');
        Route::get('dnis/create-external', 'DnisController@createExternal')->name('dnis.createExternal');

        Route::get('list/interactions', 'InteractionController@listInteractions')->name('interactions.list');
        //Route::get('list/agents', 'TpvStaffController@listAgents')->name('tpv_staff.listAgents');
        //Route::get('tpv_staff/agents/{id}/delete', 'TpvStaffController@destroy')->name('tpv_staff.destroyAgent');
        //Route::get('tpv_staff/agents/{id}/active', 'TpvStaffController@restore')->name('tpv_staff.restoreAgent');
        Route::get('list/tpv_staff', 'TpvStaffController@listTpvStaff')->name('tpv_staff.listAgents');
        Route::get('tpv_staff/{id}/delete', 'TpvStaffController@destroy')->name('tpv_staff.destroy');
        Route::get('tpv_staff/{id}/active', 'TpvStaffController@restore')->name('tpv_staff.restore');
        Route::get('tpv_staff/{staff}/time', 'TpvStaffController@timeclock_mgmt')->name('tpv_staff.time');
        Route::delete('time_clock/{punch}', 'TpvStaffController@timeclock_rm_punch')->name('time_clock.remove');
        Route::post('time_clock/{punch}', 'TpvStaffController@timeclock_update_punch')->name('time_clock.update');
        Route::post('tpv_staff/{staff}/punch/{idate}', 'TpvStaffController@timeclock_add_punch')->name('time_clock.punch');

        Route::get('list/sales_agents', 'UserController@listSalesAgents')->name('sales_agents.listSalesAgents');
        //Route::get('agents', 'TpvStaffController@agents')->name('tpv_staff.agents');
        Route::get('billing', 'BillingController@index')->name('billing.index');
        Route::get('billing/list', 'BillingController@getInvoices')->name('billing.getInvoices');
        Route::get('billing/notapproved', 'BillingController@notapproved')->name('billing.notapproved');
        Route::get('billing/charges', 'BillingController@charges')->name('billing.charges');
        Route::get('billing/{id}/invoices', 'BillingController@invoices')->name('billing.invoices');
        Route::get('invoice/{id}', 'BillingController@invoice')->name('billing.invoice');
        Route::get('invoice/{id}/send', 'InvoiceController@send')->name('billing.send');
        Route::post('invoice-item/soft-delete', 'BillingController@invoiceSoftDelete')->name('invoice-item.soft-delete');
        Route::post('invoice-item/restore', 'BillingController@invoiceRestore')->name('invoice-item.restore');
        Route::post('invoice-item/update', 'BillingController@invoiceUpdate')->name('invoice-item.update');
        Route::get('invoice/invoice_get_vars/{id}', 'BillingController@invoice_get_vars')->name('billing.invoice_get_vars');
        Route::get('invoices/{id}/live_minutes_view', 'InvoiceController@live_minutes_view')->name('invoices.live_minutes_view')->middleware(['auth']);
        Route::get('invoices/{id}/live_minutes_export', 'InvoiceController@live_minutes_export')->name('invoices.live_minutes_export')->middleware(['auth']);
        Route::get('billing/create', 'BillingController@invoice_create')->name('billing.invoice_create');
        Route::post('billing/regenerate', 'BillingController@invoiceRegenerate')->name('billing.invoiceRegenerate')->middleware('auth');
        Route::post('billing/run', 'BillingController@billingRun')->name('billing.billingRun')->middleware('auth');
        Route::post('invoice/{id}/add', 'BillingController@invoice_add_item')->name('billing.invoice_add_item')->middleware('auth');
        Route::get('invoice/{id}/approve', 'InvoiceController@approveInvoice')->name('invoice.approveInvoice')->middleware('auth');
        Route::get('invoice/categories/list', 'InvoiceController@getInvoiceCategories')->name('invoice.getInvoiceCategories');
        Route::get('invoices/{id}/supplemental_report', 'InvoiceController@supplemental_report')->middleware(['auth']);
        Route::get('invoices/{id}/live_minutes', 'InvoiceController@live_minutes')->name('invoices.live_minutes')->middleware(['auth']);
        Route::post('brands/{brand}/vendor/{vendor}/addLandingIP', 'VendorController@addLandingIP')->name('vendors.addLandingIP');
        Route::delete('brands/{brand}/vendor/{vendor}/landing/{landing}/removeIP/{ip}', 'VendorController@removeLandingIP')->name('vendors.delLandingIP');
        Route::post('brands/{brand}/vendor/{vendor}/updateVendor', 'BrandController@updateVendor')->name('brands.updateVendor');
        Route::put('brands/enableVendor/{brand}/{vendor}', 'BrandController@enableVendor')->name('brands.enableVendor');
        Route::get('brands/{brand}/vendor/{vendor}/enableVendor', 'BrandController@enableVendor')->name('brands.enableVendor');
        Route::delete('brands/destroyVendor/{brand}/{vendor}', 'BrandController@destroyVendor')->name('brands.deleteVendor');
        Route::get('brands/{brand}/vendor/{vendor}/destroyVendor/', 'BrandController@destroyVendor')->name('brands.destroyVendor');
        Route::get('brands/{brand}/vendor/{vendor}/disableVendor/', 'BrandController@disableVendor')->name('brands.disableVendor');
        Route::delete('brands/permDestroyVendor/{brand}/{vendor}', 'BrandController@permDestroyVendor')->name('brands.permDestroyVendor');
        Route::get('brands/{brand}/vendor/{vendor}/permDestroyVendor', 'BrandController@permDestroyVendor')->name('brands.permDestroyVendor');
        Route::get('brands/{brand}/vendor/{vendor}/editVendor', 'BrandController@editVendor')->name('brands.editVendor');
        Route::get('brands/{brand}/vendor/{vendor}/loginLanding', 'VendorController@loginLanding')->name('brands.loginLanding');
        Route::post('brands/{brand}/vendors/searchVendors', 'BrandController@searchVendors')->name('brands.searchVendors')->middleware('auth');
        Route::post('brands/{brand}/vendors/storeVendor', 'BrandController@storeVendor')->name('brands.storeVendor')->middleware('auth');
        Route::get('brands/{brand}/vendors/create', 'BrandController@createVendor')->name('brands.createVendor')->middleware('auth');
        Route::get('brands/{brand}/vendors', 'BrandController@vendors')->name('brands.vendors')->middleware('auth');
        Route::get('brands/{brand}/list_vendors', 'BrandController@list_vendors')->name('brands.list_vendors')->middleware('auth');

        Route::get('brands/{brand}/services', 'BrandController@services')->name('brands.services')->middleware('auth');
        Route::post('brands/saveService', 'BrandController@saveService')->middleware('auth');
        Route::delete('brands/removeService', 'BrandController@removeService')->middleware('auth');

        Route::get('brands/{brand}/scripts', 'BrandController@list_brand_scripts')->middleware('auth');

        Route::get('brands/{brand}/utilities', 'UtilityController@utilsbybrand')->name('brands.utilities')->middleware('auth');
        Route::get('brands/{brand}/utilities/{utility}/editUtility', 'UtilityController@editUtilityForBrand')->name('brands.editUtility')->middleware('auth');
        Route::post('brands/{brand}/utilities/storeUtility', 'UtilityController@storeUtilityForBrand')->name('brands.storeUtilityForBrand')->middleware('auth');
        Route::get('brands/{brand}/utilities/createUtility', 'UtilityController@createUtilityForBrand')->name('brands.createUtilityForBrand')->middleware('auth');
        Route::get('brands/disableUtility/{brand}/{vendor}', 'UtilityController@disableUtility')->name('brands.disableUtility')->middleware('auth');
        Route::get('brands/enableUtility/{brand}/{vendor}', 'UtilityController@enableUtility')->name('brands.enableUtility')->middleware('auth');
        Route::post('brands/{brand}/utilities/{utility}/updateUtility', 'UtilityController@updateUtilityForBrand')->name('brands.updateUtilityForBrand')->middleware('auth');
        Route::post('brands/{brand}/store_bgchk', 'BrandController@storeBgchk')->name('brands.storeBgchk')->middleware('auth');
        Route::get('brands/{brand}/bgchk', 'BrandController@bgchk')->name('brands.bgchk')->middleware('auth');
        Route::get('brands/{brand}/taskqueues', 'BrandController@taskqueues')->name('brands.taskqueues')->middleware('auth');
        Route::get('brands/{brand}/feeschedule', 'BrandController@feeschedule')->name('brands.feeschedule')->middleware('auth');
        Route::get('brands/{brand}/enrollments', 'BrandController@enrollments')->name('brands.enrollments')->middleware('auth');
        Route::get('brands/{brand}/recordings', 'BrandController@recordings')->name('brands.recordings')->middleware('auth');

        Route::get('brands/{brand}/user_profile_fields', 'BrandController@show_brand_user_profile_fields')->name('brands.userProfileFields')->middleware(['auth']);
        Route::post('brands/save_brand_user_profile_section', 'BrandController@save_brand_user_profile_section')->middleware(['auth']);
        Route::post('brands/save_brand_user_profile_field', 'BrandController@save_brand_user_profile_field')->middleware(['auth']);

        Route::get('brands/{brand}/rate_level_brands', 'BrandController@show_rate_level_brands')->name('brands.rateLevelBrands')->middleware(['auth']);

        Route::post('brands/{brand}/createEnrollment', 'BrandController@createEnrollment')->name('brands.createEnrollment')->middleware('auth');
        Route::post('brands/{brand}/updateEnrollmentFile', 'BrandController@updateEnrollmentFile')->name('brands.updateEnrollmentFile')->middleware('auth');
        Route::post('brands/{brand}/updateRecordings', 'BrandController@updateRecordings')->name('brands.updateRecordings')->middleware('auth');
        Route::post('brands/{brand}/feeschedule/update', 'BrandController@feescheduleupdate')->name('brands.feescheduleupdate')->middleware('auth');
        Route::get('brands/login', 'BrandController@login')->name('brands.login')->middleware('auth');
        Route::post('brands/search', 'BrandController@index')->name('brands.search');
        Route::get('/brands/{brand}/disposition-shortcuts', 'BrandController@show_dispo_shortcuts')->name('brands.dispo-shortcuts')->middleware('auth');
        Route::post('/brands/{brand}/disposition-shortcuts', 'BrandController@save_dispo_shortcuts')->middleware('auth');
        Route::resource('brands', 'BrandController')->middleware('auth');
        Route::get('/brands/{brand}/contract', 'BrandController@contract')->name('brands.contract')->middleware('auth');
        Route::get('/brands/{brand}/pay', 'BrandController@pay')->name('brands.pay')->middleware('auth');
        Route::post('/brands/{brand}/pay/update', 'BrandController@payUpdate')->name('brands.payUpdate')->middleware('auth');
        Route::get('/brands/{brand}/get_contracts', 'BrandController@get_contracts')->name('brands.get_contracts')->middleware('auth');
        Route::get('/brands/{brand}/list_contracts', 'BrandController@list_contracts')->name('brands.list_contracts')->middleware('auth');
        Route::resource('brands.contacts', 'BrandContactController')->only(['index', 'store', 'update', 'destroy'])->middleware('auth');
        Route::get('/brands/{brandId}/list-contacts', 'BrandContactController@listContacts')->middleware('auth');
        Route::post('/brands/{brand}/setup-default', 'BrandController@setupBrandDefaults')->middleware('auth');
        Route::get('brand_users', 'UserController@index');
        Route::get('brand_users/login', 'UserController@loginAs');
        Route::get('list/brands', 'BrandController@getBrands')->name('brands.getBrands')->middleware('auth');
        Route::get('list/getVendors', 'BrandController@getVendors')->name('brands.getVendors')->middleware('auth');
        Route::get('clients/list', 'ClientController@getClients')->name('clients.getClients');
        Route::post('clients/search', 'ClientController@index')->name('clients.search');
        Route::get('clients/{id}/delete', 'ClientController@destroy')->name('clients.delete');
        Route::resource('clients', 'ClientController');
        Route::get('list/update_favorites/{brandId}/', 'BrandController@updateFavoritesBrands')->middleware('auth');
        Route::get('list/favorites/', 'BrandController@getFavoritesBrands')->middleware('auth');
        Route::get('sales-dashboard', 'HomeController@index')->name('home')->middleware('auth');
        Route::get('dashboard', 'TwilioController@callcenter')->name('callcenter')->middleware('auth');
        Route::get('callcenter/stats', 'TwilioController@get_stats')->name('callcenter.stats');
        Route::get('callcenter/stats-2', 'TwilioController@get_stats_new')->name('callcenter.stats2');

        // Sales Dashboard
        Route::get('sales_dashboard', 'SalesDashboardController@index')->middleware('auth');
        Route::get('sales_dashboard/sales_no_sales', 'SalesDashboardController@sales_no_sales')->middleware('auth');
        Route::get('sales_dashboard/sales_no_sales_dataset', 'SalesDashboardController@sales_no_sales_dataset')->middleware('auth');
        Route::get('sales_dashboard/top_sale_agents', 'SalesDashboardController@top_sale_agents')->middleware('auth');
        Route::get('sales_dashboard/top_sold_products', 'SalesDashboardController@top_sold_products')->middleware('auth');
        Route::get('sales_dashboard/no_sale_dispositions', 'SalesDashboardController@no_sale_dispositions')->middleware('auth');
        Route::get('sales_dashboard/sales_by_vendor', 'SalesDashboardController@sales_by_vendor')->middleware('auth');
        Route::get('sales_dashboard/sales_by_day_of_week', 'SalesDashboardController@sales_by_day_of_week')->middleware('auth');
        Route::get('sales_dashboard/good_sales_by_zip', 'SalesDashboardController@good_sales_by_zip')->middleware('auth');
        Route::get('sales_dashboard/top_brands_sales', 'SalesDashboardController@top_brands_sales')->middleware('auth');
        Route::get('sales_dashboard/top_states_sales', 'SalesDashboardController@top_states_sales')->middleware('auth');
        Route::get('sales_dashboard/sales_amount_by_channel', 'SalesDashboardController@sales_amount_by_channel')->middleware('auth');
        Route::get('sales_dashboard/calls_amount_and_aht_by_channel', 'SalesDashboardController@calls_amount_and_aht_by_channel')->middleware('auth');
        Route::get('sales_dashboard/report/pending', 'SalesDashboardController@pending_report')->middleware('auth');
        Route::get('sales_dashboard/sales_by_source', 'SalesDashboardController@sales_by_source')->middleware('auth');

        //Sales Agents Dashboard
        Route::get('sales_dashboard/agents', 'SalesDashboardController@sales_agent_dashboard')->middleware('auth');
        Route::get('sales_dashboard/agents/get_active_agents', 'SalesDashboardController@get_active_agents')->middleware('auth');
        Route::get('sales_dashboard/agents/avg_sales_per_day', 'SalesDashboardController@avg_sales_per_day')->middleware('auth');
        Route::get('sales_dashboard/agents/avg_calls_per_day', 'SalesDashboardController@avg_calls_per_day')->middleware('auth');
        Route::get('sales_dashboard/agents/avg_agents_active_per_day', 'SalesDashboardController@avg_agents_active_per_day')->middleware('auth');
        Route::get('sales_dashboard/agents/avg_daily_sales_per_agent', 'SalesDashboardController@avg_daily_sales_per_agent')->middleware('auth');
        Route::get('sales_dashboard/agents/avg_calls_per_week', 'SalesDashboardController@avg_calls_per_week')->middleware('auth');
        Route::get('sales_dashboard/agents/avg_calls_by_half_hour', 'SalesDashboardController@avg_calls_by_half_hour')->middleware('auth');
        Route::get('sales_dashboard/agents/avg_active_agents_per_week', 'SalesDashboardController@avg_active_agents_per_week')->middleware('auth');
        Route::get('sales_dashboard/agents/s_a_d_table_dataset', 'SalesDashboardController@s_a_d_table_dataset')->middleware('auth');

        //TPV Agents Dashboard
        Route::get('sales_dashboard/tpv_agents', 'TpvAgentDashboardController@tpv_agents_dashboard')->middleware('auth');
        Route::get('sales_dashboard/tpv_agents/get_call_center_stats', 'TpvAgentDashboardController@get_call_center_stats')->middleware('auth');
        Route::get('sales_dashboard/tpv_agents/get_dow_stats', 'TpvAgentDashboardController@get_dow_stats')->middleware('auth');
        Route::get('sales_dashboard/tpv_agents/get_active_tpv_agents', 'TpvAgentDashboardController@get_active_tpv_agents')->middleware('auth');
        Route::get('sales_dashboard/tpv_agents/total_calls', 'TpvAgentDashboardController@total_calls')->middleware('auth');
        Route::get('sales_dashboard/tpv_agents/total_payroll_hours', 'TpvAgentDashboardController@total_payroll_hours')->middleware('auth');
        Route::get('sales_dashboard/tpv_agents/avg_handle_time', 'TpvAgentDashboardController@avg_handle_time')->middleware('auth');
        Route::get('sales_dashboard/tpv_agents/productive_occupancy', 'TpvAgentDashboardController@productive_occupancy')->middleware('auth');
        Route::get('sales_dashboard/tpv_agents/overall_rev_per_hour', 'TpvAgentDashboardController@overall_rev_per_hour')->middleware('auth');
        Route::get('sales_dashboard/tpv_agents/avg_calls_by_dow', 'TpvAgentDashboardController@avg_calls_by_dow')->middleware('auth');
        Route::get('sales_dashboard/tpv_agents/avg_calls_by_half_hour', 'TpvAgentDashboardController@avg_calls_by_half_hour')->middleware('auth');
        Route::get('sales_dashboard/tpv_agents/avg_active_tpv_agents_by_dow', 'TpvAgentDashboardController@avg_active_tpv_agents_by_dow')->middleware('auth');
        Route::get('sales_dashboard/tpv_agents/tpv_agent_stats', 'TpvAgentDashboardController@tpv_agent_stats')->middleware('auth');

        Route::get('dnis/choose', 'DnisController@choosePhone')->name('dnis.choose')->middleware('auth');
        Route::get('dnis/choose-external', 'DnisController@choosePhoneExternal')->name('dnis.chooseExternal')->middleware('auth');
        Route::get('dnis/lookup', 'DnisController@lookupPhone')->name('dnis.lookup')->middleware('auth');
        Route::get('dnis/list_lookupPhone', 'DnisController@list_lookupPhone')->name('dnis.list_lookupPhone')->middleware('auth');
        Route::get('dnis/list_brands', 'DnisController@listBrands')->name('dnis.list_brands')->middleware('auth');
        Route::resource('dnis', 'DnisController');

        // Documents
        Route::get('documents', 'DocumentController@index')->name('documents.index')->middleware(['auth']);
        Route::get('documents/add', 'DocumentController@add')->name('documents.add')->middleware(['auth']);
        Route::get('documents/edit/{id}', 'DocumentController@edit')->name('documents.edit')->middleware(['auth']);
        Route::post('documents/create', 'DocumentController@create')->name('documents.create')->middleware(['auth']);
        Route::resource('documents', 'DocumentController')->middleware(['auth']);
        Route::get('events/export', 'EventController@export')->name('events.export');
        Route::get('events/listAudits', 'EventController@listAudits')->name('events.listAudits');
        Route::post('events/qaupdate', 'EventController@qaupdate')->name('events.qaupdate');
        Route::post('qa/tsr-id-lookup', 'QaController@tsr_id_lookup')->name('qa.tsr_id_lookup');
        Route::post('events/{id}/add_notes', 'EventController@add_notes')->name('events.addNotes');
        Route::post('events/search', 'EventController@index')->name('event.search');
        Route::get('events/{id}/rm_qa_from_call_review', 'EventController@remove_from_call_review')->name('event.remove_from_call_review');
        Route::post('events/{event}/update-sales-rep', 'QaController@update_event_sales_rep')->name('qa.tsr_id_update')->middleware(['auth']);
        Route::resource('events', 'EventController');
        Route::post('events/api/zip_code_lookup', 'EventController@zip_code_lookup');
        Route::post('events/api/qa_update_event', 'EventController@qa_update_event');
        Route::get('eztpv/contracts', 'EzTpvController@contracts')->name('eztpv.contracts');
        Route::get('eztpv/previewSignature/{id}', 'EzTpvController@previewSignature')->name('eztpv.previewSignature');
        Route::get('interactions/transcript/{id}/{event}', 'InteractionController@transcript')->name('interactions.transcript')->middleware(['auth']);
        Route::get('interactions/monitor/{id}', 'InteractionController@monitorInteraction')->name('interactions.monitor')->middleware(['auth']);
        Route::resource('interactions', 'InteractionController');
        Route::resource('qa', 'QaController');
        Route::post('qa/update-ivr-transcript', 'QaReviewController@update_ivr_script_transcript_text')->middleware(['auth'])->name('qa.update-ivr-transcript');
        Route::get('qa-tool/callsearch', 'QaController@showSearchCallStatus');
        Route::get('qa/recording-search/{dxcid}/{date}', 'QaController@searchOutCallsByDxcId');
        Route::get('qa/in-recording-search/{dxcid}/{date}', 'QaController@searchInCallsByDxcId');
        Route::post('qa/interaction-update', 'QaController@updateInteractionSessionCallId');
        // Route::resource('qa_final', 'QaFinalController');
        //Route::post('qa_review/search', 'QaReviewController@index')->name('event.search');
        Route::get('events/mark_as_reviewed/{id}', 'EventController@markAsReviewed')->name('events.markAsReviewed')->middleware(['auth']);
        Route::post('events/{event}/ReTPV', 'QaController@initiateReTPV')->name('events.reTPV')->middleware(['auth']);
        Route::get('qa_review/list', 'QaReviewController@list')->middleware(['auth']);
        Route::resource('qa_review', 'QaReviewController');
        Route::post('rules/updateBrandConfigs', 'BusinessRuleController@updateBrandConfigs')->name('rules.updateBrandConfigs');
        Route::resource('rules', 'BusinessRuleController');
        Route::get('tpv_staff/{staff}/addclient', 'TpvStaffController@addclient')->name('tpv_staff.addclient');
        Route::get('tpv_staff/{staff}/addservicelogin', 'TpvStaffController@addservicelogin')->name('tpv_staff.addservicelogin');
        Route::get('tpv_staff/{staff}/permissions', 'TpvStaffController@edit_permissions')->name('tpv_staff.permissions');
        Route::post('tpv_staff/{staff}/permissions', 'TpvStaffController@save_permissions');
        Route::get('tpv_staff/export', 'TpvStaffController@export')->name('tpv_staff.export');
        Route::post('tpv_staff/search', 'TpvStaffController@index')->name('tpv_staff.search');
        Route::resource('tpv_staff', 'TpvStaffController');
        // Reports
        Route::get('reports/daily_agent_stats', 'ReportController@agent_stats_daily')->name('reports.agent_stats_daily');
        Route::get('reports/list_daily_agent_stats', 'ReportController@list_agent_stats_daily')->name('reports.list_agent_stats_daily');
        Route::get('reports/on_the_clock', 'ReportController@on_the_clock_report')->name('reports.on_the_clock');
        Route::get('reports/list_on_the_clock', 'ReportController@list_on_the_clock')->name('reports.list_on_the_clock');
        Route::get('reports/missing_recordings', 'ReportController@missing_recording_report')->name('reports.missing_recordings');
        Route::post('reports/list_missing_recordings', 'ReportController@list_missing_recordings');
        Route::post('reports/report_inbound_call_volume', 'ReportController@report_inbound_call_volume')->name('reports.report_inbound_call_volume');
        Route::get('reports/report_inbound_call_volume', 'ReportController@report_inbound_call_volume')->name('reports.report_inbound_call_volume');
        Route::get('reports/list_report_inbound_call_volume', 'ReportController@list_report_inbound_call_volume')->name('reports.list_report_inbound_call_volume');
        Route::post('reports/report_cph', 'ReportController@report_cph')->name('reports.report_cph');
        Route::get('reports/report_cph', 'ReportController@report_cph')->name('reports.report_cph');
        Route::get('reports/list_cph', 'ReportController@list_cph')->name('reports.list_cph');
        Route::get('reports/report_call_followups', 'ReportController@report_call_followups')->name('reports.report_call_followups');
        Route::post('reports/report_call_followups', 'ReportController@report_call_followups')->name('reports.report_call_followups');
        Route::post('reports/report_agent_statuses', 'ReportController@report_agent_statuses')->name('reports.report_agent_statuses');
        Route::get('reports/report_agent_statuses', 'ReportController@report_agent_statuses')->name('reports.report_agent_statuses');
        Route::post('reports/report_daily_billing_figures', 'ReportController@report_daily_billing_figures')->name('reports.report_daily_billing_figures');
        Route::get('reports/report_daily_billing_figures', 'ReportController@report_daily_billing_figures')->name('reports.report_daily_billing_figures');
        Route::post('reports/report_daily_call_count_by_channel', 'ReportController@report_daily_call_count_by_channel')->name('reports.report_daily_call_count_by_channel');
        Route::get('reports/report_daily_call_count_by_channel', 'ReportController@report_daily_call_count_by_channel')->name('reports.report_daily_call_count_by_channel');
        Route::post('reports/report_no_sales_by_agent', 'ReportController@report_no_sales_by_agent')->name('reports.report_no_sales_by_agent');
        Route::get('reports/report_no_sales_by_agent', 'ReportController@report_no_sales_by_agent')->name('reports.report_no_sales_by_agent');
        Route::post('reports/legacy_portal', 'ReportController@legacy_portal')->name('reports.legacy_portal');
        Route::get('reports/legacy_portal', 'ReportController@legacy_portal')->name('reports.legacy_portal');
        Route::get('reports/pending_replace_report', 'ReportController@pending_replace_report')->name('reports.pending_replace_report');
        Route::get('reports/report_qc_callbacks', 'ReportController@report_qc_callbacks')->name('reports.qc_callbacks');
        Route::post('reports/report_qc_callbacks', 'ReportController@report_qc_callbacks')->name('reports.qc_callbacks');
        Route::get('reports/call_times_by_interval', 'ReportController@callTimesByInterval')->name('reports.call_times_by_interval')->middleware('auth');
        Route::get('reports/call_times_by_interval/list_call_times_by_interval', 'ReportController@listCallTimesByInterval')->name('reports.list_call_times_by_interval')->middleware('auth');
        Route::get('reports/call_validation', 'ReportController@call_validation')->name('reports.call_validation');
        Route::get('reports/call_validation/list_call_validation', 'ReportController@list_call_validation')->name('reports.list_call_validation');
        Route::get('reports/call_center_dashboard', 'CallCenterDashboard@call_center_dashboard')->name('reports.call_center_dashboard');
        Route::get('reports/call_center_dashboard/totalCalls', 'CallCenterDashboard@totalCalls')->name('reports.totalCalls');
        Route::get('reports/call_center_dashboard/totalOccupancy', 'CallCenterDashboard@totalOccupancy')->name('reports.totalOccupancy');
        Route::get('reports/call_center_dashboard/avgSpeedToAnswer', 'CallCenterDashboard@avgSpeedToAnswer')->name('reports.avgSpeedToAnswer');
        Route::get('reports/call_center_dashboard/average_handle_time', 'CallCenterDashboard@average_handle_time')->name('reports.average_handle_time');
        Route::get('reports/call_center_dashboard/service_level', 'CallCenterDashboard@service_level')->name('reports.service_level');
        Route::get('reports/call_center_dashboard/talk_and_payroll_time', 'CallCenterDashboard@talk_and_payroll_time')->name('reports.talk_and_payroll_time');
        Route::get('reports/call_center_dashboard/_tpv_staff_status_time', 'CallCenterDashboard@_tpv_staff_status_time')->name('reports._tpv_staff_status_time');
        Route::get('reports/call_center_dashboard/_asa_by_half_hour', 'CallCenterDashboard@_asa_by_half_hour')->name('reports._asa_by_half_hour');
        Route::get('reports/call_center_dashboard/_service_level_by_half_hour', 'CallCenterDashboard@_service_level_by_half_hour')->name('reports._service_level_by_half_hour');
        Route::get('reports/call_center_dashboard/_aht_by_half_hour', 'CallCenterDashboard@_aht_by_half_hour')->name('reports._aht_by_half_hour');
        Route::get('reports/call_center_dashboard/_payroll_time_by_half_hour', 'CallCenterDashboard@_payroll_time_by_half_hour')->name('reports._payroll_time_by_half_hour');
        Route::get('reports/call_center_dashboard/call_center_dataset', 'CallCenterDashboard@call_center_dataset')->name('reports.call_center_dataset');
        Route::get('reports/daily_stats', 'ReportController@dailyStats')->name('reports.daily_stats')->middleware('auth');
        Route::get('reports/listDailyStats', 'ReportController@listDailyStats')->name('reports.listDailyStats')->middleware('auth');
        Route::get('reports/raw_billing', 'ReportController@rawBilling')->name('reports.raw_billing')->middleware('auth');
        Route::get('reports/listRawBilling', 'ReportController@ListRawBilling')->name('reports.listRawBilling')->middleware('auth');
        Route::get('reports/sla_report', 'ReportController@slaReport')->name('reports.sla_report')->middleware('auth');
        Route::get('reports/call_validation_dxc', 'ReportController@call_validation_dxc')->name('reports.call_validation_dxc');
        Route::get('reports/sales_by_channel', 'ReportController@sales_by_channel')->name('reports.sales_by_channel');
        Route::get('reports/calls_by_channel', 'ReportController@calls_by_channel')->name('reports.calls_by_channel');
        Route::get('reports/stats_product_validation', 'ReportController@stats_product_validation')->name('reports.stats_product_validation');
        Route::get('reports/list_stats_product_validation', 'ReportController@list_stats_product_validation')->name('reports.list_stats_product_validation');
        Route::get('reports/digital_report', 'ReportController@digital_report')->name('reports.digital');
        Route::get('reports/list_digital', 'ReportController@list_digital')->name('reports.list_digital');
        Route::get('reports/ivr_report', 'ReportController@ivr_report')->name('reports.ivr');
        Route::get('reports/list_ivr', 'ReportController@list_ivr')->name('reports.list_ivr');
        Route::get('reports/sms', 'ReportController@sms_report')->name('reports.sms');
        Route::get('reports/list_sms_report', 'ReportController@list_sms_report')->name('reports.sms-list');
        Route::get('reports/questionnaire_report', 'ReportController@questionnaire_report')->name('reports.questionnaire');
        Route::get('reports/list_questionnaire', 'ReportController@list_questionnaire')->name('reports.list_questionnaire');
        Route::get('reports/finalized_sales_for_digital_customers_report', 'ReportController@finalized_sales_for_digital_customers')->name('reports.finalized_sales_for_digital_customers_report');
        Route::get('reports/list_finalized_sales_for_digital_customers', 'ReportController@list_finalized_sales_for_digital_customers')->name('reports.list_finalized_sales_for_digital_customers');
        Route::get('reports/call_validation_dxc_legacy', 'ReportController@call_validation_dxc_legacy')->name('reports.call_validation_dxc_legacy');
        Route::get('reports/list_call_validation_dxc_legacy', 'ReportController@list_call_validation_dxc_legacy')->name('reports.list_call_validation_dxc_legacy');
        Route::get('reports/dxc_brands', 'ReportController@dxc_brands')->name('reports.dxc_brands');
        Route::get('reports/report_contracts', 'ReportController@report_contracts')->name('reports.report_contracts');
        Route::get('reports/list_contracts', 'ReportController@list_contracts')->name('reports.list_contracts');
        Route::get('reports/eztpv_by_channel', 'ReportController@eztpv_by_channel')->name('reports.eztpv_by_channel');
        Route::get('reports/list_eztpv_by_channel', 'ReportController@list_eztpv_by_channel')->name('reports.list_eztpv_by_channel');
        Route::get('reports/api_submissions', 'ReportController@api_submissions')->name('reports.api_submissions');
        Route::get('reports/list_api_submissions', 'ReportController@list_api_submissions')->name('reports.list_api_submissions');
        Route::get('reports/web_enroll_submissions', 'ReportController@web_enroll_submissions')->name('reports.web_enroll_submissions');
        Route::get('reports/list_web_enroll_submissions', 'ReportController@list_web_enroll_submissions')->name('reports.list_web_enroll_submissions');
        Route::get('reports/search_user_info_from_audits', 'ReportController@search_user_info_from_audits')->name('reports.search_user_info_from_audits');
        Route::get('reports/missing_contracts', 'ReportController@missing_contracts')->name('reports.missing_contracts');
        Route::get('reports/list_missing_contracts', 'ReportController@list_missing_contracts')->name('reports.list_missing_contracts');
        Route::get('reports/multiple_sale_events', 'ReportController@multiple_sale_events')->name('reports.multiple_sale_events');
        Route::get('reports/list_multiple_sale_events', 'ReportController@list_multiple_sale_events')->name('reports.list_multiple_sale_events');
        Route::get('reports/product_disassociated_contracts', 'ReportController@product_disassociated_contracts')->name('reports.product_disassociated_contracts');
        Route::get('reports/list_product_disassociated_contracts', 'ReportController@list_product_disassociated_contracts')->name('reports.list_product_disassociated_contracts');
        Route::get('reports/reprocessEztpvs/{product_id}', 'ReportController@reprocessEztpvs')->name('reports.reprocessEztpvs');
        Route::get('reports/invoice_details', 'ReportController@invoice_details')->name('reports.invoice_details');
        Route::get('reports/list_invoice_details', 'ReportController@list_invoice_details')->name('reports.list_invoice_details');
        Route::get('reports', 'ReportController@index')->name('reports.index');

        Route::get('support/send_live_enroll', 'SupportController@send_live_enroll')->name('support.live_enroll');
        Route::post('support/send_live_enroll', 'SupportController@send_live_enroll')->name('support.send_live_enroll');
        Route::get('support/send_file_transfer', 'SupportController@send_file_transfers')->name('support.file_transfer');
        Route::post('support/send_file_transfer', 'SupportController@send_file_transfers')->name('support.send_file_transfer');
        Route::get('support/tlp_test', 'SupportController@show_tlp_test_page')->name('support.tlp_test');
        Route::post('support/list_clear_test_calls', 'SupportController@list_clear_test_calls')->name('support.list_clear_test_calls');
        Route::get('support/clear_test_calls', 'SupportController@clear_test_calls')->name('support.clear_test_calls');
        Route::post('support/delete_test_calls', 'SupportController@delete_test_calls')->name('support.delete_test_calls');
        Route::post('support/clear_cache', 'SupportController@do_clear_cache')->name('support.clear_cache');
        Route::view('support/clear_cache', 'support.clear_cache');
        Route::get('support/contract_test', 'SupportController@run_contract');
        Route::post('support/contract_test', 'SupportController@run_contract');
        Route::get('support/sptest', 'SupportController@show_sales_pitch_test_page');
        Route::post('support/sptest', 'SupportController@create_sp_record');
        Route::get('support/alerts', 'SupportController@alert_testing_tool');
        Route::post('support/alerts', 'SupportController@alert_testing_tool');
        Route::get('support/email', 'SupportController@show_email_lookup_tool');
        Route::post('support/email', 'SupportController@email_tool_lookup');
        Route::post('support/email-reset', 'SupportController@email_tool_reset');
        Route::get('support/indra_audit', 'SupportController@indra_audit');
        Route::get('support/indra_audit/list', 'SupportController@indra_audit_list')->name('indra_audit.list');
        Route::post('support/indra_audit/reviewed/{event_id}', 'SupportController@indra_audit_reviewed')->name('indra_audit.reviewed');

        // Alerts
        Route::post('alerts/create','TpvAlertsController@saveAlert')->name('alerts.create');
        Route::delete('alerts/delete','TpvAlertsController@deleteAlert')->name('alerts.delete');
        Route::get('alerts/list','TpvAlertsController@getAlerts')->name('alerts.list');
        
        // Billing/Charges
        Route::get('billing/{brand}/ratecard', 'BillingController@rateCardForBrand');
        Route::get('billing/ratecard/{client_id}', 'BillingController@rateCardForClient');
        Route::post(
            'billing/charges/create',
            'BillingController@saveBillingCharge'
        )->name('billingCharge.create');
        Route::delete(
            'billing/charges/delete',
            'BillingController@deleteBillingCharge'
        )->name('billingCharge.delete');
        Route::get(
            'billing/charges/list',
            'BillingController@getBillingCharges'
        )->name('billingCharge.list');
        // Brand Contract
        Route::post(
            'brands/contract/create',
            'BrandController@createBrandContract'
        )->name('brandContract.create');
        Route::delete(
            'brands/contract/delete',
            'BrandController@deleteBrandContract'
        )->name('brandContract.delete');
        Route::get(
            'brands/contract/list/{brandId}',
            'BrandController@getBrandContracts'
        )->name('brandContract.single');
        Route::get(
            'brands/contract/list',
            'BrandController@getBrandContracts'
        )->name('brandContract.list');
        // Utilities
        Route::get(
            'utilities',
            'UtilityController@utilities'
        )->name('utilities.index');
        Route::post('utilities/update-ident/{sf}', 'UtilityController@updateUtilityIdentifier')->name('utilities.updateIdent');
        Route::post(
            'utilities/storeUtility',
            'UtilityController@storeUtility'
        )->name('utilities.storeUtility');
        Route::post(
            'utilities/{utility}/updateUtility',
            'UtilityController@updateUtility'
        )->name('utilities.updateUtility');
        Route::get(
            'utilities/createUtility',
            'UtilityController@createUtility'
        )->name('utilities.createUtility');
        Route::get(
            'utilities/{id}/editUtility',
            'UtilityController@editUtility'
        )->name('utilities.editUtility');
        // Vendors
        Route::resource('vendors', 'VendorController');
        // Remove
        Route::get(
            'tpv_staff/removePhone/{tpv_staff}/{pnl}',
            'TpvStaffController@removePhone'
        )->name('tpv_staff.removePhone');
        Route::get(
            'tpv_staff/removeEmail/{tpv_staff}/{eal}',
            'TpvStaffController@removeEmail'
        )->name('tpv_staff.removeEmail');
        Route::get(
            'lookup/event/{code}',
            'EventController@lookupConfirmationCode'
        )->name('lookup.lookupConfirmationCode');
        Route::get('katana/compare', 'KatanaController@compareConfirmationCodes')
            ->name('katana.compareConfirmationCodes');
        Route::post('katana', 'KatanaController@index')
            ->name('katana.search');
        Route::get('katana', 'KatanaController@index')
            ->name('katana.search');
        //Skill Profiles
        Route::get('agent/groups/{id}/delete', 'SkillProfileController@remove_skill_profile');
        Route::get('agent/groups', 'SkillProfileController@show_app')->name('tpv_staff.agentgroups');
        Route::post('skill-profiles/add', 'SkillProfileController@add_skill_profile');
        Route::post('skill-profiles/save', 'SkillProfileController@update_skill_profile');
        Route::get('skill-profiles/list', 'SkillProfileController@list_skill_profiles');
        Route::get('skill-profiles/list-skills', 'SkillProfileController@available_skills');
        Route::get('skill-profiles/{id}/list-agents', 'SkillProfileController@agents_by_skill_profile');
        // Work@Home
        Route::get('agent-activity-log/{staff?}', 'WorkAtHomeController@agent_activity_log')->name('live_agent.activity_log');
        Route::post('agent-activity-log/{staff?}', 'WorkAtHomeController@agent_activity_log')->name('live_agent.activity_log');
        Route::get('agent-summary-report', 'WorkAtHomeController@agent_summary_report')->name('reports.agent_summary_report');
        Route::post('agent-summary-report', 'WorkAtHomeController@agent_summary_report')->name('reports.agent_summary_report');
        Route::get('agent-status-summary', 'WorkAtHomeController@agent_status_summary')->name('reports.agent_status_summary');
        Route::post('agent-status-summary', 'WorkAtHomeController@agent_status_summary')->name('reports.agent_status_summary');
        Route::get('surveys/release/{trickle}/{language_id}/{brand}', 'SurveyController@manualRelease')->name('surveys.manualRelease');
        Route::get('surveys/release/{trickle}', 'SurveyController@manualRelease')->name('surveys.manualRelease');
        Route::get('surveys/reset', 'SurveyController@resetDefault')->name('surveys.resetDefault');
        Route::get('surveys/resetCallTime', 'SurveyController@resetCallTime')->name('surveys.resetCallTime');
        Route::get('chat-test', 'ChatController@chat_test');
        Route::get('contacts', 'ChatController@getContacts')->name('chat.contacts');
        Route::get('conversation/{id}', 'ChatController@getMessagesFor')->name('chat.messages');
        Route::post('conversation/sendMessage', 'ChatController@sendMessage')->name('chat.newmessage');
        Route::get('chat-settings', 'ChatController@index')->name('chat.settings.index');
        Route::post('chat-settings', 'ChatController@update')->name('chat.settings.update');

        Route::get('twilio-debug', 'TwilioController@debug_info')->name('twilio.debug');
        Route::post('twilio-debug', 'TwilioController@debug_info')->name('twilio.debug');
        Route::get('test_calls', 'TwilioController@test_calls')->name('twilio.testCalls');

        Route::get('image-click', 'WorkAtHomeController@image_click')->name('image.test');
        Route::post('image-click', 'WorkAtHomeController@image_click')->name('image.test');

        Route::get('/dashboard', 'TwilioController@callcenter')->name('call_center');
        Route::get('/live-agent', 'TwilioController@liveagent')->name('live_agent');
        Route::get('/not-ready', 'TwilioController@notready')->name('not_ready');
        Route::get('/queues', 'TwilioController@queues')->name('queues');
        Route::get('/television', 'TwilioController@television')->name('television');
        Route::get('/alerts', 'TwilioController@alerts')->name('alerts');
        Route::get('/surveys', 'TwilioController@surveys')->name('surveys');

        //Issues Dashboard
        Route::prefix('issues_dashboard')->group(function () {
            Route::get('/', 'IssuesDashboardController@issues')->name('issues_dashboard');
            Route::get('last_result_bef_per_brand', 'IssuesDashboardController@last_result_bef_per_brand')->name('last_result_bef_per_brand');
            Route::get('calls_without_records', 'IssuesDashboardController@calls_without_records')->name('calls_without_records');
            Route::get('last_recording', 'IssuesDashboardController@last_recording')->name('last_recording');
            Route::get('last_contract', 'IssuesDashboardController@last_contract')->name('last_contract');
            Route::get('eztpv_without_contracts', 'IssuesDashboardController@eztpv_without_contracts')->name('eztpv_without_contracts');
            Route::post('failed_sms', 'IssuesDashboardController@failed_sms');
            Route::post('failed_sms_error_check/{sid}', 'IssuesDashboardController@failed_sms_error_check');
        });

        //Sidebar
        Route::prefix('menus')->group(function () {
            Route::get('get_sidebar_menu', 'MenuLinksController@index');
            Route::get('manage_menu', 'MenuLinksController@manage_menu');
            Route::post('store', 'MenuLinksController@write_info_on_db');
            Route::get('destroy', 'MenuLinksController@destroy');
        });

        //Contracts
        Route::prefix('brand_contracts')->group(function () {
            Route::get('{id}/edit', 'ContractController@edit');
            Route::post('{id}/update', 'ContractController@update');
            Route::get('{id}/restore', 'ContractController@restore');
            Route::get('add_contract', 'ContractController@add_contract');
            Route::post('store', 'ContractController@store');
            Route::get('store', 'ContractController@store');
            Route::post('{bec}/delete', 'ContractController@deleteContract');
        });

        Route::get('storage/contracts', 'ContractController@get_file');
    }
);
