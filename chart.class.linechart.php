<?php
/**
 * ChartLineChart - Liniendiagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Liniendiagrammen zuständig,
 * einschließlich normaler Linien, Splines und gestufter Linien.
 * 
 * @version 1.0
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
            
            $points[] = [$x, $y];
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
        
        // Linie rendern
        $output .= $this->svg->createPolyline(
            $points,
            [
                'fill' => 'none',
                'stroke' => $lineColor,
                'strokeWidth' => $lineWidth,
                'strokeDasharray' => $dashArray
            ]
        );
        
        // Punkte rendern, falls aktiviert
        if (isset($seriesOptions['point']) && isset($seriesOptions['point']['enabled']) && $seriesOptions['point']['enabled']) {
            $output .= $this->renderPoints($points, $seriesOptions, $seriesY);
        }
        
        // Datenwertbeschriftungen rendern, falls aktiviert
        if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
            $output .= $this->renderDataLabels($points, $seriesOptions, $seriesY);
        }
        
        return $output;
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
                $points[] = [$x, $prevY]; // Horizontale Linie
            }
            
            $points[] = [$x, $y];
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
        
        // Linie rendern
        $output .= $this->svg->createPolyline(
            $points,
            [
                'fill' => 'none',
                'stroke' => $lineColor,
                'strokeWidth' => $lineWidth,
                'strokeDasharray' => $dashArray
            ]
        );
        
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
            
            $output .= $this->renderPoints($dataPoints, $seriesOptions, $seriesY);
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
            
            $points[] = [$x, $y];
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
        
        // SVG-Pfad für die Spline erstellen
        $path = $this->createSplinePath($points);
        
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
        
        // Punkte rendern, falls aktiviert
        if (isset($seriesOptions['point']) && isset($seriesOptions['point']['enabled']) && $seriesOptions['point']['enabled']) {
            $output .= $this->renderPoints($points, $seriesOptions, $seriesY);
        }
        
        // Datenwertbeschriftungen rendern, falls aktiviert
        if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
            $output .= $this->renderDataLabels($points, $seriesOptions, $seriesY);
        }
        
        return $output;
    }
    
    /**
     * Erstellt einen SVG-Pfad für eine Spline-Kurve
     * 
     * @param array $points Array mit Punkten als [x, y]-Arrays
     * @return string SVG-Pfad-Daten
     */
    private function createSplinePath($points) {
        $path = '';
        $n = count($points);
        
        if ($n < 2) return '';
        
        // Beginne den Pfad am ersten Punkt
        $path = 'M' . $points[0][0] . ',' . $points[0][1];
        
        if ($n === 2) {
            // Bei nur zwei Punkten zeichne eine gerade Linie
            $path .= ' L' . $points[1][0] . ',' . $points[1][1];
        } else {
            // Berechne Kontrollpunkte für den kubischen Spline
            for ($i = 0; $i < $n - 1; $i++) {
                $x1 = $points[$i][0];
                $y1 = $points[$i][1];
                $x2 = $points[$i + 1][0];
                $y2 = $points[$i + 1][1];
                
                // Bestimme die Kontrollpunkte für die kubische Bézierkurve
                if ($i === 0) {
                    // Erster Punkt
                    $cp1x = $x1 + ($x2 - $x1) / 3;
                    $cp1y = $y1 + ($y2 - $y1) / 3;
                } else {
                    // Verwende den vorherigen Punkt zur Berechnung
                    $cp1x = $x1 + ($x2 - $points[$i - 1][0]) / 3;
                    $cp1y = $y1 + ($y2 - $points[$i - 1][1]) / 3;
                }
                
                if ($i === $n - 2) {
                    // Letzter Punkt
                    $cp2x = $x2 - ($x2 - $x1) / 3;
                    $cp2y = $y2 - ($y2 - $y1) / 3;
                } else {
                    // Verwende den nächsten Punkt zur Berechnung
                    $cp2x = $x2 - ($points[$i + 2][0] - $x1) / 3;
                    $cp2y = $y2 - ($points[$i + 2][1] - $y1) / 3;
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
        
        // Rendere jeden Punkt
        foreach ($points as $idx => $point) {
            $x = $point[0];
            $y = $point[1];
            
            switch ($pointShape) {
                case 'circle':
                    $output .= $this->svg->createCircle(
                        $x,
                        $y,
                        $pointSize / 2,
                        [
                            'fill' => $pointColor,
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
                            'fill' => $pointColor,
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
                            'fill' => $pointColor,
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
                            'fill' => $pointColor,
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
                            'fill' => $pointColor,
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
                    'fill' => $color,
                    'textAnchor' => 'middle',
                    'rotate' => $rotation
                ]
            );
        }
        
        return $output;
    }
}
?>