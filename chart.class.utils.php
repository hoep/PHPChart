<?php
/**
 * ChartUtils - Hilfsfunktionen für das PHPChart-System
 * 
 * Diese Klasse stellt verschiedene Utility-Funktionen bereit, die 
 * in den verschiedenen Komponenten des Diagramm-Systems verwendet werden.
 * 
 * @version 1.2
 */
class ChartUtils {
    /**
     * Findet den Minimalwert in einem oder mehreren Arrays
     * 
     * @param array $arrays Ein oder mehrere Arrays mit Werten
     * @param bool $ignoreNull Nullwerte ignorieren
     * @return float|int Minimalwert
     */
    public function findMin($arrays, $ignoreNull = true) {
        $min = null;
        
        foreach ($arrays as $array) {
            foreach ($array as $value) {
                // Ignoriere null oder leere Werte, wenn $ignoreNull true ist
                if (($ignoreNull && ($value === null || $value === '')) || !is_numeric($value)) {
                    continue;
                }
                
                // Setze min auf den ersten gültigen Wert oder vergleiche
                if ($min === null || $value < $min) {
                    $min = $value;
                }
            }
        }
        
        return $min;
    }
    
    /**
     * Findet den Maximalwert in einem oder mehreren Arrays
     * 
     * @param array $arrays Ein oder mehrere Arrays mit Werten
     * @param bool $ignoreNull Nullwerte ignorieren
     * @return float|int Maximalwert
     */
    public function findMax($arrays, $ignoreNull = true) {
        $max = null;
        
        foreach ($arrays as $array) {
            foreach ($array as $value) {
                // Ignoriere null oder leere Werte, wenn $ignoreNull true ist
                if (($ignoreNull && ($value === null || $value === '')) || !is_numeric($value)) {
                    continue;
                }
                
                // Setze max auf den ersten gültigen Wert oder vergleiche
                if ($max === null || $value > $max) {
                    $max = $value;
                }
            }
        }
        
        return $max;
    }
    
    /**
     * Berechnet "schöne" Skala-Grenzen und Ticks für eine Achse
     * 
     * @param float $min Minimalwert
     * @param float $max Maximalwert
     * @param int $tickCount Gewünschte Anzahl von Ticks
     * @param bool $includeZero Null in die Skala einbeziehen
     * @return array Array mit min, max und tickInterval
     */
    public function calculateNiceScale($min, $max, $tickCount = 5, $includeZero = true) {
        // Sicherstellen, dass min und max unterschiedlich sind
        if ($min === $max) {
            $min -= 1;
            $max += 1;
        }
        
        // Null in die Skala einbeziehen, wenn gewünscht
        if ($includeZero && $min > 0) {
            $min = 0;
        } else if ($includeZero && $max < 0) {
            $max = 0;
        }
        
        // Berechne den Bereich
        $range = $max - $min;
        
        // Berechne das ungefähre Tick-Intervall
        $roughInterval = $range / ($tickCount - 1);
        
        // Berechne die Größenordnung des Intervalls
        $magnitude = pow(10, floor(log10($roughInterval)));
        
        // Normalisiere das Intervall in den Bereich [1, 10)
        $normalizedInterval = $roughInterval / $magnitude;
        
        // Wähle ein "schönes" Intervall
        if ($normalizedInterval < 1.5) {
            $tickInterval = 1 * $magnitude;
        } else if ($normalizedInterval < 3) {
            $tickInterval = 2 * $magnitude;
        } else if ($normalizedInterval < 7) {
            $tickInterval = 5 * $magnitude;
        } else {
            $tickInterval = 10 * $magnitude;
        }
        
        // Berechne "schöne" min und max Werte
        $niceMin = floor($min / $tickInterval) * $tickInterval;
        $niceMax = ceil($max / $tickInterval) * $tickInterval;
        
        return [
            'min' => $niceMin,
            'max' => $niceMax,
            'tickInterval' => $tickInterval,
            'tickCount' => ceil(($niceMax - $niceMin) / $tickInterval) + 1
        ];
    }
    
