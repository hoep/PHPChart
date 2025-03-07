<?php
/**
 * ChartScatterChart - Streudiagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Streudiagrammen zuständig.
 * Sie unterstützt individuelle Datenpunkte mit X- und Y-Koordinaten, verschiedene
 * Punktformen und optionale Verbindungslinien.
 * 
 * @version 1.0
 */
class ChartScatterChart {
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
     * Rendert ein Streudiagramm
     * 
     * @param array $seriesGroup Gruppe von Scatter-Diagramm-Serien
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Streudiagramms
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
            $output .= $this->renderScatterSeries(
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
     * Erstellt Gradienten für alle Serien, die diese benötigen
     * 
     * @param array $seriesGroup Gruppe von Scatter-Diagramm-Serien
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
            
            // Individuelle Punkt-Gradienten prüfen
            if (isset($seriesOptions['points']) && is_array($seriesOptions['points'])) {
                foreach ($seriesOptions['points'] as $index => $pointOptions) {
                    // Prüfen, ob der Eintrag ein Gradient hat
                    if (isset($pointOptions['gradient']) && isset($pointOptions['gradient']['enabled']) && $pointOptions['gradient']['enabled']) {
                        $safeSeriesName = preg_replace('/[^a-zA-Z0-9]/', '_', $seriesName);
                        $gradientId = 'gradient_' . $safeSeriesName . '_point_' . $index;
                        
                        // Speichere Gradientendefinition im Cache
                        $cacheKey = $seriesName . '_point_' . $index;
                        $this->gradientCache[$cacheKey] = [
                            'id' => $gradientId,
                            'options' => $pointOptions['gradient'],
                            'color' => isset($pointOptions['color']) ? $pointOptions['color'] : '#000000'
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
            if (strpos($key, '_point_') !== false) {
                // Individueller Punkt nach Index
                list($seriesName, $rest) = explode('_point_', $key);
                $index = intval($rest); // Extrahiere den Index als Zahl
                
                if (isset($updatedSeriesGroup[$seriesName]) && 
                    isset($updatedSeriesGroup[$seriesName]['points']) && 
                    isset($updatedSeriesGroup[$seriesName]['points'][$index])) {
                    $updatedSeriesGroup[$seriesName]['points'][$index]['gradientId'] = 'url(#' . $gradientInfo['id'] . ')';
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
     * Rendert eine Scatter-Diagramm-Serie
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente der Scatter-Serie
     */
    private function renderScatterSeries($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea) {
        // Bestimme die zu verwendenden Achsen
        $xAxisId = isset($seriesOptions['xAxisId']) ? $seriesOptions['xAxisId'] : 0;
        $yAxisId = isset($seriesOptions['yAxisId']) ? $seriesOptions['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Hole die X- und Y-Werte für diese Serie
        $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
        $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Wenn keine Werte vorhanden sind, nichts rendern
        if (empty($seriesX) || empty($seriesY)) {
            return '';
        }
        
        // Initialisiere Output
        $output = '';
        $points = [];
        
        // Sammele alle gültigen Punkte
        for ($i = 0; $i < count($seriesX); $i++) {
            if (!isset($seriesY[$i])) continue;
            
            $xValue = $seriesX[$i];
            $yValue = $seriesY[$i];
            
            // Ignorieren, wenn X oder Y nicht numerisch ist
            if ($xValue === null || $yValue === null || $xValue === '' || $yValue === '' || 
                !is_numeric($xValue) || !is_numeric($yValue)) {
                continue;
            }
            
            // X- und Y-Koordinaten berechnen
            $x = $this->axes->convertXValueToCoordinate($xValue, $xAxis, $chartArea);
            $y = $this->axes->convertYValueToCoordinate($yValue, $yAxis, $chartArea);
            
            $points[] = [
                'x' => $x,
                'y' => $y,
                'xValue' => $xValue,
                'yValue' => $yValue,
                'index' => $i
            ];
        }
        
        // Wenn Verbindungslinien aktiviert sind, rendere diese zuerst
        if (isset($seriesOptions['connectPoints']) && $seriesOptions['connectPoints'] === true) {
            $linePoints = array_map(function($point) {
                return [$point['x'], $point['y']];
            }, $points);
            
            // Linienoptionen
            $lineColor = isset($seriesOptions['lineColor']) ? $seriesOptions['lineColor'] : 
                       (isset($seriesOptions['color']) ? $seriesOptions['color'] : '#000000');
            $lineWidth = isset($seriesOptions['lineWidth']) ? $seriesOptions['lineWidth'] : 1;
            $lineDashArray = isset($seriesOptions['lineDashArray']) ? $seriesOptions['lineDashArray'] : '';
            
            if (!empty($linePoints)) {
                $output .= $this->svg->createPolyline(
                    $linePoints,
                    [
                        'fill' => 'none',
                        'stroke' => $lineColor,
                        'strokeWidth' => $lineWidth,
                        'strokeDasharray' => $lineDashArray
                    ]
                );
            }
        }
        
        // Standardoptionen für Datenpunkte
        $defaultPointColor = isset($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
        $defaultPointSize = isset($seriesOptions['point']) && isset($seriesOptions['point']['size']) ? 
                           $seriesOptions['point']['size'] : 5;
        $defaultPointShape = isset($seriesOptions['point']) && isset($seriesOptions['point']['shape']) ? 
                            $seriesOptions['point']['shape'] : 'circle';
        $defaultBorderColor = isset($seriesOptions['point']) && isset($seriesOptions['point']['borderColor']) ? 
                             $seriesOptions['point']['borderColor'] : '';
        $defaultBorderWidth = isset($seriesOptions['point']) && isset($seriesOptions['point']['borderWidth']) ? 
                             $seriesOptions['point']['borderWidth'] : 1;
        
        // Default Gradient ID für alle Punkte
        $defaultGradientId = isset($seriesOptions['gradientId']) ? $seriesOptions['gradientId'] : null;
        
        // Individuelle Punkt-Definitionen
        $individualPoints = isset($seriesOptions['points']) ? $seriesOptions['points'] : [];
        
        // Rendere jeden Punkt
        foreach ($points as $point) {
            $x = $point['x'];
            $y = $point['y'];
            $index = $point['index'];
            
            // Bestimme die Punkteigenschaften (Standard oder individuell)
            $pointColor = $defaultPointColor;
            $pointSize = $defaultPointSize;
            $pointShape = $defaultPointShape;
            $borderColor = $defaultBorderColor;
            $borderWidth = $defaultBorderWidth;
            $fillColor = $defaultGradientId ? $defaultGradientId : $defaultPointColor;
            
            // Prüfe auf individuelle Punktoptionen
            if (isset($individualPoints[$index])) {
                $pointOptions = $individualPoints[$index];
                
                if (isset($pointOptions['color'])) $pointColor = $pointOptions['color'];
                if (isset($pointOptions['size'])) $pointSize = $pointOptions['size'];
                if (isset($pointOptions['shape'])) $pointShape = $pointOptions['shape'];
                if (isset($pointOptions['borderColor'])) $borderColor = $pointOptions['borderColor'];
                if (isset($pointOptions['borderWidth'])) $borderWidth = $pointOptions['borderWidth'];
                
                // Verwende individuelle Gradient-ID, falls vorhanden
                $fillColor = isset($pointOptions['gradientId']) ? $pointOptions['gradientId'] : $pointColor;
            }
            
            // Rendere den Punkt basierend auf der Form
            switch ($pointShape) {
                case 'circle':
                    $output .= $this->svg->createCircle(
                        $x,
                        $y,
                        $pointSize / 2,
                        [
                            'fill' => $fillColor,
                            'stroke' => $borderColor,
                            'strokeWidth' => $borderWidth
                        ]
                    );
                    break;
                    
                case 'square':
                    $output .= $this->svg->createRect(
                        $x - $pointSize / 2,
                        $y - $pointSize / 2,
                        $pointSize,
                        $pointSize,
                        [
                            'fill' => $fillColor,
                            'stroke' => $borderColor,
                            'strokeWidth' => $borderWidth
                        ]
                    );
                    break;
                    
                case 'triangle':
                    $points = [
                        [$x, $y - $pointSize / 2],
                        [$x - $pointSize / 2, $y + $pointSize / 2],
                        [$x + $pointSize / 2, $y + $pointSize / 2]
                    ];
                    
                    $output .= $this->svg->createPolygon(
                        $points,
                        [
                            'fill' => $fillColor,
                            'stroke' => $borderColor,
                            'strokeWidth' => $borderWidth
                        ]
                    );
                    break;
                    
                case 'diamond':
                    $points = [
                        [$x, $y - $pointSize / 2],
                        [$x + $pointSize / 2, $y],
                        [$x, $y + $pointSize / 2],
                        [$x - $pointSize / 2, $y]
                    ];
                    
                    $output .= $this->svg->createPolygon(
                        $points,
                        [
                            'fill' => $fillColor,
                            'stroke' => $borderColor,
                            'strokeWidth' => $borderWidth
                        ]
                    );
                    break;
                    
                default:
                    // Standardmäßig Kreis
                    $output .= $this->svg->createCircle(
                        $x,
                        $y,
                        $pointSize / 2,
                        [
                            'fill' => $fillColor,
                            'stroke' => $borderColor,
                            'strokeWidth' => $borderWidth
                        ]
                    );
                    break;
            }
        }
        
        // Datenwertbeschriftungen rendern, falls aktiviert
        if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
            $output .= $this->renderDataLabels($points, $seriesOptions);
        }
        
        return $output;
    }
    
    /**
     * Rendert Datenwertbeschriftungen für die Punkte
     * 
     * @param array $points Array mit Punktdaten
     * @param array $seriesOptions Optionen für die Serie
     * @return string SVG-Elemente der Datenwertbeschriftungen
     */
    private function renderDataLabels($points, $seriesOptions) {
        $output = '';
        
        // Optionen für Datenwertbeschriftungen
        $offsetX = isset($seriesOptions['dataLabels']['offsetX']) ? $seriesOptions['dataLabels']['offsetX'] : 0;
        $offsetY = isset($seriesOptions['dataLabels']['offsetY']) ? $seriesOptions['dataLabels']['offsetY'] : -15;
        $fontFamily = isset($seriesOptions['dataLabels']['fontFamily']) ? $seriesOptions['dataLabels']['fontFamily'] : 'Arial, Helvetica, sans-serif';
        $fontSize = isset($seriesOptions['dataLabels']['fontSize']) ? $seriesOptions['dataLabels']['fontSize'] : 11;
        $fontWeight = isset($seriesOptions['dataLabels']['fontWeight']) ? $seriesOptions['dataLabels']['fontWeight'] : 'normal';
        $color = isset($seriesOptions['dataLabels']['color']) ? $seriesOptions['dataLabels']['color'] : '#333333';
        $format = isset($seriesOptions['dataLabels']['format']) ? $seriesOptions['dataLabels']['format'] : '{y}';
        $rotation = isset($seriesOptions['dataLabels']['rotation']) ? $seriesOptions['dataLabels']['rotation'] : 0;
        
        // Individuelle Datenlabels
        $individualLabels = isset($seriesOptions['dataLabels']['labels']) ? $seriesOptions['dataLabels']['labels'] : [];
        
        // Datenwertbeschriftungen rendern
        foreach ($points as $point) {
            $x = $point['x'];
            $y = $point['y'];
            $xValue = $point['xValue'];
            $yValue = $point['yValue'];
            $index = $point['index'];
            
            // Formatierungsoptionen (Standard oder individuell)
            $currentOffsetX = $offsetX;
            $currentOffsetY = $offsetY;
            $currentColor = $color;
            $currentFormat = $format;
            
            // Individuelle Label-Optionen prüfen
            if (isset($individualLabels[$index])) {
                $labelOptions = $individualLabels[$index];
                
                if (isset($labelOptions['offsetX'])) $currentOffsetX = $labelOptions['offsetX'];
                if (isset($labelOptions['offsetY'])) $currentOffsetY = $labelOptions['offsetY'];
                if (isset($labelOptions['color'])) $currentColor = $labelOptions['color'];
                if (isset($labelOptions['format'])) $currentFormat = $labelOptions['format'];
            }
            
            // Formatierung des Labels
            $label = $currentFormat;
            $label = str_replace('{x}', $this->utils->formatNumber($xValue), $label);
            $label = str_replace('{y}', $this->utils->formatNumber($yValue), $label);
            
            // Label rendern
            $output .= $this->svg->createText(
                $x + $currentOffsetX,
                $y + $currentOffsetY,
                $label,
                [
                    'fontFamily' => $fontFamily,
                    'fontSize' => $fontSize,
                    'fontWeight' => $fontWeight,
                    'fill' => $currentColor,
                    'textAnchor' => 'middle',
                    'rotate' => $rotation
                ]
            );
        }
        
        return $output;
    }
}
?>