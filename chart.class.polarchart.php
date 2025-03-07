<?php
/**
 * ChartPolarChart - Polar-Diagramm-Klasse für das PHPChart-System
 * 
 * Diese Klasse ist für die Erstellung und Darstellung von Polar-Diagrammen zuständig,
 * einschließlich normaler Polar-Charts und Polar-Area-Charts.
 * 
 * @version 1.0
 */
class ChartPolarChart {
    /**
     * @var ChartUtils Instanz der Utility-Klasse
     */
    private $utils;
    
    /**
     * @var ChartSVG Instanz der SVG-Klasse
     */
    private $svg;
    
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
    }
    
    /**
     * Rendert ein Polar-Diagramm
     * 
     * @param array $seriesGroup Gruppe von Polar-Diagramm-Serien
     * @param array $xValues Array mit X-Werten (Winkel in Grad)
     * @param array $yValues Array mit Y-Werten (Radius)
     * @param array $axes Achsendefinitionen (nicht vollständig verwendet bei Polar-Charts)
     * @param array $chartArea Daten zum Zeichenbereich
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Polar-Diagramms
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
        
        // Berechne Mittelpunkt und maximalen Radius für das Polar-Diagramm
        $centerX = $chartArea['x'] + $chartArea['width'] / 2;
        $centerY = $chartArea['y'] + $chartArea['height'] / 2;
        $maxRadius = min($chartArea['width'], $chartArea['height']) / 2 * 0.85; // 85% des verfügbaren Platzes
        
        // Rendere das Raster für das Polardiagramm
        $output .= $this->renderPolarGrid($centerX, $centerY, $maxRadius, $config);
        
        // Rendere jede Serie
        foreach ($updatedSeriesGroup as $seriesName => $seriesOptions) {
            $output .= $this->renderPolarSeries(
                $seriesName,
                $seriesOptions,
                $xValues,
                $yValues,
                $centerX,
                $centerY,
                $maxRadius,
                $config
            );
        }
        
        return $output;
    }
    
    /**
     * Erstellt Gradienten für alle Serien, die diese benötigen
     * 
     * @param array $seriesGroup Gruppe von Polar-Diagramm-Serien
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
            if (isset($updatedSeriesGroup[$key])) {
                $updatedSeriesGroup[$key]['gradientId'] = 'url(#' . $gradientInfo['id'] . ')';
            }
        }
        
        return $updatedSeriesGroup;
    }
    
    /**
     * Rendert das Raster für das Polardiagramm
     * 
     * @param float $centerX X-Koordinate des Mittelpunkts
     * @param float $centerY Y-Koordinate des Mittelpunkts
     * @param float $maxRadius Maximaler Radius
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente des Polar-Rasters
     */
    private function renderPolarGrid($centerX, $centerY, $maxRadius, $config) {
        $output = '';
        
        // Prüfe, ob Gitter angezeigt werden soll
        if (!isset($config['polarGrid']) || !isset($config['polarGrid']['enabled']) || !$config['polarGrid']['enabled']) {
            return $output;
        }
        
        // Konfiguration für das Polar-Raster
        $gridColor = isset($config['polarGrid']['color']) ? $config['polarGrid']['color'] : '#e0e0e0';
        $gridWidth = isset($config['polarGrid']['width']) ? $config['polarGrid']['width'] : 1;
        $gridDashArray = isset($config['polarGrid']['dashArray']) ? $config['polarGrid']['dashArray'] : '';
        
        // Anzahl der Kreise und Winkellinien
        $circleCount = isset($config['polarGrid']['circleCount']) ? $config['polarGrid']['circleCount'] : 5;
        $angleCount = isset($config['polarGrid']['angleCount']) ? $config['polarGrid']['angleCount'] : 8;
        
        // Zeichne konzentrische Kreise
        for ($i = 1; $i <= $circleCount; $i++) {
            $radius = $maxRadius * $i / $circleCount;
            
            $output .= $this->svg->createCircle(
                $centerX,
                $centerY,
                $radius,
                [
                    'fill' => 'none',
                    'stroke' => $gridColor,
                    'strokeWidth' => $gridWidth,
                    'strokeDasharray' => $gridDashArray
                ]
            );
            
            // Labels für die radialen Achsen hinzufügen, wenn aktiviert
            if (isset($config['polarGrid']['labels']) && $config['polarGrid']['labels']['enabled']) {
                $labelValue = $i * 100 / $circleCount; // Standardwert in Prozent
                
                // Benutzerdefinierte Formatierung, falls vorhanden
                if (isset($config['polarGrid']['labels']['format'])) {
                    $format = $config['polarGrid']['labels']['format'];
                    $label = str_replace('{value}', $this->utils->formatNumber($labelValue), $format);
                } else {
                    $label = $this->utils->formatNumber($labelValue);
                }
                
                // Position des Labels (unten auf dem Kreis)
                $labelX = $centerX;
                $labelY = $centerY + $radius + 15;
                
                $output .= $this->svg->createText(
                    $labelX,
                    $labelY,
                    $label,
                    [
                        'fontFamily' => isset($config['polarGrid']['labels']['fontFamily']) ? $config['polarGrid']['labels']['fontFamily'] : 'Arial, Helvetica, sans-serif',
                        'fontSize' => isset($config['polarGrid']['labels']['fontSize']) ? $config['polarGrid']['labels']['fontSize'] : 12,
                        'fontWeight' => isset($config['polarGrid']['labels']['fontWeight']) ? $config['polarGrid']['labels']['fontWeight'] : 'normal',
                        'fill' => isset($config['polarGrid']['labels']['color']) ? $config['polarGrid']['labels']['color'] : '#333333',
                        'textAnchor' => 'middle'
                    ]
                );
            }
        }
        
        // Zeichne Winkellinien
        for ($i = 0; $i < $angleCount; $i++) {
            $angle = 2 * M_PI * $i / $angleCount;
            $x2 = $centerX + $maxRadius * cos($angle);
            $y2 = $centerY + $maxRadius * sin($angle);
            
            $output .= $this->svg->createLine(
                $centerX,
                $centerY,
                $x2,
                $y2,
                [
                    'stroke' => $gridColor,
                    'strokeWidth' => $gridWidth,
                    'strokeDasharray' => $gridDashArray
                ]
            );
            
            // Labels für die Winkelachsen hinzufügen, wenn aktiviert
            if (isset($config['polarGrid']['angleLabels']) && $config['polarGrid']['angleLabels']['enabled']) {
                $degreesAngle = ($i * 360 / $angleCount) % 360;
                
                // Benutzerdefinierte Formatierung, falls vorhanden
                if (isset($config['polarGrid']['angleLabels']['format'])) {
                    $format = $config['polarGrid']['angleLabels']['format'];
                    $label = str_replace('{value}', $degreesAngle, $format);
                } else {
                    $label = $degreesAngle . '°';
                }
                
                // Position des Labels (außerhalb des Kreises)
                $labelRadius = $maxRadius * 1.1;
                $labelX = $centerX + $labelRadius * cos($angle);
                $labelY = $centerY + $labelRadius * sin($angle);
                
                $output .= $this->svg->createText(
                    $labelX,
                    $labelY,
                    $label,
                    [
                        'fontFamily' => isset($config['polarGrid']['angleLabels']['fontFamily']) ? $config['polarGrid']['angleLabels']['fontFamily'] : 'Arial, Helvetica, sans-serif',
                        'fontSize' => isset($config['polarGrid']['angleLabels']['fontSize']) ? $config['polarGrid']['angleLabels']['fontSize'] : 12,
                        'fontWeight' => isset($config['polarGrid']['angleLabels']['fontWeight']) ? $config['polarGrid']['angleLabels']['fontWeight'] : 'normal',
                        'fill' => isset($config['polarGrid']['angleLabels']['color']) ? $config['polarGrid']['angleLabels']['color'] : '#333333',
                        'textAnchor' => 'middle',
                        'dominantBaseline' => 'middle'
                    ]
                );
            }
        }
        
        return $output;
    }
    
    /**
     * Rendert eine Polar-Diagramm-Serie
     * 
     * @param string $seriesName Name der Serie
     * @param array $seriesOptions Optionen für die Serie
     * @param array $xValues Array mit X-Werten (Winkel in Grad)
     * @param array $yValues Array mit Y-Werten (Radius)
     * @param float $centerX X-Koordinate des Mittelpunkts
     * @param float $centerY Y-Koordinate des Mittelpunkts
     * @param float $maxRadius Maximaler Radius
     * @param array $config Diagramm-Konfiguration
     * @return string SVG-Elemente der Polar-Serie
     */
    private function renderPolarSeries($seriesName, $seriesOptions, $xValues, $yValues, $centerX, $centerY, $maxRadius, $config) {
        // Hole die Winkel- und Radiuswerte für diese Serie
        $seriesAngles = isset($xValues[$seriesName]) ? $xValues[$seriesName] : (isset($xValues['default']) ? $xValues['default'] : []);
        $seriesRadii = isset($yValues[$seriesName]) ? $yValues[$seriesName] : [];
        
        // Wenn keine Werte vorhanden sind, nichts rendern
        if (empty($seriesAngles) || empty($seriesRadii)) {
            return '';
        }
        
        // Bestimme den Typ des Polar-Diagramms
        $isPolarArea = isset($seriesOptions['polar']) && isset($seriesOptions['polar']['area']) && $seriesOptions['polar']['area'];
        
        // Maximaler Wert für die Normalisierung der Radien
        $maxValue = max($seriesRadii);
        if ($maxValue <= 0) {
            $maxValue = 1; // Verhindere Division durch Null
        }
        
        // Farbe für die Serie
        $color = !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000';
        
        // Gradient-ID verwenden, falls vorhanden
        $fillColor = isset($seriesOptions['gradientId']) ? $seriesOptions['gradientId'] : $color;
        
        // Punkte für den Pfad sammeln
        $points = [];
        $polarPoints = []; // Für Polar-Area
        
        for ($i = 0; $i < count($seriesAngles); $i++) {
            // Winkel in Radian umrechnen (0° = rechts, im Uhrzeigersinn)
            $angle = deg2rad($seriesAngles[$i]);
            
            // Radius normalisieren und skalieren
            $radius = ($seriesRadii[$i] / $maxValue) * $maxRadius;
            
            // Kartesische Koordinaten berechnen
            $x = $centerX + $radius * cos($angle);
            $y = $centerY + $radius * sin($angle);
            
            $points[] = [$x, $y];
            $polarPoints[] = [$angle, $radius];
        }
        
        $output = '';
        
        // Polar-Area oder normales Polar-Diagramm rendern
        if ($isPolarArea) {
            $output .= $this->renderPolarAreaChart($polarPoints, $centerX, $centerY, $fillColor, $seriesOptions);
        } else {
            // Verbinde die Punkte zu einem Pfad
            if (count($points) > 1) {
                // Schließe den Pfad, indem der erste Punkt am Ende wiederholt wird
                if ($points[0][0] !== $points[count($points) - 1][0] || $points[0][1] !== $points[count($points) - 1][1]) {
                    $points[] = $points[0];
                }
                
                $output .= $this->svg->createPolyline(
                    $points,
                    [
                        'fill' => 'none',
                        'stroke' => $color,
                        'strokeWidth' => isset($seriesOptions['line']) && isset($seriesOptions['line']['width']) ? $seriesOptions['line']['width'] : 2,
                        'strokeDasharray' => isset($seriesOptions['line']) && isset($seriesOptions['line']['dashArray']) ? $seriesOptions['line']['dashArray'] : ''
                    ]
                );
            }
        }
        
        // Punkte rendern, falls aktiviert
        if (isset($seriesOptions['point']) && isset($seriesOptions['point']['enabled']) && $seriesOptions['point']['enabled']) {
            $output .= $this->renderPoints($points, $seriesOptions);
        }
        
        // Datenwertbeschriftungen rendern, falls aktiviert
        if (isset($seriesOptions['dataLabels']) && isset($seriesOptions['dataLabels']['enabled']) && $seriesOptions['dataLabels']['enabled']) {
            $output .= $this->renderDataLabels($points, $seriesOptions, $seriesRadii);
        }
        
        return $output;
    }
    
    /**
     * Rendert ein Polar-Area-Diagramm
     * 
     * @param array $polarPoints Array mit Polarkoordinaten als [angle, radius]-Arrays
     * @param float $centerX X-Koordinate des Mittelpunkts
     * @param float $centerY Y-Koordinate des Mittelpunkts
     * @param string $fillColor Füllfarbe oder Gradient-ID
     * @param array $seriesOptions Optionen für die Serie
     * @return string SVG-Elemente des Polar-Area-Diagramms
     */
    private function renderPolarAreaChart($polarPoints, $centerX, $centerY, $fillColor, $seriesOptions) {
        // Wenn weniger als 3 Punkte vorhanden sind, kein Area-Diagramm rendern
        if (count($polarPoints) < 3) {
            return '';
        }
        
        // Bereite den Pfad für das Area-Diagramm vor
        $path = 'M ' . $centerX . ',' . $centerY . ' ';
        
        // Sortiere die Punkte nach dem Winkel
        usort($polarPoints, function($a, $b) {
            return $a[0] - $b[0];
        });
        
        // Füge jeden Punkt zum Pfad hinzu
        foreach ($polarPoints as $point) {
            $angle = $point[0];
            $radius = $point[1];
            
            $x = $centerX + $radius * cos($angle);
            $y = $centerY + $radius * sin($angle);
            
            $path .= 'L ' . $x . ',' . $y . ' ';
        }
        
        // Schließe den Pfad, um zurück zum ersten Punkt zu kommen
        $firstPoint = $polarPoints[0];
        $x = $centerX + $firstPoint[1] * cos($firstPoint[0]);
        $y = $centerY + $firstPoint[1] * sin($firstPoint[0]);
        $path .= 'L ' . $x . ',' . $y . ' ';
        
        // Schließe den Pfad zurück zum Mittelpunkt
        $path .= 'Z';
        
        // Rendere den Pfad
        return $this->svg->createPath(
            $path,
            [
                'fill' => $fillColor,
                'fillOpacity' => isset($seriesOptions['fillOpacity']) ? $seriesOptions['fillOpacity'] : 0.5,
                'stroke' => !empty($seriesOptions['color']) ? $seriesOptions['color'] : '#000000',
                'strokeWidth' => isset($seriesOptions['line']) && isset($seriesOptions['line']['width']) ? $seriesOptions['line']['width'] : 2
            ]
        );
    }
    
    /**
     * Rendert die Punkte eines Polar-Diagramms
     * 
     * @param array $points Array mit Punkten als [x, y]-Arrays
     * @param array $seriesOptions Optionen für die Serie
     * @return string SVG-Elemente der Punkte
     */
    private function renderPoints($points, $seriesOptions) {
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
        foreach ($points as $point) {
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
     * Rendert die Datenwertbeschriftungen eines Polar-Diagramms
     * 
     * @param array $points Array mit Punkten als [x, y]-Arrays
     * @param array $seriesOptions Optionen für die Serie
     * @param array $values Array mit Werten für die Beschriftungen
     * @return string SVG-Elemente der Datenwertbeschriftungen
     */
    private function renderDataLabels($points, $seriesOptions, $values) {
        $output = '';
        
        // Optionen für Datenwertbeschriftungen
        $offsetX = isset($seriesOptions['dataLabels']['offsetX']) ? $seriesOptions['dataLabels']['offsetX'] : 0;
        $offsetY = isset($seriesOptions['dataLabels']['offsetY']) ? $seriesOptions['dataLabels']['offsetY'] : -15;
        $fontFamily = isset($seriesOptions['dataLabels']['fontFamily']) ? $seriesOptions['dataLabels']['fontFamily'] : 'Arial, Helvetica, sans-serif';
        $fontSize = isset($seriesOptions['dataLabels']['fontSize']) ? $seriesOptions['dataLabels']['fontSize'] : 11;
        $fontWeight = isset($seriesOptions['dataLabels']['fontWeight']) ? $seriesOptions['dataLabels']['fontWeight'] : 'normal';
        $color = isset($seriesOptions['dataLabels']['color']) ? $seriesOptions['dataLabels']['color'] : '#333333';
        $format = isset($seriesOptions['dataLabels']['format']) ? $seriesOptions['dataLabels']['format'] : '{y}';
        
        // Datenwertbeschriftungen rendern
        for ($i = 0; $i < count($points); $i++) {
            if (isset($points[$i]) && isset($values[$i])) {
                $x = $points[$i][0];
                $y = $points[$i][1];
                $value = $values[$i];
                
                // Formatierung des Labels
                $label = str_replace('{y}', $this->utils->formatNumber($value), $format);
                
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
                        'textAnchor' => 'middle'
                    ]
                );
            }
        }
        
        return $output;
    }
}
?>