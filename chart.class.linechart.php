<?php
/**
 * ChartLineChart - Liniendiagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Liniendiagrammen zuständig,
 * einschließlich normaler Linien, Splines und gestufter Linien.
 * 
 * @version 1.2
 */
class ChartLineChart {
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
     * Konstruktor - Initialisiert die benötigten Objekte
     */
    public function __construct() {
        $this->utils = new ChartUtils();
        $this->svg = new ChartSVG();
        $this->axes = new ChartAxes();
    }
    
    /**
     * Rendert ein Liniendiagramm
     * 
     * @param array $seriesGroup Gruppe von Liniendiagramm-Serien
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Liniendiagramms
     */
    public function render($seriesGroup, $xValues, $yValues, $axes, $chartArea, $config) {
        // Initialisiere Ausgabe
        $output = '';
        
        // Rendere jede Linienserie
        foreach ($seriesGroup as $seriesName => $seriesOptions) {
            // Bestimme den Linientyp (normal, spline, stepped)
            $lineType = isset($seriesOptions['subtype']) ? $seriesOptions['subtype'] : 'normal';
            
            // Basierend auf dem Linientyp die entsprechende Render-Methode aufrufen
            if ($lineType === 'spline' || $seriesOptions['type'] === 'spline') {
                $output .= $this->renderSpline($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea);
            } else if (isset($seriesOptions['line']) && isset($seriesOptions['line']['stepped']) && $seriesOptions['line']['stepped']) {
                $output .= $this->renderSteppedLine($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea);
            } else {
                $output .= $this->renderLine($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea);
            }
        }
        
        return $output;
    }
    
    /**
     * Rendert eine einfache Linie
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente der Linie
     */
    private function renderLine($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea) {
        // Bestimme die zu verwendenden Achsen
        $xAxisId = isset($seriesOptions['xAxisId']) ? $seriesOptions['xAxisId'] : 0;
        $yAxisId = isset($seriesOptions['yAxisId']) ? $seriesOptions['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Hole die X- und Y-Werte für diese Serie
        $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
        $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Wenn keine X-Werte angegeben sind, verwende Indizes
        if (empty($seriesX)) {
            $seriesX = range(0, count($seriesY) - 1);
        }
        
        // Sammele die Punkte für die Linie
        $points = [];
        $validYValues = []; // Für spätere Verwendung bei Punkten und Labels
        
        foreach ($seriesY as $idx => $yValue) {
            if (!isset($seriesX[$idx])) continue;
            $xValue = $seriesX[$idx];
            
            // Ignorieren, wenn der Y-Wert null oder nicht numerisch ist, 
            // es sei denn, connectNulls ist aktiviert
            $connectNulls = isset($seriesOptions['line']) && isset($seriesOptions['line']['connectNulls']) && $seriesOptions['line']['connectNulls'];
            if (($yValue === null || $yValue === '' || !is_numeric($yValue)) && !$connectNulls) {
                continue;
            }
            
            // X-Koordinate basierend auf dem Achsentyp berechnen
            if ($xAxis['type'] === 'category') {
                $x = $this->axes->convertXValueToCoordinate($idx, $xAxis, $chartArea);
            } else {
                $x = $this->axes->convertXValueToCoordinate($xValue, $xAxis, $chartArea);
            }
            
            // Y-Koordinate berechnen
            $y = $this->axes->convertYValueToCoordinate($yValue, $yAxis, $chartArea);
            
            $points[] = [$x, $y, $yValue]; // Speichere den Y-Wert für Farbschwellen
            $validYValues[] = $yValue;
        }
        
        // Wenn keine Punkte vorhanden sind, nichts rendern
        if (empty($points)) {
            return '';
        }
        
        $output = '';
        
        // Linienoptionen
        $lineWidth = isset($seriesOptions['line']) && isset($seriesOptions['line']['width']) ? $seriesOptions['line']['width'] : 2;
        $lineColor = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
        $dashArray = isset($seriesOptions['line']) && isset($seriesOptions['line']['dashArray']) ? $seriesOptions['line']['dashArray'] : '';
        
        // Prüfe, ob farbliche Segmentierung basierend auf Y-Werten verwendet werden soll
        $useColorThresholds = isset($seriesOptions['line']) && isset($seriesOptions['line']['colorThresholds']) && is_array($seriesOptions['line']['colorThresholds']);
        
        if ($useColorThresholds) {
            // Segmentierte Linie mit verschiedenen Farben basierend auf Y-Werten
            $output .= $this->renderSegmentedLine($points, $seriesOptions, $lineWidth, $dashArray);
        } else {
            // Standardmäßige einfarbige Linie rendern
            $polylinePoints = [];
            foreach ($points as $point) {
                $polylinePoints[] = [$point[0], $point[1]];
            }
            
            $output .= $this->svg->createPolyline(
                $polylinePoints,
                [
                    'fill' => 'none',
                    'stroke' => $lineColor,
                    'strokeWidth' => $lineWidth,
                    'strokeDasharray' => $dashArray
                ]
            );
        }
        
        // Punkte rendern, falls aktiviert
        if (isset($seriesOptions['point']) && isset($seriesOptions['point']['enabled']) && $seriesOptions['point']['enabled']) {
            $pointsWithoutYValues = array_map(function($point) {
                return [$point[0], $point[1]];
            }, $points);
            $output .= $this->renderPoints($pointsWithoutYValues, $seriesOptions, $validYValues);
        }
        
        // Datenwertbeschriftungen rendern, falls aktiviert
        if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
            $pointsWithoutYValues = array_map(function($point) {
                return [$point[0], $point[1]];
            }, $points);
            $output .= $this->renderDataLabels($pointsWithoutYValues, $seriesOptions, $validYValues);
        }
        
        return $output;
    }
    
