<?php

namespace App\Services;

use Exception;

class TimezoneService
{
    /**
     * Get timezone from coordinates using a simple timezone mapping
     * This is a basic implementation using timezone boundaries
     * 
     * @param float $lat
     * @param float $lng
     * @return string
     */
    public static function getTimezoneFromCoordinates(float $lat, float $lng): string
    {
        // For production, you might want to use a more sophisticated service
        // like Google Time Zone API or a timezone boundary database
        // This is a simplified implementation for common cave regions
        
        try {
            // Europe
            if ($lat >= 35 && $lat <= 71 && $lng >= -10 && $lng <= 40) {
                // UK and Ireland
                if ($lng >= -10 && $lng <= 2 && $lat >= 50 && $lat <= 61) {
                    return 'Europe/London';
                }
                // France, Spain, etc.
                if ($lng >= -5 && $lng <= 10 && $lat >= 42 && $lat <= 51) {
                    return 'Europe/Paris';
                }
                // Central Europe (Germany, Austria, etc.)
                if ($lng >= 5 && $lng <= 20 && $lat >= 45 && $lat <= 55) {
                    return 'Europe/Berlin';
                }
                // Eastern Europe
                if ($lng >= 15 && $lng <= 40 && $lat >= 45 && $lat <= 65) {
                    return 'Europe/Budapest';
                }
                // Italy, Balkans
                if ($lng >= 8 && $lng <= 25 && $lat >= 35 && $lat <= 48) {
                    return 'Europe/Rome';
                }
                // Default to UTC for other European locations
                return 'Europe/London';
            }
            
            // North America
            if ($lat >= 25 && $lat <= 72 && $lng >= -180 && $lng <= -50) {
                // Eastern Time
                if ($lng >= -85 && $lng <= -65) {
                    return 'America/New_York';
                }
                // Central Time
                if ($lng >= -105 && $lng <= -85) {
                    return 'America/Chicago';
                }
                // Mountain Time
                if ($lng >= -115 && $lng <= -105) {
                    return 'America/Denver';
                }
                // Pacific Time
                if ($lng >= -125 && $lng <= -115) {
                    return 'America/Los_Angeles';
                }
                // Default to Eastern for other NA locations
                return 'America/New_York';
            }
            
            // Australia
            if ($lat >= -45 && $lat <= -10 && $lng >= 110 && $lng <= 160) {
                // Eastern Australia
                if ($lng >= 140 && $lng <= 155) {
                    return 'Australia/Sydney';
                }
                // Central Australia
                if ($lng >= 125 && $lng <= 140) {
                    return 'Australia/Adelaide';
                }
                // Western Australia
                if ($lng >= 110 && $lng <= 125) {
                    return 'Australia/Perth';
                }
                return 'Australia/Sydney';
            }
            
            // Asia
            if ($lat >= -10 && $lat <= 80 && $lng >= 60 && $lng <= 180) {
                // Japan
                if ($lng >= 128 && $lng <= 146 && $lat >= 30 && $lat <= 46) {
                    return 'Asia/Tokyo';
                }
                // China
                if ($lng >= 73 && $lng <= 135 && $lat >= 18 && $lat <= 54) {
                    return 'Asia/Shanghai';
                }
                // India
                if ($lng >= 68 && $lng <= 97 && $lat >= 8 && $lat <= 37) {
                    return 'Asia/Kolkata';
                }
                return 'Asia/Shanghai';
            }
            
            // Default to UTC for unknown locations
            return 'UTC';
            
        } catch (Exception $e) {
            // Fall back to UTC if there's any error
            return 'UTC';
        }
    }
}