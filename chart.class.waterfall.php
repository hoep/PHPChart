<?php
/**
 * ChartWaterfallChart - Waterfall-Diagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Waterfall-Diagrammen zuständig,
 * einschließlich vertikaler und horizontaler Varianten.
 * 
 * @version 1.3
 */
class ChartWaterfallChart {
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
     * Rendert ein Waterfall-Diagramm
     * 
     * @param array $seriesGroup Gruppe von Waterfall-Diagramm-Serien
     * @param array $xValues Array mit X-Werten (Kategorien)
     * @param array $yValues Array mit Y-Werten (Werte)
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Waterfall-Diagramms
     */
    public function render($seriesGroup, $xValues, $yValues, $axes, $chartArea, $config) {
        // Prüfen, ob es horizontale Waterfall-Diagramme gibt
        $hasHorizontalWaterfall = false;
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            if (isset($seriesOptions['waterfall']) && isset($seriesOptions['waterfall']['horizontal']) && $seriesOptions['waterfall']['horizontal']) {
                $hasHorizontalWaterfall = true;
                break;
            }
        }
        
        // Setze das Flag für horizontale Balken in der Achsenklasse
        $this->axes->setHorizontalBars($hasHorizontalWaterfall);
        
        // Erstelle Gradienten für alle Serien, die diese benötigen
        $this->prepareGradients($seriesGroup, $hasHorizontalWaterfall);
        
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
            // Prüfe, ob es sich um ein horizontales Waterfall-Diagramm handelt
            $isHorizontal = isset($seriesOptions['waterfall']) && 
                           isset($seriesOptions['waterfall']['horizontal']) && 
                           $seriesOptions['waterfall']['horizontal'];
            