    /**
     * Rendert eine Linie mit farbigen Segmenten basierend auf Y-Werten
     * 
     * @param array $points Array mit Punkten als [x, y, yValue]
     * @param array $seriesOptions Optionen für die Serie
     * @param float $lineWidth Breite der Linie
     * @param string $dashArray Strichmuster (falls vorhanden)
     * @return string SVG-Elemente der segmentierten Linie
     */
    private function renderSegmentedLine($points, $seriesOptions, $lineWidth, $dashArray) {
        $output = '';
        $colorThresholds = $seriesOptions['line']['colorThresholds'];
        
        // Sortiere die Schwellenwerte nach aufsteigend
        usort($colorThresholds, function($a, $b) {
            return $a['value'] <=> $b['value'];
        });
        
        // Standardfarbe für Werte unter dem niedrigsten Schwellenwert
        $defaultColor = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
        
        // Rendere die Liniensegmente
        for ($i = 0; $i < count($points) - 1; $i++) {
            $startPoint = $points[$i];
            $endPoint = $points[$i + 1];
            $startValue = $startPoint[2];
            $endValue = $endPoint[2];
            
            // Bestimme die Farbe basierend auf dem Y-Wert
            $color = $this->getColorForValue($startValue, $colorThresholds, $defaultColor);
            
            // Erstelle das Liniensegment
            $output .= $this->svg->createLine(
                $startPoint[0],
                $startPoint[1],
                $endPoint[0],
                $endPoint[1],
                [
                    'stroke' => $color,
                    'strokeWidth' => $lineWidth,
                    'strokeDasharray' => $dashArray
                ]
            );
        }
        
        return $output;
    }
    
    /**
     * Bestimmt die Farbe für einen Wert anhand der definierten Schwellenwerte
     * 
     * @param float $value Der zu prüfende Wert
     * @param array $thresholds Array mit Schwellenwerten und Farben
     * @param string $defaultColor Standardfarbe, falls kein Schwellenwert passt
     * @return string Farbcode (Hex oder RGB)
     */
    private function getColorForValue($value, $thresholds, $defaultColor) {
        $color = $defaultColor; // Standardwert
        
        // Finde die passende Farbe basierend auf den Schwellenwerten
        foreach ($thresholds as $threshold) {
            if ($value <= $threshold['value']) {
                return $threshold['color'];
            }
        }
        
        // Wenn kein Schwellenwert passt, verwende die Farbe des höchsten Schwellenwerts
        if (!empty($thresholds)) {
            $highestThreshold = end($thresholds);
            return $highestThreshold['color'];
        }
        
        return $color;
    }
    
