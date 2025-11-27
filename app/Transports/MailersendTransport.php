<?php

namespace App\Transports;

use Swift_Mime_SimpleMessage;
use Illuminate\Mail\Transport\Transport;
use GuzzleHttp\ClientInterface;

class MailersendTransport extends Transport
{
    /**
     * Guzzle client instance.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $client;

    /**
     * The Mailersend API key.
     *
     * @var string
     */
    protected $key;

    /**
     * The Mailersend API endpoint.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Create a new Mailersend transport instance.
     *
     * @param  \GuzzleHttp\ClientInterface  $client
     * @param  string  $key
     * @param  string  $endpoint
     * @return void
     */
    public function __construct(ClientInterface $client, $key, $endpoint)
    {
        $this->key = $key;
        $this->client = $client;
        $this->endpoint = $endpoint ?? 'api.mailersend.com';
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $to = $this->getTo($message);

        $message->setBcc([]);

        $this->client->request(
            'POST',
            "https://{$this->endpoint}/v1/email",
            $this->payload($message, $to)
        );

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    /**
     * Get the HTTP payload for sending the Mailersend message.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     * @param  string  $to
     * @return array
     */
    protected function payload(Swift_Mime_SimpleMessage $message, $to)
    {
        return [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'Authorization' => 'Bearer ' . $this->key,
            ],
            'body' => [
                'from' => [
                    'email' => $message->getFrom()
                ],
                'to' => [
                    [
                        'email' => $to,
                    ],
                ],
                'subject' => $message->getSubject(),
                'text' => strip_tags($message->toString()),
                'html' => $message->toString(),
            ],
        ];
    }

    /**
     * Get the "to" payload field for the API request.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     * @return string
     */
    protected function getTo(Swift_Mime_SimpleMessage $message)
    {
        return collect($this->allContacts($message))->map(function ($display, $address) {
            return $display ? $display . " <{$address}>" : $address;
        })->values()->implode(',');
    }

    /**
     * Get all of the contacts for the message.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     * @return array
     */
    protected function allContacts(Swift_Mime_SimpleMessage $message)
    {
        return array_merge(
            (array) $message->getTo(),
            (array) $message->getCc(),
            (array) $message->getBcc()
        );
    }

    /**
     * Get the API key being used by the transport.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set the API key being used by the transport.
     *
     * @param  string  $key
     * @return string
     */
    public function setKey($key)
    {
        return $this->key = $key;
    }

    /**
     * Get the API endpoint being used by the transport.
     *
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * Set the API endpoint being used by the transport.
     *
     * @param  string  $endpoint
     * @return string
     */
    public function setEndpoint($endpoint)
    {
        return $this->endpoint = $endpoint;
    }
}