            if ($isHorizontal) {
                $output .= $this->renderHorizontalWaterfall(
                    $seriesName,
                    $seriesOptions,
                    $xValues,
                    $yValues,
                    $axes,
                    $chartArea
                );
            } else {
                $output .= $this->renderVerticalWaterfall(
                    $seriesName,
                    $seriesOptions,
                    $xValues,
                    $yValues,
                    $axes,
                    $chartArea
                );
            }
        }
        
        return $output;
    }
    
    /**
     * Erstellt Gradienten für alle Serien, die diese benötigen
     * 
     * @param array $seriesGroup Gruppe von Waterfall-Diagramm-Serien
     * @param bool $horizontal Ob horizontale Balken gerendert werden
     */
    private function prepareGradients($seriesGroup, $horizontal = false) {
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            // Sichere Serien-Name für Gradient-IDs
            $safeSeriesName = preg_replace('/[^a-zA-Z0-9]/', '_', $seriesName);
            
            if (isset($seriesOptions['gradient']) && isset($seriesOptions['gradient']['enabled']) && $seriesOptions['gradient']['enabled']) {
                $gradientId = 'gradient_' . $safeSeriesName . '_' . $this->utils->generateId();
                
                // Speichere Gradientendefinition im Cache
                $this->gradientCache[$seriesName] = [
                    'id' => $gradientId,
                    'options' => $seriesOptions['gradient'],
                    'horizontal' => $horizontal,
                    'color' => $seriesOptions['color']
                ];
            }
            
            // Für individuelle Balkenfarben auch Gradienten erstellen, wenn nötig
            if (isset($seriesOptions['waterfall']) && isset($seriesOptions['waterfall']['colors'])) {
                $colors = $seriesOptions['waterfall']['colors'];
                foreach ($colors as $key => $colorOptions) {
                    if (isset($colorOptions['gradient']) && isset($colorOptions['gradient']['enabled']) && $colorOptions['gradient']['enabled']) {
                        $safeKey = preg_replace('/[^a-zA-Z0-9]/', '_', $key);
                        $gradientId = 'gradient_' . $safeSeriesName . '_' . $safeKey . '_' . $this->utils->generateId();
                        
                        // Speichere Gradientendefinition im Cache
                        $colorCacheKey = $seriesName . '_' . $key;
                        $this->gradientCache[$colorCacheKey] = [
                            'id' => $gradientId,
                            'options' => $colorOptions['gradient'],
                            'horizontal' => $horizontal,
                            'color' => $colorOptions['color']
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
            $horizontal = $gradientInfo['horizontal'];
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
            
            // Bestimme Default-Winkel je nach Balkenorientierung
            $defaultAngle = $horizontal ? 0 : 90;
            
            if ($type === 'linear') {
                $angle = isset($gradientOptions['angle']) ? $gradientOptions['angle'] : $defaultAngle;
                
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
            // Prüfe, ob es sich um eine Serienfarbe oder eine spezifische Balkenfarbe handelt
            if (strpos($key, '_') !== false) {
                // Balkenfarbe: seriesName_key
                list($seriesName, $colorKey) = explode('_', $key, 2);
                if (isset($updatedSeriesGroup[$seriesName]) && 
                    isset($updatedSeriesGroup[$seriesName]['waterfall']) && 
                    isset($updatedSeriesGroup[$seriesName]['waterfall']['colors']) && 
                    isset($updatedSeriesGroup[$seriesName]['waterfall']['colors'][$colorKey])) {
                    $updatedSeriesGroup[$seriesName]['waterfall']['colors'][$colorKey]['gradientId'] = 'url(#' . $gradientInfo['id'] . ')';
                }
            } else {
                // Serienfarbe
                if (isset($updatedSeriesGroup[$key])) {
                    $updatedSeriesGroup[$key]['gradientId'] = 'url(#' . $gradientInfo['id'] . ')';
                }
            }
        }
        
        return $updatedSeriesGroup;
    }
    
    /**
     * Rendert ein vertikales Waterfall-Diagramm
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten (Kategorien)
     * @param array $yValues Array mit Y-Werten (Werte)
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente des vertikalen Waterfall-Diagramms
     */
    private function renderVerticalWaterfall($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea) {
        // Bestimme die zu verwendenden Achsen
        $xAxisId = isset($seriesOptions['xAxisId']) ? $seriesOptions['xAxisId'] : 0;
        $yAxisId = isset($seriesOptions['yAxisId']) ? $seriesOptions['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Hole die X- und Y-Werte für diese Serie
        $seriesX = isset($xValues['default']) ? $xValues['default'] : [];
        $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Standardfarben für Waterfall-Diagramm
        $defaultColors = [
            'positive' => isset($seriesOptions['waterfall']['positiveColor']) ? $seriesOptions['waterfall']['positiveColor'] : '#4CAF50', // Grün
            'negative' => isset($seriesOptions['waterfall']['negativeColor']) ? $seriesOptions['waterfall']['negativeColor'] : '#F44336', // Rot
            'total' => isset($seriesOptions['waterfall']['totalColor']) ? $seriesOptions['waterfall']['totalColor'] : '#2196F3',        // Blau
            'subtotal' => isset($seriesOptions['waterfall']['subtotalColor']) ? $seriesOptions['waterfall']['subtotalColor'] : '#9C27B0' // Lila
        ];
        
        // Bei individuellen Farben diese verwenden
        $useIndividualColors = isset($seriesOptions['waterfall']['useIndividualColors']) ? 
                               $seriesOptions['waterfall']['useIndividualColors'] : false;
        
        // Balkenbreite berechnen
        $barWidth = isset($seriesOptions['waterfall']['barWidth']) ? 
                    $seriesOptions['waterfall']['barWidth'] : 
                    (isset($xAxis['categoryWidth']) ? $xAxis['categoryWidth'] * 0.8 : 40);
        
        // Eckenradius für Balken
        $cornerRadius = isset($seriesOptions['waterfall']['cornerRadius']) ? 
                       $seriesOptions['waterfall']['cornerRadius'] : 0;
        
        // Berechne den initialen Wert und die Summe
        $runningTotal = isset($seriesOptions['waterfall']['initialValue']) ? 
                       $seriesOptions['waterfall']['initialValue'] : 0;
        
        // Definiere Typen für Balken
        $barTypes = isset($seriesOptions['waterfall']['barTypes']) ? 
                   $seriesOptions['waterfall']['barTypes'] : [];
        
        // Individuelle Balkenfarben
        $individualColors = isset($seriesOptions['waterfall']['colors']) ? 
                          $seriesOptions['waterfall']['colors'] : [];
        
        // Finde die Y-Null-Position
        $zeroY = $this->axes->convertYValueToCoordinate(0, $yAxis, $chartArea);
        
        $output = '';
        $connectorPoints = [];
        $lastEndY = null;  // Letztwert für die Verbindungslinien
        $lastX = null;     // Letztwert X-Position
        
        // Für jeden Datenpunkt
        for ($i = 0; $i < count($seriesY); $i++) {
            // Der aktuelle Wert
            $value = isset($seriesY[$i]) ? $seriesY[$i] : 0;
            
            // Bestimme den Balkentyp
            $barType = 'normal';
            if (isset($barTypes[$i])) {
                $barType = $barTypes[$i];
            } else if ($value > 0) {
                $barType = 'positive';
            } else if ($value < 0) {
                $barType = 'negative';
            }
            
            // Bestimme die Balkenfarbe basierend auf dem Typ
            $barColor = $defaultColors['positive']; // Standardfarbe
            
            if ($useIndividualColors && isset($individualColors[$i])) {
                // Verwende individuelle Farbe für diesen Balken
                $barColor = isset($individualColors[$i]['gradientId']) ? 
                           $individualColors[$i]['gradientId'] : 
                           $individualColors[$i]['color'];
            } else {
                // Verwende die Standardfarben basierend auf dem Balkentyp
                switch ($barType) {
                    case 'positive':
                        $barColor = $defaultColors['positive'];
                        break;
                    case 'negative':
                        $barColor = $defaultColors['negative'];
                        break;
                    case 'total':
                        $barColor = $defaultColors['total'];
                        break;
                    case 'subtotal':
                        $barColor = $defaultColors['subtotal'];
                        break;
                }
            }
            
            // X-Position des Balkens - WICHTIG: Hier den Index verwenden, nicht den Wert!
            $x = $this->axes->convertXValueToCoordinate($i, $xAxis, $chartArea);
            $x = $x - $barWidth / 2; // Verschieben, damit der Balken zentriert ist
            
            // Bei Subtotals und Totals Werte anzeigen
            $displayValue = $value;
            
            // Bei Totals und Subtotals ist der Wert absolut, sonst relativ zur bisherigen Summe
            $startValue = $runningTotal;
            if ($barType === 'total' || $barType === 'subtotal') {
                $endValue = $value;
                // Für Subtotals und Totals zeigen wir den tatsächlichen Wert an, nicht 0
                $displayValue = $runningTotal;
            } else {
                $endValue = $runningTotal + $value;
                $displayValue = $value;
            }
            
            // Aktualisiere die laufende Summe, außer bei Totals und Subtotals
            if ($barType !== 'total' && $barType !== 'subtotal') {
                $runningTotal = $endValue;
            }
            
            // Y-Positionen berechnen
            $y1 = $this->axes->convertYValueToCoordinate($startValue, $yAxis, $chartArea);
            $y2 = $this->axes->convertYValueToCoordinate($endValue, $yAxis, $chartArea);
            
            // Stelle sicher, dass y1 immer die obere Kante ist
            if ($y1 > $y2) {
                list($y1, $y2) = [$y2, $y1];
            }
            
            // Balkenhöhe berechnen
            $barHeight = abs($y2 - $y1);
            
            // Balken rendern
            $output .= $this->svg->createRect(
                $x,
                $y1,
                $barWidth,
                $barHeight,
                [
                    'fill' => $barColor,
                    'fillOpacity' => isset($seriesOptions['fillOpacity']) ? $seriesOptions['fillOpacity'] : 1,
                    'rx' => $cornerRadius,
                    'ry' => $cornerRadius
                ]
            );
            
            // Verbindungslinien berechnen - auch für Subtotals und Totals
            if ($i > 0) {
                $prevEndY = $this->axes->convertYValueToCoordinate($startValue, $yAxis, $chartArea);
                $prevX = $this->axes->convertXValueToCoordinate($i - 1, $xAxis, $chartArea);
                $currentX = $this->axes->convertXValueToCoordinate($i, $xAxis, $chartArea);
                
                $connectorPoints[] = [
                    'x1' => $prevX + $barWidth / 2, // Mitte des vorherigen Balkens
                    'y1' => $prevEndY,
                    'x2' => $currentX - $barWidth / 2, // Mitte des aktuellen Balkens
                    'y2' => $prevEndY
                ];
            }
            
            // Datenwertbeschriftung rendern, falls aktiviert
            if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
                $labelX = $x + $barWidth / 2;
                $labelY = ($y1 + $y2) / 2; // Mitte des Balkens
                
                $labelText = isset($seriesOptions['dataLabels']['format']) ? 
                           str_replace('{y}', $this->utils->formatNumber($displayValue), $seriesOptions['dataLabels']['format']) : 
                           $this->utils->formatNumber($displayValue);
                
                $output .= $this->svg->createText(
                    $labelX,
                    $labelY,
                    $labelText,
                    [
                        'fontFamily' => isset($seriesOptions['dataLabels']['fontFamily']) ? $seriesOptions['dataLabels']['fontFamily'] : 'Arial, Helvetica, sans-serif',
                        'fontSize' => isset($seriesOptions['dataLabels']['fontSize']) ? $seriesOptions['dataLabels']['fontSize'] : 12,
                        'fontWeight' => isset($seriesOptions['dataLabels']['fontWeight']) ? $seriesOptions['dataLabels']['fontWeight'] : 'normal',
                        'fill' => isset($seriesOptions['dataLabels']['color']) ? $seriesOptions['dataLabels']['color'] : '#333333',
                        'textAnchor' => 'middle',
                        'dominantBaseline' => 'middle'
                    ]
                );
            }
        }
        
        // Konnektoren (Verbindungslinien) rendern, falls aktiviert
        if (isset($seriesOptions['waterfall']['connectors']) && isset($seriesOptions['waterfall']['connectors']['enabled']) && $seriesOptions['waterfall']['connectors']['enabled']) {
            $connectorColor = isset($seriesOptions['waterfall']['connectors']['color']) ? 
                             $seriesOptions['waterfall']['connectors']['color'] : '#999999';
            $connectorWidth = isset($seriesOptions['waterfall']['connectors']['width']) ? 
                             $seriesOptions['waterfall']['connectors']['width'] : 1;
            $connectorDashArray = isset($seriesOptions['waterfall']['connectors']['dashArray']) ? 
                                $seriesOptions['waterfall']['connectors']['dashArray'] : '';
            
            foreach ($connectorPoints as $connector) {
                $output .= $this->svg->createLine(
                    $connector['x1'],
                    $connector['y1'],
                    $connector['x2'],
                    $connector['y2'],
                    [
                        'stroke' => $connectorColor,
                        'strokeWidth' => $connectorWidth,
                        'strokeDasharray' => $connectorDashArray
                    ]
                );
            }
        }
        
        return $output;
    }
    
    /**
     * Rendert ein horizontales Waterfall-Diagramm
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten (Werte)
     * @param array $yValues Array mit Y-Werten (Kategorien)
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente des horizontalen Waterfall-Diagramms
     */
    private function renderHorizontalWaterfall($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea) {
        // Bestimme die zu verwendenden Achsen
        $xAxisId = isset($seriesOptions['xAxisId']) ? $seriesOptions['xAxisId'] : 0;
        $yAxisId = isset($seriesOptions['yAxisId']) ? $seriesOptions['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Hole die X- und Y-Werte für diese Serie
        $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : [];
        $seriesY = isset($yValues['default']) ? $yValues['default'] : [];
        
        // Standardfarben für Waterfall-Diagramm
        $defaultColors = [
            'positive' => isset($seriesOptions['waterfall']['positiveColor']) ? $seriesOptions['waterfall']['positiveColor'] : '#4CAF50', // Grün
            'negative' => isset($seriesOptions['waterfall']['negativeColor']) ? $seriesOptions['waterfall']['negativeColor'] : '#F44336', // Rot
            'total' => isset($seriesOptions['waterfall']['totalColor']) ? $seriesOptions['waterfall']['totalColor'] : '#2196F3',        // Blau
            'subtotal' => isset($seriesOptions['waterfall']['subtotalColor']) ? $seriesOptions['waterfall']['subtotalColor'] : '#9C27B0' // Lila
        ];
        
        // Bei individuellen Farben diese verwenden
        $useIndividualColors = isset($seriesOptions['waterfall']['useIndividualColors']) ? 
                             $seriesOptions['waterfall']['useIndividualColors'] : false;
        
        // Balkenhöhe berechnen
        $barHeight = isset($seriesOptions['waterfall']['barHeight']) ? 
                    $seriesOptions['waterfall']['barHeight'] : 
                    (isset($yAxis['categoryHeight']) ? $yAxis['categoryHeight'] * 0.8 : 40);
        
        // Eckenradius für Balken
        $cornerRadius = isset($seriesOptions['waterfall']['cornerRadius']) ? 
                      $seriesOptions['waterfall']['cornerRadius'] : 0;
        
        // Berechne den initialen Wert und die Summe
        $runningTotal = isset($seriesOptions['waterfall']['initialValue']) ? 
                      $seriesOptions['waterfall']['initialValue'] : 0;
        
        // Definiere Typen für Balken
        $barTypes = isset($seriesOptions['waterfall']['barTypes']) ? 
                  $seriesOptions['waterfall']['barTypes'] : [];
        
        // Individuelle Balkenfarben
        $individualColors = isset($seriesOptions['waterfall']['colors']) ? 
                          $seriesOptions['waterfall']['colors'] : [];
        
        // Finde die X-Null-Position
        $zeroX = $this->axes->convertXValueToCoordinate(0, $xAxis, $chartArea);
        
        $output = '';
        $connectorPoints = [];
        
        // Für jeden Datenpunkt
        for ($i = 0; $i < count($seriesX); $i++) {
            // Der aktuelle Wert
            $value = isset($seriesX[$i]) ? $seriesX[$i] : 0;
            
            // Bestimme den Balkentyp
            $barType = 'normal';
            if (isset($barTypes[$i])) {
                $barType = $barTypes[$i];
            } else if ($value > 0) {
                $barType = 'positive';
            } else if ($value < 0) {
                $barType = 'negative';
            }
            
            // Bestimme die Balkenfarbe basierend auf dem Typ
            $barColor = $defaultColors['positive']; // Standardfarbe
            
            if ($useIndividualColors && isset($individualColors[$i])) {
                // Verwende individuelle Farbe für diesen Balken
                $barColor = isset($individualColors[$i]['gradientId']) ? 
                          $individualColors[$i]['gradientId'] : 
                          $individualColors[$i]['color'];
            } else {
                // Verwende die Standardfarben basierend auf dem Balkentyp
                switch ($barType) {
                    case 'positive':
                        $barColor = $defaultColors['positive'];
                        break;
                    case 'negative':
                        $barColor = $defaultColors['negative'];
                        break;
                    case 'total':
                        $barColor = $defaultColors['total'];
                        break;
                    case 'subtotal':
                        $barColor = $defaultColors['subtotal'];
                        break;
                }
            }
            
            // Y-Position des Balkens - WICHTIG: Hier den Index verwenden, nicht den Wert!
            $y = $this->axes->convertYValueToCoordinate($i, $yAxis, $chartArea);
            $y = $y - $barHeight / 2; // Verschieben, damit der Balken zentriert ist
            
            // Bei Subtotals und Totals Werte anzeigen
            $displayValue = $value;
            
            // Bei Totals und Subtotals ist der Wert absolut, sonst relativ zur bisherigen Summe
            $startValue = $runningTotal;
            if ($barType === 'total' || $barType === 'subtotal') {
                $endValue = $value;
                // Für Subtotals und Totals zeigen wir den tatsächlichen Wert an, nicht 0
                $displayValue = $runningTotal;
            } else {
                $endValue = $runningTotal + $value;
                $displayValue = $value;
            }
            
            // Aktualisiere die laufende Summe, außer bei Totals und Subtotals
            if ($barType !== 'total' && $barType !== 'subtotal') {
                $runningTotal = $endValue;
            }
            
            // X-Positionen berechnen
            $x1 = $this->axes->convertXValueToCoordinate($startValue, $xAxis, $chartArea);
            $x2 = $this->axes->convertXValueToCoordinate($endValue, $xAxis, $chartArea);
            
            // Stelle sicher, dass x1 immer die linke Kante ist
            $barLeft = min($x1, $x2);
            $barWidth = abs($x2 - $x1);
            
            // Balken rendern
            $output .= $this->svg->createRect(
                $barLeft,
                $y,
                $barWidth,
                $barHeight,
                [
                    'fill' => $barColor,
                    'fillOpacity' => isset($seriesOptions['fillOpacity']) ? $seriesOptions['fillOpacity'] : 1,
                    'rx' => $cornerRadius,
                    'ry' => $cornerRadius
                ]
            );
            
            // Verbindungslinien berechnen - auch für Subtotals und Totals
            if ($i > 0) {
                $prevEndX = $this->axes->convertXValueToCoordinate($startValue, $xAxis, $chartArea);
                $prevY = $this->axes->convertYValueToCoordinate($i - 1, $yAxis, $chartArea);
                $currentY = $this->axes->convertYValueToCoordinate($i, $yAxis, $chartArea);
                
                $connectorPoints[] = [
                    'x1' => $prevEndX,
                    'y1' => $prevY + $barHeight / 2, // Mitte des vorherigen Balkens
                    'x2' => $prevEndX,
                    'y2' => $currentY - $barHeight / 2 // Mitte des aktuellen Balkens
                ];
            }
            
            // Datenwertbeschriftung rendern, falls aktiviert
            if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
                $labelX = ($x1 + $x2) / 2; // Mitte des Balkens
                $labelY = $y + $barHeight / 2;
                
                $labelText = isset($seriesOptions['dataLabels']['format']) ? 
                           str_replace('{y}', $this->utils->formatNumber($displayValue), $seriesOptions['dataLabels']['format']) : 
                           $this->utils->formatNumber($displayValue);
                
                $output .= $this->svg->createText(
                    $labelX,
                    $labelY,
                    $labelText,
                    [
                        'fontFamily' => isset($seriesOptions['dataLabels']['fontFamily']) ? $seriesOptions['dataLabels']['fontFamily'] : 'Arial, Helvetica, sans-serif',
                        'fontSize' => isset($seriesOptions['dataLabels']['fontSize']) ? $seriesOptions['dataLabels']['fontSize'] : 12,
                        'fontWeight' => isset($seriesOptions['dataLabels']['fontWeight']) ? $seriesOptions['dataLabels']['fontWeight'] : 'normal',
                        'fill' => isset($seriesOptions['dataLabels']['color']) ? $seriesOptions['dataLabels']['color'] : '#333333',
                        'textAnchor' => 'middle',
                        'dominantBaseline' => 'middle'
                    ]
                );
            }
        }
        
        // Konnektoren (Verbindungslinien) rendern, falls aktiviert
        if (isset($seriesOptions['waterfall']['connectors']) && isset($seriesOptions['waterfall']['connectors']['enabled']) && $seriesOptions['waterfall']['connectors']['enabled']) {
            $connectorColor = isset($seriesOptions['waterfall']['connectors']['color']) ? 
                            $seriesOptions['waterfall']['connectors']['color'] : '#999999';
            $connectorWidth = isset($seriesOptions['waterfall']['connectors']['width']) ? 
                            $seriesOptions['waterfall']['connectors']['width'] : 1;
            $connectorDashArray = isset($seriesOptions['waterfall']['connectors']['dashArray']) ? 
                                $seriesOptions['waterfall']['connectors']['dashArray'] : '';
            
            foreach ($connectorPoints as $connector) {
                $output .= $this->svg->createLine(
                    $connector['x1'],
                    $connector['y1'],
                    $connector['x2'],
                    $connector['y2'],
                    [
                        'stroke' => $connectorColor,
                        'strokeWidth' => $connectorWidth,
                        'strokeDasharray' => $connectorDashArray
                    ]
                );
            }
        }
        
        return $output;
    }
}
?>