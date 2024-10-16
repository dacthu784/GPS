<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DistanceController extends Controller
{
    public function calculateDistance(Request $request)
    {


        $request->validate([
            'start_lat' => 'required|numeric',
            'start_lon' => 'required|numeric',
            'end_lat' => 'required|numeric',
            'end_lon' => 'required|numeric',
            'mode' => 'required|in:air,road',
        ]);

        try {
            // ... existing validation and calculations ...
            $start_lat = $request->start_lat;
            $start_lon = $request->start_lon;
            $end_lat = $request->end_lat;
            $end_lon = $request->end_lon;

            $airDistance = $this->haversineDistance($start_lat, $start_lon, $end_lat, $end_lon);
            $midpoint = $this->calculateMidpoint($start_lat, $start_lon, $end_lat, $end_lon);
            $bearing = $this->calculateBearing($start_lat, $start_lon, $end_lat, $end_lon);

            $result = [
                'air_distance' => round($airDistance, 2),
                'unit' => 'km',
                'midpoint' => $midpoint,
                'bearing' => $bearing,
                'start_point' => [
                    'lat' => $start_lat,
                    'lon' => $start_lon
                ],
                'end_point' => [
                    'lat' => $end_lat,
                    'lon' => $end_lon
                ]
            ];

            if ($request->mode === 'road') {
                $roadData = $this->calculateRoadDistance($start_lon, $start_lat, $end_lon, $end_lat);
                $result['road_distance'] = $roadData['distance'];
                $result['route_geometry'] = $roadData['geometry'];
            }

            return response()->json($result);


        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }


    }

    private function calculateRoadDistance($startLon, $startLat, $endLon, $endLat)
    {
        $url = "http://router.project-osrm.org/route/v1/driving/$startLon,$startLat;$endLon,$endLat?overview=full";
        $response = Http::get($url);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['routes'][0]['distance']) && isset($data['routes'][0]['geometry'])) {
                return [
                    'distance' => round($data['routes'][0]['distance'] / 1000, 2),
                    'geometry' => $data['routes'][0]['geometry']
                ];
            }
        }

        throw new \Exception('Unable to calculate road distance');
    }

    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function calculateMidpoint($lat1, $lon1, $lat2, $lon2)
    {
        $dLon = deg2rad($lon2 - $lon1);

        $Bx = cos(deg2rad($lat2)) * cos($dLon);
        $By = cos(deg2rad($lat2)) * sin($dLon);

        $midLat = rad2deg(atan2(
            sin(deg2rad($lat1)) + sin(deg2rad($lat2)),
            sqrt(
                (cos(deg2rad($lat1)) + $Bx) * (cos(deg2rad($lat1)) + $Bx) + $By * $By
            )
        ));

        $midLon = $lon1 + rad2deg(atan2($By, cos(deg2rad($lat1)) + $Bx));

        return [
            'lat' => round($midLat, 6),
            'lon' => round($midLon, 6)
        ];
    }

    private function calculateBearing($lat1, $lon1, $lat2, $lon2)
    {
        $dLon = deg2rad($lon2 - $lon1);

        $y = sin($dLon) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2)) -
            sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos($dLon);

        $bearing = atan2($y, $x);
        $bearingDegrees = round(rad2deg($bearing));

        // Normalize to 0-360
        return ($bearingDegrees + 360) % 360;
    }
}
