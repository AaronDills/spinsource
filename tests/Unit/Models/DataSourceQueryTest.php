<?php

namespace Tests\Unit\Models;

use App\Models\DataSourceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataSourceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_source_query_factory_creates_valid_model(): void
    {
        $query = DataSourceQuery::factory()->create();

        $this->assertNotNull($query->id);
        $this->assertNotEmpty($query->name);
        $this->assertNotEmpty($query->data_source);
        $this->assertNotEmpty($query->query);
        $this->assertTrue($query->is_active);
    }

    public function test_for_music_brainz_factory_state(): void
    {
        $query = DataSourceQuery::factory()->forMusicBrainz()->create();

        $this->assertEquals('musicbrainz', $query->data_source);
        $this->assertEquals('api', $query->query_type);
    }

    public function test_inactive_factory_state(): void
    {
        $query = DataSourceQuery::factory()->inactive()->create();

        $this->assertFalse($query->is_active);
    }

    public function test_with_variables_factory_state(): void
    {
        $variables = ['limit' => 100, 'offset' => 0];
        $query = DataSourceQuery::factory()->withVariables($variables)->create();

        $this->assertEquals($variables, $query->variables);
    }

    public function test_named_factory_state(): void
    {
        $query = DataSourceQuery::factory()->named('test-query')->create();

        $this->assertEquals('test-query', $query->name);
    }

    public function test_get_method_returns_query(): void
    {
        DataSourceQuery::factory()->create([
            'name' => 'test-query',
            'data_source' => 'wikidata',
            'query' => 'SELECT * FROM test',
            'is_active' => true,
        ]);

        $result = DataSourceQuery::get('test-query', 'wikidata');

        $this->assertEquals('SELECT * FROM test', $result);
    }

    public function test_get_method_replaces_variables(): void
    {
        DataSourceQuery::factory()->create([
            'name' => 'test-query',
            'data_source' => 'wikidata',
            'query' => 'SELECT * FROM test LIMIT {{limit}} OFFSET {{offset}}',
            'is_active' => true,
        ]);

        $result = DataSourceQuery::get('test-query', 'wikidata', ['limit' => 10, 'offset' => 5]);

        $this->assertEquals('SELECT * FROM test LIMIT 10 OFFSET 5', $result);
    }

    public function test_get_method_throws_for_missing_query(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query not found');

        DataSourceQuery::get('nonexistent', 'wikidata');
    }

    public function test_get_method_throws_for_inactive_query(): void
    {
        DataSourceQuery::factory()->create([
            'name' => 'inactive-query',
            'data_source' => 'wikidata',
            'is_active' => false,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query is inactive');

        DataSourceQuery::get('inactive-query', 'wikidata');
    }

    public function test_variables_cast(): void
    {
        $query = DataSourceQuery::factory()->create([
            'variables' => ['key' => 'value'],
        ]);

        $this->assertIsArray($query->variables);
        $this->assertEquals('value', $query->variables['key']);
    }
}
