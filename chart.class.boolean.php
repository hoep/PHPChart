<?php
/**
 * ChartBooleanChart - Boolean-Diagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Boolean-Diagrammen zuständig.
 * Boolean-Diagramme visualisieren Zeitreihen von booleschen Werten als farbcodierte Balken,
 * wobei verschiedene Farben die true/false-Zustände repräsentieren.
 * 
 * @version 1.0
 */
class ChartBooleanChart {
    /**
     * @var ChartUtils Instanz der Utility-Klasse
     */
    private $utils;
    
    /**
     * @var ChartSVG Instanz der SVG-Klasse
     */
    private $svg;
    
    /**
     * Konstruktor - Initialisiert die benötigten Objekte
     */
    public function __construct() {
        $this->utils = new ChartUtils();
        $this->svg = new ChartSVG();
    }
    
    /**
     * Rendert ein Boolean-Diagramm
     * 
     * @param array $seriesGroup Gruppe von Boolean-Diagramm-Serien
     * @param array $xValues Array mit X-Werten (Timestamps)
     * @param array $yValues Array mit Y-Werten (Booleans)
     * @param array $axes Achsendefinitionen (optional, oft nicht angezeigt bei Boolean-Diagrammen)
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Boolean-Diagramms
     */
    public function render($seriesGroup, $xValues, $yValues, $axes, $chartArea, $config) {
        // Initialisiere Ausgabe
        $output = '';
        
        // Rendere jede Serie
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            $output .= $this->renderBooleanSeries(
                $seriesName,
                $seriesOptions,
                $xValues,
                $yValues,
                $chartArea,
                $config
            );
        }
        
