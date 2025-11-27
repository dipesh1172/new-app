<?php

namespace App\Providers;

use Swift_Mailer;
use Ramsey\Uuid\Uuid;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Collection;
use Illuminate\Mail\Mailer;
use GuzzleHttp\Client as HttpClient;
use App\Transports\MailersendTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (!App::environment('local')) {
            URL::forceScheme('https');
        }

        $this->app['request']->server->set('HTTPS', true);
        Audit::creating(
            function (Audit $model) {
                $model->id = strtoupper(Uuid::uuid4());
            }
        );

        Blade::directive('breadcrumbs', 'bootstrap4_breadcrumbs');
        Blade::directive('alert', 'bootstrap4_alert_b');
        Blade::directive('endalert', 'bootstrap4_alert_e');
        Blade::directive('fa', 'fontawesome');
        Blade::if('can', 'perms_show_if');

        //Macro to obtain the full SQL with bindings for a specific call to Eloquent\Builder
        \Illuminate\Database\Eloquent\Builder::macro('fullSQL', function () {
            $query = array_reduce($this->getBindings(), function ($sql, $binding) {
                return preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sql, 1);
            }, $this->toSql());
            return $query;
        });

        \Illuminate\Database\Query\Builder::macro('fullSQL', function () {
            $query = array_reduce($this->getBindings(), function ($sql, $binding) {
                return preg_replace('/\?/', is_numeric($binding) ? $binding : "'" . $binding . "'", $sql, 1);
            }, $this->toSql());
            return $query;
        });

        //Solving error with SQL key length
        Schema::defaultStringLength(191);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // register alternate mailer
        $this->app->bind('alt.mailer', function ($app, $parameters) {
            $client = new HttpClient();
            $transport = new MailersendTransport($client, config('service.mailersend.secret'), config('service.mailersend.domain'));
            $smailer = new Swift_Mailer($transport);
            $mailer = new Mailer($app->get('view'), $smailer, $app->get('events'));
            $mailer->alwaysFrom('no-reply@tpvhub.com');
            $mailer->alwaysReplyTo('no-reply@tpvhub.com');

            return $mailer;
        });

        if (!App::environment('production')) {
            Event::listen(
                'Illuminate\Database\Events\QueryExecuted',
                function ($query) {
                    Log::debug(
                        vsprintf(
                            str_replace(
                                array('%', '?'),
                                array('%%', '%s'),
                                $query->sql
                            ),
                            $query->bindings
                        )
                    );
                }
            );
        }

        Collection::macro('paginate', function ($perPage = 15, $page = null, $baseUrl = null, $options = []) {
            $page = $page ?: (\Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1);

            $lap = new \Illuminate\Pagination\LengthAwarePaginator(
                //Adding values solve the problem reseting the keys to avoid issues on the client side when the data is being parsed
                //whit values() "data": [{
                //without values() "data": ["25":{
                $this->forPage($page, $perPage)->values(),
                $this->count(),
                $perPage,
                $page,
                $options
            );

            if ($baseUrl) {
                $lap->setPath($baseUrl);
            }

            return $lap;
        });
    }
}