    /**
     * Rendert eine gestufte Linie
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente der gestuften Linie
     */
    private function renderSteppedLine($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea) {
        // Bestimme die zu verwendenden Achsen
        $xAxisId = isset($seriesOptions['xAxisId']) ? $seriesOptions['xAxisId'] : 0;
        $yAxisId = isset($seriesOptions['yAxisId']) ? $seriesOptions['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Hole die X- und Y-Werte für diese Serie
        $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
        $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Wenn keine X-Werte angegeben sind, verwende Indizes
        if (empty($seriesX)) {
            $seriesX = range(0, count($seriesY) - 1);
        }
        
        // Sammele die Punkte für die Linie
        $points = [];
        $prevX = null;
        $prevY = null;
        $validYValues = []; // Für spätere Verwendung bei Punkten und Labels
        
        foreach ($seriesY as $idx => $yValue) {
            if (!isset($seriesX[$idx])) continue;
            $xValue = $seriesX[$idx];
            
            // Ignorieren, wenn der Y-Wert null oder nicht numerisch ist, 
            // es sei denn, connectNulls ist aktiviert
            $connectNulls = isset($seriesOptions['line']) && isset($seriesOptions['line']['connectNulls']) && $seriesOptions['line']['connectNulls'];
            if (($yValue === null || $yValue === '' || !is_numeric($yValue)) && !$connectNulls) {
                continue;
            }
            
            // X-Koordinate basierend auf dem Achsentyp berechnen
            if ($xAxis['type'] === 'category') {
                $x = $this->axes->convertXValueToCoordinate($idx, $xAxis, $chartArea);
            } else {
                $x = $this->axes->convertXValueToCoordinate($xValue, $xAxis, $chartArea);
            }
            
            // Y-Koordinate berechnen
            $y = $this->axes->convertYValueToCoordinate($yValue, $yAxis, $chartArea);
            
            // Bei gestufter Linie müssen wir einen zusätzlichen Punkt einfügen
            if ($prevX !== null && $prevY !== null) {
                $points[] = [$x, $prevY, $validYValues[count($validYValues)-1]]; // Horizontale Linie mit dem letzten Y-Wert
            }
            
            $points[] = [$x, $y, $yValue];
            $validYValues[] = $yValue;
            $prevX = $x;
            $prevY = $y;
        }
        
        // Wenn keine Punkte vorhanden sind, nichts rendern
        if (empty($points)) {
            return '';
        }
        
        $output = '';
        
        // Linienoptionen
        $lineWidth = isset($seriesOptions['line']) && isset($seriesOptions['line']['width']) ? $seriesOptions['line']['width'] : 2;
        $lineColor = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
        $dashArray = isset($seriesOptions['line']) && isset($seriesOptions['line']['dashArray']) ? $seriesOptions['line']['dashArray'] : '';
        
        // Prüfe, ob farbliche Segmentierung basierend auf Y-Werten verwendet werden soll
        $useColorThresholds = isset($seriesOptions['line']) && isset($seriesOptions['line']['colorThresholds']) && is_array($seriesOptions['line']['colorThresholds']);
        
        if ($useColorThresholds) {
            // Segmentierte Linie mit verschiedenen Farben basierend auf Y-Werten
            $output .= $this->renderSegmentedLine($points, $seriesOptions, $lineWidth, $dashArray);
        } else {
            // Standardmäßige einfarbige Linie rendern
            $polylinePoints = [];
            foreach ($points as $point) {
                $polylinePoints[] = [$point[0], $point[1]];
            }
            
            $output .= $this->svg->createPolyline(
                $polylinePoints,
                [
                    'fill' => 'none',
                    'stroke' => $lineColor,
                    'strokeWidth' => $lineWidth,
                    'strokeDasharray' => $dashArray
                ]
            );
        }
        
        // Punkte rendern, falls aktiviert
        if (isset($seriesOptions['point']) && isset($seriesOptions['point']['enabled']) && $seriesOptions['point']['enabled']) {
            // Für gestufte Linien rendern wir nur die tatsächlichen Datenpunkte, nicht die Zwischenpunkte
            $dataPoints = [];
            foreach ($seriesY as $idx => $yValue) {
                if (!isset($seriesX[$idx])) continue;
                if (($yValue === null || $yValue === '' || !is_numeric($yValue))) continue;
                
                // X-Koordinate basierend auf dem Achsentyp berechnen
                if ($xAxis['type'] === 'category') {
                    $x = $this->axes->convertXValueToCoordinate($idx, $xAxis, $chartArea);
                } else {
                    $x = $this->axes->convertXValueToCoordinate($seriesX[$idx], $xAxis, $chartArea);
                }
                
                // Y-Koordinate berechnen
                $y = $this->axes->convertYValueToCoordinate($yValue, $yAxis, $chartArea);
                
                $dataPoints[] = [$x, $y];
            }
            
            $output .= $this->renderPoints($dataPoints, $seriesOptions, $validYValues);
        }
        
        // Datenwertbeschriftungen rendern, falls aktiviert
        if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
            // Auch hier nur für die tatsächlichen Datenpunkte
            $dataPoints = [];
            $dataValues = [];
            foreach ($seriesY as $idx => $yValue) {
                if (!isset($seriesX[$idx])) continue;
                if (($yValue === null || $yValue === '' || !is_numeric($yValue))) continue;
                
                // X-Koordinate basierend auf dem Achsentyp berechnen
                if ($xAxis['type'] === 'category') {
                    $x = $this->axes->convertXValueToCoordinate($idx, $xAxis, $chartArea);
                } else {
                    $x = $this->axes->convertXValueToCoordinate($seriesX[$idx], $xAxis, $chartArea);
                }
                
                // Y-Koordinate berechnen
                $y = $this->axes->convertYValueToCoordinate($yValue, $yAxis, $chartArea);
                
                $dataPoints[] = [$x, $y];
                $dataValues[] = $yValue;
            }
            
            $output .= $this->renderDataLabels($dataPoints, $seriesOptions, $dataValues);
        }
        
        return $output;
    }
    
