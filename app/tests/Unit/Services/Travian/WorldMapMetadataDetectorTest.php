<?php

namespace Tests\Unit\Services\Travian;

use App\Services\Travian\WorldMapMetadataDetector;
use PHPUnit\Framework\TestCase;

class WorldMapMetadataDetectorTest extends TestCase
{
    public function test_it_detects_a_small_torus_world(): void
    {
        $detector = new WorldMapMetadataDetector();

        $result = $detector->detect(160801, false);

        $this->assertSame([
            'has_regions' => false,
            'map_topology' => 'torus',
            'map_width' => 401,
            'map_height' => 401,
            'map_tile_count' => 160801,
            'map_radius' => 200,
        ], $result);
    }

    public function test_it_detects_a_large_torus_world(): void
    {
        $detector = new WorldMapMetadataDetector();

        $result = $detector->detect(160802, false);

        $this->assertSame([
            'has_regions' => false,
            'map_topology' => 'torus',
            'map_width' => 801,
            'map_height' => 801,
            'map_tile_count' => 641601,
            'map_radius' => 400,
        ], $result);
    }

    public function test_it_marks_region_worlds_as_plane(): void
    {
        $detector = new WorldMapMetadataDetector();

        $result = $detector->detect(1, true);

        $this->assertSame('plane', $result['map_topology']);
        $this->assertTrue($result['has_regions']);
        $this->assertSame(401, $result['map_width']);
        $this->assertSame(200, $result['map_radius']);
    }
}
