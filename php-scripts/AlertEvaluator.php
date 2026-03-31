<?php
declare(strict_types=1);

class AlertEvaluator
{
    private float $oilTempThreshold;
    private float $coolantTempThreshold;

    public function __construct(float $oilTempThreshold = 85.0, float $coolantTempThreshold = 90.0)
    {
        $this->oilTempThreshold = $oilTempThreshold;
        $this->coolantTempThreshold = $coolantTempThreshold;
    }

    /**
     * Evaluate a single report row and return an array of alert strings.
     *
     * @param array<string, string> $row Associative array with Google Sheets column headers as keys.
     * @return string[] Array of alert strings, empty if no alerts.
     */
    public function evaluate(array $row): array
    {
        $alerts = [];

        // Port Engine checks
        $portOilLevel = $row['Port Oil Level'] ?? '';
        if ($portOilLevel !== '' && $portOilLevel !== 'OK') {
            $alerts[] = "Port Oil {$portOilLevel}";
        }

        $portOilTemp = (float) ($row['Port Oil Temp C'] ?? '0');
        $portOilTempStatus = $row['Port Oil Temp Status'] ?? '';
        if ($portOilTemp >= $this->oilTempThreshold || $portOilTempStatus === 'High') {
            $alerts[] = "Port Oil Temp HIGH ({$portOilTemp}°C)";
        }

        $portCoolantLevel = $row['Port Coolant Level'] ?? '';
        if ($portCoolantLevel !== '' && $portCoolantLevel !== 'OK') {
            $alerts[] = "Port Coolant {$portCoolantLevel}";
        }

        $portCoolantTemp = (float) ($row['Port Coolant Temp C'] ?? '0');
        if ($portCoolantTemp >= $this->coolantTempThreshold) {
            $alerts[] = "Port Coolant Temp {$portCoolantTemp}°C";
        }

        $portSmoke = $row['Port Smoke'] ?? '';
        if ($portSmoke !== '' && $portSmoke !== 'No Smoke') {
            $alerts[] = "Port Smoke: {$portSmoke}";
        }

        if (($row['Port LO Leak'] ?? '') === 'Yes') {
            $alerts[] = 'Port LO Leak';
        }
        if (($row['Port FO Leak'] ?? '') === 'Yes') {
            $alerts[] = 'Port FO Leak';
        }
        if (($row['Port Gearbox Oil'] ?? '') === 'LOW') {
            $alerts[] = 'Port Gearbox Oil LOW';
        }

        // Starboard Engine checks
        $stbdOilLevel = $row['Stbd Oil Level'] ?? '';
        if ($stbdOilLevel !== '' && $stbdOilLevel !== 'OK') {
            $alerts[] = "Stbd Oil {$stbdOilLevel}";
        }

        $stbdOilTemp = (float) ($row['Stbd Oil Temp C'] ?? '0');
        $stbdOilTempStatus = $row['Stbd Oil Temp Status'] ?? '';
        if ($stbdOilTemp >= $this->oilTempThreshold || $stbdOilTempStatus === 'High') {
            $alerts[] = "Stbd Oil Temp HIGH ({$stbdOilTemp}°C)";
        }

        $stbdCoolantLevel = $row['Stbd Coolant Level'] ?? '';
        if ($stbdCoolantLevel !== '' && $stbdCoolantLevel !== 'OK') {
            $alerts[] = "Stbd Coolant {$stbdCoolantLevel}";
        }

        $stbdCoolantTemp = (float) ($row['Stbd Coolant Temp C'] ?? '0');
        if ($stbdCoolantTemp >= $this->coolantTempThreshold) {
            $alerts[] = "Stbd Coolant Temp {$stbdCoolantTemp}°C";
        }

        $stbdSmoke = $row['Stbd Smoke'] ?? '';
        if ($stbdSmoke !== '' && $stbdSmoke !== 'No Smoke') {
            $alerts[] = "Stbd Smoke: {$stbdSmoke}";
        }

        if (($row['Stbd LO Leak'] ?? '') === 'Yes') {
            $alerts[] = 'Stbd LO Leak';
        }
        if (($row['Stbd FO Leak'] ?? '') === 'Yes') {
            $alerts[] = 'Stbd FO Leak';
        }
        if (($row['Stbd Gearbox Oil'] ?? '') === 'LOW') {
            $alerts[] = 'Stbd Gearbox Oil LOW';
        }

        // Bilge checks
        if (($row['Bilge Status'] ?? '') === 'Water') {
            $alerts[] = 'Bilge WATER';
        }
        if (($row['Bilge Pump'] ?? '') === 'Not Working') {
            $alerts[] = 'Bilge Pump DOWN';
        }

        return $alerts;
    }
}