    /**
     * Formatiert einen numerischen Wert für die Anzeige
     * 
     * @param mixed $value Der zu formatierende Wert
     * @param array $options Formatierungsoptionen
     * @return string Formatierter Wert
     */
    public function formatNumber($value, $options = []) {
        // Überprüfen, ob der Wert numerisch ist
        if (!is_numeric($value)) {
            // Wenn nicht numerisch, unverändert zurückgeben
            return (string)$value;
        }
        
        // Standardoptionen
        $defaults = [
            'decimals' => null,  // Anzahl der Dezimalstellen (null = automatisch)
            'decimalPoint' => ',', // Dezimaltrennzeichen
            'thousandsSep' => '.', // Tausendertrennzeichen
            'prefix' => '',      // Präfix vor dem Wert
            'suffix' => ''       // Suffix nach dem Wert
        ];
        
        // Optionen zusammenführen
        $options = array_merge($defaults, $options);
        
        // Sonderfall: Wert ist genau Null
        if ($value === 0 || abs($value) < 0.0000001) {
            return $options['prefix'] . '0' . $options['suffix'];
        }
        
        // Bestimme Anzahl der Dezimalstellen
        $decimals = $options['decimals'];
        if ($decimals === null) {
            // Automatische Bestimmung der Dezimalstellen
            $absValue = abs($value);
            if ($absValue >= 100) {
                $decimals = 0;
            } else if ($absValue >= 10) {
                $decimals = 1;
            } else if ($absValue >= 1) {
                $decimals = 2;
            } else {
                // Finde die erste signifikante Stelle
                $decimals = 2;
                $temp = $absValue;
                while ($temp < 0.1 && $decimals < 10) {
                    $temp *= 10;
                    $decimals++;
                }
            }
        }
        
        // Formatiere die Zahl
        $formattedValue = number_format(
            $value,
            $decimals,
            $options['decimalPoint'],
            $options['thousandsSep']
        );
        
        // Füge Präfix und Suffix hinzu
        return $options['prefix'] . $formattedValue . $options['suffix'];
    }
    
    /**
     * Formatiert einen Zeitstempel nach dem angegebenen Format
     * 
     * @param int $timestamp Unix-Zeitstempel
     * @param string $format Datumsformat (PHP date() kompatibel)
     * @return string Formatiertes Datum
     */
    public function formatDate($timestamp, $format = 'd/m/Y') {
        return date($format, $timestamp);
    }
    
    /**
     * Erzeugt eine eindeutige ID für SVG-Elemente
     * 
     * @param string $prefix Präfix für die ID
     * @return string Eindeutige ID
     */
    public function generateId($prefix = 'chart') {
        return $prefix . '_' . uniqid();
    }
    
