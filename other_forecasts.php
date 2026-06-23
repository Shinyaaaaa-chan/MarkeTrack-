<?php

/**
 * Calculates Simple Moving Average (SMA) forecast.
 *
 * @param array $data Historical data array
 * @param int $period The number of periods to average (e.g., 12 for 12 months)
 * @param int $forecastPeriods Number of future periods to forecast
 * @return array ['forecast' => [f1, f2, ...]]
 */
function simpleMovingAverage(array $data, int $period, int $forecastPeriods): array
{
    $forecast = [];
    $history = $data;
    
    if (count($history) < $period) {
        // Not enough data, return array of zeros
        return ['forecast' => array_fill(0, $forecastPeriods, 0)];
    }

    for ($i = 0; $i < $forecastPeriods; $i++) {
        // Get the last 'period' number of items from history
        $slice = array_slice($history, -$period);
        
        // Calculate the average
        $nextForecast = count($slice) > 0 ? array_sum($slice) / count($slice) : 0;
        
        $forecast[] = $nextForecast;
        
        // Add the new forecast to history for the next iteration (rolling forecast)
        $history[] = $nextForecast;
    }
    
    return ['forecast' => $forecast];
}


/**
 * Calculates forecast using Simple Linear Regression (y = mx + b).
 *
 * @param array $data Historical data array
 * @param int $forecastPeriods Number of future periods to forecast
 * @return array ['forecast' => [f1, f2, ...]]
 */
function linearRegressionForecast(array $data, int $forecastPeriods): array
{
    $n = count($data);

    // Cannot calculate with less than 2 data points
    if ($n < 2) {
        return ['forecast' => array_fill(0, $forecastPeriods, 0)];
    }

    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $sumX2 = 0;

    // Calculate sums
    for ($i = 0; $i < $n; $i++) {
        $x = $i + 1; // Time period (1, 2, 3, ...)
        $y = $data[$i]; // Sales value
        
        $sumX += $x;
        $sumY += $y;
        $sumXY += ($x * $y);
        $sumX2 += ($x * $x);
    }

    // Calculate slope (m) and intercept (b)
    $denominator = ($n * $sumX2) - ($sumX * $sumX);

    // Avoid division by zero
    if ($denominator == 0) {
        // Data is likely vertical, return average or 0
        return ['forecast' => array_fill(0, $forecastPeriods, $sumY / $n)];
    }

    $m = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
    $b = ($sumY - $m * $sumX) / $n;

    // Generate forecasts
    $forecast = [];
    for ($i = 1; $i <= $forecastPeriods; $i++) {
        $futureX = $n + $i; // The next time periods
        $futureY = ($m * $futureX) + $b;
        $forecast[] = $futureY;
    }

    return ['forecast' => $forecast];
}


/**
 * Calculates Mean Absolute Error (MAE).
 *
 * @param array $actuals The actual historical values.
 * @param array $forecasts The forecasted values for the same period.
 * @return float The MAE value.
 */
function calculateMAE(array $actuals, array $forecasts): float
{
    $errorSum = 0;
    $n = min(count($actuals), count($forecasts)); // Ensure arrays are same size
    
    if ($n == 0) return 0;

    for ($i = 0; $i < $n; $i++) {
        $errorSum += abs($actuals[$i] - $forecasts[$i]);
    }
    
    return $errorSum / $n;
}

/**
 * Calculates Root Mean Squared Error (RMSE).
 *
 * @param array $actuals The actual historical values.
 * @param array $forecasts The forecasted values for the same period.
 * @return float The RMSE value.
 */
function calculateRMSE(array $actuals, array $forecasts): float
{
    $errorSumSq = 0;
    $n = min(count($actuals), count($forecasts)); // Ensure arrays are same size
    
    if ($n == 0) return 0;

    for ($i = 0; $i < $n; $i++) {
        $errorSumSq += pow($actuals[$i] - $forecasts[$i], 2);
    }
    
    return sqrt($errorSumSq / $n);
}

// (Nagdagdag din ako ng function para sa DES, para kung kailanganin mo)
function doubleExponentialSmoothing(array $data, float $alpha, float $beta, int $forecastPeriods): array
{
    if (count($data) < 2) {
         return ['forecast' => array_fill(0, $forecastPeriods, 0)];
    }

    $level = $data[0];
    $trend = $data[1] - $data[0]; 
    
    $n = count($data);

    for ($i = 1; $i < $n; $i++) {
        $lastLevel = $level;
        $level = $alpha * $data[$i] + (1 - $alpha) * ($level + $trend);
        $trend = $beta * ($level - $lastLevel) + (1 - $beta) * $trend;
    }

    $forecast = [];
    for ($i = 1; $i <= $forecastPeriods; $i++) {
        $forecast[] = $level + $i * $trend;
    }

    return ['forecast' => $forecast];
}

?>