    /**
     * Rendert eine Spline-Kurve (glatte Kurve durch die Punkte)
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten
     * @param array $yValues Array mit Y-Werten
     * @param array $axes Achsendefinitionen
     * @param array $chartArea Daten zum Zeichenbereich
     * @return string SVG-Elemente der Spline-Kurve
     */
    private function renderSpline($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea) {
        // Bestimme die zu verwendenden Achsen
        $xAxisId = isset($seriesOptions['xAxisId']) ? $seriesOptions['xAxisId'] : 0;
        $yAxisId = isset($seriesOptions['yAxisId']) ? $seriesOptions['yAxisId'] : 0;
        $xAxis = $axes['x'][$xAxisId];
        $yAxis = $axes['y'][$yAxisId];
        
        // Hole die X- und Y-Werte für diese Serie
        $seriesX = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
        $seriesY = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Wenn keine X-Werte angegeben sind, verwende Indizes
        if (empty($seriesX)) {
            $seriesX = range(0, count($seriesY) - 1);
        }
        
        // Sammele die Punkte für die Spline
        $points = [];
        $validYValues = []; // Für spätere Verwendung bei Punkten und Labels
        
        foreach ($seriesY as $idx => $yValue) {
            if (!isset($seriesX[$idx])) continue;
            $xValue = $seriesX[$idx];
            
            // Ignorieren, wenn der Y-Wert null oder nicht numerisch ist, 
            // es sei denn, connectNulls ist aktiviert
            $connectNulls = isset($seriesOptions['line']) && isset($seriesOptions['line']['connectNulls']) && $seriesOptions['line']['connectNulls'];
            if (($yValue === null || $yValue === '' || !is_numeric($yValue)) && !$connectNulls) {
                continue;
            }
            
            // X-Koordinate basierend auf dem Achsentyp berechnen
            if ($xAxis['type'] === 'category') {
                $x = $this->axes->convertXValueToCoordinate($idx, $xAxis, $chartArea);
            } else {
                $x = $this->axes->convertXValueToCoordinate($xValue, $xAxis, $chartArea);
            }
            
            // Y-Koordinate berechnen
            $y = $this->axes->convertYValueToCoordinate($yValue, $yAxis, $chartArea);
            
            $points[] = [$x, $y, $yValue];
            $validYValues[] = $yValue;
        }
        
        // Wenn weniger als 2 Punkte vorhanden sind, verwende normale Linie
        if (count($points) < 2) {
            return $this->renderLine($seriesName, $seriesOptions, $xValues, $yValues, $axes, $chartArea);
        }
        
        $output = '';
        
        // Linienoptionen
        $lineWidth = isset($seriesOptions['line']) && isset($seriesOptions['line']['width']) ? $seriesOptions['line']['width'] : 2;
        $lineColor = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
        $dashArray = isset($seriesOptions['line']) && isset($seriesOptions['line']['dashArray']) ? $seriesOptions['line']['dashArray'] : '';
        
        // Spline-Tension (Biegungsgrad) 0 bis 1
        $tension = isset($seriesOptions['line']) && isset($seriesOptions['line']['tension']) ? 
                 $seriesOptions['line']['tension'] : 0.5;
        
        // Prüfe, ob farbliche Segmentierung basierend auf Y-Werten verwendet werden soll
        $useColorThresholds = isset($seriesOptions['line']) && isset($seriesOptions['line']['colorThresholds']) && is_array($seriesOptions['line']['colorThresholds']);
        
        if ($useColorThresholds) {
            // Bei Splines ist die Segmentierung komplexer - wir müssen für jeden Abschnitt einen separaten Pfad erstellen
            $output .= $this->renderSegmentedSpline($points, $seriesOptions, $lineWidth, $dashArray, $tension);
        } else {
            // Standardmäßige einfarbige Spline rendern
            $pointsWithoutYValues = array_map(function($point) {
                return [$point[0], $point[1]];
            }, $points);
            
            // SVG-Pfad für die Spline erstellen
            $path = $this->createSplinePath($pointsWithoutYValues, $tension);
            
            // Spline rendern
            $output .= $this->svg->createPath(
                $path,
                [
                    'fill' => 'none',
                    'stroke' => $lineColor,
                    'strokeWidth' => $lineWidth,
                    'strokeDasharray' => $dashArray
                ]
            );
        }
        
        // Punkte rendern, falls aktiviert
        if (isset($seriesOptions['point']) && isset($seriesOptions['point']['enabled']) && $seriesOptions['point']['enabled']) {
            $pointsWithoutYValues = array_map(function($point) {
                return [$point[0], $point[1]];
            }, $points);
            $output .= $this->renderPoints($pointsWithoutYValues, $seriesOptions, $validYValues);
        }
        
        // Datenwertbeschriftungen rendern, falls aktiviert
        if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
            $pointsWithoutYValues = array_map(function($point) {
                return [$point[0], $point[1]];
            }, $points);
            $output .= $this->renderDataLabels($pointsWithoutYValues, $seriesOptions, $validYValues);
        }
        