        return $output;
    }
    
    /**
     * Rendert eine Boolean-Serie
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten (Timestamps)
     * @param array $yValues Array mit Y-Werten (Booleans)
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente der Boolean-Serie
     */
    private function renderBooleanSeries($seriesName, $seriesOptions, $xValues, $yValues, $chartArea, $config) {
        // Hole die Werte für diese Serie
        $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : [];
        $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Wenn keine Werte vorhanden sind, nichts rendern
        if (empty($seriesX) || empty($seriesY)) {
            return '';
        }
        
        // Initialisiere Ausgabe
        $output = '';
        
        // Hole die Boolean-Optionen aus den Serienoptionen
        $booleanOptions = isset($seriesOptions['boolean']) ? $seriesOptions['boolean'] : [];
        
        // Bestimme die Ausrichtung (horizontal oder vertikal)
        $horizontal = isset($booleanOptions['horizontal']) ? $booleanOptions['horizontal'] : true;
        
        // Bestimme Farben für true und false
        $trueColor = isset($booleanOptions['trueColor']) ? $booleanOptions['trueColor'] : '#4CAF50';
        $falseColor = isset($booleanOptions['falseColor']) ? $booleanOptions['falseColor'] : '#F44336';
        
        // Bestimme die Größen und Position des Balkens
        $barHeight = isset($booleanOptions['barHeight']) ? $booleanOptions['barHeight'] : 30;
        $barWidth = isset($booleanOptions['barWidth']) ? $booleanOptions['barWidth'] : null;
        $barPosition = isset($booleanOptions['position']) ? $booleanOptions['position'] : 0;
        
        // Sortiere die Zeitstempel und stelle sicher, dass sie mit den booleschen Werten übereinstimmen
        $timeData = [];
        for ($i = 0; $i < count($seriesX); $i++) {
            if (isset($seriesX[$i]) && isset($seriesY[$i])) {
                $timeData[] = [
                    'timestamp' => $seriesX[$i],
                    'value' => $this->convertToBoolean($seriesY[$i])
                ];
            }
        }
        
        // Sortiere die Daten nach Timestamp
        usort($timeData, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });
        
        // Wenn weniger als 2 Datenpunkte vorhanden sind, nichts rendern
        if (count($timeData) < 2) {
            return '';
        }
        
        // Bestimme Min- und Max-Zeiten
        $minTime = $timeData[0]['timestamp'];
        $maxTime = $timeData[count($timeData) - 1]['timestamp'];
        
        // Zeitspanne berechnen
        $timeSpan = $maxTime - $minTime;
        
        // Bestimme die Dimensionen und Position des Diagramms
        if ($horizontal) {
            // Horizontaler Balken - Breite entspricht der Zeichenfläche
            $barWidth = $barWidth !== null ? $barWidth : $chartArea['width'];
            
            // Y-Position des Balkens basierend auf der Position und Anzahl der Balken
            $barY = $chartArea['y'] + $barPosition * ($barHeight + (isset($booleanOptions['barMargin']) ? $booleanOptions['barMargin'] : 10));
            
            // Rendere die Zeitsegmente
            $lastTime = $minTime;
            $lastValue = $timeData[0]['value'];
            $lastX = $chartArea['x'];
            
            for ($i = 1; $i < count($timeData); $i++) {
                $currentTime = $timeData[$i]['timestamp'];
                $currentValue = $timeData[$i]['value'];
                
                // Wenn sich der Wert ändert oder es der letzte Wert ist, zeichne das Segment
                if ($currentValue !== $lastValue || $i === count($timeData) - 1) {
                    // Berechne die X-Position und Breite des Segments
                    $segmentWidth = ($currentTime - $lastTime) / $timeSpan * $barWidth;
                    
                    // Zeichne das Segment
                    $output .= $this->svg->createRect(
                        $lastX,
                        $barY,
                        $segmentWidth,
                        $barHeight,
                        [
                            'fill' => $lastValue ? $trueColor : $falseColor,
                            'stroke' => 'none'
                        ]
                    );
                    
                    // Aktualisiere die Werte für das nächste Segment
                    $lastTime = $currentTime;
                    $lastValue = $currentValue;
                    $lastX += $segmentWidth;
                }
            }
            
            // Zeichne das letzte Segment (bis zum Ende der Zeitreihe)
            if ($lastTime < $maxTime) {
                $segmentWidth = ($maxTime - $lastTime) / $timeSpan * $barWidth;
                $output .= $this->svg->createRect(
                    $lastX,
                    $barY,
                    $segmentWidth,
                    $barHeight,
                    [
                        'fill' => $lastValue ? $trueColor : $falseColor,
                        'stroke' => 'none'
                    ]
                );
            }
            
            // Zeichne den Kategorienamen, falls vorhanden
            if (isset($booleanOptions['showLabel']) && $booleanOptions['showLabel'] && isset($booleanOptions['label'])) {
                $labelX = isset($booleanOptions['labelPosition']) && $booleanOptions['labelPosition'] === 'right' 
                    ? $chartArea['x'] + $barWidth + 10
                    : $chartArea['x'] - 10;
                $labelY = $barY + $barHeight / 2;
                $textAnchor = isset($booleanOptions['labelPosition']) && $booleanOptions['labelPosition'] === 'right'
                    ? 'start'
                    : 'end';
                
                $output .= $this->svg->createText(
                    $labelX,
                    $labelY,
                    $booleanOptions['label'],
                    [
                        'fontFamily' => isset($booleanOptions['labelFont']) ? $booleanOptions['labelFont'] : 'Arial, Helvetica, sans-serif',
                        'fontSize' => isset($booleanOptions['labelFontSize']) ? $booleanOptions['labelFontSize'] : 12,
                        'fontWeight' => isset($booleanOptions['labelFontWeight']) ? $booleanOptions['labelFontWeight'] : 'normal',
                        'fill' => isset($booleanOptions['labelColor']) ? $booleanOptions['labelColor'] : '#333333',
                        'textAnchor' => $textAnchor,
                        'dominantBaseline' => 'middle'
                    ]
                );
            }
        } else {
            // Vertikaler Balken - Höhe entspricht der Zeichenfläche
            $barWidth = $barWidth !== null ? $barWidth : $barHeight; // In diesem Fall ist barWidth die Breite des vertikalen Balkens
            
            // X-Position des Balkens basierend auf der Position und Anzahl der Balken
            $barX = $chartArea['x'] + $barPosition * ($barWidth + (isset($booleanOptions['barMargin']) ? $booleanOptions['barMargin'] : 10));
            
            // Rendere die Zeitsegmente
            $lastTime = $minTime;
            $lastValue = $timeData[0]['value'];
            $lastY = $chartArea['y'] + $chartArea['height'];
            
            for ($i = 1; $i < count($timeData); $i++) {
                $currentTime = $timeData[$i]['timestamp'];
                $currentValue = $timeData[$i]['value'];
                
                // Wenn sich der Wert ändert oder es der letzte Wert ist, zeichne das Segment
                if ($currentValue !== $lastValue || $i === count($timeData) - 1) {
                    // Berechne die Y-Position und Höhe des Segments
                    $segmentHeight = ($currentTime - $lastTime) / $timeSpan * $chartArea['height'];
                    
                    // Zeichne das Segment (vom unteren Ende nach oben)
                    $output .= $this->svg->createRect(
                        $barX,
                        $lastY - $segmentHeight,
                        $barWidth,
                        $segmentHeight,
                        [
                            'fill' => $lastValue ? $trueColor : $falseColor,
                            'stroke' => 'none'
                        ]
                    );
                    
                    // Aktualisiere die Werte für das nächste Segment
                    $lastTime = $currentTime;
                    $lastValue = $currentValue;
                    $lastY -= $segmentHeight;
                }
            }
            
            // Zeichne das letzte Segment (bis zum Ende der Zeitreihe)
            if ($lastTime < $maxTime) {
                $segmentHeight = ($maxTime - $lastTime) / $timeSpan * $chartArea['height'];
                $output .= $this->svg->createRect(
                    $barX,
                    $chartArea['y'],
                    $barWidth,
                    $segmentHeight,
                    [
                        'fill' => $lastValue ? $trueColor : $falseColor,
                        'stroke' => 'none'
                    ]
                );
            }
            
            // Zeichne den Kategorienamen, falls vorhanden
            if (isset($booleanOptions['showLabel']) && $booleanOptions['showLabel'] && isset($booleanOptions['label'])) {
                $labelX = $barX + $barWidth / 2;
                $labelY = isset($booleanOptions['labelPosition']) && $booleanOptions['labelPosition'] === 'bottom'
                    ? $chartArea['y'] + $chartArea['height'] + 20
                    : $chartArea['y'] - 10;
                
                $output .= $this->svg->createText(
                    $labelX,
                    $labelY,
                    $booleanOptions['label'],
                    [
                        'fontFamily' => isset($booleanOptions['labelFont']) ? $booleanOptions['labelFont'] : 'Arial, Helvetica, sans-serif',
                        'fontSize' => isset($booleanOptions['labelFontSize']) ? $booleanOptions['labelFontSize'] : 12,
                        'fontWeight' => isset($booleanOptions['labelFontWeight']) ? $booleanOptions['labelFontWeight'] : 'normal',
                        'fill' => isset($booleanOptions['labelColor']) ? $booleanOptions['labelColor'] : '#333333',
                        'textAnchor' => 'middle',
                        'dominantBaseline' => isset($booleanOptions['labelPosition']) && $booleanOptions['labelPosition'] === 'bottom' ? 'hanging' : 'auto'
                    ]
                );
            }
        }
        
        return $output;
    }
    
    /**
     * Konvertiert verschiedene Eingaben in einen booleschen Wert
     * 
     * @param mixed $value Zu konvertierender Wert
     * @return bool Konvertierter boolescher Wert
     */
    private function convertToBoolean($value) {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return $value != 0;
        }
        
        if (is_string($value)) {
            $value = strtolower($value);
            return $value === 'true' || $value === '1' || $value === 'yes' || $value === 'y' || $value === 'on';
        }
        
        return false;
    }
}
?>