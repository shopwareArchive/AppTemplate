<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Service\Stripe\SessionService;
use App\Shop\ShopRepository;
use GuzzleHttp\Psr7\Request;
use Shopware\AppBundle\Client\ClientFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ActionController
{
    private OrderRepository $orderRepository;
    private ShopRepository $shopRepository;
    private ClientFactoryInterface $clientFactory;
    private SessionService $sessionService;

    public function __construct(
        OrderRepository $orderRepository,
        ShopRepository $shopRepository,
        ClientFactoryInterface $clientFactory,
        SessionService $sessionService
    ) {
        $this->orderRepository = $orderRepository;
        $this->shopRepository = $shopRepository;
        $this->clientFactory = $clientFactory;
        $this->sessionService = $sessionService;
    }

    /**
     * @Route("/action/capture-payments", name="action.capture-payments", methods={"POST"})
     */
    public function capturePayments(SymfonyRequest $request): Response
    {
        $content = \json_decode($request->getContent(), true);

        $data = $content['data'];

        $shop = $this->shopRepository->getShopFromId($content['source']['shopId']);
        $client = $this->clientFactory->createClient($shop);

        foreach ($data['ids'] as $orderId) {
            foreach ($this->orderRepository->fetchOrdersByOrderId($orderId) as $order) {
                $payment = $this->sessionService->getPaymentIntentFromSession($order['session_id']);
                if (!$payment) {
                    continue;
                }

                $payment->capture();

                $client->sendRequest(
                    new Request(
                        'POST',
                        sprintf('/api/_action/order_transaction/%s/state/paid', $order['transaction_id'])
                    )
                );
            }
        }

        return $this->sign([
            'actionType' => 'reload',
            'payload' => [],
        ], $content['source']['shopId']);

    }

    private function sign(array $content, string $shopId): JsonResponse
    {
        $response = new JsonResponse($content);

        $shop = $this->shopRepository->getShopFromId($shopId);

        $hmac = \hash_hmac('sha256', $response->getContent(), $shop->getShopSecret());
        $response->headers->set('shopware-app-signature', $hmac);

        return $response;
    }
}