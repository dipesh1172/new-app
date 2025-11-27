<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

class MailgunPasswordReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailgun:reset-password {--list} {--delete} {--reset}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Changes the SMTP password for the configured domain';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function generatePassword(int $length = 32): string
    {
        $comb = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789,./?;:'!@#$%^&*()[]{}\\|+=";
        $pass = [];
        $combLen = strlen($comb) - 1;
        for ($i = 0; $i < $length; $i++) {
            $n = random_int(0, $combLen);
            $pass[] = $comb[$n];
        }
        return implode($pass);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $doShowList = $this->option('list');
        $doResetPassword = $this->option('reset');
        $doDeleteLogin = $this->option('delete');
        if (
            ($doShowList && $doResetPassword && $doDeleteLogin)
            || ($doShowList && $doResetPassword)
            || ($doShowList && $doDeleteLogin)
            || ($doDeleteLogin && $doResetPassword)
        ) {
            $this->error('You can only specify one of --list or --delete or --reset');
            return;
        }

        $mgApiKey = config('services.mailgun.secret');
        $mgDomain = config('services.mailgun.domain');
        if (!empty($mgApiKey)) {
            $mgUrl = 'https://api.mailgun.net/v3/domains/' . $mgDomain . '/credentials';
            $http = new Client();
            try {
                $ret = $http->get($mgUrl, [
                    'auth' => ['api', $mgApiKey],
                ]);
                $statusCode = $ret->getStatusCode();
                if ($statusCode !== 200) {
                    throw new \Exception($ret->getReasonPhrase(), $statusCode);
                }
                $logins = json_decode((string)($ret->getBody()), true);

                if (empty($logins) || empty($logins['items'])) {
                    throw new \Exception('No SMTP logins exist on remote', 1);
                }
                foreach ($logins['items'] as $login) {
                    if ($doShowList) {
                        $this->info('Login: ' . $login['login']);
                    }
                    if ($doDeleteLogin) {
                        if ($login['login'] === 'postmaster@' . $mgDomain) {
                            $this->warn('Cannot delete system mailbox: ' . $login['login']);
                        } else {
                            $ret = $http->delete($mgUrl . '/' . $login['login'], [
                                'auth' => ['api', $mgApiKey],
                            ]);
                            $statusCode = $ret->getStatusCode();
                            if ($statusCode !== 200) {
                                throw new \Exception('Error deleting login (' . $login['login'] . ') ' . $ret->getReasonPhrase(), $statusCode);
                            } else {
                                $this->info('Login: "' . $login['login'] . '" deleted');
                            }
                        }
                    }
                    if ($doResetPassword) {
                        $ret = $http->put($mgUrl . '/' . $login['login'], [
                            'auth' => ['api', $mgApiKey],
                            'form_params' => [
                                'password' => $this->generatePassword(32)
                            ],
                        ]);
                        $statusCode = $ret->getStatusCode();
                        if ($statusCode !== 200) {
                            throw new \Exception('Error resetting password on login (' . $login['login'] . ') ' . $ret->getReasonPhrase(), $statusCode);
                        } else {
                            $this->info('Login: "' . $login['login'] . '" password reset');
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error('Error (' . $e->getCode() . '): ' . $e->getMessage());
                info('Mailgun Error', [$e]);
            }
        } else {
            $this->error('Mailgun API Key not set');
        }
    }
}
