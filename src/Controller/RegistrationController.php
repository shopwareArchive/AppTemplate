<?php declare(strict_types=1);

namespace App\Controller;

use Shopware\AppBundle\Attribute\ConfirmationRoute;
use Shopware\AppBundle\Attribute\RegistrationRoute;
use Shopware\AppBundle\Registration\RegistrationService;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private RegistrationService $registrationService,
        private HttpMessageFactoryInterface $psrHttpFactory
    ) {
    }

    #[RegistrationRoute(name: 'shopware_app.register', path: '/register')]
    public function register(Request $request): JsonResponse
    {
        $proof = $this->registrationService->handleShopRegistrationRequest(
            $this->psrHttpFactory->createRequest($request),
            $this->generateUrl('shopware_app.confirm', [], RouterInterface::ABSOLUTE_URL)
        );

        return new JsonResponse($proof, Response::HTTP_OK);
    }

    #[ConfirmationRoute(name: 'shopware_app.confirm', path: '/confirm')]
    public function confirm(Request $request): JsonResponse
    {
        $this->registrationService->handleConfirmation(
            $this->psrHttpFactory->createRequest($request)
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

}