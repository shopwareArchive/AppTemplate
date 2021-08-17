<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Service\Stripe\SessionService;
use App\Shop\ShopRepository;
use GuzzleHttp\Psr7\Request;
use Shopware\AppBundle\Client\ClientFactoryInterface;
use Stripe\Charge;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class RedirectController extends AbstractController
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
     * @Route("/redirect/success/{transaction}", name="payment.redirect.success", methods={"GET"})
     */
    public function redirectSuccess(string $transaction): Response
    {
        $order = $this->updateStatus($transaction);

        $stripeDetails = [];

        /** @var Charge $charge */
        foreach ($this->sessionService->getChargesForSession($order['session_id']) as $charge) {
            $stripeDetails[] = [
                'number' => '****' . $charge->payment_method_details->card->last4,
                'brand' => $charge->payment_method_details->card->brand,
                'expiry' => $charge->payment_method_details->card->exp_month . '/' . $charge->payment_method_details->card->exp_year,

            ];
        }

        if (\count($stripeDetails) === 0) {
            return RedirectResponse::create($order['return_url']);
        }

        $shop = $this->shopRepository->getShopFromId($order['shop_id']);
        $client = $this->clientFactory->createClient($shop);

        $client->sendRequest(
            new Request(
                'PATCH',
                'api/order-transaction/' . $transaction,
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/vnd.api+json',
                ],
                \json_encode([
                    'customFields' => [
                        'AppDaysDemoStripeDetails' => $stripeDetails,
                    ],
                ])
            )
        );

        return new RedirectResponse($order['return_url']);
    }

    /**
     * @Route("/redirect/cancel/{transaction}", name="payment.redirect.cancel", methods={"GET"})
     */
    public function redirectCancel(string $transaction): Response
    {
        $order = $this->updateStatus($transaction, 'cancel');

        return new RedirectResponse($order['return_url']);
    }

    private function updateStatus(string $transactionId, ?string $newStatus = null): array
    {
        $order = $this->orderRepository->fetchOrder($transactionId);

        if ($order === null) {
            throw new BadRequestHttpException('Invalid session');
        }

        if ($newStatus === null) {
            $newStatus = $this->sessionService->getPaymentStatusForSession($order['session_id']);
        }

        $this->orderRepository->updateOrderStatus($newStatus, $transactionId);

        return $order;
    }
}