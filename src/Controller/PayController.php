<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class PayController extends AbstractController
{
    private OrderRepository $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/pay/{transaction}", name="payment.pay", methods={"GET"})
     */
    public function userForwarded(string $transaction): Response
    {
        $sessionId = $this->orderRepository->fetchColumn('session_id', $transaction);

        if ($sessionId === null) {
            throw new BadRequestHttpException('Invalid transaction');
        }

        return $this->render('base.html.twig', ['sessionId' => $sessionId, 'publicApiKey' => $_SERVER['STRIPE_PUBLIC_KEY']]);
    }
}