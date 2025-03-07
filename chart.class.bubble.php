<?php
/**
 * ChartBubbleChart - Bubble-Diagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Bubble-Diagrammen zuständig.
 * 
 * @version 1.1
 */
class ChartBubbleChart {
    /**
     * @var ChartUtils Instanz der Utility-Klasse
     */
    private $utils;
    
    /**
     * @var ChartSVG Instanz der SVG-Klasse
     */
    private $svg;
    
    /**
     * @var ChartAxes Instanz der Achsen-Klasse
     */
    private $axes;
    
    /**
     * Cache für generierte Gradienten-IDs
     * @var array
     */
    private $gradientCache = [];
    
    /**
     * Konstruktor - Initialisiert die benötigten Objekte
     */
    public function __construct() {
        $this->utils = new ChartUtils();
        $this->svg = new ChartSVG();
        $this->axes = new ChartAxes();
    }
    
    /**
     * Rendert ein Bubble-Diagramm
     * 
     * @param array $seriesGroup Gruppe von Bubble-Diagramm-Serien
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Bubble-Diagramms
     */
    public function render($seriesGroup, $xValues, $yValues, $axes, $chartArea, $config) {
        // Initialisiere Gradienten-Cache vor jeder Nutzung
        $this->gradientCache = [];
        
        // Erstelle Gradienten für alle Serien, die diese benötigen
        $this->prepareGradients($seriesGroup);
        
        // Initialisiere Ausgabe
        $output = '';
        
        // Erzeuge Defs-Sektion für Gradienten
        $defs = $this->generateGradientDefsSection();
        if (!empty($defs)) {
            $output .= $this->svg->createDefs($defs);
        }
        
        // Aktualisiere die Serien mit Gradienten-IDs
        $updatedSeriesGroup = $this->applyGradientIds($seriesGroup);
        
        // Rendere jede Serie
        foreach ($updatedSeriesGroup as $seriesName => $seriesOptions) {
            $output .= $this->renderBubbleSeries(
                $seriesName,
                $seriesOptions,
                $xValues,
                $yValues,
                $axes,
                $chartArea
            );
        }
        
        return $output;
    }
    
