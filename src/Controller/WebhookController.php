<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Service\Stripe\SessionService;
use App\Shop\ShopRepository;
use GuzzleHttp\Psr7\Request;
use Shopware\AppBundle\Client\ClientFactoryInterface;
use Stripe\Charge;
use Stripe\Refund;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebhookController extends AbstractController
{
    private OrderRepository $orderRepository;
    private SessionService $sessionService;
    private ShopRepository $shopRepository;
    private ClientFactoryInterface $clientFactory;

    public function __construct(
        OrderRepository $orderRepository,
        SessionService $sessionService,
        ShopRepository $shopRepository,
        ClientFactoryInterface $clientFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->sessionService = $sessionService;
        $this->shopRepository = $shopRepository;
        $this->clientFactory = $clientFactory;
    }

    /**
     * @Route("/webhook/order-cancel", name="webhook.order-cancel", methods={"POST"})
     */
    public function onOrderCanceled(SymfonyRequest $request): Response
    {
        $data = \json_decode($request->getContent(), true);

        $charges = $this->getStripeChargesForOrder($data['data']['payload']['order']);

        if (\count($charges) === 0) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $shop = $this->shopRepository->getShopFromId($data['source']['shopId']);
        $client = $this->clientFactory->createClient($shop);

        foreach ($charges as $transaction => $chargeIds) {
            foreach ($chargeIds as $charge) {
                Refund::create([
                    'charge' => $charge
                ]);
            }

            $this->orderRepository->updateOrderStatus('refunded', $transaction);

            $client->sendRequest(
                new Request(
                    'POST',
                    sprintf('/api/_action/order_transaction/%s/state/refund', $transaction)
                )
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function getStripeChargesForOrder(array $order): array
    {
        $charges = [];
        foreach ($order['transactions'] as $transaction) {
            $order = $this->orderRepository->fetchOrder($transaction['id']);

            if ($order === null) {
                // The transaction was not paid via Stripe
                continue;
            }

            /** @var Charge $charge */
            foreach ($this->sessionService->getChargesForSession($order['session_id']) as $charge) {
                $charges[$transaction['id']][] = $charge->id;
            }
        }

        return $charges;
    }
}