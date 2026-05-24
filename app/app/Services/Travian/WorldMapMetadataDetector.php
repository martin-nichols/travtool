<?php

namespace App\Services\Travian;

class WorldMapMetadataDetector
{
    private const SMALL_WORLD_TILE_COUNT = 160801;
    private const SMALL_WORLD_SIZE = 401;
    private const LARGE_WORLD_SIZE = 801;

    /**
     * @return array{
     *     has_regions: bool,
     *     map_topology: string,
     *     map_width: ?int,
     *     map_height: ?int,
     *     map_tile_count: ?int,
     *     map_radius: ?int
     * }
     */
    public function detect(int $maxMapTileId, bool $hasRegions): array
    {
        if ($maxMapTileId <= 0) {
            return [
                'has_regions' => $hasRegions,
                'map_topology' => $hasRegions ? 'plane' : 'torus',
                'map_width' => null,
                'map_height' => null,
                'map_tile_count' => null,
                'map_radius' => null,
            ];
        }

        $mapWidth = $maxMapTileId > self::SMALL_WORLD_TILE_COUNT
            ? self::LARGE_WORLD_SIZE
            : self::SMALL_WORLD_SIZE;

        return [
            'has_regions' => $hasRegions,
            'map_topology' => $hasRegions ? 'plane' : 'torus',
            'map_width' => $mapWidth,
            'map_height' => $mapWidth,
            'map_tile_count' => $mapWidth * $mapWidth,
            'map_radius' => (int) (($mapWidth - 1) / 2),
        ];
    }
}
