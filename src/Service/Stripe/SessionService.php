<?php declare(strict_types=1);

namespace App\Service\Stripe;

use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;

class SessionService
{
    public function startSession(array $order, array $transaction, string $successUrl, string $cancelUrl): Session
    {
        $currency = $order['currency']['isoCode'];
        $amount = $transaction['amount']['totalPrice'];
        $amount *= pow(10, 2); // this should obviously be better implemented to support zero-decimal currencies: https://stripe.com/docs/currencies
        $customerEmail = $order['orderCustomer']['email'];
        $orderNumber = $order['orderNumber'];

        return Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amount,
                    'product_data' => [
                        'name' => \sprintf('Order %s', $orderNumber),
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'payment_intent_data' => [
                'capture_method' => 'manual',
            ],
            'customer_email' => $customerEmail,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    public function getPaymentStatusForSession(string $sessionId): string
    {
        try {
            $session = Session::retrieve($sessionId);
            return $session->payment_status === 'paid' ? 'paid' : 'authorize';
        } catch (ApiErrorException $exception) {
            return 'fail';
        }
    }

    public function getPaymentIntentFromSession(string $sessionId): ?PaymentIntent
    {
        $session = Session::retrieve($sessionId);

        if (!$session->payment_intent) {
            return null;
        }

        return PaymentIntent::retrieve($session->payment_intent);
    }
}