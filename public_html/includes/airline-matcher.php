<?php
/**
 * airline-matcher.php — score each active airline against a charter request
 * and return the top-N candidates ranked by score.
 *
 * Score components:
 *   +30  service_type ∈ airline.service_types
 *   +30  origin or destination text matches airline.regions_served OR base_country
 *   +20  capacity fits (passengers ≤ pax_max OR weight ≤ kg_max)
 *   +10  rating >= 4
 *   +5   not new (is_new = 0)
 *
 * Filters out active = 0.
 *
 * Returns array of rows decorated with 'score' and 'why' (short reason list).
 */

function match_airlines_for_request(array $req, int $limit = 10): array {
    $stmt = db()->query(
        "SELECT id, name, iata_code, icao_code, base_country, contact_email,
                fleet_types, regions_served, service_types,
                capacity_pax_max, capacity_kg_max, rating, active, is_new
         FROM airlines
         WHERE active = 1 AND contact_email IS NOT NULL AND contact_email <> ''"
    );
    $airlines = $stmt->fetchAll();

    $service     = (string)($req['service_type'] ?? '');
    $origin      = (string)($req['origin'] ?? '');
    $destination = (string)($req['destination'] ?? '');
    $passengers  = isset($req['passengers']) ? (int)$req['passengers'] : 0;
    $weightKg    = isset($req['approx_weight_kg']) ? (int)$req['approx_weight_kg'] : 0;

    $routeText = strtolower($origin . ' ' . $destination);

    $scored = [];
    foreach ($airlines as $a) {
        $score = 0;
        $why   = [];

        $services = $a['service_types'] ? json_decode($a['service_types'], true) : [];
        if (is_array($services) && in_array($service, $services, true)) {
            $score += 30;
            $why[] = 'service match';
        }

        // Region match: any region code OR full base_country name appears in route text
        $regionHit = false;
        $regions = $a['regions_served'] ? json_decode($a['regions_served'], true) : [];
        if (is_array($regions)) {
            foreach ($regions as $r) {
                $rL = strtolower(trim((string)$r));
                if ($rL !== '' && strlen($rL) >= 2 && str_contains($routeText, $rL)) {
                    $regionHit = true;
                    break;
                }
            }
        }
        if (!$regionHit && $a['base_country']) {
            $bc = strtolower((string)$a['base_country']);
            if ($bc !== '' && str_contains($routeText, $bc)) {
                $regionHit = true;
            }
        }
        if ($regionHit) {
            $score += 30;
            $why[] = 'route region';
        }

        // Capacity fit
        $capHit = false;
        if ($passengers > 0 && $a['capacity_pax_max'] && $passengers <= (int)$a['capacity_pax_max']) {
            $capHit = true;
        }
        if (!$capHit && $weightKg > 0 && $a['capacity_kg_max'] && $weightKg <= (int)$a['capacity_kg_max']) {
            $capHit = true;
        }
        if ($capHit) {
            $score += 20;
            $why[] = 'capacity fit';
        }

        if ((int)$a['rating'] >= 4) {
            $score += 10;
            $why[] = 'high rating';
        }
        if ((int)$a['is_new'] === 0) {
            $score += 5;
        }

        $a['score'] = $score;
        $a['why']   = $why;
        $scored[] = $a;
    }

    usort($scored, fn($x, $y) => $y['score'] <=> $x['score']
                              ?: strcmp((string)$x['name'], (string)$y['name']));

    return array_slice($scored, 0, $limit);
}
