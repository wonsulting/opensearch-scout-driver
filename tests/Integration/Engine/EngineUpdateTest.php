<?php declare(strict_types=1);

namespace OpenSearch\ScoutDriver\Tests\Integration\Engine;

use OpenSearch\Adapter\Documents\DocumentManager;
use OpenSearch\Adapter\Indices\IndexManager;
use OpenSearch\ScoutDriver\Engine;
use OpenSearch\ScoutDriver\Factories\DocumentFactory;
use OpenSearch\ScoutDriver\Factories\DocumentFactoryInterface;
use OpenSearch\ScoutDriver\Factories\ModelFactory;
use OpenSearch\ScoutDriver\Factories\ModelFactoryInterface;
use OpenSearch\ScoutDriver\Factories\SearchParametersFactory;
use OpenSearch\ScoutDriver\Factories\SearchParametersFactoryInterface;
use OpenSearch\ScoutDriver\Tests\App\Client;
use OpenSearch\ScoutDriver\Tests\Integration\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Engine::class)]
#[UsesClass(DocumentFactory::class)]
#[UsesClass(ModelFactory::class)]
#[UsesClass(SearchParametersFactory::class)]
final class EngineUpdateTest extends TestCase
{
    public function test_empty_model_collection_can_not_be_indexed(): void
    {
        $documentManager = $this->createMock(DocumentManager::class);
        $documentManager->expects($this->never())->method('index');

        $engine = new Engine(
            $documentManager,
            resolve(DocumentFactoryInterface::class),
            resolve(SearchParametersFactoryInterface::class),
            resolve(ModelFactoryInterface::class),
            resolve(IndexManager::class)
        );

        $engine->update((new Client())->newCollection());
    }

    public function test_not_empty_model_collection_can_be_indexed(): void
    {
        $source = Client::factory()->count(rand(2, 10))->create();
        $found = Client::search()->get();

        // assert that the amount of created models corresponds number of found models
        $this->assertSame($source->count(), $found->count());
        // assert that all source models are found
        $this->assertCount(0, $source->pluck('id')->diff($found->pluck('id')));
    }
}
