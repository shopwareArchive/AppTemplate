<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Services\Stripe\SessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class RedirectController extends AbstractController
{
    private OrderRepository $orderRepository;
    private SessionService $sessionService;

    public function __construct(OrderRepository $orderRepository, SessionService $sessionService)
    {
        $this->orderRepository = $orderRepository;
        $this->sessionService = $sessionService;
    }

    /**
     * @Route("/redirect/success/{transaction}", name="payment.redirect.success", methods={"GET"})
     */
    public function redirectSuccess(string $transaction): Response
    {
        $order = $this->updateStatus($transaction);

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