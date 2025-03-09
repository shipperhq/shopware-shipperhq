<?php declare(strict_types=1);

namespace SHQ\RateProvider\Controller\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[RouteScope(scopes: ['api'])]
class ShqApiRefreshMethodsController extends AbstractController
{
    #[Route(path: '/api/_action/shq-api-refresh-methods/verify', name: 'api.action.shq-api-refresh-methods.verify', methods: ['POST'])]
    public function reload(Context $context): JsonResponse
    {
        try {
            // Add your rate refresh logic here
            // For example, calling your API to fetch latest rates
            
            return new JsonResponse(['success' => true, 'message' => 'Shipping methods refreshed successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}