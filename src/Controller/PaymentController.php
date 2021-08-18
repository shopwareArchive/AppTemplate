<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Services\Stripe\SessionService;
use App\Shop\ShopRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class PaymentController extends AbstractController
{
    private ShopRepository $shopRepository;
    private OrderRepository $orderRepository;
    private SessionService $sessionService;

    public function __construct(ShopRepository $shopRepository, OrderRepository $orderRepository, SessionService $sessionService)
    {
        $this->shopRepository = $shopRepository;
        $this->orderRepository = $orderRepository;
        $this->sessionService = $sessionService;
    }

    /**
     * @Route("/payment/process", name="payment.process", methods={"POST"})
     */
    public function paymentStarted(Request $request): JsonResponse
    {
        $content = \json_decode($request->getContent(), true);

        $session = $this->sessionService->startSession(
            $content['order'],
            $content['orderTransaction'],
            $this->generateUrl(
                'payment.redirect.success',
                [
                    'transaction' => $content['orderTransaction']['id'],
                ],
                RouterInterface::ABSOLUTE_URL
            ),
            $this->generateUrl(
                'payment.redirect.cancel',
                [
                    'transaction' => $content['orderTransaction']['id'],
                ],
                RouterInterface::ABSOLUTE_URL
            )
        );

        $this->orderRepository->insertNewOrder([
            'transaction_id' => $content['orderTransaction']['id'],
            'order_id' => $content['order']['id'],
            'shop_id' => $content['source']['shopId'],
            'session_id' => $session->id,
            'return_url' => $content['returnUrl'],
        ]);

        return $this->sign(
            [
                'redirectUrl' => $this->generateUrl(
                    'payment.pay',
                    [
                        'transaction' => $content['orderTransaction']['id'],
                    ],
                    RouterInterface::ABSOLUTE_URL
                ),
            ],
            $content['source']['shopId']
        );
    }

    /**
     * @Route("/payment/finalize", name="payment.finalize", methods={"POST"})
     */
    public function paymentFinalized(Request $request): Response
    {
        $content = \json_decode($request->getContent(), true);

        $status = $this->orderRepository->fetchColumn('status', $content['orderTransaction']['id']);

        return $this->sign(
            ['status' => $status ?? 'fail'],
            $content['source']['shopId']
        );
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