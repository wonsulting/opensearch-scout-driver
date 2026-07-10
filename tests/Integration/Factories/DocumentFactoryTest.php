<?php declare(strict_types=1);

namespace OpenSearch\ScoutDriver\Tests\Integration\Factories;

use OpenSearch\ScoutDriver\Engine;
use OpenSearch\ScoutDriver\Factories\DocumentFactory;
use OpenSearch\ScoutDriver\Tests\App\Client;
use OpenSearch\ScoutDriver\Tests\Integration\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use UnexpectedValueException;

#[CoversClass(DocumentFactory::class)]
#[UsesClass(Engine::class)]
final class DocumentFactoryTest extends TestCase
{
    private DocumentFactory $documentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentFactory = new DocumentFactory();
    }

    public function test_document_collection_can_be_made_from_model_collection(): void
    {
        $clients = Client::factory()->count(rand(2, 10))->create();
        $documents = $this->documentFactory->makeFromModels($clients);

        for ($i = 0; $i < $clients->count(); $i++) {
            $model = $clients->get($i);
            $document = $documents->get($i);

            $this->assertSame((string)$model->getScoutKey(), $document->id());
            $this->assertSame($model->toSearchableArray(), $document->content());
        }
    }

    public function test_an_exception_is_thrown_when_document_content_has_restricted_fields(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->expectExceptionMessage(
            '_id is not allowed in the document content. Please, make sure the field is not returned ' .
            'by the Client::toSearchableArray or Client::scoutMetadata methods.'
        );

        $clients = Client::factory()->count(rand(2, 10))->create();

        // add restricted _id field in the scout metadata
        $clients->each(static function (Client $client) {
            $client->withScoutMetadata('_id', random_int(0, 1000));
        });

        $this->documentFactory->makeFromModels($clients);
    }
}
