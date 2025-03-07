<?php
/**
 * ChartWaterfallChart - Waterfall-Diagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Waterfall-Diagrammen zuständig,
 * einschließlich vertikaler und horizontaler Varianten.
 * 
 * @version 2.6
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
     * @param array $xValues Array mit X-Werten (Kategorien oder Werte)
     * @param array $yValues Array mit Y-Werten (Werte oder Kategorien)
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
        
        // Initialisiere die Gradienten-Cache vor jeder Nutzung
        $this->gradientCache = [];
        
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
            // Gradienten für die Hauptserie prüfen
            if (isset($seriesOptions['gradient']) && isset($seriesOptions['gradient']['enabled']) && $seriesOptions['gradient']['enabled']) {
                // Generiere eine sichere ID ohne Leerzeichen oder ungültige Zeichen
                $safeSeriesName = preg_replace('/[^a-zA-Z0-9]/', '_', $seriesName);
                $gradientId = 'gradient_' . $safeSeriesName . '_' . $this->utils->generateId();
                
                // Speichere Gradientendefinition im Cache
                $this->gradientCache[$seriesName] = [
                    'id' => $gradientId,
                    'options' => $seriesOptions['gradient'],
                    'horizontal' => $horizontal,
                    'color' => isset($seriesOptions['color']) ? $seriesOptions['color'] : '#000000'
                ];
            }
            
            // Nur fortfahren, wenn waterfall-Optionen vorhanden sind
            if (!isset($seriesOptions['waterfall'])) continue;
            
            // Gradienten für individuelle Balkentypen prüfen (initial, positive, negative, total, subtotal)
            $types = ['initial', 'positive', 'negative', 'total', 'subtotal'];
            foreach ($types as $type) {
                $colorKey = $type . 'Color';
                $gradientKey = $type . 'Gradient';
                
                // Prüfen ob Gradient für diesen Typ definiert ist
                if (isset($seriesOptions['waterfall'][$colorKey]) && 
                    isset($seriesOptions['waterfall'][$gradientKey]) && 
                    isset($seriesOptions['waterfall'][$gradientKey]['enabled']) && 
                    $seriesOptions['waterfall'][$gradientKey]['enabled']) {
                    
                    $color = $seriesOptions['waterfall'][$colorKey];
                    $safeSeriesName = preg_replace('/[^a-zA-Z0-9]/', '_', $seriesName);
                    $gradientId = 'gradient_' . $safeSeriesName . '_' . $type . '_' . $this->utils->generateId();
                    
                    // Speichere Gradientendefinition im Cache
                    $this->gradientCache[$seriesName . '_' . $type] = [
                        'id' => $gradientId,
                        'options' => $seriesOptions['waterfall'][$gradientKey],
                        'horizontal' => $horizontal,
                        'color' => $color
                    ];
                }
            }
            
            // Individuelle Balkenfarben prüfen
            if (isset($seriesOptions['waterfall']['colors']) && is_array($seriesOptions['waterfall']['colors'])) {
                foreach ($seriesOptions['waterfall']['colors'] as $index => $colorData) {
                    // Prüfen, ob der Eintrag ein Gradient hat
                    if (isset($colorData['gradient']) && isset($colorData['gradient']['enabled']) && $colorData['gradient']['enabled']) {
                        $safeSeriesName = preg_replace('/[^a-zA-Z0-9]/', '_', $seriesName);
                        $gradientId = 'gradient_' . $safeSeriesName . '_bar_' . $index;
                        
                        // Speichere Gradientendefinition im Cache
                        $cacheKey = $seriesName . '_bar_' . $index;
                        $this->gradientCache[$cacheKey] = [
                            'id' => $gradientId,
                            'options' => $colorData['gradient'],
                            'horizontal' => $horizontal,
                            'color' => isset($colorData['color']) ? $colorData['color'] : '#000000'
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
            // Prüfe verschiedene Schlüsseltypen
            if (strpos($key, '_bar_') !== false) {
                // Individueller Balken nach Index
                list($seriesName, $rest) = explode('_bar_', $key);
                $index = intval($rest); // Extrahiere den Index als Zahl
                
                if (isset($updatedSeriesGroup[$seriesName]) && 
                    isset($updatedSeriesGroup[$seriesName]['waterfall']) && 
                    isset($updatedSeriesGroup[$seriesName]['waterfall']['colors']) && 
                    isset($updatedSeriesGroup[$seriesName]['waterfall']['colors'][$index])) {
                    $updatedSeriesGroup[$seriesName]['waterfall']['colors'][$index]['gradientId'] = 'url(#' . $gradientInfo['id'] . ')';
                }
            } else if (strpos($key, '_') !== false) {
                // Balkentyp (initial, positive, negative, total, subtotal)
                list($seriesName, $type) = explode('_', $key);
                
                if (isset($updatedSeriesGroup[$seriesName]) && 
                    isset($updatedSeriesGroup[$seriesName]['waterfall'])) {
                    $typeKey = $type . 'GradientId';
                    $updatedSeriesGroup[$seriesName]['waterfall'][$typeKey] = 'url(#' . $gradientInfo['id'] . ')';
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
        
        // Standardfarben für Waterfall-Diagramm
        $defaultColors = [
            'initial' => isset($seriesOptions['waterfall']['initialColor']) ? $seriesOptions['waterfall']['initialColor'] : '#1E88E5', // Blau
            'positive' => isset($seriesOptions['waterfall']['positiveColor']) ? $seriesOptions['waterfall']['positiveColor'] : '#4CAF50', // Grün
            'negative' => isset($seriesOptions['waterfall']['negativeColor']) ? $seriesOptions['waterfall']['negativeColor'] : '#F44336', // Rot
            'total' => isset($seriesOptions['waterfall']['totalColor']) ? $seriesOptions['waterfall']['totalColor'] : '#2196F3',        // Blau
            'subtotal' => isset($seriesOptions['waterfall']['subtotalColor']) ? $seriesOptions['waterfall']['subtotalColor'] : '#9C27B0' // Lila
        ];
        
        // Gradient-IDs für Standardtypen
        $gradientIds = [
            'initial' => isset($seriesOptions['waterfall']['initialGradientId']) ? $seriesOptions['waterfall']['initialGradientId'] : null,
            'positive' => isset($seriesOptions['waterfall']['positiveGradientId']) ? $seriesOptions['waterfall']['positiveGradientId'] : null,
            'negative' => isset($seriesOptions['waterfall']['negativeGradientId']) ? $seriesOptions['waterfall']['negativeGradientId'] : null,
            'total' => isset($seriesOptions['waterfall']['totalGradientId']) ? $seriesOptions['waterfall']['totalGradientId'] : null,
            'subtotal' => isset($seriesOptions['waterfall']['subtotalGradientId']) ? $seriesOptions['waterfall']['subtotalGradientId'] : null
        ];
        
        // Individuelle Balkenfarben
        $individualColors = isset($seriesOptions['waterfall']['colors']) ? 
                          $seriesOptions['waterfall']['colors'] : [];
        
        // Balkenbreite berechnen
        $barWidth = isset($seriesOptions['waterfall']['barWidth']) ? 
                    $seriesOptions['waterfall']['barWidth'] : 
                    (isset($xAxis['categoryWidth']) ? $xAxis['categoryWidth'] * 0.8 : 40);
        
        // Eckenradius für Balken
        $cornerRadius = isset($seriesOptions['waterfall']['cornerRadius']) ? 
                       $seriesOptions['waterfall']['cornerRadius'] : 0;
        
        // Berechne den initialen Wert und die Summe
        $initialValue = isset($seriesOptions['waterfall']['initialValue']) ? 
                       $seriesOptions['waterfall']['initialValue'] : 0;
        
        // Definiere Typen für Balken
        $barTypes = isset($seriesOptions['waterfall']['barTypes']) ? 
                   $seriesOptions['waterfall']['barTypes'] : [];
        
        // Finde die Y-Null-Position
        $zeroY = $this->axes->convertYValueToCoordinate(0, $yAxis, $chartArea);
        
        $output = '';
        $connectorPoints = [];
        $lastEndY = null;  // Letztwert für die Verbindungslinien
        $lastX = null;     // Letztwert X-Position
        
        // Für die automatische Subtotal-Berechnung
        $runningTotal = $initialValue;
        $subtotalValues = [];
        
        // Hole die X- und Y-Werte für diese Serie
        $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
        $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Durchlauf für automatische Subtotal-Berechnung
        for ($i = 0; $i < count($seriesY); $i++) {
            $value = isset($seriesY[$i]) ? $seriesY[$i] : 0;
            $barType = isset($barTypes[$i]) ? $barTypes[$i] : ($value > 0 ? 'positive' : ($value < 0 ? 'negative' : 'subtotal'));
            
            // Speichere aktuelle Werte für Subtotals und Totals
            if ($barType === 'initial') {
                $runningTotal = $value; // Setze den initialen Wert
            } else if ($barType === 'subtotal') {
                // Bei Subtotal: Wenn Wert 0 ist, berechne automatisch, sonst verwende den angegebenen Wert
                if ($value === 0) {
                    $subtotalValues[$i] = $runningTotal;
                } else {
                    // Bei explizitem Wert wird dieser unabhängig vom aktuellen runningTotal verwendet
                    $subtotalValues[$i] = $value;
                }
                // Setze runningTotal auf den Subtotal-Wert für nachfolgende Berechnungen
                $runningTotal = $subtotalValues[$i];
            } else if ($barType === 'total') {
                // Bei Total: Wenn Wert 0 ist, berechne automatisch, sonst verwende den angegebenen Wert
                if ($value === 0) {
                    $subtotalValues[$i] = $runningTotal;
                } else {
                    $subtotalValues[$i] = $value;
                }
            } else {
                // Normale Balken (positive oder negative)
                $runningTotal += $value;
            }
        }
        
        // Zurücksetzen für die tatsächliche Darstellung
        $runningTotal = $initialValue;
        
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
            
            // Behandle automatische Subtotal-Berechnung
            if ($barType === 'subtotal') {
                if ($value === 0) {
                    $value = $subtotalValues[$i];
                } else {
                    // Wenn expliziter Wert angegeben wurde, überschreibe den berechneten Wert
                    $subtotalValues[$i] = $value;
                }
            } else if ($barType === 'total') {
                if ($value === 0) {
                    $value = $subtotalValues[$i];
                } else {
                    // Wenn expliziter Wert angegeben wurde, überschreibe den berechneten Wert
                    $subtotalValues[$i] = $value;
                }
            }
            
            // Bestimme die Balkenfarbe basierend auf dem Typ
            $barColor = $defaultColors['positive']; // Standardfarbe
            $fillColor = $barColor;
            
            // Prüfe erst auf individuelle Farben
            if (isset($individualColors[$i])) {
                // Verwende individuelle Farbe für diesen Balken
                $barColor = isset($individualColors[$i]['color']) ? $individualColors[$i]['color'] : $barColor;
                $fillColor = isset($individualColors[$i]['gradientId']) ? $individualColors[$i]['gradientId'] : $barColor;
            } else {
                // Verwende die Standardfarben basierend auf dem Balkentyp
                switch ($barType) {
                    case 'initial':
                        $barColor = $defaultColors['initial'];
                        $fillColor = $gradientIds['initial'] ? $gradientIds['initial'] : $barColor;
                        break;
                    case 'positive':
                        $barColor = $defaultColors['positive'];
                        $fillColor = $gradientIds['positive'] ? $gradientIds['positive'] : $barColor;
                        break;
                    case 'negative':
                        $barColor = $defaultColors['negative'];
                        $fillColor = $gradientIds['negative'] ? $gradientIds['negative'] : $barColor;
                        break;
                    case 'total':
                        $barColor = $defaultColors['total'];
                        $fillColor = $gradientIds['total'] ? $gradientIds['total'] : $barColor;
                        break;
                    case 'subtotal':
                        $barColor = $defaultColors['subtotal'];
                        $fillColor = $gradientIds['subtotal'] ? $gradientIds['subtotal'] : $barColor;
                        break;
                }
            }
            
            // X-Position des Balkens
            $x = $this->axes->convertXValueToCoordinate($i, $xAxis, $chartArea);
            $x = $x - $barWidth / 2; // Verschieben, damit der Balken zentriert ist
            
            // Berechnung der Balken-Positionen und Label abhängig vom Typ
            if ($barType === 'initial') {
                // Initial-Balken: Start bei 0, Ende beim Initialwert
                $startValue = 0;
                $endValue = $value;
                $displayValue = $value;
                
                // Setze den Running Total auf den Initial-Wert
                $runningTotal = $value;
            } else if ($barType === 'total' || $barType === 'subtotal') {
                // Für Totals und Subtotals: Balken starten bei 0
                $startValue = 0;
                $endValue = $value;
                $displayValue = $value;
                
                // Bei Subtotal den Running Total für die folgenden Balken setzen
                if ($barType === 'subtotal') {
                    $runningTotal = $value;
                }
            } else {
                // Für normale Balken: Relativ zum laufenden Total
                $startValue = $runningTotal;
                $endValue = $runningTotal + $value;
                $displayValue = $value;
                
                // Aktualisiere running total nur für normale Balken
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
                    'fill' => $fillColor,
                    'fillOpacity' => isset($seriesOptions['fillOpacity']) ? $seriesOptions['fillOpacity'] : 1,
                    'rx' => $cornerRadius,
                    'ry' => $cornerRadius
                ]
            );
            
            // Verbindungslinien berechnen - auch für Subtotals und Totals
            if ($i > 0) {
                // Wenn aktueller Balken subtotal/total ist, nehmen wir das Ende des vorherigen Balkens als Start
                $prevEndY = $this->axes->convertYValueToCoordinate(
                    ($barType === 'subtotal' || $barType === 'total') ? $runningTotal : $startValue, 
                    $yAxis, 
                    $chartArea
                );
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
        
        // WICHTIG: Bei horizontalen Balken müssen wir die Werte anders behandeln!
        // Die X-Achse zeigt numerische Werte, die Y-Achse zeigt Kategorien
        
        // X-Werte für die Serie (wird auf der X-Achse dargestellt)
        $seriesValues = isset($xValues[$seriesName]) ? $xValues[$seriesName] : [];
        
        // Y-Werte für die Serie (Kategorien für die Y-Achse)
        $categories = [];
        
        // Versuche zuerst, Kategorien aus der Y-Achsendefinition zu bekommen
        if (isset($yAxis['categories']) && !empty($yAxis['categories'])) {
            $categories = $yAxis['categories'];
        } 
        // Andernfalls versuche, Kategorien aus den yValues zu bekommen
        else if (isset($yValues['default']) && !empty($yValues['default'])) {
            $categories = $yValues['default'];
        }
        // Falls immer noch keine Kategorien vorhanden sind, erstelle Standardkategorien
        else {
            $count = count($seriesValues);
            for ($i = 0; $i < $count; $i++) {
                $categories[] = "Kategorie " . ($i + 1);
            }
        }
        
        // Stelle sicher, dass die Achsen richtig konfiguriert sind
        if (!isset($yAxis['type']) || $yAxis['type'] !== 'category') {
            $yAxis['type'] = 'category';
            $yAxis['categories'] = $categories;
        }
        
        if (!isset($xAxis['type']) || $xAxis['type'] !== 'numeric') {
            $xAxis['type'] = 'numeric';
        }
        
        // Standardfarben für Waterfall-Diagramm
        $defaultColors = [
            'initial' => isset($seriesOptions['waterfall']['initialColor']) ? $seriesOptions['waterfall']['initialColor'] : '#1E88E5', // Blau
            'positive' => isset($seriesOptions['waterfall']['positiveColor']) ? $seriesOptions['waterfall']['positiveColor'] : '#4CAF50', // Grün
            'negative' => isset($seriesOptions['waterfall']['negativeColor']) ? $seriesOptions['waterfall']['negativeColor'] : '#F44336', // Rot
            'total' => isset($seriesOptions['waterfall']['totalColor']) ? $seriesOptions['waterfall']['totalColor'] : '#2196F3',        // Blau
            'subtotal' => isset($seriesOptions['waterfall']['subtotalColor']) ? $seriesOptions['waterfall']['subtotalColor'] : '#9C27B0' // Lila
        ];
        
        // Gradient-IDs für Standardtypen
        $gradientIds = [
            'initial' => isset($seriesOptions['waterfall']['initialGradientId']) ? $seriesOptions['waterfall']['initialGradientId'] : null,
            'positive' => isset($seriesOptions['waterfall']['positiveGradientId']) ? $seriesOptions['waterfall']['positiveGradientId'] : null,
            'negative' => isset($seriesOptions['waterfall']['negativeGradientId']) ? $seriesOptions['waterfall']['negativeGradientId'] : null,
            'total' => isset($seriesOptions['waterfall']['totalGradientId']) ? $seriesOptions['waterfall']['totalGradientId'] : null,
            'subtotal' => isset($seriesOptions['waterfall']['subtotalGradientId']) ? $seriesOptions['waterfall']['subtotalGradientId'] : null
        ];
        
        // Individuelle Balkenfarben
        $individualColors = isset($seriesOptions['waterfall']['colors']) ? 
                          $seriesOptions['waterfall']['colors'] : [];
        
        // Balkenhöhe berechnen
        $categoryCount = count($categories);
        $availableHeight = $chartArea['height'];
        $categoryHeight = $availableHeight / max(1, $categoryCount);
        $barHeight = isset($seriesOptions['waterfall']['barHeight']) ? 
                    $seriesOptions['waterfall']['barHeight'] : 
                    ($categoryHeight * 0.8);
        
        // Eckenradius für Balken
        $cornerRadius = isset($seriesOptions['waterfall']['cornerRadius']) ? 
                      $seriesOptions['waterfall']['cornerRadius'] : 0;
        
        // Berechne den initialen Wert und die Summe
        $initialValue = isset($seriesOptions['waterfall']['initialValue']) ? 
                      $seriesOptions['waterfall']['initialValue'] : 0;
        
        // Definiere Typen für Balken
        $barTypes = isset($seriesOptions['waterfall']['barTypes']) ? 
                  $seriesOptions['waterfall']['barTypes'] : [];
        
        // Finde die X-Null-Position
        $zeroX = $this->axes->convertXValueToCoordinate(0, $xAxis, $chartArea);
                      
        // Array zur Verfolgung der tatsächlichen X-Positionen der Balken für korrekte Connectors
        $endPositions = [];
        
        $output = '';
        $connectorPoints = [];
        
        // Für automatische Subtotal-Berechnung
        $runningTotal = $initialValue;
        $subtotalValues = [];
        
        // Hole die Y-Werte (repräsentieren die Kategorien)
        $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Erster Durchlauf für automatische Subtotal-Berechnung
        for ($i = 0; $i < count($seriesValues); $i++) {
            $value = isset($seriesValues[$i]) ? $seriesValues[$i] : 0;
            $barType = isset($barTypes[$i]) ? $barTypes[$i] : ($value > 0 ? 'positive' : ($value < 0 ? 'negative' : 'subtotal'));
            
            // Speichere aktuelle Werte für Subtotals
            if ($barType === 'initial') {
                $runningTotal = $value; // Setze den initialen Wert
            } else if ($barType === 'subtotal') {
                // Bei Subtotal: Wenn Wert 0 ist, berechne automatisch, sonst verwende den angegebenen Wert
                if ($value === 0) {
                    $subtotalValues[$i] = $runningTotal;
                } else {
                    $subtotalValues[$i] = $value;
                }
                // Setze runningTotal auf den Subtotal-Wert für nachfolgende Berechnungen
                $runningTotal = $subtotalValues[$i];
            } else if ($barType === 'total') {
                // Bei Total: Wenn Wert 0 ist, berechne automatisch, sonst verwende den angegebenen Wert
                if ($value === 0) {
                    $subtotalValues[$i] = $runningTotal;
                } else {
                    $subtotalValues[$i] = $value;
                }
            } else {
                // Normale Balken (positive oder negative)
                $runningTotal += $value;
            }
        }
        
        // Zurücksetzen für die tatsächliche Darstellung
        $runningTotal = $initialValue;
        
        // Für jeden Datenpunkt
        for ($i = 0; $i < count($seriesValues); $i++) {
            // Der aktuelle Wert
            $value = isset($seriesValues[$i]) ? $seriesValues[$i] : 0;
            
            // Bestimme den Balkentyp
            $barType = 'normal';
            if (isset($barTypes[$i])) {
                $barType = $barTypes[$i];
            } else if ($value > 0) {
                $barType = 'positive';
            } else if ($value < 0) {
                $barType = 'negative';
            }
            
            // Kategorie für diese Position
            $category = isset($categories[$i]) ? $categories[$i] : "Kategorie " . ($i + 1);
            
            // Behandle automatische Subtotal-Berechnung
            if ($barType === 'subtotal') {
                if ($value === 0) {
                    $value = $subtotalValues[$i];
                }
            } else if ($barType === 'total') {
                if ($value === 0) {
                    $value = $subtotalValues[$i];
                }
            }
            
            // Bestimme die Balkenfarbe basierend auf dem Typ
            $barColor = $defaultColors['positive']; // Standardfarbe
            $fillColor = $barColor;
            
            // Prüfe erst auf individuelle Farben
            if (isset($individualColors[$i])) {
                // Verwende individuelle Farbe für diesen Balken
                $barColor = isset($individualColors[$i]['color']) ? $individualColors[$i]['color'] : $barColor;
                $fillColor = isset($individualColors[$i]['gradientId']) ? $individualColors[$i]['gradientId'] : $barColor;
            } else {
                // Verwende die Standardfarben basierend auf dem Balkentyp
                switch ($barType) {
                    case 'initial':
                        $barColor = $defaultColors['initial'];
                        $fillColor = $gradientIds['initial'] ? $gradientIds['initial'] : $barColor;
                        break;
                    case 'positive':
                        $barColor = $defaultColors['positive'];
                        $fillColor = $gradientIds['positive'] ? $gradientIds['positive'] : $barColor;
                        break;
                    case 'negative':
                        $barColor = $defaultColors['negative'];
                        $fillColor = $gradientIds['negative'] ? $gradientIds['negative'] : $barColor;
                        break;
                    case 'total':
                        $barColor = $defaultColors['total'];
                        $fillColor = $gradientIds['total'] ? $gradientIds['total'] : $barColor;
                        break;
                    case 'subtotal':
                        $barColor = $defaultColors['subtotal'];
                        $fillColor = $gradientIds['subtotal'] ? $gradientIds['subtotal'] : $barColor;
                        break;
                }
            }
            
            // Y-Position des Balkens (entspricht der Kategorie auf der Y-Achse)
            $y = $this->axes->convertYValueToCoordinate($i, $yAxis, $chartArea);
            $y = $y - $barHeight / 2; // Verschieben, damit der Balken zentriert ist
            
            // Berechnung der Balken-Positionen und Label abhängig vom Typ
            if ($barType === 'initial') {
                // Initial-Balken: Start bei 0, Ende beim Initialwert
                $startValue = 0;
                $endValue = $value;
                $displayValue = $value;
                
                // Setze den Running Total auf den Initial-Wert
                $runningTotal = $value;
            } else if ($barType === 'total' || $barType === 'subtotal') {
                // Für Totals und Subtotals: Balken starten bei 0
                $startValue = 0;
                $endValue = $value;
                $displayValue = $value;
                
                // Bei Subtotal den Running Total für die folgenden Balken setzen
                if ($barType === 'subtotal') {
                    $runningTotal = $value;
                }
            } else {
                // Für normale Balken: Relativ zum laufenden Total
                $startValue = $runningTotal;
                $endValue = $runningTotal + $value;
                $displayValue = $value;
                
                // Aktualisiere running total nur für normale Balken
                $runningTotal = $endValue;
            }
            
            // Speichere die Werte für Connector-Berechnung
            $endPositions[$i] = [
                'startValue' => $startValue,
                'endValue' => $endValue,
                'type' => $barType
            ];
            
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
                    'fill' => $fillColor,
                    'fillOpacity' => isset($seriesOptions['fillOpacity']) ? $seriesOptions['fillOpacity'] : 1,
                    'rx' => $cornerRadius,
                    'ry' => $cornerRadius
                ]
            );
            
            // Verbindungslinien-Berechnung erfolgt nach der Balken-Erstellung
            
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
        
        // Berechne die Connector-Punkte nachträglich
        for ($i = 1; $i < count($seriesValues); $i++) {
            $prevEndPos = $endPositions[$i-1];
            $currPos = $endPositions[$i];
            
            // X-Position des Start-Connectors: Der Endwert des vorherigen Balkens
            $prevX = $this->axes->convertXValueToCoordinate($prevEndPos['endValue'], $xAxis, $chartArea);
            
            // X-Position des End-Connectors
            $currX = $this->axes->convertXValueToCoordinate(
                ($currPos['type'] === 'total' || $currPos['type'] === 'subtotal') 
                    ? $prevEndPos['endValue']  // Bei Subtotal/Total vom vorherigen Balkenende verbinden
                    : $currPos['startValue'],  // Bei normalen Balken zum Startpunkt
                $xAxis, 
                $chartArea
            );
            
            $prevY = $this->axes->convertYValueToCoordinate($i - 1, $yAxis, $chartArea);
            $currY = $this->axes->convertYValueToCoordinate($i, $yAxis, $chartArea);
            
            $connectorPoints[] = [
                'x1' => $prevX,
                'y1' => $prevY + $barHeight / 2, // Mitte des vorherigen Balkens
                'x2' => $currX,
                'y2' => $currY - $barHeight / 2  // Mitte des aktuellen Balkens
            ];
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