<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\FeatureInterestEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FeatureTrackerController extends AbstractController
{
    #[Route('/api/track/feature-interest', name: 'api_track_feature_interest', methods: ['POST'])]
    public function trackInterest(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $featureCode = is_array($payload) && !empty($payload['feature']) ? (string) $payload['feature'] : 'apple_id';
        $origin = is_array($payload) && !empty($payload['origin']) ? (string) $payload['origin'] : 'unknown';

        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');

        $event = new FeatureInterestEvent($featureCode, $origin, $ip, $userAgent);
        $entityManager->persist($event);
        $entityManager->flush();

        return new JsonResponse([
            'status' => 'tracked',
            'feature' => $featureCode,
        ], Response::HTTP_OK);
    }
}
