<?php
// holt_winters_forecast.php - KUMPLETONG LOGIC NA ITO

function holtWintersForecast($series, $season, $forecast_length, $alpha=0.2, $beta=0.1, $gamma=0.1) {
    $n = count($series);
    if ($n < $season) {
        // Hindi sapat ang data para sa seasonality, ibalik ang zero
        return ['forecast' => array_fill(0, $forecast_length, 0)];
    }

    // 1. Initialization
    $L = array(); // Level
    $T = array(); // Trend
    $S = array(); // Seasonal

    // Initial Level (L_0) - Average of first season
    $L[0] = array_sum(array_slice($series, 0, $season)) / $season;

    // Initial Trend (T_0) - Average difference over first season
    $T[0] = 0;
    for ($i = 0; $i < $season; $i++) {
        $T[0] += ($series[$season + $i] - $series[$i]);
    }
    $T[0] /= ($season * $season);

    // Initial Seasonal (S_i) - Ratio of actual sales to initial level
    for ($i = 0; $i < $season; $i++) {
        $S[$i] = $series[$i] / $L[0];
    }

    // 2. Smoothing and Calculations
    for ($i = 1; $i <= $n; $i++) {
        $t = $i - 1;
        $m = ($t) % $season; // Current season index

        $L[$i] = $alpha * ($series[$t] / $S[$m]) + (1 - $alpha) * ($L[$t] + $T[$t]);
        $T[$i] = $beta * ($L[$i] - $L[$t]) + (1 - $beta) * $T[$t];
        $S[$m] = $gamma * ($series[$t] / $L[$i]) + (1 - $gamma) * $S[$m];
    }

    // 3. Forecast
    $forecast = array();
    for ($i = 1; $i <= $forecast_length; $i++) {
        $m = ($n + $i - 1) % $season;
        $forecast[$i-1] = ($L[$n] + $i * $T[$n]) * $S[$m];
    }

    return [
        'forecast' => $forecast, 
        'level' => end($L), 
        'trend' => end($T), 
        'season' => $S
    ];
}


// flattenSalesData (Ito ang function na nag-aayos ng data format)
function flattenSalesData($productSales) {
    $flattened = [];
      // ✅ Add this to see raw input:
      error_log("DEBUG: Raw productSales");
      error_log(print_r($productSales, true));
    if (empty($productSales)) {
        return $flattened;
    }
    
    ksort($productSales);
    $startYear = key($productSales);
    $currentYear = intval(date('Y'));
    $currentMonth = intval(date('n'));
    
    for ($y = $startYear; $y <= $currentYear; $y++) {
        $months = $productSales[$y] ?? [];
        ksort($months);

        for ($m=1; $m<=12; $m++) {
            if ($y == $currentYear && $m > $currentMonth) {
                break 2;
            }
            $flattened[] = $months[$m] ?? 0;
        }
    }
    return $flattened;
}

?>