        return $output;
    }
    
    /**
     * Rendert eine Spline-Kurve mit farbigen Segmenten basierend auf Y-Werten
     * 
     * @param array $points Array mit Punkten als [x, y, yValue]
     * @param array $seriesOptions Optionen für die Serie
     * @param float $lineWidth Breite der Linie
     * @param string $dashArray Strichmuster (falls vorhanden)
     * @param float $tension Spline-Spannung (0-1)
     * @return string SVG-Elemente der segmentierten Spline
     */
    private function renderSegmentedSpline($points, $seriesOptions, $lineWidth, $dashArray, $tension = 0.5) {
        $output = '';
        $colorThresholds = $seriesOptions['line']['colorThresholds'];
        
        // Sortiere die Schwellenwerte nach aufsteigend
        usort($colorThresholds, function($a, $b) {
            return $a['value'] <=> $b['value'];
        });
        
        // Standardfarbe für Werte unter dem niedrigsten Schwellenwert
        $defaultColor = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
        
        // Da wir die Spline nicht so einfach segmentieren können wie eine gerade Linie,
        // erstellen wir für jedes Segment von Punkt zu Punkt eine eigene Spline
        for ($i = 0; $i < count($points) - 1; $i++) {
            $segmentPoints = [$points[$i], $points[$i+1]];
            $yValue = $points[$i][2]; // Verwende den Y-Wert des Startpunkts
            
            // Bestimme die Farbe basierend auf dem Y-Wert
            $color = $this->getColorForValue($yValue, $colorThresholds, $defaultColor);
            
            // Für zwei Punkte können wir eine einfache Linie verwenden
            if (count($segmentPoints) == 2) {
                $startPoint = $segmentPoints[0];
                $endPoint = $segmentPoints[1];
                
                $output .= $this->svg->createLine(
                    $startPoint[0],
                    $startPoint[1],
                    $endPoint[0],
                    $endPoint[1],
                    [
                        'stroke' => $color,
                        'strokeWidth' => $lineWidth,
                        'strokeDasharray' => $dashArray
                    ]
                );
            } else {
                // Für mehr als zwei Punkte müssen wir eine Spline erstellen
                $pointsWithoutYValues = array_map(function($point) {
                    return [$point[0], $point[1]];
                }, $segmentPoints);
                
                // SVG-Pfad für die Segment-Spline erstellen
                $path = $this->createSplinePath($pointsWithoutYValues, $tension);
                
                // Segment-Spline rendern
                $output .= $this->svg->createPath(
                    $path,
                    [
                        'fill' => 'none',
                        'stroke' => $color,
                        'strokeWidth' => $lineWidth,
                        'strokeDasharray' => $dashArray
                    ]
                );
            }
        }
        
        return $output;
    }
    
    /**
     * Erstellt einen SVG-Pfad für eine Spline-Kurve
     * 
     * @param array $points Array mit Punkten als [x, y]-Arrays
     * @param float $tension Spannungsfaktor für die Kurve (0-1, 0=gerade Linie, 1=volle Krümmung)
     * @return string SVG-Pfad-Daten
     */
    private function createSplinePath($points, $tension = 0.5) {
        $path = '';
        $n = count($points);
        
        if ($n < 2) return '';
        
        // Beginne den Pfad am ersten Punkt
        $path = 'M' . $points[0][0] . ',' . $points[0][1];
        
        if ($n === 2) {
            // Bei nur zwei Punkten zeichne eine gerade Linie
            $path .= ' L' . $points[1][0] . ',' . $points[1][1];
        } else {
            // Spannungsfaktor begrenzen (0 bis 1)
            $tension = max(0, min(1, $tension));
            // Faktor, der die Länge der Tangenten beeinflusst
            // Bei tension=0 sind die Tangenten 0 (gerade Linie)
            // Bei tension=1 sind die Tangenten etwa 1/3 der Strecke zum nächsten Punkt
            $factor = $tension / 3;
            
            // Berechne Kontrollpunkte für den kubischen Spline
            for ($i = 0; $i < $n - 1; $i++) {
                $x1 = $points[$i][0];
                $y1 = $points[$i][1];
                $x2 = $points[$i + 1][0];
                $y2 = $points[$i + 1][1];
                
                // Bestimme die Kontrollpunkte für die kubische Bézierkurve
                if ($i === 0) {
                    // Erster Punkt
                    $cp1x = $x1 + ($x2 - $x1) * $factor;
                    $cp1y = $y1 + ($y2 - $y1) * $factor;
                } else {
                    // Verwende den vorherigen Punkt zur Berechnung
                    $cp1x = $x1 + ($x2 - $points[$i - 1][0]) * $factor;
                    $cp1y = $y1 + ($y2 - $points[$i - 1][1]) * $factor;
                }
                
                if ($i === $n - 2) {
                    // Letzter Punkt
                    $cp2x = $x2 - ($x2 - $x1) * $factor;
                    $cp2y = $y2 - ($y2 - $y1) * $factor;
                } else {
                    // Verwende den nächsten Punkt zur Berechnung
                    $cp2x = $x2 - ($points[$i + 2][0] - $x1) * $factor;
                    $cp2y = $y2 - ($points[$i + 2][1] - $y1) * $factor;
                }
                
                // Füge die kubische Bézierkurve zum Pfad hinzu
                $path .= ' C' . $cp1x . ',' . $cp1y . ' ' . $cp2x . ',' . $cp2y . ' ' . $x2 . ',' . $y2;
            }
        }
        
        return $path;
    }
    
    /**
     * Rendert die Punkte einer Linie
     * 
     * @param array $points Array mit Punkten als [x, y]-Arrays
     * @param array $seriesOptions Optionen für die Serie
     * @param array $yValues Y-Werte für die Punkte
     * @return string SVG-Elemente der Punkte
     */
    private function renderPoints($points, $seriesOptions, $yValues) {
        $output = '';
        
        // Punktoptionen
        $pointSize = isset($seriesOptions['point']['size']) ? $seriesOptions['point']['size'] : 5;
        $pointColor = isset($seriesOptions['point']['color']) && $seriesOptions['point']['color'] ? 
                     $seriesOptions['point']['color'] : 
                     (!empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000');
        $pointShape = isset($seriesOptions['point']['shape']) ? $seriesOptions['point']['shape'] : 'circle';
        $borderColor = isset($seriesOptions['point']['borderColor']) ? $seriesOptions['point']['borderColor'] : '';
        $borderWidth = isset($seriesOptions['point']['borderWidth']) ? $seriesOptions['point']['borderWidth'] : 1;
        
        // Prüfe, ob farbliche Punkte basierend auf Y-Werten verwendet werden sollen
        $useColorThresholds = isset($seriesOptions['point']) && isset($seriesOptions['point']['useSeriesThresholds']) && 
                             $seriesOptions['point']['useSeriesThresholds'] && 
                             isset($seriesOptions['line']) && isset($seriesOptions['line']['colorThresholds']);
        
        // Rendere jeden Punkt
        foreach ($points as $idx => $point) {
            $x = $point[0];
            $y = $point[1];
            $yValue = isset($yValues[$idx]) ? $yValues[$idx] : null;
            
            // Bestimme die Punktfarbe
            $color = $pointColor;
            if ($useColorThresholds && $yValue !== null) {
                $color = $this->getColorForValue($yValue, $seriesOptions['line']['colorThresholds'], $pointColor);
            }
            
            switch ($pointShape) {
                case 'circle':
                    $output .= $this->svg->createCircle(
                        $x,
                        $y,
                        $pointSize / 2,
                        [
                            'fill' => $color,
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
                            'fill' => $color,
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
                            'fill' => $color,
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
                            'fill' => $color,
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
                            'fill' => $color,
                            'stroke' => $borderColor,
                            'strokeWidth' => $borderWidth
                        ]
                    );
                    break;
            }
        }
        
        return $output;
    }
    
    /**
     * Rendert die Datenwertbeschriftungen einer Linie
     * 
     * @param array $points Array mit Punkten als [x, y]-Arrays
     * @param array $seriesOptions Optionen für die Serie
     * @param array $yValues Y-Werte für die Punkte
     * @return string SVG-Elemente der Datenwertbeschriftungen
     */
    private function renderDataLabels($points, $seriesOptions, $yValues) {
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
        
        // Prüfe, ob farbliche Labels basierend auf Y-Werten verwendet werden sollen
        $useColorThresholds = isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['useSeriesThresholds']) && 
                             $seriesOptions['dataLabels']['useSeriesThresholds'] && 
                             isset($seriesOptions['line']) && isset($seriesOptions['line']['colorThresholds']);
        
        // Datenwertbeschriftungen rendern
        $validValues = array_filter($yValues, function($val) {
            return $val !== null && $val !== '' && is_numeric($val);
        });
        $validIdx = array_keys($validValues);
        
        foreach ($points as $idx => $point) {
            // Den entsprechenden Y-Wert finden
            $valueIdx = isset($validIdx[$idx]) ? $validIdx[$idx] : null;
            $yValue = $valueIdx !== null && isset($yValues[$valueIdx]) ? $yValues[$valueIdx] : null;
            
            if ($yValue === null || $yValue === '' || !is_numeric($yValue)) continue;
            
            $x = $point[0];
            $y = $point[1];
            
            // Bestimme die Labelfarbe
            $labelColor = $color;
            if ($useColorThresholds) {
                $labelColor = $this->getColorForValue($yValue, $seriesOptions['line']['colorThresholds'], $color);
            }
            
            // Formatierung des Labels
            $label = str_replace('{y}', $this->utils->formatNumber($yValue), $format);
            
            // Label rendern
            $output .= $this->svg->createText(
                $x + $offsetX,
                $y + $offsetY,
                $label,
                [
                    'fontFamily' => $fontFamily,
                    'fontSize' => $fontSize,
                    'fontWeight' => $fontWeight,
                    'fill' => $labelColor,
                    'textAnchor' => 'middle',
                    'rotate' => $rotation
                ]
            );
        }
        
        return $output;
    }
}
?>