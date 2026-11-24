<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

class GmailApiTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $config = config('services.gmail');

        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (empty($config[$key])) {
                throw new TransportException(
                    'Missing Gmail configuration: '.$key
                );
            }
        }

        $email = $message->getOriginalMessage();

        if (! $email instanceof Email || $email->getBcc()) {
            throw new TransportException(
                'This Gmail transport supports standard To and Cc messages only.'
            );
        }

        $addresses = fn ($items) => array_map(
            fn ($address) => $address->getAddress(),
            $items
        );

        $recipients = array_unique($addresses(
            array_merge($email->getTo(), $email->getCc())
        ));

        $envelope = array_unique($addresses(
            $message->getEnvelope()->getRecipients()
        ));

        if (array_diff($recipients, $envelope)
            || array_diff($envelope, $recipients)) {
            throw new TransportException('Recipient mismatch.');
        }

        $tokenResponse = Http::asForm()
            ->timeout(20)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'refresh_token' => $config['refresh_token'],
                'grant_type' => 'refresh_token',
            ]);

        $accessToken = $tokenResponse->json('access_token');

        if (! $tokenResponse->successful() || ! $accessToken) {
            throw new TransportException(
                'Gmail OAuth failed. HTTP '.$tokenResponse->status()
            );
        }

        $rawMessage = rtrim(
            strtr(
                base64_encode($message->toString()),
                '+/',
                '-_'
            ),
            '='
        );

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->post(
                'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
                ['raw' => $rawMessage]
            );

        if (! $response->successful()) {
            throw new TransportException(
                'Gmail API sending failed. HTTP '.$response->status()
            );
        }
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }
}
