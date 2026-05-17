<?php

namespace Tests\Unit\Services\Travian;

use App\Services\Travian\MapSqlLineParser;
use PHPUnit\Framework\TestCase;

class MapSqlLineParserTest extends TestCase
{
    public function test_it_parses_a_standard_map_sql_line(): void
    {
        $parser = new MapSqlLineParser();

        $parsed = $parser->parse('INSERT INTO `x_world` VALUES (22028,173,146,5,31912,\'Natars 173|146\',1,\'Natars\',0,"",498,NULL,FALSE,NULL,NULL,NULL);');

        $this->assertSame([
            'map_tile_id' => 22028,
            'x' => 173,
            'y' => 146,
            'tribe_id' => 5,
            'external_village_id' => 31912,
            'village_name_raw' => 'Natars 173|146',
            'external_player_id' => 1,
            'player_name_raw' => 'Natars',
            'external_alliance_id' => 0,
            'alliance_tag_raw' => '',
            'population' => 498,
            'region_name_raw' => null,
            'is_capital' => false,
            'is_city' => null,
            'has_harbor' => null,
            'victory_points' => null,
        ], $parsed);
    }

    public function test_it_parses_html_entities_and_boolean_flags(): void
    {
        $parser = new MapSqlLineParser();

        $parsed = $parser->parse('INSERT INTO `x_world` VALUES (12,-44,9,3,998,\'L&#39;Etoile\',457,\'J&#34;ul\',77,\'TAG\',1234,\'Caledonia\',TRUE,FALSE,TRUE,19);');

        $this->assertSame('L&#39;Etoile', $parsed['village_name_raw']);
        $this->assertSame('J&#34;ul', $parsed['player_name_raw']);
        $this->assertSame('Caledonia', $parsed['region_name_raw']);
        $this->assertTrue($parsed['is_capital']);
        $this->assertFalse($parsed['is_city']);
        $this->assertTrue($parsed['has_harbor']);
        $this->assertSame(19, $parsed['victory_points']);
    }
}