    /**
     * Erstellt Gradienten für alle Serien und Bubbles, die diese benötigen
     * 
     * @param array $seriesGroup Gruppe von Bubble-Diagramm-Serien
     */
    private function prepareGradients($seriesGroup) {
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            // Gradienten für die Hauptserie prüfen
            if (isset($seriesOptions['gradient']) && isset($seriesOptions['gradient']['enabled']) && $seriesOptions['gradient']['enabled']) {
                // Generiere eine sichere ID ohne Leerzeichen oder ungültige Zeichen
                $safeSeriesName = preg_replace('/[^a-zA-Z0-9]/', '_', $seriesName);
                $gradientId = 'gradient_' . $safeSeriesName . '_' . $this->utils->generateId();
                
                // Speichere Gradientendefinition im Cache
                $this->gradientCache[$seriesName] = [
                    'id' => $gradientId,
                    'options' => $seriesOptions['gradient'],
                    'color' => isset($seriesOptions['color']) ? $seriesOptions['color'] : '#000000'
                ];
            }
            
            // Individuelle Bubble-Gradienten prüfen
            if (isset($seriesOptions['bubbles']) && is_array($seriesOptions['bubbles'])) {
                foreach ($seriesOptions['bubbles'] as $index => $bubbleOptions) {
                    // Prüfen, ob der Eintrag ein Gradient hat
                    if (isset($bubbleOptions['gradient']) && isset($bubbleOptions['gradient']['enabled']) && $bubbleOptions['gradient']['enabled']) {
                        $safeSeriesName = preg_replace('/[^a-zA-Z0-9]/', '_', $seriesName);
                        $gradientId = 'gradient_' . $safeSeriesName . '_bubble_' . $index;
                        
                        // Speichere Gradientendefinition im Cache
                        $cacheKey = $seriesName . '_bubble_' . $index;
                        $this->gradientCache[$cacheKey] = [
                            'id' => $gradientId,
                            'options' => $bubbleOptions['gradient'],
                            'color' => isset($bubbleOptions['color']) ? $bubbleOptions['color'] : '#000000'
                        ];
                    }
                }
            }
        }
    }
    
    /**
     * Generiert alle Gradienten-Definitionen
     * 
     * @return string SVG-Gradient-Definitionen
     */
    private function generateGradientDefsSection() {
        $defs = '';
        
        foreach ($this->gradientCache as $key => $gradientInfo) {
            $gradientId = $gradientInfo['id'];
            $gradientOptions = $gradientInfo['options'];
            $baseColor = $gradientInfo['color'];
            
            $type = isset($gradientOptions['type']) ? $gradientOptions['type'] : 'linear';
            $stops = [];
            
            // Mehrere Farben für den Gradienten ermöglichen
            if (isset($gradientOptions['colors']) && !empty($gradientOptions['colors'])) {
                // Wenn ein Array von Farben angegeben ist
                $colors = $gradientOptions['colors'];
                $stopCount = count($colors);
                
                // Prüfe, ob benutzerdefinierte Stops vorhanden sind
                $customStops = isset($gradientOptions['stops']) && !empty($gradientOptions['stops']) 
                              ? $gradientOptions['stops'] : [];
                
                // Erzeuge Stops basierend auf den Farben
                for ($i = 0; $i < $stopCount; $i++) {
                    $offset = isset($customStops[$i]) ? $customStops[$i] : ($i * (100 / max(1, $stopCount - 1))) . '%';
                    $stops[] = [
                        'offset' => $offset,
                        'color' => $colors[$i],
                        'opacity' => 1.0
                    ];
                }
            } else {
                // Fallback auf Start- und Endfarbe (Kompatibilität)
                $startColor = !empty($gradientOptions['startColor']) ? 
                              $gradientOptions['startColor'] : 
                              $baseColor;
                $endColor = !empty($gradientOptions['endColor']) ? 
                            $gradientOptions['endColor'] : 
                            $this->utils->alphaBlend($baseColor, 0.5);
                
                $stops = [
                    ['offset' => '0%', 'color' => $startColor, 'opacity' => 1.0],
                    ['offset' => '100%', 'color' => $endColor, 'opacity' => 1.0]
                ];
            }
            
            // Erstelle den entsprechenden Gradienten
            if ($type === 'linear') {
                $angle = isset($gradientOptions['angle']) ? $gradientOptions['angle'] : 90;
                
                // Konvertiere Winkel in Gradient-Koordinaten
                $angleRad = deg2rad($angle);
                $x1 = 50 - cos($angleRad) * 50;
                $y1 = 50 - sin($angleRad) * 50;
                $x2 = 50 + cos($angleRad) * 50;
                $y2 = 50 + sin($angleRad) * 50;
                
                $lineGradientOptions = [
                    'x1' => $x1 . '%',
                    'y1' => $y1 . '%',
                    'x2' => $x2 . '%',
                    'y2' => $y2 . '%'
                ];
                
                $defs .= $this->svg->createLinearGradient($gradientId, $stops, $lineGradientOptions);
            } else { // 'radial'
                $defs .= $this->svg->createRadialGradient($gradientId, $stops);
            }
        }
        
        return $defs;
    }
    
    /**
     * Aktualisiert die Serien mit den korrekten Gradienten-IDs
     * 
     * @param array $seriesGroup Originale Seriengruppe
     * @return array Aktualisierte Seriengruppe mit Gradienten-IDs
     */
    private function applyGradientIds($seriesGroup) {
        $updatedSeriesGroup = $seriesGroup;
        
        foreach ($this->gradientCache as $key => $gradientInfo) {
            // Prüfe verschiedene Schlüsseltypen
            if (strpos($key, '_bubble_') !== false) {
                // Individuelle Bubble nach Index
                list($seriesName, $rest) = explode('_bubble_', $key);
                $index = intval($rest); // Extrahiere den Index als Zahl
                
                if (isset($updatedSeriesGroup[$seriesName]) && 
                    isset($updatedSeriesGroup[$seriesName]['bubbles']) && 
                    isset($updatedSeriesGroup[$seriesName]['bubbles'][$index])) {
                    $updatedSeriesGroup[$seriesName]['bubbles'][$index]['gradientId'] = 'url(#' . $gradientInfo['id'] . ')';
                }
            } else {
                // Hauptserien-Farbe
                if (isset($updatedSeriesGroup[$key])) {
                    $updatedSeriesGroup[$key]['gradientId'] = 'url(#' . $gradientInfo['id'] . ')';
                }
            }
        }
        
        return $updatedSeriesGroup;
    }
    
    /**
     * Rendert eine Bubble-Serie
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente der Bubble-Serie
     */
    private function renderBubbleSeries($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea) {
        // Bestimme die zu verwendenden Achsen
        $xAxisId = isset($seriesOptions['xAxisId']) ? $seriesOptions['xAxisId'] : 0;
        $yAxisId = isset($seriesOptions['yAxisId']) ? $seriesOptions['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Hole die X- und Y-Werte für diese Serie
        $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
        $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Bestimme das Feld für die Größenwerte (z-Werte)
        $sizeField = isset($seriesOptions['bubble']['sizeField']) ? $seriesOptions['bubble']['sizeField'] : 'size';
        
        // Hole die Größenwerte für diese Serie
        $seriesZ = isset($seriesOptions['size']) ? $seriesOptions['size'] : [];
        
        // Wenn keine Größenwerte angegeben sind, verwende Standardgröße
        $defaultSize = isset($seriesOptions['bubble']['defaultSize']) ? $seriesOptions['bubble']['defaultSize'] : 20;
        
        // Minimale und maximale Größe für die Skalierung
        $minSize = isset($seriesOptions['bubble']['minSize']) ? $seriesOptions['bubble']['minSize'] : 5;
        $maxSize = isset($seriesOptions['bubble']['maxSize']) ? $seriesOptions['bubble']['maxSize'] : 50;
        
        // Standardfarbe und Opacity für Bubbles
        $defaultColor = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
        $defaultFillOpacity = isset($seriesOptions['fillOpacity']) ? $seriesOptions['fillOpacity'] : 0.7;
        
        // Border-Optionen
        $defaultBorderColor = isset($seriesOptions['bubble']['borderColor']) ? $seriesOptions['bubble']['borderColor'] : $defaultColor;
        $defaultBorderWidth = isset($seriesOptions['bubble']['borderWidth']) ? $seriesOptions['bubble']['borderWidth'] : 1;
        
        // Standardfüllung (Gradient oder Farbe)
        $defaultFill = isset($seriesOptions['gradientId']) ? $seriesOptions['gradientId'] : $defaultColor;
        
        // Finde Minimum und Maximum der Z-Werte für die Skalierung
        $minZ = $maxZ = null;
        if (!empty($seriesZ)) {
            $minZ = min($seriesZ);
            $maxZ = max($seriesZ);
        }
        
        // Output initialisieren
        $output = '';
        
        // Individuelle Bubble-Definitionen prüfen
        $individualBubbles = isset($seriesOptions['bubbles']) ? $seriesOptions['bubbles'] : [];
        
        // Für jeden Datenpunkt
        for ($i = 0; $i < count($seriesX); $i++) {
            // Die aktuellen Werte
            $xValue = isset($seriesX[$i]) ? $seriesX[$i] : null;
            $yValue = isset($seriesY[$i]) ? $seriesY[$i] : null;
            $zValue = isset($seriesZ[$i]) ? $seriesZ[$i] : null;
            
            // Ignorieren, wenn X oder Y nicht numerisch ist
            if ($xValue === null || $yValue === null || !is_numeric($xValue) || !is_numeric($yValue)) {
                continue;
            }
            
            // X-Koordinate basierend auf dem Achsentyp berechnen
            if ($xAxis['type'] === 'category') {
                $x = $this->axes->convertXValueToCoordinate($i, $xAxis, $chartArea);
            } else {
                $x = $this->axes->convertXValueToCoordinate($xValue, $xAxis, $chartArea);
            }
            
            // Y-Koordinate berechnen
            $y = $this->axes->convertYValueToCoordinate($yValue, $yAxis, $chartArea);
            
            // Größe der Bubble berechnen (Radius)
            $size = $defaultSize;
            if ($zValue !== null && is_numeric($zValue) && $minZ !== $maxZ) {
                // Skaliere Z-Wert zwischen minSize und maxSize
                $size = $minSize + (($zValue - $minZ) / ($maxZ - $minZ)) * ($maxSize - $minSize);
            }
            
            // Individuelle Bubble-Optionen, falls definiert
            $bubbleOptions = isset($individualBubbles[$i]) ? $individualBubbles[$i] : [];
            
            // Bestimme die Farbe und andere Attribute für diese Bubble
            $color = isset($bubbleOptions['color']) ? $bubbleOptions['color'] : $defaultColor;
            $fillOpacity = isset($bubbleOptions['fillOpacity']) ? $bubbleOptions['fillOpacity'] : $defaultFillOpacity;
            $borderColor = isset($bubbleOptions['borderColor']) ? $bubbleOptions['borderColor'] : $defaultBorderColor;
            $borderWidth = isset($bubbleOptions['borderWidth']) ? $bubbleOptions['borderWidth'] : $defaultBorderWidth;
            
            // Überschreibe die Größe, falls individuell angegeben
            if (isset($bubbleOptions['size'])) {
                $size = $bubbleOptions['size'];
            }
            
            // Bestimme die Füllung (Gradient oder Farbe)
            $fill = isset($bubbleOptions['gradientId']) ? $bubbleOptions['gradientId'] : $color;
            
            // Bubble rendern
            $output .= $this->svg->createCircle(
                $x,
                $y,
                $size / 2,
                [
                    'fill' => $fill,
                    'fillOpacity' => $fillOpacity,
                    'stroke' => $borderColor,
                    'strokeWidth' => $borderWidth
                ]
            );
            
            // Datenwertbeschriftung rendern, falls aktiviert
            if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
                $label = $zValue ?? '';
                if (isset($seriesOptions['dataLabels']['format'])) {
                    $format = $seriesOptions['dataLabels']['format'];
                    $label = str_replace('{z}', $this->utils->formatNumber($zValue), $format);
                    $label = str_replace('{y}', $this->utils->formatNumber($yValue), $label);
                    $label = str_replace('{x}', $this->utils->formatNumber($xValue), $label);
                }
                
                // Label-Optionen
                $labelOffsetX = isset($seriesOptions['dataLabels']['offsetX']) ? $seriesOptions['dataLabels']['offsetX'] : 0;
                $labelOffsetY = isset($seriesOptions['dataLabels']['offsetY']) ? $seriesOptions['dataLabels']['offsetY'] : 0;
                $labelColor = isset($seriesOptions['dataLabels']['color']) ? $seriesOptions['dataLabels']['color'] : '#333333';
                $labelFontSize = isset($seriesOptions['dataLabels']['fontSize']) ? $seriesOptions['dataLabels']['fontSize'] : 12;
                
                // Individuelle Label-Optionen pro Bubble
                if (isset($bubbleOptions['dataLabel'])) {
                    if (isset($bubbleOptions['dataLabel']['text'])) {
                        $label = $bubbleOptions['dataLabel']['text'];
                    }
                    if (isset($bubbleOptions['dataLabel']['color'])) {
                        $labelColor = $bubbleOptions['dataLabel']['color'];
                    }
                    if (isset($bubbleOptions['dataLabel']['offsetX'])) {
                        $labelOffsetX = $bubbleOptions['dataLabel']['offsetX'];
                    }
                    if (isset($bubbleOptions['dataLabel']['offsetY'])) {
                        $labelOffsetY = $bubbleOptions['dataLabel']['offsetY'];
                    }
                    if (isset($bubbleOptions['dataLabel']['fontSize'])) {
                        $labelFontSize = $bubbleOptions['dataLabel']['fontSize'];
                    }
                }
                
                // Label rendern
                $output .= $this->svg->createText(
                    $x + $labelOffsetX,
                    $y + $labelOffsetY,
                    $label,
                    [
                        'fontFamily' => isset($seriesOptions['dataLabels']['fontFamily']) ? $seriesOptions['dataLabels']['fontFamily'] : 'Arial, Helvetica, sans-serif',
                        'fontSize' => $labelFontSize,
                        'fontWeight' => isset($seriesOptions['dataLabels']['fontWeight']) ? $seriesOptions['dataLabels']['fontWeight'] : 'normal',
                        'fill' => $labelColor,
                        'textAnchor' => 'middle',
                        'dominantBaseline' => 'middle'
                    ]
                );
            }
        }
        
        return $output;
    }
}
?>