    /**
     * Konvertiert einen Hex-Farbcode in ein RGB(A)-Array
     * 
     * @param string $hex Hex-Farbcode (mit oder ohne #)
     * @param float $alpha Alpha-Kanal (0-1)
     * @return array RGB(A)-Array mit Schlüsseln r, g, b, a
     */
    public function hexToRgb($hex, $alpha = 1) {
        // Entferne # am Anfang, falls vorhanden
        $hex = ltrim($hex, '#');
        
        // Parsen des Hex-Codes
        if (strlen($hex) == 3) {
            // Kurzer Hex-Code (#RGB)
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            // Regulärer Hex-Code (#RRGGBB)
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        
        return [
            'r' => $r,
            'g' => $g,
            'b' => $b,
            'a' => $alpha
        ];
    }
    
    /**
     * Konvertiert ein RGB(A)-Array in einen CSS-Farbstring
     * 
     * @param array $rgb RGB(A)-Array mit Schlüsseln r, g, b, a
     * @return string CSS-Farbstring (rgba oder rgb)
     */
    public function rgbToCss($rgb) {
        // Wenn Alpha-Kanal vorhanden und nicht 1, verwende rgba
        if (isset($rgb['a']) && $rgb['a'] != 1) {
            return "rgba({$rgb['r']}, {$rgb['g']}, {$rgb['b']}, {$rgb['a']})";
        }
        
        // Ansonsten verwende rgb
        return "rgb({$rgb['r']}, {$rgb['g']}, {$rgb['b']})";
    }
    
    /**
     * Berechnet die Summe von Werten in einem Array
     * 
     * @param array $values Array mit Werten
     * @param bool $ignoreNull Nullwerte ignorieren
     * @return float|int Summe der Werte
     */
    public function sum($values, $ignoreNull = true) {
        $sum = 0;
        
        foreach ($values as $value) {
            // Ignoriere null oder leere Werte, wenn $ignoreNull true ist
            if (($ignoreNull && ($value === null || $value === '')) || !is_numeric($value)) {
                continue;
            }
            
            $sum += $value;
        }
        
        return $sum;
    }
    
    /**
     * Berechnet den Durchschnitt von Werten in einem Array
     * 
     * @param array $values Array mit Werten
     * @param bool $ignoreNull Nullwerte ignorieren
     * @return float|int Durchschnitt der Werte
     */
    public function average($values, $ignoreNull = true) {
        $sum = 0;
        $count = 0;
        
        foreach ($values as $value) {
            // Ignoriere null oder leere Werte, wenn $ignoreNull true ist
            if (($ignoreNull && ($value === null || $value === '')) || !is_numeric($value)) {
                continue;
            }
            
            $sum += $value;
            $count++;
        }
        
        return $count > 0 ? $sum / $count : 0;
    }
    
    /**
     * Erzeugt eine Kontrastfarbe zu einer gegebenen Farbe
     * 
     * @param string $hex Hex-Farbcode
     * @return string Kontrastfarbe als Hex-Code
     */
    public function getContrastColor($hex) {
        $rgb = $this->hexToRgb($hex);
        
        // Berechne die Helligkeit nach YIQ-Formel
        $brightness = (($rgb['r'] * 299) + ($rgb['g'] * 587) + ($rgb['b'] * 114)) / 1000;
        
        // Verwende weiß für dunkle Farben, schwarz für helle Farben
        return $brightness > 128 ? '#000000' : '#ffffff';
    }
    
    /**
     * Interpoliert zwischen zwei Farben
     * 
     * @param string $color1 Erste Farbe als Hex-Code
     * @param string $color2 Zweite Farbe als Hex-Code
     * @param float $factor Interpolationsfaktor (0-1)
     * @return string Interpolierte Farbe als Hex-Code
     */
    public function interpolateColor($color1, $color2, $factor) {
        $rgb1 = $this->hexToRgb($color1);
        $rgb2 = $this->hexToRgb($color2);
        
        // Interpoliere die einzelnen Farbkomponenten
        $r = $rgb1['r'] + $factor * ($rgb2['r'] - $rgb1['r']);
        $g = $rgb1['g'] + $factor * ($rgb2['g'] - $rgb1['g']);
        $b = $rgb1['b'] + $factor * ($rgb2['b'] - $rgb1['b']);
        
        // Begrenze die Werte auf 0-255
        $r = max(0, min(255, round($r)));
        $g = max(0, min(255, round($g)));
        $b = max(0, min(255, round($b)));
        
        // Konvertiere zurück zu Hex
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    /**
     * Erzeugt einen eindeutigen Array-Schlüssel für Arrays ohne Schlüssel
     * 
     * @param mixed $item Der zu prüfende Wert
     * @return string Ein eindeutiger Schlüssel
     */
    public function generateKey($item) {
        if (is_scalar($item)) {
            return (string) $item;
        } else {
            return md5(serialize($item));
        }
    }
    
    /**
     * Prüft, ob ein String ein gültiges Datum ist
     * 
     * @param string $dateString Zu prüfender String
     * @return bool True, wenn es ein gültiges Datum ist
     */
    public function isValidDate($dateString) {
        $timestamp = strtotime($dateString);
        return $timestamp !== false && $timestamp !== -1;
    }
    
    /**
     * Konvertiert eine Farbe mit Alpha-Wert zu einer nicht-transparenten Farbe auf weißem Hintergrund
     * 
     * @param string $color Farbe als Hex-Code
     * @param float $alpha Alpha-Wert (0-1)
     * @return string Nicht-transparente Farbe als Hex-Code
     */
    public function alphaBlend($color, $alpha) {
        $rgb = $this->hexToRgb($color);
        
        // Weißer Hintergrund
        $bgR = 255;
        $bgG = 255;
        $bgB = 255;
        
        // Alpha-Blending
        $r = $rgb['r'] * $alpha + $bgR * (1 - $alpha);
        $g = $rgb['g'] * $alpha + $bgG * (1 - $alpha);
        $b = $rgb['b'] * $alpha + $bgB * (1 - $alpha);
        
        // Begrenze die Werte auf 0-255
        $r = max(0, min(255, round($r)));
        $g = max(0, min(255, round($g)));
        $b = max(0, min(255, round($b)));
        
        // Konvertiere zurück zu Hex
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
?>