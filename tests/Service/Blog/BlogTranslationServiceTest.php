<?php

declare(strict_types=1);

namespace App\Tests\Service\Blog;

use App\Entity\BlogTranslation;
use App\Repository\BlogTranslationRepository;
use App\Service\Blog\BlogTranslationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class BlogTranslationServiceTest extends TestCase
{
    public function testReturnsOriginalTextForCatalanWithoutCallingCacheOrAi(): void
    {
        $cacheRepo = $this->createMock(BlogTranslationRepository::class);
        $cacheRepo->expects($this->never())->method('findByHash');

        $em = $this->createMock(EntityManagerInterface::class);
        $httpClient = new MockHttpClient(function () {
            $this->fail('No s\'hauria de cridar la IA per a català.');
        });

        $service = new BlogTranslationService($cacheRepo, $em, $httpClient, new NullLogger());
        $result = $service->translate('Text original', 'cat');

        $this->assertSame('Text original', $result['text']);
        $this->assertFalse($result['from_cache']);
        $this->assertFalse($result['fallback']);
    }

    public function testReturnsCachedTranslationOnSha256Hit(): void
    {
        $cached = new BlogTranslation('hash', 'es', 'Hola món', 'Hola mundo', 'Qwen');

        $cacheRepo = $this->createMock(BlogTranslationRepository::class);
        $cacheRepo->expects($this->once())
            ->method('findByHash')
            ->with(BlogTranslationRepository::generateHash('Hola món', 'es'))
            ->willReturn($cached);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $httpClient = new MockHttpClient(function () {
            $this->fail('No s\'hauria de cridar la IA quan hi ha hit de memòria cau.');
        });

        $service = new BlogTranslationService($cacheRepo, $em, $httpClient, new NullLogger());
        $result = $service->translate('Hola món', 'es');

        $this->assertSame('Hola mundo', $result['text']);
        $this->assertTrue($result['from_cache']);
        $this->assertFalse($result['fallback']);
        $this->assertSame(2, $cached->getHits());
    }

    public function testCallsLocalAiAndPersistsOnCacheMiss(): void
    {
        $source = 'Custòdia digital segura';
        $expectedHash = BlogTranslationRepository::generateHash($source, 'en');

        $cacheRepo = $this->createMock(BlogTranslationRepository::class);
        $cacheRepo->expects($this->once())
            ->method('findByHash')
            ->with($expectedHash)
            ->willReturn(null);
        $cacheRepo->expects($this->once())
            ->method('saveTranslation')
            ->with($expectedHash, 'en', $source, 'Secure digital custody', 'Qwen/Qwen2.5-VL-7B-Instruct');

        $em = $this->createMock(EntityManagerInterface::class);

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'choices' => [
                    ['message' => ['content' => 'Secure digital custody']],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $service = new BlogTranslationService(
            $cacheRepo,
            $em,
            $httpClient,
            new NullLogger(),
            'http://127.0.0.1:30000/v1',
            'Qwen/Qwen2.5-VL-7B-Instruct'
        );

        $result = $service->translate($source, 'en');

        $this->assertSame('Secure digital custody', $result['text']);
        $this->assertFalse($result['from_cache']);
        $this->assertFalse($result['fallback']);
    }

    public function testFallsBackGracefullyWhenAiServiceIsUnavailable(): void
    {
        $cacheRepo = $this->createMock(BlogTranslationRepository::class);
        $cacheRepo->method('findByHash')->willReturn(null);
        $cacheRepo->expects($this->never())->method('saveTranslation');

        $em = $this->createMock(EntityManagerInterface::class);

        $httpClient = new MockHttpClient([
            new MockResponse('Service Unavailable', ['http_code' => 503]),
        ]);

        $service = new BlogTranslationService($cacheRepo, $em, $httpClient, new NullLogger());
        $result = $service->translate('Text sense servei', 'es');

        $this->assertSame('Text sense servei', $result['text']);
        $this->assertFalse($result['from_cache']);
        $this->assertTrue($result['fallback']);
    }
}

