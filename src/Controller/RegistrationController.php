<?php declare(strict_types=1);

namespace App\Controller;

use Shopware\AppBundle\Registration\RegistrationService;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    public function __construct(
        private RegistrationService $registrationService,
        private HttpMessageFactoryInterface $psrHttpFactory
    ) {
    }

    #[Route('/register', name: 'shopware_app.register', methods: ['GET'])]
    public function register(Request $request): Response
    {
        $proof = $this->registrationService->handleShopRegistrationRequest(
            $this->psrHttpFactory->createRequest($request),
            $this->generateUrl('shopware.app.confirm')
        );

        return new JsonResponse($proof, Response::HTTP_OK);
    }

    #[Route('/confirm', name: 'shopware.app.confirm', methods: ['POST'])]
    public function confirm(Request $request): Response
    {
        $this->registrationService->handleConfirmation(
            $this->psrHttpFactory->createRequest($request)
        );

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}